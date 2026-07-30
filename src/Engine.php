<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\DeckBuilder;
use Likewinter\CardDeck\DrawMode;
use Likewinter\CardDeckEngine\Definition\DeckComposition;
use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Likewinter\CardDeckEngine\Family\FamilyHandler;
use Likewinter\CardDeckEngine\Family\TrickTaking\TrickTakingHandler;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\State\GameState;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * The game runtime: interprets a GameDefinition and drives games over the
 * card-deck primitives.
 *
 * Phase 1 provides game setup (build the deck, shuffle deterministically,
 * deal, and produce the initial immutable state). Move handling, resolution,
 * scoring, and end detection land in later phases.
 */
final class Engine
{
    /**
     * Start a new game: build and shuffle the deck, deal per the definition,
     * and return the initial state.
     *
     * Pass a seed for a reproducible deal (tests, replays, server fairness);
     * omit it for a cryptographically random one.
     *
     * @param list<string> $players seated player ids, in turn order
     */
    public static function start(GameDefinition $definition, array $players, ?int $seed = null): GameState
    {
        self::validatePlayers($definition, $players);

        $deck = self::shuffle(self::buildDeck($definition), $seed);
        $hands = self::deal($definition, $deck, $players);
        $firstPhase = $definition->phases[0] ?? throw new \LogicException('A game definition has at least one phase');

        return new GameState(
            definition: $definition,
            players: $players,
            hands: $hands,
            scores: array_fill_keys($players, 0),
            phase: $firstPhase->id(),
            turn: 0,
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
     */
    public static function apply(GameState $state, Move $move): GameState
    {
        return self::handlerFor($state->definition)->apply($state, $move);
    }

    private static function handlerFor(GameDefinition $definition): FamilyHandler
    {
        return match ($definition->meta->family) {
            FamilyKind::TrickTaking => new TrickTakingHandler(),
            default => throw new \InvalidArgumentException("Unsupported family: {$definition->meta->family->value}"),
        };
    }

    /**
     * @param list<string> $players
     */
    private static function validatePlayers(GameDefinition $definition, array $players): void
    {
        $count = count($players);
        $allowed = $definition->players->count;

        if (!$allowed->allows($count)) {
            throw new \InvalidArgumentException(
                "This game takes {$allowed->min}..{$allowed->max} players, got {$count}",
            );
        }

        if (count(array_unique($players)) !== $count) {
            throw new \InvalidArgumentException('Player ids must be unique');
        }
    }

    /**
     * @return list<Card>
     */
    private static function buildDeck(GameDefinition $definition): array
    {
        $cards = [];
        foreach ($definition->deck->specs as $spec) {
            $builder = match ($spec->composition) { DeckComposition::Standard52 => DeckBuilder::standard52() };

            $cards = [...$cards, ...$builder->times($spec->copies)->buildCards()];
        }

        return $cards;
    }

    /**
     * Deterministic Fisher–Yates. A seeded Mt19937 makes the deal reproducible;
     * without a seed, a secure engine is used.
     *
     * @param list<Card> $cards
     *
     * @return list<Card>
     */
    private static function shuffle(array $cards, ?int $seed): array
    {
        $randomizer = new Randomizer($seed !== null ? new Mt19937($seed) : new Secure());

        $result = $cards;
        for ($i = count($result) - 1; $i > 0; $i--) {
            $j = $randomizer->getInt(0, $i);
            [$result[$i], $result[$j]] = [$result[$j], $result[$i]];
        }

        return array_values($result);
    }

    /**
     * Deal the (already shuffled) deck into player hands per the definition's
     * deal steps. Supports the 'each_player' target with OneByOne (round-robin)
     * or Sequential (chunked) modes; `cards: null` deals all remaining cards.
     *
     * @param list<Card>   $deck
     * @param list<string> $players
     *
     * @return array<string, list<Card>>
     */
    private static function deal(GameDefinition $definition, array $deck, array $players): array
    {
        /** @var array<string, list<Card>> $hands */
        $hands = [];
        foreach ($players as $player) {
            $hands[$player] = [];
        }

        $index = 0;
        $total = count($deck);

        foreach ($definition->deal->steps as $step) {
            if ($step->to !== 'each_player') {
                continue;
            }

            $perPlayer = $step->cards ?? intdiv($total - $index, count($players));

            if ($step->mode === DrawMode::Sequential) {
                foreach ($players as $player) {
                    for ($n = 0; $n < $perPlayer && $index < $total; $n++) {
                        $hands[$player][] = $deck[$index];
                        $index++;
                    }
                }

                continue;
            }

            // OneByOne (and Random, which is round-robin after the shuffle).
            for ($n = 0; $n < $perPlayer; $n++) {
                foreach ($players as $player) {
                    if ($index >= $total) {
                        break 2;
                    }
                    $hands[$player][] = $deck[$index];
                    $index++;
                }
            }
        }

        return $hands;
    }
}
