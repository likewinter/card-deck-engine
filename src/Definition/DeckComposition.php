<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * What cards a deck is built from. Grows as families need new compositions
 * (short decks, pinochle, jokers, ...). Maps onto DeckBuilder presets.
 */
enum DeckComposition: string
{
    case Standard52 = 'standard52';
}
