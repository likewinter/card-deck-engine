<?php

declare(strict_types=1);

use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\Bid;
use Tests\Fixtures\Spades;

test('during bidding the current player may bid 0 through 13', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    $moves = Engine::legalMoves($state);

    expect($moves)->toHaveCount(14);

    $amounts = [];
    foreach ($moves as $move) {
        expect($move)->toBeInstanceOf(Bid::class);
        if (!$move instanceof Bid) {
            continue;
        }
        expect($move->player())->toBe('north');
        $amounts[] = $move->amount;
    }

    expect($amounts)->toBe(range(0, 13));
});

test('a bid advances the turn and records the bid', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    $state = Engine::apply($state, new Bid('north', 3));

    expect($state->turn)->toBe(1);
    expect($state->currentPlayer())->toBe('south');
    expect($state->phase)->toBe('bidding');
    expect($state->round->bids)->toBe(['north' => 3]);
});

test('once all four bid, play begins with the bids preserved', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    $state = Engine::apply($state, new Bid('north', 3));
    $state = Engine::apply($state, new Bid('south', 0));
    $state = Engine::apply($state, new Bid('east', 5));
    $state = Engine::apply($state, new Bid('west', 2));

    expect($state->phase)->toBe('play');
    expect($state->turn)->toBe(0);
    expect($state->round->bids)->toBe(['north' => 3, 'south' => 0, 'east' => 5, 'west' => 2]);
});

test('a bid outside the allowed range is rejected', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    expect(static fn () => Engine::apply($state, new Bid('north', 14)))
        ->toThrow(\InvalidArgumentException::class);
});

test('only the current player may bid', function (): void {
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    expect(static fn () => Engine::apply($state, new Bid('south', 3)))
        ->toThrow(\InvalidArgumentException::class);
});
