<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * Seating / turn direction around the table.
 */
enum PlayOrder: string
{
    case Clockwise = 'clockwise';
    case CounterClockwise = 'counterclockwise';
}
