<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Phases;

use Likewinter\CardDeckEngine\Definition\Phase;

/**
 * Trick-taking play: a fixed number of tricks, one card per player each.
 * Trump and lead rules live with the resolver / family dialect.
 */
final readonly class TrickPlayPhase implements Phase
{
    public function __construct(
        public string $id,
        public ?string $then,
        public int $tricks,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('Phase id must not be empty');
        }
        if ($tricks < 1) {
            throw new \InvalidArgumentException('Trick count must be at least 1');
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
        return 'trick-play';
    }

    public function isRoundEnd(): bool
    {
        return false;
    }
}
