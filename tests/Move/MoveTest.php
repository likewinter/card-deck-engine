<?php

declare(strict_types=1);

use Likewinter\CardDeck\Card;
use Likewinter\CardDeck\Card\Rank;
use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\PlayCard;

test('a bid carries its player and amount', function (): void {
    $bid = new Bid('north', 3);

    expect($bid->player())->toBe('north');
    expect($bid->amount)->toBe(3);
});

test('a play-card carries its player and card', function (): void {
    $card = new Card(Suit::Spades, Rank::Ace);
    $move = new PlayCard('north', $card);

    expect($move->player())->toBe('north');
    expect($move->card->equals($card))->toBeTrue();
});

test('a bid rejects a negative amount', function (): void {
    expect(static fn () => new Bid('north', -1))
        ->toThrow(\InvalidArgumentException::class);
});
