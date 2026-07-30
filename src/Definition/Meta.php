<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * Identity of a game definition: its name, its family (dialect), and an
 * optional `extends` naming the concrete game this is a variant of.
 */
final readonly class Meta
{
    public function __construct(
        public string $name,
        public FamilyKind $family,
        public ?string $extends = null,
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Game name must not be empty');
        }
    }

    public function isVariant(): bool
    {
        return $this->extends !== null;
    }
}
