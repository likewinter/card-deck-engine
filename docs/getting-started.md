# Getting started

## Install

```bash
composer require likewinter/card-deck-engine
```

Requires PHP 8.4 or newer and
[`likewinter/card-deck`](https://github.com/likewinter/card-deck).

## The mental model

The engine separates **what a game is** from **how a game runs**:

```
Layer 1 — The IR          GameDefinition and its spine sections (pure data)
Layer 2 — The reducer     Engine: start / legalMoves / apply / isOver / winner
Layer 3 — Families        FamilyHandler dialects (trick-taking, poker, ...)
Layer 4 — The primitives  likewinter/card-deck (Card, SuitOrder, DeckBuilder, ...)
```

**Layer 1** describes a game as data — players, deck, deal, phases, how a
contest is resolved, how points accrue, when the game ends. A game is a
*value* you can serialize, validate, diff, and hand to the engine. There is no
game-specific code.

**Layer 2** is a pure reducer. Given a state and a move, it returns the next
state. `start()` deals the first round; `legalMoves()` reports what the current
player may do; `apply()` advances the game; `isOver()` and `winner()` report
the result.

**Layer 3** carries the behavior that differs between games. The IR is a stable
*spine*; each [family](families.md) is a *dialect* that knows how to handle its
phases, resolve its contests, and score its results.

**Layer 4** is the card-deck library. The engine reuses its immutable
primitives (`Card`, `Rank`/`Suit`, `RankOrder`, `SuitOrder`, `DeckBuilder`) and
manages its own immutable card collections.

## Your first game

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

// 1. Describe Spades as data.
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

// 2. Start a game with a seeded (reproducible) deal.
$state = Engine::start($spades, ['north', 'south', 'east', 'west'], seed: 42);

// 3. Drive the game: legal moves in, a new state out.
while (!Engine::isOver($state)) {
    $moves = Engine::legalMoves($state);        // for the current player
    $state = Engine::apply($state, $moves[0]);  // pick a move, advance
}

echo Engine::winner($state);
```

Every call to `apply()` returns a *new* `GameState`; the previous one is
unchanged. That immutability is what makes undo, replay, and persistence easy —
see [Playing a game](playing-a-game.md).

## Where to go next

- [The game definition (IR)](game-definition.md) — every spine section, in detail
- [Families](families.md) — how game-specific behavior is organized
- [Playing a game](playing-a-game.md) — the engine API and determinism
- [Design](design.md) — the architecture and the decisions behind it
