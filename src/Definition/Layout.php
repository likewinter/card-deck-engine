<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The table layout: the named stacks that make up the game state. `deal` is
 * just the initial population of this layout.
 */
final readonly class Layout
{
    /**
     * @param list<StackSpec> $stacks
     */
    public function __construct(
        public array $stacks,
    ) {}

    public function named(string $name): ?StackSpec
    {
        foreach ($this->stacks as $stack) {
            if ($stack->name === $name) {
                return $stack;
            }
        }

        return null;
    }
}
