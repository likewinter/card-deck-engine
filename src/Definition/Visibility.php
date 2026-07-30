<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * A stack-level visibility policy in the layout.
 *
 * Covers both the face axis (up / down / top card up) and the ownership axis
 * (private to a seat vs shared). The engine maps this onto the library's
 * runtime Face / CardInPlay state.
 */
enum Visibility: string
{
    case Up = 'up';
    case Down = 'down';
    case TopUp = 'top_up';
    case Private = 'private';
    case Shared = 'shared';
}
