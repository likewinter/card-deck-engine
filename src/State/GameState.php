<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\State;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeckEngine\Definition\GameDefinition;

/**
 * The complete, immutable state of a game in progress.
 *
 * A pure reducer operates on this: (state, move) -> state. Card collections
 * are plain immutable lists (not the library's mutable Stack/Table), which
 * keeps the state serializable and trivially cloneable for undo/replay and a
 * future authoritative server. The library's immutable value objects (Card,
 * Rank, Suit, RankOrder, SuitOrder) are still used for domain logic.
 *
 * `seed` and `roundNumber` make multi-round deals reproducible: round N is
 * dealt from Mt19937(seed + N).
 */
final readonly class GameState
{
    /**
     * @param list<string>              $players seated player ids, in turn order
     * @param array<string, list<Card>> $hands   player => their cards
     * @param array<string, int>        $scores  player => cumulative score
     */
    public function __construct(
        public GameDefinition $definition,
        public array $players,
        public array $hands,
        public array $scores,
        public string $phase,
        public int $turn,
        public RoundState $round,
        public ?int $seed = null,
        public int $roundNumber = 0,
    ) {}

    public function currentPlayer(): string
    {
        return $this->players[$this->turn];
    }

    /**
     * @return list<Card>
     */
    public function hand(string $player): array
    {
        return $this->hands[$player] ?? [];
    }

    public function score(string $player): int
    {
        return $this->scores[$player] ?? 0;
    }

    public function hasCard(string $player, Card $card): bool
    {
        foreach ($this->hand($player) as $existing) {
            if ($existing->equals($card)) {
                return true;
            }
        }

        return false;
    }

    public function handHasSuit(string $player, Suit $suit): bool
    {
        foreach ($this->hand($player) as $card) {
            if ($card->suit === $suit) {
                return true;
            }
        }

        return false;
    }

    public function playerIndex(string $player): int
    {
        $index = array_search($player, $this->players, true);

        return $index === false ? 0 : $index;
    }

    public function withoutCard(string $player, Card $card): self
    {
        $hand = $this->hand($player);
        foreach ($hand as $i => $existing) {
            if ($existing->equals($card)) {
                unset($hand[$i]);
                $hands = $this->hands;
                $hands[$player] = array_values($hand);

                return $this->withHands($hands);
            }
        }

        return $this;
    }

    /**
     * @param array<string, list<Card>> $hands
     */
    public function withHands(array $hands): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $hands,
            scores: $this->scores,
            phase: $this->phase,
            turn: $this->turn,
            round: $this->round,
            seed: $this->seed,
            roundNumber: $this->roundNumber,
        );
    }

    /**
     * @param array<string, int> $scores
     */
    public function withScores(array $scores): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $this->hands,
            scores: $scores,
            phase: $this->phase,
            turn: $this->turn,
            round: $this->round,
            seed: $this->seed,
            roundNumber: $this->roundNumber,
        );
    }

    public function withPhase(string $phase): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $this->hands,
            scores: $this->scores,
            phase: $phase,
            turn: $this->turn,
            round: $this->round,
            seed: $this->seed,
            roundNumber: $this->roundNumber,
        );
    }

    public function withTurn(int $turn): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $this->hands,
            scores: $this->scores,
            phase: $this->phase,
            turn: $turn,
            round: $this->round,
            seed: $this->seed,
            roundNumber: $this->roundNumber,
        );
    }

    public function withRound(RoundState $round): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $this->hands,
            scores: $this->scores,
            phase: $this->phase,
            turn: $this->turn,
            round: $round,
            seed: $this->seed,
            roundNumber: $this->roundNumber,
        );
    }

    public function withRoundNumber(int $roundNumber): self
    {
        return new self(
            definition: $this->definition,
            players: $this->players,
            hands: $this->hands,
            scores: $this->scores,
            phase: $this->phase,
            turn: $this->turn,
            round: $this->round,
            seed: $this->seed,
            roundNumber: $roundNumber,
        );
    }
}
