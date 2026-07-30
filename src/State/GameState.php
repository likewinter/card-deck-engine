<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\State;

use Likewinter\CardDeck\Card;
use Likewinter\CardDeckEngine\Definition\GameDefinition;

/**
 * The complete, immutable state of a game in progress.
 *
 * A pure reducer operates on this: (state, move) -> state. Card collections
 * are plain immutable lists (not the library's mutable Stack/Table), which
 * keeps the state serializable and trivially cloneable for undo/replay and a
 * future authoritative server. The library's immutable value objects (Card,
 * Rank, Suit, RankOrder, SuitOrder) are still used for domain logic.
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
        public RoundState $round = new RoundState(),
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
        );
    }
}
