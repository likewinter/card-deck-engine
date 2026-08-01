<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine;

use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Likewinter\CardDeckEngine\Family\FamilyHandler;
use Likewinter\CardDeckEngine\Family\TrickTaking\TrickTakingHandler;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\Setup\Dealer;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * The game runtime: interprets a GameDefinition and drives games over the
 * card-deck primitives.
 *
 * A pure reducer over immutable GameState: start() produces the initial state;
 * legalMoves() and apply() drive play; isOver() and winner() report the
 * result. Dealing lives in Dealer; family-specific rules live in FamilyHandler
 * implementations.
 */
final class Engine
{
    /**
     * Start a new game: deal the first round and return the initial state.
     *
     * Pass a seed for reproducible deals across every round (tests, replays,
     * server fairness); omit it for cryptographically random ones.
     *
     * @param list<string> $players seated player ids, in turn order
     */
    public static function start(GameDefinition $definition, array $players, ?int $seed = null): GameState
    {
        self::validatePlayers($definition, $players);

        $handler = self::handlerFor($definition);
        $hands = Dealer::deal($definition, $players, $seed, 0);
        $firstPhase = $definition->phases[0] ?? throw new \LogicException('A game definition has at least one phase');

        return new GameState(
            definition: $definition,
            players: $players,
            hands: $hands,
            scores: array_fill_keys($players, 0),
            phase: $firstPhase->id(),
            turn: 0,
            round: $handler->freshRound(),
            seed: $seed,
            roundNumber: 0,
        );
    }

    /**
     * The legal moves for the current actor in the current phase.
     *
     * @return list<Move>
     */
    public static function legalMoves(GameState $state): array
    {
        return self::handlerFor($state->definition)->legalMoves($state);
    }

    /**
     * Apply a move, returning the next immutable state.
     *
     * When the move completes a round (the handler transitions to a
     * round-end phase), the engine scores the round, checks the end
     * condition, and either terminates or deals a fresh round.
     */
    public static function apply(GameState $state, Move $move): GameState
    {
        $handler = self::handlerFor($state->definition);
        $state = $handler->apply($state, $move);

        return self::advanceRoundIfComplete($handler, $state);
    }

    public static function isOver(GameState $state): bool
    {
        return self::handlerFor($state->definition)->isOver($state);
    }

    public static function winner(GameState $state): ?string
    {
        return self::handlerFor($state->definition)->winner($state);
    }

    private static function handlerFor(GameDefinition $definition): FamilyHandler
    {
        return match ($definition->meta->family) {
            FamilyKind::TrickTaking => new TrickTakingHandler(),
            default => throw new \InvalidArgumentException("Unsupported family: {$definition->meta->family->value}"),
        };
    }

    /**
     * If the handler transitioned to a round-end phase, run the lifecycle:
     * score the round, check the end condition, and either terminate or
     * deal a fresh round and reset.
     */
    private static function advanceRoundIfComplete(FamilyHandler $handler, GameState $state): GameState
    {
        $phase = $state->definition->phase($state->phase);
        if ($phase === null || !$phase->isRoundEnd()) {
            return $state;
        }

        $state = $state->withScores($handler->scoreRound($state));

        if ($handler->isOver($state)) {
            return $state;
        }

        $next = $phase->then() ?? throw new \LogicException('Round-end phase has no successor');
        $nextRound = $state->roundNumber + 1;

        return $state
            ->withHands(Dealer::deal($state->definition, $state->players, $state->seed, $nextRound))
            ->withRound($handler->freshRound())
            ->withPhase($next)
            ->withTurn(0)
            ->withRoundNumber($nextRound);
    }

    /**
     * @param list<string> $players
     */
    private static function validatePlayers(GameDefinition $definition, array $players): void
    {
        $count = count($players);
        $allowed = $definition->players;

        if (!$allowed->allows($count)) {
            throw new \InvalidArgumentException(
                "This game takes {$allowed->min}..{$allowed->max} players, got {$count}",
            );
        }

        if (count(array_unique($players)) !== $count) {
            throw new \InvalidArgumentException('Player ids must be unique');
        }
    }
}
