<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * How results accumulate (cumulative points, pot award, payout multiplier).
 *
 * Family-polymorphic and optional — puzzle games have no scoring. The IR
 * carries the scoring *parameters* as data; the engine evaluates them against
 * play results.
 */
interface ScoringModel
{
    public function kind(): string;
}
