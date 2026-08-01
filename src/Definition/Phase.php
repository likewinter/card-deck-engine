<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * A phase in the game's state machine.
 *
 * Concrete phases are family-specific (trick-taking contributes bid /
 * trick-play / tally; poker contributes betting-round / showdown; and so on).
 * Every phase has an id, an optional `then` (the phase to advance to; null
 * for an open phase that runs until the end condition fires), and a `kind`
 * discriminator used by the engine and for serialization.
 */
interface Phase
{
    public function id(): string;

    public function then(): ?string;

    public function kind(): string;

    /**
     * Whether this phase ends a round: the engine scores, checks the end
     * condition, and either terminates or deals a fresh round.
     */
    public function isRoundEnd(): bool;
}
