<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family\TrickTaking;

use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TrickPlayPhase;
use Likewinter\CardDeckEngine\Family\FamilyHandler;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\Move\PlayCard;
use Likewinter\CardDeckEngine\State\GameState;
use Likewinter\CardDeckEngine\State\RoundState;

/**
 * The trick-taking dialect (Spades, Hearts, Bridge, ...).
 *
 * Orchestrates bidding, trick play, and the multi-round loop, delegating trick
 * mechanics to TrickResolver and scoring/end detection to TrickTakingScorer.
 */
final class TrickTakingHandler implements FamilyHandler
{
    public function legalMoves(GameState $state): array
    {
        if ($this->isOver($state)) {
            return [];
        }

        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase) {
            return $this->legalBids($state, $phase);
        }
        if ($phase instanceof TrickPlayPhase) {
            return $this->legalPlays($state);
        }

        return [];
    }

    public function apply(GameState $state, Move $move): GameState
    {
        if ($this->isOver($state)) {
            throw new \InvalidArgumentException('The game is over');
        }

        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase && $move instanceof Bid) {
            return $this->applyBid($state, $phase, $move);
        }
        if ($phase instanceof TrickPlayPhase && $move instanceof PlayCard) {
            return $this->applyPlay($state, $phase, $move);
        }

        throw new \InvalidArgumentException('Move is not legal in the current phase');
    }

    public function isOver(GameState $state): bool
    {
        return TrickTakingScorer::isOver($state);
    }

    public function winner(GameState $state): ?string
    {
        return TrickTakingScorer::winner($state);
    }

    public function freshRound(): RoundState
    {
        return TrickTakingRound::fresh();
    }

    public function scoreRound(GameState $state): array
    {
        return TrickTakingScorer::scoresAfterRound($state);
    }

    // --- Bidding -----------------------------------------------------------

    /**
     * @return list<Move>
     */
    private function legalBids(GameState $state, BidPhase $phase): array
    {
        $player = $state->currentPlayer();
        $round = $this->round($state);

        if (array_key_exists($player, $round->bids)) {
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

        $round = $this->round($state);
        if (array_key_exists($player, $round->bids)) {
            throw new \InvalidArgumentException("{$player} has already bid");
        }

        $round = $round->withBid($player, $move->amount);
        $state = $state->withRound($round);

        if (count($round->bids) === count($state->players)) {
            $next = $phase->then() ?? throw new \LogicException('Bidding phase has no successor');

            return $state->withPhase($next)->withTurn(0);
        }

        return $state->withTurn($state->turn + 1);
    }

    // --- Trick play --------------------------------------------------------

    /**
     * @return list<Move>
     */
    private function legalPlays(GameState $state): array
    {
        $player = $state->currentPlayer();

        $moves = [];
        foreach ($state->hand($player) as $card) {
            if (TrickResolver::isLegalPlay($state, $card)) {
                $moves[] = new PlayCard($player, $card);
            }
        }

        return $moves;
    }

    private function applyPlay(GameState $state, TrickPlayPhase $phase, PlayCard $move): GameState
    {
        $player = $move->player();

        if ($player !== $state->currentPlayer()) {
            throw new \InvalidArgumentException("It is not {$player}'s turn to play");
        }
        if (!$state->hasCard($player, $move->card)) {
            throw new \InvalidArgumentException("{$player} does not hold that card");
        }
        if (!TrickResolver::isLegalPlay($state, $move->card)) {
            throw new \InvalidArgumentException('That card is not a legal play');
        }

        $state = $state->withoutCard($player, $move->card);

        $round = $this->round($state);
        $trick = $round->trick;
        $trick[$player] = $move->card;
        $round = $round->withTrick($trick)->withTrickLeader($round->trickLeader ?? $player);
        $state = $state->withRound($round);

        if (count($trick) === count($state->players)) {
            return $this->resolveTrick($state, $phase);
        }

        return $state->withTurn(($state->turn + 1) % count($state->players));
    }

    private function resolveTrick(GameState $state, TrickPlayPhase $phase): GameState
    {
        $winner = TrickResolver::trickWinner($state);
        $round = $this->round($state);
        $tricksWon = ($round->tricksWon[$winner] ?? 0) + 1;

        $round = $round
            ->withTricksWon($winner, $tricksWon)
            ->withTrick([])
            ->withTrickLeader($winner)
            ->withTricksPlayed($round->tricksPlayed + 1);
        $state = $state->withRound($round);

        $turn = $state->playerIndex($winner);

        if ($round->tricksPlayed >= $phase->tricks) {
            $next = $phase->then() ?? throw new \LogicException('Trick-play phase has no successor');

            return $state->withPhase($next)->withTurn($turn);
        }

        return $state->withTurn($turn);
    }

    private function round(GameState $state): TrickTakingRound
    {
        $round = $state->round;

        return $round instanceof TrickTakingRound
            ? $round
            : throw new \LogicException('Trick-taking requires a TrickTakingRound');
    }
}
