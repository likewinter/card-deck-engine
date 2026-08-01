<?php

declare(strict_types=1);

use Likewinter\CardDeck\Card;
use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\Bid;
use Likewinter\CardDeckEngine\Move\PlayCard;
use Likewinter\CardDeckEngine\State\GameState;
use Likewinter\CardDeckEngine\Family\TrickTaking\TrickTakingRound;
use Tests\Fixtures\Spades;

/**
 * Build a Spades state in the play phase with twelve tricks complete, ready
 * for the thirteenth (final) trick — which triggers the tally.
 *
 * @param array<string, int>        $bids
 * @param array<string, int>        $tricksWon
 * @param array<string, list<Card>> $hands
 * @param array<string, int>        $scores
 */
function stateBeforeFinalTrick(array $bids, array $tricksWon, array $hands, array $scores = []): GameState
{
    $players = ['north', 'south', 'east', 'west'];
    $state = Engine::start(Spades::definition(), $players, seed: 1);

    $round = TrickTakingRound::fresh();
    foreach ($bids as $player => $bid) {
        $round = $round->withBid($player, $bid);
    }
    foreach ($tricksWon as $player => $won) {
        $round = $round->withTricksWon($player, $won);
    }
    $round = $round->withTricksPlayed(12);

    return $state
        ->withHands($hands)
        ->withScores($scores === [] ? array_fill_keys($players, 0) : $scores)
        ->withPhase('play')
        ->withTurn(0)
        ->withRound($round);
}

/**
 * Play the final trick with north leading (one card per player).
 */
function playFinalTrick(GameState $state, string $north, string $south, string $east, string $west): GameState
{
    $state = Engine::apply($state, new PlayCard('north', Card::fromString($north)));
    $state = Engine::apply($state, new PlayCard('south', Card::fromString($south)));
    $state = Engine::apply($state, new PlayCard('east', Card::fromString($east)));

    return Engine::apply($state, new PlayCard('west', Card::fromString($west)));
}

test('making a bid scores ten per trick plus one per bag', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 3, 'south' => 2, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 3, 'south' => 2, 'east' => 1, 'west' => 6],
        hands: [
            'north' => [Card::fromString('A♠')],
            'south' => [Card::fromString('2♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
    );

    $state = playFinalTrick($state, 'A♠', '2♠', '3♠', '4♠');

    expect($state->scores)->toBe(['north' => 31, 'south' => 20, 'east' => 10, 'west' => 15]);
});

test('failing a bid loses ten per trick bid', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 5, 'south' => 1, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 2, 'south' => 4, 'east' => 3, 'west' => 3],
        hands: [
            'north' => [Card::fromString('2♠')],
            'south' => [Card::fromString('A♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
    );

    $state = playFinalTrick($state, '2♠', 'A♠', '3♠', '4♠');

    expect($state->score('north'))->toBe(-50);
});

test('a successful nil bid scores the nil bonus', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 0, 'south' => 1, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 0, 'south' => 4, 'east' => 4, 'west' => 4],
        hands: [
            'north' => [Card::fromString('2♠')],
            'south' => [Card::fromString('A♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
    );

    $state = playFinalTrick($state, '2♠', 'A♠', '3♠', '4♠');

    expect($state->score('north'))->toBe(100);
});

test('a failed nil bid loses the nil bonus', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 0, 'south' => 1, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 0, 'south' => 4, 'east' => 4, 'west' => 4],
        hands: [
            'north' => [Card::fromString('A♠')],
            'south' => [Card::fromString('2♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
    );

    $state = playFinalTrick($state, 'A♠', '2♠', '3♠', '4♠');

    expect($state->score('north'))->toBe(-100);
});

test('a round that does not reach the target deals a fresh round', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 3, 'south' => 2, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 3, 'south' => 2, 'east' => 1, 'west' => 6],
        hands: [
            'north' => [Card::fromString('A♠')],
            'south' => [Card::fromString('2♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
    );

    $state = playFinalTrick($state, 'A♠', '2♠', '3♠', '4♠');

    expect(Engine::isOver($state))->toBeFalse();
    expect($state->phase)->toBe('bidding');
    expect($state->roundNumber)->toBe(1);
    $round = $state->round;
    assert($round instanceof TrickTakingRound);
    expect($round->bids)->toBe([]);
    expect($round->tricksPlayed)->toBe(0);
    foreach ($state->hands as $hand) {
        expect($hand)->toHaveCount(13);
    }
});

test('reaching the target ends the game with the highest score winning', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 1, 'south' => 1, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 0, 'south' => 4, 'east' => 4, 'west' => 4],
        hands: [
            'north' => [Card::fromString('A♠')],
            'south' => [Card::fromString('2♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
        scores: ['north' => 490, 'south' => 0, 'east' => 0, 'west' => 0],
    );

    $state = playFinalTrick($state, 'A♠', '2♠', '3♠', '4♠');

    expect(Engine::isOver($state))->toBeTrue();
    expect(Engine::winner($state))->toBe('north');
    expect($state->score('north'))->toBe(500);
    expect(Engine::legalMoves($state))->toBe([]);
});

test('applying a move after the game is over is rejected', function (): void {
    $state = stateBeforeFinalTrick(
        bids: ['north' => 1, 'south' => 1, 'east' => 1, 'west' => 1],
        tricksWon: ['north' => 0, 'south' => 4, 'east' => 4, 'west' => 4],
        hands: [
            'north' => [Card::fromString('A♠')],
            'south' => [Card::fromString('2♠')],
            'east' => [Card::fromString('3♠')],
            'west' => [Card::fromString('4♠')],
        ],
        scores: ['north' => 490, 'south' => 0, 'east' => 0, 'west' => 0],
    );

    $state = playFinalTrick($state, 'A♠', '2♠', '3♠', '4♠');

    expect(Engine::isOver($state))->toBeTrue();
    expect(static fn () => Engine::apply($state, new Bid('north', 1)))
        ->toThrow(\InvalidArgumentException::class);
});
