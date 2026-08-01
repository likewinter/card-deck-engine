<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Phases;

use Likewinter\CardDeckEngine\Definition\Phase;

/**
 * Score the completed round, then advance (typically looping back to bidding
 * until the end condition fires).
 */
final readonly class TallyPhase implements Phase
{
    public function __construct(
        public string $id,
        public ?string $then,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('Phase id must not be empty');
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
        return 'tally';
    }

    public function isRoundEnd(): bool
    {
        return true;
    }
}
