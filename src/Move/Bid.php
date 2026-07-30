<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Move;

/**
 * A trick-taking bid: a player commits to winning `amount` tricks. An amount
 * of 0 is the special "nil" bid.
 */
final readonly class Bid implements Move
{
    public function __construct(
        public string $player,
        public int $amount,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Bid amount must be >= 0');
        }
    }

    public function player(): string
    {
        return $this->player;
    }
}
