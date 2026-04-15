<?php

namespace App\Enums;

enum RideStatus: string
{
    case REQUESTED   = 'requested';
    case ACCEPTED    = 'accepted';
    case ARRIVING    = 'arriving';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED   = 'completed';
    case CANCELLED   = 'cancelled';

    /** Statuts considérés comme "terminaux". */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }

    /** Statuts où le passager attend encore un chauffeur. */
    public function isOpenForMatching(): bool
    {
        return $this === self::REQUESTED;
    }

    /** Transitions autorisées depuis ce statut. */
    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::REQUESTED   => in_array($next, [self::ACCEPTED, self::CANCELLED], true),
            self::ACCEPTED    => in_array($next, [self::ARRIVING, self::CANCELLED], true),
            self::ARRIVING    => in_array($next, [self::IN_PROGRESS, self::CANCELLED], true),
            self::IN_PROGRESS => $next === self::COMPLETED,
            default           => false,
        };
    }
}
