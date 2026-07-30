<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeck\DrawMode;
use Likewinter\CardDeckEngine\Definition\Deal;
use Likewinter\CardDeckEngine\Definition\DealStep;
use Likewinter\CardDeckEngine\Definition\Deck;
use Likewinter\CardDeckEngine\Definition\Ends\TargetScoreEnd;
use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\GameDefinition;
use Likewinter\CardDeckEngine\Definition\Layout;
use Likewinter\CardDeckEngine\Definition\Meta;
use Likewinter\CardDeckEngine\Definition\Phases\BidPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TallyPhase;
use Likewinter\CardDeckEngine\Definition\Phases\TrickPlayPhase;
use Likewinter\CardDeckEngine\Definition\PlayerCount;
use Likewinter\CardDeckEngine\Definition\Players;
use Likewinter\CardDeckEngine\Definition\PlayOrder;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\Definition\Scoring\CumulativeScoring;
use Likewinter\CardDeckEngine\Definition\StackSpec;
use Likewinter\CardDeckEngine\Definition\Visibility;

/**
 * A canonical Spades game definition, hand-built from the IR (see
 * design/ir/spades.yaml). Used across the suite as the reference trick-taking
 * game.
 */
final class Spades
{
    public static function definition(): GameDefinition
    {
        return new GameDefinition(
            meta: new Meta('spades', FamilyKind::TrickTaking),
            players: new Players(PlayerCount::fixed(4), PlayOrder::Clockwise),
            deck: Deck::standard52(),
            layout: new Layout([
                new StackSpec('hand', Visibility::Private, count: 4),
            ]),
            deal: new Deal([
                new DealStep(
                    to: 'each_player',
                    cards: null,
                    mode: DrawMode::OneByOne,
                    visibility: Visibility::Up,
                ),
            ]),
            phases: [
                new BidPhase('bidding', 'play', min: 0, max: 13),
                new TrickPlayPhase('play', 'score', tricks: 13),
                new TallyPhase('score', 'bidding'),
            ],
            end: new TargetScoreEnd(500),
            resolver: new TrickWinnerResolver(Suit::Spades),
            scoring: new CumulativeScoring(
                perTrick: 10,
                overtrick: 1,
                nilSuccess: 100,
                nilFailure: -100,
            ),
        );
    }

    /**
     * An invalid definition (no phases) for exercising validation.
     */
    public static function withoutPhases(): GameDefinition
    {
        return new GameDefinition(
            meta: new Meta('broken', FamilyKind::TrickTaking),
            players: new Players(PlayerCount::fixed(4)),
            deck: Deck::standard52(),
            layout: new Layout([]),
            deal: new Deal([]),
            phases: [],
            end: new TargetScoreEnd(500),
        );
    }
}
