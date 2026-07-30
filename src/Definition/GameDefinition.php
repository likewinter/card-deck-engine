<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The root of the IR: a complete, serializable description of a card game.
 *
 * The spine is fixed; `resolver`, `scoring`, and `triggers` are optional
 * (whole families omit them). The contents of most sections are family
 * dialects. This is pure data — the engine interprets it.
 */
final readonly class GameDefinition
{
    /**
     * @param list<Phase>   $phases
     * @param list<Trigger> $triggers
     */
    public function __construct(
        public Meta $meta,
        public Players $players,
        public Deck $deck,
        public Layout $layout,
        public Deal $deal,
        public array $phases,
        public EndCondition $end,
        public ?Resolver $resolver = null,
        public ?ScoringModel $scoring = null,
        public array $triggers = [],
    ) {
        if ($phases === []) {
            throw new \InvalidArgumentException('A game needs at least one phase');
        }
    }
}
