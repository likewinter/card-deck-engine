# Families

Card games differ mostly *within* a family, not across the whole space. The
engine exploits this: the [IR](game-definition.md) is a stable **spine** of
sections, and each **family** is a **dialect** — a coherent bundle of phase
handling, resolution, and scoring that fits together.

A concrete game *parameterizes* a family (Spades sets trump = spades, target =
500). A *variant* `extends` a concrete game and overrides a few fields.

## The family contract

A family implements
[`FamilyHandler`](../src/Family/FamilyHandler.php):

```php
interface FamilyHandler
{
    /** @return list<Move> */
    public function legalMoves(GameState $state): array;

    public function apply(GameState $state, Move $move): GameState;

    public function isOver(GameState $state): bool;

    public function winner(GameState $state): ?string;
}
```

The engine dispatches to a handler keyed by the definition's
[`FamilyKind`](../src/Definition/FamilyKind.php):

```php
// Engine::handlerFor()
return match ($definition->meta->family) {
    FamilyKind::TrickTaking => new TrickTakingHandler(),
    default => throw new \InvalidArgumentException(/* unsupported family */),
};
```

Given the immutable state, a handler reports the legal moves for the current
actor, applies a move to produce the next state (including phase transitions,
resolution, and scoring), and reports whether the game is over and who won.

## The trick-taking family

The trick-taking dialect ([`src/Family/TrickTaking/`](../src/Family/TrickTaking/))
is split into three focused classes, mirroring the IR's own separation of
`phases`, `resolver`, and `scoring`:

| Class | Responsibility |
|-------|----------------|
| [`TrickTakingHandler`](../src/Family/TrickTaking/TrickTakingHandler.php) | Orchestration: bidding, trick play, and the multi-round loop |
| [`TrickResolver`](../src/Family/TrickTaking/TrickResolver.php) | Follow-suit legality and trick resolution (via the library's `SuitOrder`) |
| [`TrickTakingScorer`](../src/Family/TrickTaking/TrickTakingScorer.php) | Cumulative scoring (made/failed bids, bags, nil) and target-score end |

The handler reads the current phase from the definition and acts on it:

- **`BidPhase`** — the current player may bid `min..max`; once everyone has
  bid, the phase advances to `then`.
- **`TrickPlayPhase`** — the leader may play any card; followers must follow
  the lead suit when able; when the trick is full, `TrickResolver` picks the
  winner (highest trump, else highest lead-suit card), who leads the next
  trick. After the final trick, the phase advances to `then`.
- **`TallyPhase`** — `TrickTakingScorer` scores the round, then the game either
  ends (target reached) or deals a fresh round and returns to bidding.

Resolution builds a `SuitOrder` from the
[`TrickWinnerResolver`](../src/Definition/Resolvers/TrickWinnerResolver.php)'s
trump suit plus an A-high `RankOrder::poker()`, and uses `SuitOrder::beats()`
to compare cards — so the engine leans on `card-deck`'s trick-taking primitive
rather than reimplementing it.

## Adding a family

To add a family (say, poker):

1. Sketch its IR — the phase types, resolver, scoring model, and end semantics
   it needs. The [`design/ir/`](../design/ir/) sketches are the starting point
   (Texas Hold'em, Blackjack, and Klondike are already drafted).
2. Add any missing IR value objects (new phase types, a resolver, a scoring
   model, an end kind) under [`src/Definition/`](../src/Definition/).
3. Implement a `FamilyHandler` for the family, following the
   handler + resolver + scorer split.
4. Register it in `Engine::handlerFor()`.
5. Add a fixture and tests, including an end-to-end game to a winner.

Each family is a self-contained, independently testable module — the spine and
the engine do not change.
