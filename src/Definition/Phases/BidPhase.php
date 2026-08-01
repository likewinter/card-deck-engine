<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Phases;

use Likewinter\CardDeckEngine\Definition\Phase;

/**
 * Trick-taking bidding: each player commits a bid in [min, max] in turn.
 * A minimum of 0 enables the special "nil" bid.
 */
final readonly class BidPhase implements Phase
{
    public function __construct(
        public string $id,
        public ?string $then,
        public int $min,
        public int $max,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('Phase id must not be empty');
        }
        if ($min < 0) {
            throw new \InvalidArgumentException('Bid minimum must be >= 0');
        }
        if ($max < $min) {
            throw new \InvalidArgumentException('Bid maximum must be >= minimum');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function then(): ?string
    {
        return $this->then;
    }

    public function kind(): string
    {
        return 'bid';
    }

    public function isRoundEnd(): bool
    {
        return false;
    }
}
