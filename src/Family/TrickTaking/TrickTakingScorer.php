<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family\TrickTaking;

use Likewinter\CardDeckEngine\Definition\Ends\TargetScoreEnd;
use Likewinter\CardDeckEngine\Definition\Scoring\CumulativeScoring;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * Scoring and end detection for the trick-taking family: cumulative points
 * (made/failed bids, bags, nil) and a target-score ending. Stateless.
 */
final class TrickTakingScorer
{
    /**
     * The cumulative scores after adding the completed round's points.
     *
     * @return array<string, int>
     */
    public static function scoresAfterRound(GameState $state): array
    {
        $scoring = self::scoringModel($state);

        $scores = $state->scores;
        foreach ($state->players as $player) {
            $scores[$player] = ($scores[$player] ?? 0) + self::roundScore($state, $scoring, $player);
        }

        return $scores;
    }

    public static function isOver(GameState $state): bool
    {
        $end = $state->definition->end;
        if (!$end instanceof TargetScoreEnd) {
            return false;
        }

        foreach ($state->scores as $score) {
            if ($score >= $end->target) {
                return true;
            }
        }

        return false;
    }

    public static function winner(GameState $state): ?string
    {
        if (!self::isOver($state)) {
            return null;
        }

        $winner = null;
        $best = null;
        foreach ($state->scores as $player => $score) {
            if ($best === null || $score > $best) {
                $winner = $player;
                $best = $score;
            }
        }

        return $winner;
    }

    private static function scoringModel(GameState $state): CumulativeScoring
    {
        $scoring = $state->definition->scoring;
        if (!$scoring instanceof CumulativeScoring) {
            throw new \LogicException('Trick-taking requires a cumulative scoring model');
        }

        return $scoring;
    }

    private static function roundScore(GameState $state, CumulativeScoring $scoring, string $player): int
    {
        $bid = $state->round->bids[$player] ?? 0;
        $tricks = $state->round->tricksWon[$player] ?? 0;

        if ($bid === 0) {
            return $tricks === 0 ? $scoring->nilSuccess : $scoring->nilFailure;
        }

        if ($tricks >= $bid) {
            return ($bid * $scoring->perTrick) + (($tricks - $bid) * $scoring->overtrick);
        }

        return -$bid * $scoring->perTrick;
    }
}
