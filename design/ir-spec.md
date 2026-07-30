# Card Game IR — Specification v0.1

**Status:** draft, derived from four hand-authored corpus sketches
(`design/ir/spades.yaml`, `texas-holdem.yaml`, `blackjack.yaml`,
`solitaire.yaml`). This is a design spec, not a formal grammar.

---

## 1. Purpose and scope

The **IR (intermediate representation)** is the engine-facing contract for a
card game: a structured, validated, serializable description of a game's
rules that the runtime can play without any game-specific code.

The IR is **not** the authoring surface. Non-technical users never see it.
The architecture is the classic compiler shape:

```
authoring surface  →  IR  →  engine (reducer over card-deck primitives)
(forms / mini-language)   (this spec)   (interprets the IR, plays the game)
```

The authoring surface is a *frontend that produces the IR*; the engine is a
*backend that consumes it*. They are decoupled and evolve independently.

**Design goals**

- **Bounded variation.** Games differ mostly *within* a family, not across
  the whole space. The IR captures a stable spine and delegates variation to
  per-family dialects.
- **Closed world.** The IR can only express games the engine can actually
  run. Nothing unexecutable is representable.
- **Validatable at authoring time.** Bad definitions are rejected early with
  specific, friendly messages — essential because authors are non-technical.
- **Serializable & deterministic.** State round-trips between requests;
  randomness is seeded. (Enables a future authoritative server layer.)

---

## 2. The spine

Every game definition is a fixed set of sections. Some are **required**,
some **optional** (absent for whole families). The spine is stable; the
*contents* of most sections are family-defined (§3).

| Section | Req? | Role |
|---|---|---|
| `meta` | ✓ | name, `family`, optional `extends` (variant base) |
| `players` | ✓ | count (fixed or `{min,max}`), seating/order |
| `actors` | opt | non-player participants (house/NPC) and their policies |
| `deck` | ✓ | composition + copy count (a shoe of N decks) |
| `layout` | ✓ | the named stacks that make up the table and their rules |
| `deal` | ✓ | initial population of the layout |
| `phases` | ✓ | the state machine (typed phases) or a single free-play phase |
| `triggers` | opt | automatic on-event effects |
| `resolution` | opt | how a contest is decided (trick, hand-rank, total) |
| `scoring` | opt | how points/chips/result accumulate |
| `end` | ✓ | when the game stops and what the result is |

**Principle:** the spine is a *menu*, not a checklist. Puzzle games omit
`actors`, `resolution`, and `scoring` entirely. The engine treats absent
sections as "this family doesn't have that concept."

---

## 3. Families and the family contract

A **family** is a dialect: a coherent bundle of phase types, a resolver, a
scoring model, end semantics, and a rule/move vocabulary that fit together.
A concrete game *parameterizes* a family; a *variant* `extends` a concrete
game and overrides a few fields.

**Family contract.** A family module must provide:

1. **Phase types** it supports (with each type's control flow and close
   condition).
2. **A resolver** (or declare it has none).
3. **A scoring model** (or declare it has none).
4. **End semantics** (which end kinds are meaningful).
5. **A rule/move vocabulary** — the curated, named terms authors pick from.
6. **Validation rules** — family-specific well-formedness checks.

**Reference families** (from the corpus):

| | trick-taking | poker | push-your-luck | solitaire |
|---|---|---|---|---|
| Phase types | `bid`, `trick_play`, `tally` | `betting_round`, `reveal`, `showdown`, `settle` | `wager`, `hit_stand`, `actor_play`, `compare` | `free_play` |
| Resolver | trick-winner (suit/rank) | hand-ranking (best-5-of-7) | additive total (soft/hard ace) | — |
| Scoring | cumulative → target | pot → winner(s) | payout × wager | — |
| End | target score | elimination / fixed rounds | fixed hands / bankroll | goal state |

The family is the unit of implementation and testing in the engine, and the
unit of authoring in the UI ("pick a family → fill in its form").

---

## 4. State model: layout and stacks

The game state is a **layout** — a set of **named stacks**, each with:

- a **visibility** model (`up`, `down`, `top_up`, per-card lists, or
  `private`/`shared`),
