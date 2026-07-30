<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * A named stack in the layout: its visibility policy, how many copies of it
 * exist (e.g. seven tableau piles), and an optional capacity.
 *
 * Build/move rules are family-specific and live with the family dialect; this
 * spec captures the structural, family-agnostic facts.
 */
final readonly class StackSpec
{
    public function __construct(
        public string $name,
        public Visibility $visibility = Visibility::Up,
        public int $count = 1,
        public ?int $capacity = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Stack name must not be empty');
        }
        if ($count < 1) {
            throw new \InvalidArgumentException('Stack count must be at least 1');
        }
        if ($capacity !== null && $capacity < 1) {
            throw new \InvalidArgumentException('Stack capacity must be at least 1 when set');
        }
    }
}
