<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Move;

use Likewinter\CardDeck\Card;

/**
 * Play a card from the player's hand into the current trick.
 */
final readonly class PlayCard implements Move
{
    public function __construct(
        public string $player,
        public Card $card,
    ) {}

    public function player(): string
    {
        return $this->player;
    }
}