- a **build/move rule** (how cards may enter/leave — e.g. foundations build
  `up_by_suit_from: ace`; tableau builds `down_alternating_color`),
- optional **capacity** and **count** (e.g. `tableau.count: 7`,
  `foundations.count: 4`).

`deal` is just the **initial population** of the layout (possibly staged
across phases via `at:` and revealed later). Staged reveals and per-card
visibility are first-class.

**Mapping to `likewinter/card-deck`** (the library is ahead of the IR here):

- Named stacks → `Table` named hands / `Stack` instances.
- Face-down / partial visibility → `CardInPlay` + `Face`.
- Copy count / shoes → `DeckBuilder::times(N)`.
- Build rules that depend on rank/suit ordering → `RankOrder` / `SuitOrder`.

**Hands vs shared stacks.** Player hands are private, per-seat stacks; the
board, stock, waste, foundations, and tableau are shared or house stacks.
The layout names them uniformly. A seat may hold **multiple hands** (e.g.
poker `split`) — the state model must not assume one hand per player.

---

## 5. Actors

An **actor** is a participant. Two kinds:

- **`player`** — decisions arrive from outside the engine (a human or
  client). The default; described by `players`.
- **`house`** — driven by a declared **policy**, e.g. Blackjack's
  `hit_until: 17`. The engine computes its moves deterministically.

The `actors` section is optional (absent when there is no house). The same
policy mechanism can later power simple bots, so policies should be data,
not hardcoded.

---

## 6. Phases

`phases` is a state machine of **typed phases**. Two broad modes:

- **Turn-based** — players (and actors) act in order; the phase advances via
  `then:` when its close condition is met. Loops are expressed by pointing
  `then:` back at an earlier phase plus an `end` condition.
- **Free-play** — a single open sandbox (`type: free_play`) offering a set of
  legal move types, with no turn order, until `end` fires (solitaire).

**Phase-type catalog** (each owned by a family):

| Type | Family | Behavior |
|---|---|---|
| `bid` | trick-taking | each player commits a bid in turn |
| `trick_play` | trick-taking | one card each; winner leads next |
| `tally` | trick-taking | score the completed tricks |
| `betting_round` | poker | **looping sub-protocol**: act in turn (fold/check/call/bet/raise) until action is even and all active players matched |
| `reveal` | poker | turn over staged shared cards |
| `showdown` | poker | reveal and rank remaining hands |
| `wager` | push-your-luck | forced/optional bet before the deal |
| `hit_stand` | push-your-luck | per-player loop: hit until stand or bust |
| `actor_play` | push-your-luck | a house actor applies its policy |
| `compare` / `settle` | poker, push-your-luck | resolve and award |
| `free_play` | solitaire | open sandbox of move types |

`betting_round` is the hardest case and the reason phases need *types with
internal control flow* rather than a flat "action + then."

---

## 7. Legality and moves

This is where expressive power lives, and the design is deliberately split
into two layers that **compile to the same node grammar**:

1. **Per-family curated vocabulary** — named, friendly terms authors pick
   from: `follow_suit_if_able` (trick-taking), `best_5_of` (poker),
   `hit_until` (push-your-luck), `move_to_foundation` (solitaire). Impossible
   to get wrong; covers the corpus and most variants. This is what
   non-technical users see.
2. **A total expression language** — the escape hatch for the long tail and
   power users. *Total* means no loops and no recursion, which guarantees
   termination, makes it trivially sandboxable (no I/O, no reflection), and
   lets us validate references at authoring time.

Both desugar to a small **predicate/move node grammar** (comparisons, boolean
connectives, arithmetic, and a fixed set of game-state accessors like
`card.suit`, `hand.has_suit(...)`, `led_suit`, `top(waste)`). The exact
grammar is still open (§14), but the *approach* is decided: vocabulary as
macros over a total expression core.

**Move types** vary by family: `play_card`, bet actions
(`fold/check/call/bet/raise`), `hit`/`stand`, and stack-to-stack **transfer**
moves (solitaire). A move's legality is a predicate over the current state.

---

## 8. Triggers

**Triggers** are automatic effects that fire on state changes, not on player
decisions. Optional section.

