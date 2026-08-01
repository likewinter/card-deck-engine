<?php

declare(strict_types=1);

/*
 * Plays a complete game of Spades against itself and prints the result.
 *
 * Usage:
 *   php demo/spades.php          # seed 42
 *   php demo/spades.php 7        # seed 7 (a different, reproducible game)
 *
 * Move selection is a simple stand-in — bid a makeable three, otherwise play
 * a random legal card — so the game terminates. A real consumer chooses moves
 * from UI input or an AI.
 */

use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeck\DrawMode;
use Likewinter\CardDeckEngine\Definition\DealStep;
use Likewinter\CardDeckEngine\Definition\DeckSpec;
use Likewinter\CardDeckEngine\Definition\Ends\TargetScoreEnd;
use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Likewinter\CardDeckEngine\Definition\Meta;
use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TallyPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TrickPlayPhase;
use Likewinter\CardDeckEngine\Definition\PlayerCount;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\Definition\Scoring\CumulativeScoring;
use Likewinter\CardDeckEngine\Definition\StackSpec;
use Likewinter\CardDeckEngine\Definition\Visibility;
use Likewinter\CardDeckEngine\Engine;
use Likewinter\CardDeckEngine\Move\Bid;
use Random\Engine\Mt19937;
use Random\Randomizer;

require __DIR__ . '/../vendor/autoload.php';

$spades = new GameDefinition(
    meta: new Meta('spades', FamilyKind::TrickTaking),
    players: PlayerCount::fixed(4),
    deck: [DeckSpec::standard52()],
    layout: [new StackSpec('hand', Visibility::Private, count: 4)],
    deal: [new DealStep(to: 'each_player', mode: DrawMode::OneByOne)],
    phases: [
        new BidPhase('bidding', 'play', min: 0, max: 13),
        new TrickPlayPhase('play', 'score', tricks: 13),
        new TallyPhase('score', 'bidding'),
    ],
    end: new TargetScoreEnd(500),
    resolver: new TrickWinnerResolver(Suit::Spades),
    scoring: new CumulativeScoring(perTrick: 10, overtrick: 1, nilSuccess: 100, nilFailure: -100),
);

$players = ['north', 'south', 'east', 'west'];
$seed = (int) ($argv[1] ?? 42);

$formatScores = static function (array $scores) use ($players): string {
    $parts = [];
    foreach ($players as $player) {
        $parts[] = "{$player}={$scores[$player]}";
    }

    return implode(', ', $parts);
};

$state = Engine::start($spades, $players, seed: $seed);
$random = new Randomizer(new Mt19937($seed));

echo "Playing Spades to 500 (seed {$seed})...\n";

while (!Engine::isOver($state)) {
    $moves = Engine::legalMoves($state);

    // Bid a makeable three when bidding; otherwise play a random legal card.
    $move = null;
    foreach ($moves as $candidate) {
        if ($candidate instanceof Bid && $candidate->amount === 3) {
            $move = $candidate;
            break;
        }
    }
    $move ??= $moves[$random->getInt(0, count($moves) - 1)];

    $roundBefore = $state->roundNumber;
    $state = Engine::apply($state, $move);

    if ($state->roundNumber !== $roundBefore) {
        echo "  round {$roundBefore} done: {$formatScores($state->scores)}\n";
    }
}

echo "Winner: " . Engine::winner($state) . " — {$formatScores($state->scores)}\n";
