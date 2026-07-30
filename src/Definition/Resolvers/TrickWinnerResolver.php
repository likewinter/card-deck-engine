<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition\Resolvers;

use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeckEngine\Definition\Resolver;

/**
 * Trick-winner resolution parameters: the trump suit (null for no-trump).
 * The engine builds a SuitOrder from this plus a rank ordering and uses it to
 * decide each trick.
 */
final readonly class TrickWinnerResolver implements Resolver
{
    public function __construct(
        public ?Suit $trump = null,
    ) {
        if ($trump === Suit::Joker) {
            throw new \InvalidArgumentException('Joker cannot be a trump suit');
        }
    }

    public function kind(): string
    {
        return 'trick-winner';
    }
}
