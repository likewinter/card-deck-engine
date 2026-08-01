<?php

declare(strict_types=1);

namespace Likewinter\CardDeckEngine\State;

/**
 * Per-round transient state, scoped to a single round of play.
 *
 * A marker interface: each family provides its own implementation carrying
 * the round-scoped data it needs (trick-taking: bids, tricks won, the current
 * trick; poker: pot, community cards, betting state). The Engine creates a
 * fresh round via the family handler and passes it through GameState; the
 * family code narrows to its concrete type.
 */
interface RoundState {}
