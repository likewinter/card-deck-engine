<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Ends;

use Likewinter\CardDeckEngine\Definition\EndCondition;
use Likewinter\CardDeckEngine\Definition\WinnerRule;

/**
 * The game ends when a player's cumulative score reaches the target; the
 * winner is decided by the given rule (highest score by default).
 */
final readonly class TargetScoreEnd implements EndCondition
{
    public function __construct(
        public int $target,
        public WinnerRule $winner = WinnerRule::HighestScore,
    ) {
        if ($target < 1) {
            throw new \InvalidArgumentException('Target score must be at least 1');
        }
    }

    public function kind(): string
    {
        return 'target-score';
    }
}
