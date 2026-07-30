<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * How a winner is determined when the game ends. Family-dependent: not every
 * game has a ranking (puzzle games yield solved/stuck instead).
 */
enum WinnerRule: string
{
    case HighestScore = 'highest_score';
    case LastStanding = 'last_standing';
    case MostChips = 'most_chips';
    case Solved = 'solved';
}
