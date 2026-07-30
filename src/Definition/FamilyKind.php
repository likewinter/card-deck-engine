<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\Definition;

/**
 * The family (dialect) a game definition belongs to.
 *
 * A family bundles a coherent set of phase types, a resolver, a scoring
 * model, end semantics, and a rule vocabulary. The engine maps a family to
 * its handler; a concrete game parameterizes the family, and a variant
 * extends a concrete game.
 */
enum FamilyKind: string
{
    case TrickTaking = 'trick-taking';
    case Poker = 'poker';
    case PushYourLuck = 'push-your-luck';
    case Solitaire = 'solitaire';
}
