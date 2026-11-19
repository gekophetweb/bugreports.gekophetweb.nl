<?php

namespace App\Enums;

enum SubscriptionFrequency: string
{
    case Instant = 'instant';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Instant => 'Straight away',
            self::Daily => 'Daily digest (08:00)',
            self::Weekly => 'Weekly digest (Monday 08:00)',
        };
    }
}
