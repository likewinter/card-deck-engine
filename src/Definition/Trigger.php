<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * An automatic effect that fires on a state change, not on a player decision
 * (e.g. flip a tableau card when it is uncovered).
 */
final readonly class Trigger
{
    public function __construct(
        public string $on,
        public string $action,
    ) {
        if ($on === '' || $action === '') {
            throw new \InvalidArgumentException('Trigger event and action must not be empty');
        }
    }
}
