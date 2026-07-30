<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * How many players a game takes: a fixed count or an inclusive [min, max]
 * range.
 */
final readonly class PlayerCount
{
    public function __construct(
        public int $min,
        public int $max,
    ) {
        if ($min < 1) {
            throw new \InvalidArgumentException('Player count minimum must be at least 1');
        }
        if ($max < $min) {
            throw new \InvalidArgumentException('Player count maximum must be >= minimum');
        }
    }

    public static function fixed(int $count): self
    {
        return new self($count, $count);
    }

    public function isFixed(): bool
    {
        return $this->min === $this->max;
    }

    public function allows(int $count): bool
    {
        return $count >= $this->min && $count <= $this->max;
    }
}
