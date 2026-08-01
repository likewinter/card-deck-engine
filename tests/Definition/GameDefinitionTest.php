<?php

declare(strict_types=1);

use Likewinter\CardDeck\Card\Suit;
use Likewinter\CardDeckEngine\Definition\Ends\TargetScoreEnd;
use Likewinter\CardDeckEngine\Definition\FamilyKind;
use Likewinter\CardDeckEngine\Definition\Phase;
use Likewinter\CardDeckEngine\Definition\PlayerCount;
use Likewinter\CardDeckEngine\Definition\Resolvers\TrickWinnerResolver;
use Likewinter\CardDeckEngine\Definition\Scoring\CumulativeScoring;
use Tests\Fixtures\Spades;

test('a spades definition assembles the full IR spine', function (): void {
    $game = Spades::definition();

    expect($game->meta->name)->toBe('spades');
    expect($game->meta->family)->toBe(FamilyKind::TrickTaking);
    expect($game->players->isFixed())->toBeTrue();
    expect($game->players->min)->toBe(4);
    expect($game->phases)->toHaveCount(3);
    expect($game->resolver)->toBeInstanceOf(TrickWinnerResolver::class);
    expect($game->scoring)->toBeInstanceOf(CumulativeScoring::class);
    expect($game->end)->toBeInstanceOf(TargetScoreEnd::class);
    expect($game->triggers)->toBe([]);
});

test('phases form a bidding -> play -> score loop', function (): void {
    $game = Spades::definition();

    $kinds = array_map(static fn (Phase $phase): string => $phase->kind(), $game->phases);
    $thens = array_map(static fn (Phase $phase): ?string => $phase->then(), $game->phases);

    expect($kinds)->toBe(['bid', 'trick-play', 'tally']);
    expect($thens)->toBe(['play', 'score', 'bidding']);
});

test('the trick-winner resolver is parameterized by the trump suit', function (): void {
    $resolver = new TrickWinnerResolver(Suit::Spades);

    expect($resolver->kind())->toBe('trick-winner');
    expect($resolver->trump)->toBe(Suit::Spades);
});

test('cumulative scoring carries the spades formula', function (): void {
    $scoring = Spades::definition()->scoring;

    expect($scoring)->toBeInstanceOf(CumulativeScoring::class);

    if (!$scoring instanceof CumulativeScoring) {
        return;
    }

    expect($scoring->perTrick)->toBe(10);
    expect($scoring->overtrick)->toBe(1);
    expect($scoring->nilSuccess)->toBe(100);
    expect($scoring->nilFailure)->toBe(-100);
});

test('the end condition targets 500 points', function (): void {
    $end = Spades::definition()->end;

    expect($end)->toBeInstanceOf(TargetScoreEnd::class);

    if (!$end instanceof TargetScoreEnd) {
        return;
    }

    expect($end->target)->toBe(500);
    expect($end->kind())->toBe('target-score');
});

test('player count rejects an empty table', function (): void {
    expect(static fn () => new PlayerCount(0, 0))
        ->toThrow(\InvalidArgumentException::class);
});

test('a game needs at least one phase', function (): void {
    expect(static fn () => Spades::withoutPhases())
        ->toThrow(\InvalidArgumentException::class);
});
