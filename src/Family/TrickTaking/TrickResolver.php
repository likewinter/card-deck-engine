<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family\TrickTaking;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeck\RankOrder;
use Likewinter\CardDeck\SuitOrder;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * Trick mechanics for the trick-taking family: follow-suit legality and trick
 * resolution. Stateless; built on the library's SuitOrder.
 */
final class TrickResolver
{
    /**
     * May the current player play this card? Leading an empty trick is always
     * legal; otherwise a player must follow the lead suit when able.
     */
    public static function isLegalPlay(GameState $state, Card $card): bool
    {
        if ($state->round->trick === []) {
            return true;
        }

        $leadSuit = self::leadSuit($state);
        if ($leadSuit === null) {
            return true;
        }

        if (self::handHasSuit($state->hand($state->currentPlayer()), $leadSuit)) {
            return $card->suit === $leadSuit;
        }

        return true;
    }

    /**
     * The player who won the current (complete) trick.
     */
    public static function trickWinner(GameState $state): string
    {
        $trick = $state->round->trick;
        $order = self::playOrder($state);
        $suitOrder = self::suitOrder($state);

        $winner = $order[0] ?? throw new \LogicException('A trick has at least one card');
        $winningCard = $trick[$winner] ?? throw new \LogicException('Trick is missing the lead card');
        $leadSuit = $winningCard->suit;

        for ($i = 1, $n = count($order); $i < $n; $i++) {
            $player = $order[$i];
            $card = $trick[$player] ?? throw new \LogicException('Trick is missing a played card');
            if ($suitOrder->beats($card, $winningCard, $leadSuit)) {
                $winner = $player;
                $winningCard = $card;
            }
        }

        return $winner;
    }

    public static function playerIndex(GameState $state, string $player): int
    {
        $index = array_search($player, $state->players, true);

        return $index === false ? 0 : $index;
    }

    /**
     * @param list<Card> $hand
     */
    public static function handContains(array $hand, Card $card): bool
    {
        foreach ($hand as $existing) {
            if ($existing->equals($card)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove the first occurrence of a card from a hand.
     *
     * @param list<Card> $hand
     *
     * @return list<Card>
     */
    public static function removeCard(array $hand, Card $card): array
    {
        foreach ($hand as $i => $existing) {
            if ($existing->equals($card)) {
                unset($hand[$i]);

                return array_values($hand);
            }
        }

        return $hand;
    }

    private static function suitOrder(GameState $state): SuitOrder
    {
        $resolver = $state->definition->resolver;
        $trump = $resolver instanceof TrickWinnerResolver ? $resolver->trump : null;
        $rankOrder = RankOrder::poker();

        return $trump !== null ? SuitOrder::suit($trump, $rankOrder) : SuitOrder::noTrump($rankOrder);
    }

    private static function leadSuit(GameState $state): ?Suit
    {
        $leader = $state->round->trickLeader;
        if ($leader === null) {
            return null;
        }

        return ($state->round->trick[$leader] ?? null)?->suit;
    }

    /**
     * Players in the order they play to the current trick (leader first).
     *
     * @return list<string>
     */
    private static function playOrder(GameState $state): array
    {
        $players = $state->players;
        $leader = $state->round->trickLeader;
        if ($leader === null) {
            return $players;
        }

        $leaderIndex = self::playerIndex($state, $leader);
        $count = count($players);

        $order = [];
        for ($k = 0; $k < $count; $k++) {
            $order[] = $players[($leaderIndex + $k) % $count];
        }

        return $order;
    }

    /**
     * @param list<Card> $hand
     */
    private static function handHasSuit(array $hand, Suit $suit): bool
    {
        foreach ($hand as $card) {
            if ($card->suit === $suit) {
                return true;
            }
        }

        return false;
    }
}
