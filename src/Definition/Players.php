<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The player configuration: how many seats and the turn direction.
 */
final readonly class Players
{
    public function __construct(
        public PlayerCount $count,
        public PlayOrder $order = PlayOrder::Clockwise,
    ) {}
}
