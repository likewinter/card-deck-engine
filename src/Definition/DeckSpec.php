<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * One entry in a deck: a composition repeated `copies` times (a shoe of N
 * decks is copies > 1).
 */
final readonly class DeckSpec
{
    public function __construct(
        public DeckComposition $composition,
        public int $copies = 1,
    ) {
        if ($copies < 1) {
            throw new \InvalidArgumentException('Deck copies must be at least 1');
        }
    }

    public static function standard52(int $copies = 1): self
    {
        return new self(DeckComposition::Standard52, $copies);
    }
}
