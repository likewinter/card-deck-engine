# Design

The architecture and the decisions behind it. For the full IR specification see
[`design/ir-spec.md`](../design/ir-spec.md).

## The shape

```
authoring surface  →  IR (GameDefinition)  →  Engine (reducer)  →  GameState
(forms / a language)   pure, serializable      interprets the IR     immutable
                       data                    over card-deck        (state, move) -> state
```

The classic compiler layout: a *frontend* (the authoring surface) produces the
IR; a *backend* (the engine) consumes it. They are decoupled and evolve
independently. The IR is the contract between them.

## Key decisions

### Interpreted data, not codegen

A game is data the engine interprets at runtime — not source code generated
from a DSL. Codegen was considered and rejected as far more complex for no
benefit at this stage. Interpreted data keeps the IR serializable, validatable,
and inspectable, and means a non-technical author's game is just a value.

### A pure reducer over immutable state

The engine is `(state, move) -> state`. `GameState` is immutable; card
collections are plain `list<Card>`, not the library's mutable `Stack`/`Table`.

This was a deliberate departure from "wrap the `card-deck` `Table`." A pure
reducer needs immutable, serializable state for undo, replay, persistence, and
a future authoritative server; deep-cloning a mutable `Table` on every move
would be fragile and defeat those properties. The engine still *uses* the
library — its immutable half (`Card`, `Rank`/`Suit`, `RankOrder`, `SuitOrder`,
`DeckBuilder`), which is the right half for a reducer.

### Family dialects over a stable spine

The IR is a fixed spine of sections; the contents are family-specific. Each
family is a small module — a `FamilyHandler` plus a resolver and a scorer —
mirroring the IR's own separation of `phases`, `resolver`, and `scoring`.

This came straight from validating the IR against a corpus. Hand-authoring
Spades, Texas Hold'em, Blackjack, and Klondike (in [`design/ir/`](../design/ir/))
showed that the *spine* is stable while resolution, scoring, end semantics, and
phase types vary by family — and that some sections are optional (puzzle games
have no resolver or scoring). Designing the dialect model around that finding
kept each family bounded and independently testable.

### Deterministic by construction

Deals come from `Mt19937(seed + round)`. A seed reproduces the entire game
across every round; no seed deals cryptographically random rounds.
Determinism is what makes the test suite (including a multi-seed termination
soak) possible and what a server needs for fairness and replay.

### The authoring audience drives the design

The target author is a non-technical player defining game *variants* through a
product UI. That pushes everything toward bounded, per-family choices and
authoring-time validation, and is why the IR is data (a UI can produce and edit
it) rather than a typed language a user would have to write.

## What's implemented (v0.1)

- The full IR spine as immutable value objects.
- The `Engine` reducer (`start`, `legalMoves`, `apply`, `isOver`, `winner`).
- The **trick-taking** family, end-to-end: bidding, trick play with
  follow-suit legality, `SuitOrder` resolution, cumulative scoring
  (made/failed bids, bags, nil), multi-round play, and target-score end.
- Seeded, reproducible dealing and a multi-seed soak test proving games always
  terminate without deadlock.

## Roadmap

In rough order:

1. **More families** — poker, push-your-luck, and solitaire IRs are already
   sketched; melding/shedding (rummy, crazy eights) next. Each reuses the
   handler + resolver + scorer pattern.
2. **Authoring surface** — the user-facing piece: structured forms or a
   readable mini-language that emits the IR. The biggest open product decision.
3. **IR serialization** — a concrete JSON/YAML representation of the definition
   (and of game state), needed for persistence and the authoring surface.
4. **Authoring-time validation** — friendly, specific errors for bad
   definitions; first-class because authors are non-technical.
5. **Server layer** — per-player state views (information hiding), persistence,
   and seeded RNG on top of the reducer.
