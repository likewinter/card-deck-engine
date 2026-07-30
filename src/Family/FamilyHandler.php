<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family;

use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * A family dialect: the behavior the engine delegates to for a given family.
 *
 * Given the immutable game state, a handler reports the legal moves for the
 * current actor, applies a move to produce the next state (including phase
 * transitions, resolution, and scoring), and reports whether the game is over
 * and who won. The engine dispatches to a handler keyed by the definition's
 * family.
 */
interface FamilyHandler
{
    /**
     * @return list<Move>
     */
    public function legalMoves(GameState $state): array;

    public function apply(GameState $state, Move $move): GameState;

    public function isOver(GameState $state): bool;

    public function winner(GameState $state): ?string;
}
