<?php

declare(strict_types=1);

use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\Move;
use Likewinter\CardDeckEngine\State\GameState;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Fixtures\Spades;

/**
 * Choose a move: bid conservatively (the legal bid closest to $bidTarget, so
 * bids are usually made and scores accumulate) and play cards at random.
 *
 * @param list<Move> $legal
 */
function pickMove(array $legal, int $bidTarget, Randomizer $random): Move
{
    $bestBid = null;
    $bestDistance = null;
    foreach ($legal as $move) {
        if (!$move instanceof Bid) {
            continue;
        }
        $distance = abs($move->amount - $bidTarget);
        if ($bestDistance === null || $distance < $bestDistance) {
            $bestBid = $move;
            $bestDistance = $distance;
        }
    }

    if ($bestBid !== null) {
        return $bestBid;
    }

    return $legal[$random->getInt(0, count($legal) - 1)] ?? throw new \LogicException('No legal move');
}

/**
 * Play a complete Spades game to a winner. Deterministic for a given seed.
 * Throws if the game deadlocks (no legal move while not over) or fails to
 * terminate within the move bound.
 */
function playGame(int $seed, int $bidTarget = 3, int $maxMoves = 5000): GameState
{
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: $seed);
    $random = new Randomizer(new Mt19937($seed));

    $moves = 0;
    while (!Engine::isOver($state)) {
        $legal = Engine::legalMoves($state);
        if ($legal === []) {
            throw new \RuntimeException("Deadlock: no legal moves in phase '{$state->phase}'");
        }

        $state = Engine::apply($state, pickMove($legal, $bidTarget, $random));

        if (++$moves > $maxMoves) {
            throw new \RuntimeException("Game did not terminate within {$maxMoves} moves");
        }
    }

    return $state;
}

test('a full game played to completion terminates with a winner', function (): void {
    $state = playGame(seed: 42);

    expect(Engine::isOver($state))->toBeTrue();
    expect(Engine::winner($state))->not->toBeNull();
    expect(Engine::legalMoves($state))->toBe([]);
});

test('the same seed plays the same game', function (): void {
    $a = playGame(seed: 7);
    $b = playGame(seed: 7);

    expect($a->scores)->toBe($b->scores);
    expect($a->roundNumber)->toBe($b->roundNumber);
    expect(Engine::winner($a))->toBe(Engine::winner($b));
});

test('the winner has the highest score', function (): void {
    $state = playGame(seed: 99);

    $winner = Engine::winner($state);
    expect($winner)->not->toBeNull();
    if ($winner === null) {
        return;
    }

    $winnerScore = $state->score($winner);
    foreach ($state->scores as $score) {
        expect($winnerScore)->toBeGreaterThanOrEqual($score);
    }
});

test('many seeded games all terminate without deadlock', function (): void {
    for ($seed = 1; $seed <= 25; $seed++) {
        $state = playGame(seed: $seed);

        expect(Engine::isOver($state))->toBeTrue();
        expect(Engine::winner($state))->not->toBeNull();
    }
});
