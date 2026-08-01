# Playing a game

The [`Engine`](../src/Engine.php) is a pure reducer over an immutable
[`GameState`](../src/State/GameState.php): given a state and a move, it returns
the next state. Nothing is mutated in place.

## The API

```php
Engine::start(GameDefinition $definition, array $players, ?int $seed = null): GameState
Engine::legalMoves(GameState $state): array      // list<Move> for the current player
Engine::apply(GameState $state, Move $move): GameState
Engine::isOver(GameState $state): bool
Engine::winner(GameState $state): ?string        // null while the game is ongoing
```

- **`start()`** validates the players, deals the first round, and returns the
  initial state (scores zero, first phase, first player to act).
- **`legalMoves()`** returns the moves the current player may make in the
  current phase. It is empty once the game is over.
- **`apply()`** validates the move and returns the next state. It throws
  `InvalidArgumentException` on an illegal move (wrong turn, card not held,
  off-suit when able to follow, game already over).
- **`isOver()` / `winner()`** report the result. For a target-score game, the
  game ends when any score reaches the target; the winner is the highest score.

## The game loop

```php
$state = Engine::start($spades, ['north', 'south', 'east', 'west'], seed: 42);

while (!Engine::isOver($state)) {
    $moves = Engine::legalMoves($state);
    // ...choose a move (UI input, an AI, a script)...
    $state = Engine::apply($state, $move);
}

$winner = Engine::winner($state);
```

The engine never needs to be told to advance phases or start a new round. When
the last trick of a round is played, it scores the round and either ends the
game or deals a fresh round and returns to bidding — all within that single
`apply()` call.

## Moves

Moves are small value objects implementing
[`Move`](../src/Move/Move.php) (every move names its player):

- [`Bid`](../src/Move/Bid.php) — `new Bid('north', 3)`
- [`PlayCard`](../src/Move/PlayCard.php) — `new PlayCard('north', $card)`

Other families add their own move types (bet/raise/fold, hit/stand, ...).

## Determinism and seeds

`start()` accepts an optional seed. Deals come from `Mt19937(seed + round)`,
so:

- the same seed always produces the same deal, in every round;
- different seeds produce different deals;
- omitting the seed deals cryptographically random rounds.

This makes games fully reproducible — the same seed replays the same game —
which is what the test suite relies on and what a future authoritative server
needs for fairness and replay.

```php
$a = Engine::start($spades, $players, seed: 7);
$b = Engine::start($spades, $players, seed: 7);
// $a and $b deal identically, round after round
```

## The state

[`GameState`](../src/State/GameState.php) is immutable. Card collections are
plain `list<Card>` (not the library's mutable `Stack`/`Table`), so a state is
trivially serializable and safe to keep around for undo/replay. Useful
accessors:

```php
$state->currentPlayer();      // whose turn it is
$state->hand('north');        // list<Card>
$state->score('north');       // cumulative score
$state->phase;                // current phase id
$state->roundNumber;          // which round (0-based)
$state->round;                // RoundState: bids, tricksWon, current trick, ...
```

`apply()` returns a new state; the old one is untouched. To branch (try a move,
then undo), simply keep the previous state — there is nothing to roll back.
