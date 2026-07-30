<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * When the game stops and what the result means.
 *
 * Family-polymorphic: target score, elimination, fixed hands / bankroll, or a
 * goal state. The IR carries the condition as data; the engine evaluates it.
 */
interface EndCondition
{
    public function kind(): string;
}