```yaml
triggers:
  - on: tableau_top_uncovered
    do: flip_up
```

Multiplayer games have these too, only implicitly ("a trick ends when four
cards are played"). Making them explicit lets families and variants declare
auto-effects (auto-flip, auto-score, forced draws) uniformly.

---

## 9. Resolution (optional)

How a contest is decided. Family-polymorphic and pluggable; absent for
puzzle games. Kinds seen so far:

- **trick-winner** — highest trump, else highest of led suit
  (`SuitOrder` + `Trick`).
- **hand-ranking** — classify and compare hands, best-5-of-7, tie splits
  (`PokerHand` / `HandRank`).
- **additive-total** — sum card values with special rules (soft/hard ace),
  compare vs a threshold or the house.

A family provides its resolver; a game parameterizes it (e.g. trump suit,
select-best-N, tie behavior).

---

## 10. Scoring (optional)

How results accumulate. Family-polymorphic; absent for puzzle games. Kinds:

- **cumulative-points** — per-round formula summed toward a target
  (Spades: `+10`/trick bid, bags, nil ±100).
- **pot-award** — chips committed to a pot, awarded to the winner(s), with
  optional side pots (poker).
- **payout-multiplier** — win/loss/push multiplied by the wager against a
  bankroll (blackjack: `+1`, `push 0`, `natural 1.5`).

Scoring is frequently **conditional** (nil success depends on tricks taken;
a bid only pays if made), so the scoring model can reference play results,
not just flat numbers.

---

## 11. End

When the game stops and what the result is. Kinds:

- **target-score** — first to N (Spades: 500).
- **elimination** — last player standing (poker tournament).
- **fixed-hands / bankroll** — after N hands, or bankroll exhausted
  (blackjack).
- **goal-state** — a predicate over the board, plus an optional loss state
  (solitaire: `win_when: foundations.all_complete`,
  `lose_when: no_legal_moves`).

The **result** is family-dependent: a ranking of players, a winner, or simply
`solved | stuck`. "Winner" is not always meaningful.

---

## 12. Validation (cross-cutting)

Because authors are non-technical, validation is a first-class concern, not
an afterthought:

- **Closed-world rejection** — only representable, executable games parse.
- **Reference checks** — every term/stack/phase referenced must exist in
  scope for that family and phase.
- **Family well-formedness** — each family enforces its invariants (e.g. a
  trick-taking game must define a trump or no-trump; a betting game must
  define how a round closes).
- **Static analysis where feasible** — flag obviously broken definitions
  (a phase with no legal move; an unreachable end) at authoring time.
- **Friendly messages** — specific and actionable, naming the offending
  field and the likely fix.

---

## 13. Worked examples

| File | Family | Demonstrates |
|---|---|---|
| `design/ir/spades.yaml` | trick-taking | the baseline spine; bidding, trick play, cumulative scoring |
| `design/ir/texas-holdem.yaml` | poker | variable player count, staged shared cards, betting rounds, hand-ranking resolution, pot scoring |
| `design/ir/blackjack.yaml` | push-your-luck | house actor + policy, additive resolution, payout scoring, per-player loops |
| `design/ir/solitaire.yaml` | solitaire | optional sections, layout of named stacks, free-play phase, triggers, goal-state end |

---

## 14. Open questions and deferred

- **Authoring surface** — forms vs. a readable mini-language for
  non-technical users. Separate design effort; produces IR. Deferred.
- **Expression-language grammar** — the exact accessor set and node grammar
  for the total expression core. Needs a formal pass.
- **Betting depth** — raise semantics, no-limit vs fixed-limit, side pots.
  Deliberately abstracted so far.
- **Remaining families** — melding/rummy and shedding are unvalidated.
  Solitaire was the sharpest edge; these are lower-risk but worth a sketch.
- **Serialization format** — concrete on-the-wire/storage representation of
  the IR and of game state.
- **Server layer** — per-player state views (information hiding),
  persistence, seeded RNG. Built on top of the reducer later.
- **Engine packaging** — separate package depending on `likewinter/card-deck`
  via a Composer path repository (decided in principle).
