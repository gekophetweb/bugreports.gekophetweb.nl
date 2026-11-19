<?php

namespace App\Enums;

enum FeedbackStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Planned = 'planned';
    case Done = 'done';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In progress',
            self::Planned => 'Planned',
            self::Done => 'Done',
            self::Declined => 'Declined',
        };
    }
}
