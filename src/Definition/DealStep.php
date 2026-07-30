<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

use Likewinter\CardDeck\DrawMode;

/**
 * One dealing action: move cards from the deck into a named stack (or into
 * each player's hand via the 'each_player' target).
 *
 * `cards` is null to deal all remaining cards. `at` is null for the initial
 * deal, or a phase id to stage the deal/reveal at that phase.
 */
final readonly class DealStep
{
    public function __construct(
        public string $to,
        public ?int $cards = null,
        public DrawMode $mode = DrawMode::OneByOne,
        public Visibility $visibility = Visibility::Up,
        public ?string $at = null,
    ) {
        if ($to === '') {
            throw new \InvalidArgumentException('Deal target must not be empty');
        }
        if ($cards !== null && $cards < 1) {
            throw new \InvalidArgumentException('Deal card count must be at least 1 when set');
        }
    }
}
