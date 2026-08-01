# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-08-01

### Added

- The card-game IR: `GameDefinition` and its spine sections as immutable value
  objects (meta, players, deck, layout, deal, phases, resolution, scoring, end),
  with the family-contract interfaces (`Phase`, `Resolver`, `ScoringModel`,
  `EndCondition`).
- The `Engine` reducer: `start`, `legalMoves`, `apply`, `isOver`, `winner`.
- The trick-taking family dialect (`TrickTakingHandler`, `TrickResolver`,
  `TrickTakingScorer`): bidding, trick play with follow-suit legality,
  `SuitOrder`-based resolution, cumulative scoring (made/failed bids, bags,
  nil), multi-round play, and target-score end detection.
- `Dealer` for seeded, reproducible dealing (`Mt19937(seed + round)`).
- Immutable `GameState` / `RoundState` and the `Move` types (`Bid`, `PlayCard`).
- Design artifacts: the IR specification (`design/ir-spec.md`) and corpus
  sketches for Spades, Texas Hold'em, Blackjack, and Klondike.
