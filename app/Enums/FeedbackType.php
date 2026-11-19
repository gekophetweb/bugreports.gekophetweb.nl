<?php

namespace App\Enums;

enum FeedbackType: string
{
    case Bug = 'bug';
    case Idea = 'idea';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Something is broken',
            self::Idea => 'An idea or request',
            self::Other => 'Something else',
        };
    }
}
