<?php

declare(strict_types=1);

use Likewinter\CardDeck\Card;
use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\PlayCard;
use Likewinter\CardDeckEngine\State\GameState;
use Likewinter\CardDeckEngine\State\RoundState;
use Tests\Fixtures\Spades;

/**
 * Build a Spades state in the play phase with the given hands
 * (player => cards). Hands are small and chosen to exercise specific trick
 * scenarios; the engine does not require a full 13-card hand to play a trick.
 *
 * @param array<string, list<Card>> $hands
 */
function playState(array $hands, int $turn = 0): GameState
{
    $state = Engine::start(Spades::definition(), ['north', 'south', 'east', 'west'], seed: 1);

    return $state
        ->withHands($hands)
        ->withPhase('play')
        ->withTurn($turn)
        ->withRound(RoundState::fresh());
}

test('the leader may play any card; a follower must follow suit when able', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♠'), Card::fromString('K♥')],
        'south' => [Card::fromString('2♠'), Card::fromString('3♥')],
        'east' => [Card::fromString('4♦')],
        'west' => [Card::fromString('5♣')],
    ]);

    expect(Engine::legalMoves($state))->toHaveCount(2);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♠')));

    $southMoves = Engine::legalMoves($state);
    expect($southMoves)->toHaveCount(1);
    foreach ($southMoves as $move) {
        expect($move)->toBeInstanceOf(PlayCard::class);
        if ($move instanceof PlayCard) {
            expect((string) $move->card)->toBe('2♠');
        }
    }
});

test('a player void in the lead suit may play any card', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♠')],
        'south' => [Card::fromString('2♠')],
        'east' => [Card::fromString('4♦'), Card::fromString('5♦')],
        'west' => [Card::fromString('6♣')],
    ]);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♠')));
    $state = Engine::apply($state, new PlayCard('south', Card::fromString('2♠')));

    expect(Engine::legalMoves($state))->toHaveCount(2);
});

test('the highest trump wins the trick and leads the next', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♥')],
        'south' => [Card::fromString('2♠')],
        'east' => [Card::fromString('3♥')],
        'west' => [Card::fromString('4♥')],
    ]);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♥')));
    $state = Engine::apply($state, new PlayCard('south', Card::fromString('2♠')));
    $state = Engine::apply($state, new PlayCard('east', Card::fromString('3♥')));
    $state = Engine::apply($state, new PlayCard('west', Card::fromString('4♥')));

    expect($state->round->tricksWon['south'] ?? 0)->toBe(1);
    expect($state->round->trick)->toBe([]);
    expect($state->round->trickLeader)->toBe('south');
    expect($state->round->tricksPlayed)->toBe(1);
    expect($state->turn)->toBe(1);
});

test('with no trump played, the highest lead-suit card wins', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♥')],
        'south' => [Card::fromString('K♥')],
        'east' => [Card::fromString('2♦')],
        'west' => [Card::fromString('3♥')],
    ]);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♥')));
    $state = Engine::apply($state, new PlayCard('south', Card::fromString('K♥')));
    $state = Engine::apply($state, new PlayCard('east', Card::fromString('2♦')));
    $state = Engine::apply($state, new PlayCard('west', Card::fromString('3♥')));

    expect($state->round->tricksWon['north'] ?? 0)->toBe(1);
    expect($state->round->trickLeader)->toBe('north');
    expect($state->turn)->toBe(0);
});

test('playing a card removes it from the hand', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♠'), Card::fromString('K♠')],
        'south' => [Card::fromString('2♥')],
        'east' => [Card::fromString('3♥')],
        'west' => [Card::fromString('4♥')],
    ]);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♠')));

    $northHand = array_map(static fn (Card $card): string => (string) $card, $state->hand('north'));
    expect($northHand)->toBe(['K♠']);
});

test('a player cannot play a card they do not hold', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♠')],
        'south' => [Card::fromString('2♥')],
        'east' => [Card::fromString('3♥')],
        'west' => [Card::fromString('4♥')],
    ]);

    expect(static fn () => Engine::apply($state, new PlayCard('north', Card::fromString('K♠'))))
        ->toThrow(\InvalidArgumentException::class);
});

test('a player who can follow suit may not discard off-suit', function (): void {
    $state = playState([
        'north' => [Card::fromString('A♠')],
        'south' => [Card::fromString('2♠'), Card::fromString('3♥')],
        'east' => [Card::fromString('4♦')],
        'west' => [Card::fromString('5♣')],
    ]);

    $state = Engine::apply($state, new PlayCard('north', Card::fromString('A♠')));

    expect(static fn () => Engine::apply($state, new PlayCard('south', Card::fromString('3♥'))))
        ->toThrow(\InvalidArgumentException::class);
});
