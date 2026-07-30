<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family\TrickTaking;

use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Family\FamilyHandler;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * The trick-taking dialect (Spades, Hearts, Bridge, ...).
 *
 * Phase 2 implements bidding and the bidding -> play phase transition. Trick
 * play, resolution, scoring, and end detection land in later phases.
 */
final class TrickTakingHandler implements FamilyHandler
{
    public function legalMoves(GameState $state): array
    {
        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase) {
            return $this->legalBids($state, $phase);
        }

        return [];
    }

    public function apply(GameState $state, Move $move): GameState
    {
        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase && $move instanceof Bid) {
            return $this->applyBid($state, $phase, $move);
        }

        throw new \InvalidArgumentException('Move is not legal in the current phase');
    }

    /**
     * @return list<Move>
     */
    private function legalBids(GameState $state, BidPhase $phase): array
    {
        $player = $state->currentPlayer();

        if (array_key_exists($player, $state->round->bids)) {
            return [];
        }

        $moves = [];
        for ($amount = $phase->min; $amount <= $phase->max; $amount++) {
            $moves[] = new Bid($player, $amount);
        }

        return $moves;
    }

    private function applyBid(GameState $state, BidPhase $phase, Bid $move): GameState
    {
        $player = $move->player();

        if ($player !== $state->currentPlayer()) {
            throw new \InvalidArgumentException("It is not {$player}'s turn to bid");
        }
        if ($move->amount < $phase->min || $move->amount > $phase->max) {
            throw new \InvalidArgumentException("Bid must be between {$phase->min} and {$phase->max}");
        }
        if (array_key_exists($player, $state->round->bids)) {
            throw new \InvalidArgumentException("{$player} has already bid");
        }

        $round = $state->round->withBid($player, $move->amount);
        $state = $state->withRound($round);

        if (count($round->bids) === count($state->players)) {
            $next = $phase->then() ?? throw new \LogicException('Bidding phase has no successor');

            return $state->withPhase($next)->withTurn(0);
        }

        return $state->withTurn($state->turn + 1);
    }
}
