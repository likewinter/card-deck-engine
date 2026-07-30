<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Scoring;

use Likewinter\CardDeckEngine\Definition\ScoringModel;

/**
 * Cumulative-points scoring: a per-round formula summed toward a target.
 *
 * For trick-taking: +perTrick for each trick bid (when the bid is made),
 * +overtrick per bag, and nilSuccess / nilFailure for a nil bid. The target
 * itself lives in the end condition, not here.
 */
final readonly class CumulativeScoring implements ScoringModel
{
    public function __construct(
        public int $perTrick,
        public int $overtrick = 0,
        public int $nilSuccess = 0,
        public int $nilFailure = 0,
    ) {}

    public function kind(): string
    {
        return 'cumulative';
    }
}
