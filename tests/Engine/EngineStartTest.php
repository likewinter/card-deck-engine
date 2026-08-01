<?php

declare(strict_types=1);

use Likewinter\CardDeck\Card;
use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Family\TrickTaking\TrickTakingRound;
use Likewinter\CardDeckEngine\State\GameState;
use Tests\Fixtures\Spades;

/**
 * Serialize a state's hands to plain strings for value comparison (Card
 * instances differ across deals even when the cards are equal).
 *
 * @return array<string, list<string>>
 */
function handsAsStrings(GameState $state): array
{
    return array_map(
        static fn (array $hand): array => array_map(static fn (Card $card): string => (string) $card, $hand),
        $state->hands,
    );
}

test('start deals thirteen cards to each of four players', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 42);

    expect($state->hands)->toHaveCount(4);
    foreach ($state->hands as $hand) {
        expect($hand)->toHaveCount(13);
    }
});

test('start deals all fifty-two distinct cards', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 7);

    $all = [];
    foreach ($state->hands as $hand) {
        foreach ($hand as $card) {
            $all[] = (string) $card;
        }
    }

    expect($all)->toHaveCount(52);
    expect(array_unique($all))->toHaveCount(52);
});

test('the same seed produces the same deal', function (): void {
    $players = ['north', 'south', 'east', 'west'];
    $a = Engine::start(Spades::definition(), $players, seed: 1234);
    $b = Engine::start(Spades::definition(), $players, seed: 1234);

    expect(handsAsStrings($a))->toBe(handsAsStrings($b));
});

test('different seeds produce different deals', function (): void {
    $players = ['north', 'south', 'east', 'west'];
    $a = Engine::start(Spades::definition(), $players, seed: 1);
    $b = Engine::start(Spades::definition(), $players, seed: 2);

    expect(handsAsStrings($a))->not->toBe(handsAsStrings($b));
});

test('the initial state is ready for bidding', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 42);

    expect($state->phase)->toBe('bidding');
    expect($state->turn)->toBe(0);
    expect($state->currentPlayer())->toBe('north');
    expect($state->scores)->toBe(['north' => 0, 'south' => 0, 'east' => 0, 'west' => 0]);
    $round = $state->round;
    assert($round instanceof TrickTakingRound);
    expect($round->bids)->toBe([]);
    expect($round->tricksWon)->toBe([]);
    expect($round->trick)->toBe([]);
    expect($round->tricksPlayed)->toBe(0);
});

test('start rejects a player count the game does not allow', function (): void {
    expect(static fn () => Engine::start(Spades::definition(), ['a', 'b', 'c'], seed: 1))
        ->toThrow(\InvalidArgumentException::class);
});

test('start rejects duplicate player ids', function (): void {
    expect(static fn () => Engine::start(Spades::definition(), ['a', 'a', 'b', 'c'], seed: 1))
        ->toThrow(\InvalidArgumentException::class);
});
