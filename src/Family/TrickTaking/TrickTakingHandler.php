<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Family\TrickTaking;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeck\RankOrder;
use Likewinter\CardDeck\SuitOrder;
use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TrickPlayPhase;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\Family\FamilyHandler;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\Move\PlayCard;
use Likewinter\CardDeckEngine\State\GameState;

/**
 * The trick-taking dialect (Spades, Hearts, Bridge, ...).
 *
 * Implements bidding, trick play with follow-suit legality, and trick
 * resolution via the library's SuitOrder. Scoring and end detection land in a
 * later phase.
 */
final class TrickTakingHandler implements FamilyHandler
{
    public function legalMoves(GameState $state): array
    {
        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase) {
            return $this->legalBids($state, $phase);
        }
        if ($phase instanceof TrickPlayPhase) {
            return $this->legalPlays($state);
        }

        return [];
    }

    public function apply(GameState $state, Move $move): GameState
    {
        $phase = $state->definition->phase($state->phase);

        if ($phase instanceof BidPhase && $move instanceof Bid) {
            return $this->applyBid($state, $phase, $move);
        }
        if ($phase instanceof TrickPlayPhase && $move instanceof PlayCard) {
            return $this->applyPlay($state, $phase, $move);
        }

        throw new \InvalidArgumentException('Move is not legal in the current phase');
    }

    // --- Bidding -----------------------------------------------------------

    /**
     * @return list<Move>
     */
    private function legalBids(GameState $state, BidPhase $phase): array
    {
        $player = $state->currentPlayer();

        if (array_key_exists($player, $state->round->bids)) {
            return [];
        }

        $moves = [];
        for ($amount = $phase->min; $amount <= $phase->max; $amount++) {
            $moves[] = new Bid($player, $amount);
        }

        return $moves;
    }

    private function applyBid(GameState $state, BidPhase $phase, Bid $move): GameState
    {
        $player = $move->player();

        if ($player !== $state->currentPlayer()) {
            throw new \InvalidArgumentException("It is not {$player}'s turn to bid");
        }
        if ($move->amount < $phase->min || $move->amount > $phase->max) {
            throw new \InvalidArgumentException("Bid must be between {$phase->min} and {$phase->max}");
        }
        if (array_key_exists($player, $state->round->bids)) {
            throw new \InvalidArgumentException("{$player} has already bid");
        }

        $round = $state->round->withBid($player, $move->amount);
        $state = $state->withRound($round);

        if (count($round->bids) === count($state->players)) {
            $next = $phase->then() ?? throw new \LogicException('Bidding phase has no successor');

            return $state->withPhase($next)->withTurn(0);
        }

        return $state->withTurn($state->turn + 1);
    }

    // --- Trick play --------------------------------------------------------

    /**
     * @return list<Move>
     */
    private function legalPlays(GameState $state): array
    {
        $player = $state->currentPlayer();

        $moves = [];
        foreach ($state->hand($player) as $card) {
            if (!$this->isLegalPlay($state, $card)) {
                continue;
            }

            $moves[] = new PlayCard($player, $card);
        }

        return $moves;
    }

    private function applyPlay(GameState $state, TrickPlayPhase $phase, PlayCard $move): GameState
    {
        $player = $move->player();

        if ($player !== $state->currentPlayer()) {
            throw new \InvalidArgumentException("It is not {$player}'s turn to play");
        }
        if (!$this->handContains($state->hand($player), $move->card)) {
            throw new \InvalidArgumentException("{$player} does not hold that card");
        }
        if (!$this->isLegalPlay($state, $move->card)) {
            throw new \InvalidArgumentException('That card is not a legal play');
        }

        $hands = $state->hands;
        $hands[$player] = $this->removeCard($state->hand($player), $move->card);
        $state = $state->withHands($hands);

        $trick = $state->round->trick;
        $trick[$player] = $move->card;
        $round = $state->round->withTrick($trick)->withTrickLeader($state->round->trickLeader ?? $player);
        $state = $state->withRound($round);

        if (count($trick) === count($state->players)) {
            return $this->resolveTrick($state, $phase);
        }

        return $state->withTurn(($state->turn + 1) % count($state->players));
    }

    private function isLegalPlay(GameState $state, Card $card): bool
    {
        // Leading an empty trick: any card is legal. (The spades-broken
        // restriction is deliberately omitted in v1.)
        if ($state->round->trick === []) {
            return true;
        }

        $leadSuit = $this->leadSuit($state);
        if ($leadSuit === null) {
            return true;
        }

        // Must follow the lead suit when able; otherwise anything goes.
        if ($this->handHasSuit($state->hand($state->currentPlayer()), $leadSuit)) {
            return $card->suit === $leadSuit;
        }

        return true;
    }

    private function resolveTrick(GameState $state, TrickPlayPhase $phase): GameState
    {
        $winner = $this->trickWinner($state);
        $tricksWon = ($state->round->tricksWon[$winner] ?? 0) + 1;

        $round = $state
            ->round
            ->withTricksWon($winner, $tricksWon)
            ->withTrick([])
            ->withTrickLeader($winner)
            ->withTricksPlayed($state->round->tricksPlayed + 1);
        $state = $state->withRound($round);

        $turn = $this->playerIndex($state, $winner);

        if ($round->tricksPlayed >= $phase->tricks) {
            $next = $phase->then() ?? throw new \LogicException('Trick-play phase has no successor');

            return $state->withPhase($next)->withTurn($turn);
        }

        return $state->withTurn($turn);
    }

    private function trickWinner(GameState $state): string
    {
        $trick = $state->round->trick;
        $order = $this->playOrder($state);
        $suitOrder = $this->suitOrder($state);

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

    // --- Helpers -----------------------------------------------------------

    private function suitOrder(GameState $state): SuitOrder
    {
        $resolver = $state->definition->resolver;
        $trump = $resolver instanceof TrickWinnerResolver ? $resolver->trump : null;
        $rankOrder = RankOrder::poker();

        return $trump !== null ? SuitOrder::suit($trump, $rankOrder) : SuitOrder::noTrump($rankOrder);
    }

    private function leadSuit(GameState $state): ?Suit
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
    private function playOrder(GameState $state): array
    {
        $players = $state->players;
        $leader = $state->round->trickLeader;
        if ($leader === null) {
            return $players;
        }

        $leaderIndex = $this->playerIndex($state, $leader);
        $count = count($players);

        $order = [];
        for ($k = 0; $k < $count; $k++) {
            $order[] = $players[($leaderIndex + $k) % $count];
        }

        return $order;
    }

    private function playerIndex(GameState $state, string $player): int
    {
        $index = array_search($player, $state->players, true);

        return $index === false ? 0 : $index;
    }

    /**
     * @param list<Card> $hand
     */
    private function handHasSuit(array $hand, Suit $suit): bool
    {
        foreach ($hand as $card) {
            if ($card->suit === $suit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Card> $hand
     */
    private function handContains(array $hand, Card $card): bool
    {
        foreach ($hand as $existing) {
            if ($existing->equals($card)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Card> $hand
     *
     * @return list<Card>
     */
    private function removeCard(array $hand, Card $card): array
    {
        foreach ($hand as $i => $existing) {
            if ($existing->equals($card)) {
                unset($hand[$i]);

                return array_values($hand);
            }
        }

        return $hand;
    }
}
