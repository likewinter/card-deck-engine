<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Move;

/**
 * A player action submitted to the engine. Concrete moves are family-specific
 * (a bid, a played card, a hit/stand decision, ...). Every move names the
 * player making it.
 */
interface Move
{
    public function player(): string;
}
