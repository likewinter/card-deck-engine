# Card Deck Engine

[![CI](https://github.com/likewinter/card-deck-engine/actions/workflows/ci.yml/badge.svg)](https://github.com/likewinter/card-deck-engine/actions/workflows/ci.yml)
![Coverage](https://likewinter.github.io/card-deck-engine/coverage.svg)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen)](https://phpstan.org/)

A generic card-game runtime — describe a game's rules as data, and let the
engine play it.

`likewinter/card-deck-engine` interprets a structured game definition (the
**IR**) and drives complete games on top of
[`likewinter/card-deck`](https://github.com/likewinter/card-deck). You supply
the rules as data; the engine supplies the table, the turn order, the
resolution, the scoring, and the win condition. There is no game-specific
code to write — a game is a value, not a class.

The design target is non-technical authors defining game *variants* through a
product UI: the IR is the validated, serializable contract that an authoring
surface produces and the engine consumes.

## Requirements

- PHP 8.4 or newer
- [`likewinter/card-deck`](https://github.com/likewinter/card-deck)

## Install

```bash
composer require likewinter/card-deck-engine
```

## Quick start

```php
use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeck\DrawMode;
use Likewinter\CardDeckEngine\Definition\Deal;
use Likewinter\CardDeckEngine\Definition\DealStep;
use Likewinter\CardDeckEngine\Definition\Deck;
use Likewinter\CardDeckEngine\Definition\Ends\TargetScoreEnd;
use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Likewinter\CardDeckEngine\Definition\Layout;
use Likewinter\CardDeckEngine\Definition\Meta;
use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TallyPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TrickPlayPhase;
use Likewinter\CardDeckEngine\Definition\PlayerCount;
use Likewinter\CardDeckEngine\Definition\Players;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\Definition\Scoring\CumulativeScoring;
use Likewinter\CardDeckEngine\Definition\StackSpec;
use Likewinter\CardDeckEngine\Definition\Visibility;
use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\Bid;
use Random\Engine\Mt19937;
use Random\Randomizer;

// 1. Describe the game as data (the IR).
$spades = new GameDefinition(
    meta: new Meta('spades', FamilyKind::TrickTaking),
    players: new Players(PlayerCount::fixed(4)),
    deck: Deck::standard52(),
    layout: new Layout([new StackSpec('hand', Visibility::Private, count: 4)]),
    deal: new Deal([new DealStep(to: 'each_player', mode: DrawMode::OneByOne)]),
    phases: [
        new BidPhase('bidding', 'play', min: 0, max: 13),
        new TrickPlayPhase('play', 'score', tricks: 13),
        new TallyPhase('score', 'bidding'),
    ],
    end: new TargetScoreEnd(500),
    resolver: new TrickWinnerResolver(Suit::Spades),
    scoring: new CumulativeScoring(perTrick: 10, overtrick: 1, nilSuccess: 100, nilFailure: -100),
);

// 2. Start a game. Pass a seed for a reproducible deal.
$state = Engine::start($spades, ['north', 'south', 'east', 'west'], seed: 42);

// 3. Play it to a winner. legalMoves() returns the current player's options
//    and apply() returns the next (immutable) state. Move selection here is a
//    simple stand-in — bid a makeable three, else play a random legal card
//    (see demo/spades.php); a real consumer chooses from UI input or an AI.
$random = new Randomizer(new Mt19937(42));
while (!Engine::isOver($state)) {
    $moves = Engine::legalMoves($state);
    $bid = null;
    foreach ($moves as $candidate) {
        if ($candidate instanceof Bid && $candidate->amount === 3) {
            $bid = $candidate;
        }
    }
    $state = Engine::apply($state, $bid ?? $moves[$random->getInt(0, count($moves) - 1)]);
}

echo Engine::winner($state);                    // the winning player
```

## Run the demo

A self-contained script plays a full seeded game to a winner:

```bash
php demo/spades.php          # seed 42
php demo/spades.php 7        # a different, reproducible game
```

## How it works

```
authoring surface  →  IR (GameDefinition)  →  Engine (reducer)  →  GameState
(forms / a language)   pure, serializable      interprets the IR     immutable
                       data                    over card-deck        (state, move) -> state
```

- **The IR** ([`GameDefinition`](src/Definition/GameDefinition.php)) is a pure
  data description of a game: players, deck, layout, deal, phases, resolution,
  scoring, and end condition. It is serializable and validated — the contract
  between whoever authors a game and the engine that plays it.
- **The Engine** ([`Engine`](src/Engine.php)) is a pure reducer. `start()`
  produces the initial state; `legalMoves()` and `apply()` drive play;
  `isOver()` and `winner()` report the result.
- **Families** ([`FamilyHandler`](src/Family/FamilyHandler.php)) carry the
  game-specific behavior. The IR is a stable *spine*; each family is a
  *dialect* that supplies phase handling, resolution, and scoring. Trick-taking
  ships today; poker, push-your-luck, and solitaire are designed (see
  [`design/`](design/)) and on the roadmap.

## What's included

| Component | Purpose |
|-----------|---------|
| [`Definition\GameDefinition`](src/Definition/GameDefinition.php) | The IR root: the game's spine |
| [`Definition\*`](src/Definition/) | The spine sections as immutable value objects (meta, players, deck, layout, deal, phases, resolution, scoring, end) |
| [`Engine`](src/Engine.php) | The reducer: `start`, `legalMoves`, `apply`, `isOver`, `winner` |
| [`Family\FamilyHandler`](src/Family/FamilyHandler.php) | The family contract a dialect implements |
| [`Family\TrickTaking\*`](src/Family/TrickTaking/) | The trick-taking dialect: handler + `TrickResolver` + `TrickTakingScorer` |
| [`Setup\Dealer`](src/Setup/Dealer.php) | Builds, shuffles (seeded), and deals — for setup and re-deals |
| [`State\GameState`](src/State/GameState.php), [`State\RoundState`](src/State/RoundState.php) | Immutable runtime state |
| [`Move\*`](src/Move/) | Player actions: `Bid`, `PlayCard` |

## Documentation

- [Getting started](docs/getting-started.md) — install, the mental model, your first game
- [The game definition (IR)](docs/game-definition.md) — every spine section, with Spades
- [Families](docs/families.md) — the dialect model and the family contract
- [Playing a game](docs/playing-a-game.md) — the engine API, the game loop, determinism
- [Design](docs/design.md) — architecture and the decisions behind it

The [`design/`](design/) directory holds the IR specification
([`design/ir-spec.md`](design/ir-spec.md)) and hand-authored corpus sketches
(Spades, Texas Hold'em, Blackjack, Klondike) that shaped it.

## Family status

The IR spine is family-agnostic; families are added incrementally.

| Family | Status | Notes |
|--------|--------|-------|
| Trick-taking | ✅ | Spades end-to-end: bidding, trick play, resolution, cumulative scoring, multi-round to a target |
| Poker | 🚧 | IR sketched ([`design/ir/texas-holdem.yaml`](design/ir/texas-holdem.yaml)); no handler yet |
| Push-your-luck | 🚧 | IR sketched ([`design/ir/blackjack.yaml`](design/ir/blackjack.yaml)); no handler yet |
| Solitaire | 🚧 | IR sketched ([`design/ir/solitaire.yaml`](design/ir/solitaire.yaml)); no handler yet |
| Melding / shedding | 🚧 | Not yet sketched (rummy, crazy eights) |

## Design principles

1. **Data, not code.** A game is an IR value the engine interprets — no
   codegen, no game base class to extend. New games are new data.
2. **Pure reducer.** `(state, move) -> state` over an immutable `GameState`.
   State is serializable, which makes undo, replay, persistence, and a future
   authoritative server straightforward.
3. **Family dialects over a stable spine.** The spine of sections is fixed;
   the contents are family-specific. Each family is a small, testable module
   (handler + resolver + scorer).
4. **Built on `card-deck`.** The engine reuses the library's immutable
   primitives — `Card`, `Rank`/`Suit`, `RankOrder`, `SuitOrder`, `DeckBuilder` —
   and manages its own immutable card collections rather than wrapping the
   mutable `Stack`/`Table`.
5. **Deterministic.** Deals come from `Mt19937(seed + round)`, so the same seed
   replays the same game — essential for tests, replays, and server fairness.
6. **Honest about scope.** v0.1 plays trick-taking. The other families are
   designed in the IR but not yet implemented.

## Testing

Tests cover the IR, the engine, and a full trick-taking game end-to-end —
including a multi-seed soak that plays many complete games to verify they
always terminate without deadlock.

```bash
composer test           # Pest test suite
composer phpstan        # PHPStan level 8 static analysis
composer lint           # Mago linter
composer analyze        # Mago static analyzer
composer format:check   # Mago formatting check
composer ci             # All of the above + security audit
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for notable changes between releases.

## License

MIT — see [LICENSE](LICENSE).
