<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The initial population of the layout: an ordered list of deal steps.
 */
final readonly class Deal
{
    /**
     * @param list<DealStep> $steps
     */
    public function __construct(
        public array $steps,
    ) {}
}
