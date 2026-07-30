<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\State;

use Likewinter\CardDeck\Card;

/**
 * Per-round transient state for a trick-taking game: the bids, tricks won,
 * the cards in the current trick, who led it, and how many tricks have been
 * completed. Reset to a fresh instance at the start of each round.
 *
 * Immutable: every mutator returns a new instance.
 */
final readonly class RoundState
{
    /**
     * @param array<string, int>  $bids      player => bid for this round
     * @param array<string, int>  $tricksWon player => tricks won this round
     * @param array<string, Card> $trick     player => card played in the current trick
     */
    public function __construct(
        public array $bids = [],
        public array $tricksWon = [],
        public array $trick = [],
        public ?string $trickLeader = null,
        public int $tricksPlayed = 0,
    ) {}

    public static function fresh(): self
    {
        return new self();
    }

    public function withBid(string $player, int $amount): self
    {
        $bids = $this->bids;
        $bids[$player] = $amount;

        return new self($bids, $this->tricksWon, $this->trick, $this->trickLeader, $this->tricksPlayed);
    }

    public function withTricksWon(string $player, int $count): self
    {
        $tricksWon = $this->tricksWon;
        $tricksWon[$player] = $count;

        return new self($this->bids, $tricksWon, $this->trick, $this->trickLeader, $this->tricksPlayed);
    }

    /**
     * @param array<string, Card> $trick
     */
    public function withTrick(array $trick): self
    {
        return new self($this->bids, $this->tricksWon, $trick, $this->trickLeader, $this->tricksPlayed);
    }

    public function withTrickLeader(?string $leader): self
    {
        return new self($this->bids, $this->tricksWon, $this->trick, $leader, $this->tricksPlayed);
    }

    public function withTricksPlayed(int $count): self
    {
        return new self($this->bids, $this->tricksWon, $this->trick, $this->trickLeader, $count);
    }
}
