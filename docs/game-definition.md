# The game definition (IR)

A game is a [`GameDefinition`](../src/Definition/GameDefinition.php) — a pure,
serializable value describing the rules. The engine interprets it; nothing
about a game is code.

The definition has a fixed **spine** of sections. Some are required, some are
optional (whole families omit them), and the *contents* of most sections are
family-specific (see [Families](families.md)).

```php
new GameDefinition(
    meta:      new Meta('spades', FamilyKind::TrickTaking),
    players:   new Players(PlayerCount::fixed(4)),
    deck:      Deck::standard52(),
    layout:    new Layout([new StackSpec('hand', Visibility::Private, count: 4)]),
    deal:      new Deal([new DealStep(to: 'each_player', mode: DrawMode::OneByOne)]),
    phases:    [/* the state machine */],
    end:       new TargetScoreEnd(500),
    resolver:  new TrickWinnerResolver(Suit::Spades),   // optional
    scoring:   new CumulativeScoring(perTrick: 10, overtrick: 1, nilSuccess: 100, nilFailure: -100), // optional
    triggers:  [],                                       // optional
);
```

## The sections

| Section | Req? | Role |
|---------|------|------|
| `meta` | ✓ | name, [`FamilyKind`](../src/Definition/FamilyKind.php), optional `extends` (the game this is a variant of) |
| `players` | ✓ | count (fixed or `{min,max}`) and seating/turn direction |
| `deck` | ✓ | composition and copy count (a shoe of N decks) |
| `layout` | ✓ | the named stacks that make up the table |
| `deal` | ✓ | initial population of the layout |
| `phases` | ✓ | the state machine (a non-empty list of typed phases) |
| `resolver` | opt | how a contest is decided (trick winner, hand rank, total) |
| `scoring` | opt | how points/results accumulate |
| `triggers` | opt | automatic on-event effects |
| `end` | ✓ | when the game stops and what the result means |

### `meta`

```php
new Meta('spades', FamilyKind::TrickTaking);
new Meta('my-spades', FamilyKind::TrickTaking, extends: 'spades'); // a variant
```

The `family` selects the [dialect](families.md) the engine uses. `extends`
names the concrete game a variant is derived from.

### `players`

```php
new Players(PlayerCount::fixed(4));                 // exactly four
new Players(PlayerCount::fixed(4), PlayOrder::Clockwise);
new Players(new PlayerCount(2, 10));                // two to ten
```

### `deck`

```php
Deck::standard52();            // one 52-card deck
Deck::standard52(copies: 6);   // a six-deck shoe
```

A `Deck` is a list of [`DeckSpec`](../src/Definition/DeckSpec.php) entries
(a composition repeated `copies` times). Compositions map onto
`DeckBuilder` presets; today that is `DeckComposition::Standard52`.

### `layout`

The table is a set of **named stacks**, each with a visibility policy, a count
(how many such stacks exist), and an optional capacity:

```php
new Layout([
    new StackSpec('hand', Visibility::Private, count: 4),  // four private hands
    new StackSpec('board', Visibility::Shared),            // a shared board (poker)
]);
```

[`Visibility`](../src/Definition/Visibility.php) covers the face axis
(`Up`, `Down`, `TopUp`) and the ownership axis (`Private`, `Shared`). Build and
move rules are family-specific and live with the family dialect.

### `deal`

An ordered list of steps that populate the layout:

```php
new Deal([
    new DealStep(to: 'each_player', cards: null, mode: DrawMode::OneByOne),
]);
```

- `to` — a named stack, or `'each_player'` for the per-seat hands.
- `cards` — how many, or `null` to deal all remaining cards.
- `mode` — `DrawMode::OneByOne` (round-robin) or `DrawMode::Sequential`
  (chunked), reused from `card-deck`.
- `at` — `null` for the initial deal, or a phase id to stage a deal/reveal
  later (e.g. poker's community cards).

### `phases`

A non-empty list of typed phases forming the state machine. Each phase has an
`id`, an optional `then` (the phase to advance to), and a `kind`. The available
phase types are family-specific:

```php
phases: [
    new BidPhase('bidding', 'play', min: 0, max: 13),
    new TrickPlayPhase('play', 'score', tricks: 13),
    new TallyPhase('score', 'bidding'),   // loops back to bidding
]
```

Trick-taking contributes `BidPhase`, `TrickPlayPhase`, and `TallyPhase`. Other
families contribute their own (poker's betting rounds and showdown, the
push-your-luck hit/stand loop, solitaire's free play).

### `resolver` (optional)

How a contest is decided. Family-polymorphic; puzzle games have none.

```php
new TrickWinnerResolver(Suit::Spades);  // trump = spades
new TrickWinnerResolver(null);          // no-trump
```

### `scoring` (optional)

How results accumulate. Family-polymorphic; puzzle games have none.

```php
new CumulativeScoring(perTrick: 10, overtrick: 1, nilSuccess: 100, nilFailure: -100);
```

For trick-taking: `+perTrick` for each trick bid (when the bid is made),
`+overtrick` per bag, and `nilSuccess`/`nilFailure` for a nil (zero) bid.

### `end`

When the game stops and what the result means.

```php
new TargetScoreEnd(500);                       // first to 500, highest score wins
new TargetScoreEnd(500, WinnerRule::HighestScore);
```

End kinds are family-dependent (target score, elimination, fixed hands,
goal state); see [`WinnerRule`](../src/Definition/WinnerRule.php).

### `triggers` (optional)

Automatic effects that fire on state changes, not on player decisions:

```php
new Trigger(on: 'tableau_top_uncovered', action: 'flip_up');
```

## Worked examples

The [`design/ir/`](../design/ir/) directory has four hand-authored definitions
that exercise the spine across families: Spades (trick-taking), Texas Hold'em
(poker), Blackjack (push-your-luck), and Klondike (solitaire). The Spades
fixture used by the test suite lives at
[`tests/Fixtures/Spades.php`](../tests/Fixtures/Spades.php).
