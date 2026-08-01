<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Setup;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\DeckBuilder;
use Likewinter\CardDeck\DrawMode;
use Likewinter\CardDeckEngine\Definition\DeckComposition;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Random\Engine\Mt19937;
use Random\Engine\Secure;
use Random\Randomizer;

/**
 * Builds, shuffles, and deals a deck for a game definition.
 *
 * Shared by game setup (Engine::start) and multi-round re-deals. A base seed
 * plus the round number yields a distinct, reproducible shuffle per round; a
 * null seed deals cryptographically random rounds.
 */
final class Dealer
{
    /**
     * @param list<string> $players
     *
     * @return array<string, list<Card>>
     */
    public static function deal(GameDefinition $definition, array $players, ?int $seed, int $round): array
    {
        $effectiveSeed = $seed !== null ? $seed + $round : null;

        return self::dealCards($definition, self::shuffle(self::buildDeck($definition), $effectiveSeed), $players);
    }

    /**
     * @return list<Card>
     */
    private static function buildDeck(GameDefinition $definition): array
    {
        $cards = [];
        foreach ($definition->deck as $spec) {
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
    private static function dealCards(GameDefinition $definition, array $deck, array $players): array
    {
        /** @var array<string, list<Card>> $hands */
        $hands = [];
        foreach ($players as $player) {
            $hands[$player] = [];
        }

        $index = 0;
        $total = count($deck);

        foreach ($definition->deal as $step) {
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
