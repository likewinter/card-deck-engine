<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The deck composition: one or more DeckSpec entries combined.
 */
final readonly class Deck
{
    /**
     * @param list<DeckSpec> $specs
     */
    public function __construct(
        public array $specs,
    ) {
        if ($specs === []) {
            throw new \InvalidArgumentException('A deck needs at least one specification');
        }
    }

    public static function standard52(int $copies = 1): self
    {
        return new self([new DeckSpec(DeckComposition::Standard52, $copies)]);
    }
}
