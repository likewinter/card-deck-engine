<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * How a contest is decided (trick winner, hand ranking, additive total, ...).
 *
 * Family-polymorphic and optional — puzzle games have no resolver. The IR
 * carries resolver *parameters* as immutable data; the engine supplies the
 * behavior (e.g. building a SuitOrder for a trick-winner resolver).
 */
interface Resolver
{
    public function kind(): string;
}
