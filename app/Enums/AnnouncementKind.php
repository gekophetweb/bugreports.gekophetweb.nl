<?php

namespace App\Enums;

/** What a php.net post is about. Other posts (RCs, conferences) are not stored. */
enum AnnouncementKind: string
{
    case Upcoming = 'upcoming';           // "Upcoming Critical PHP Security Release": date and window, no details
    case Advisory = 'advisory';           // "Security advisory: …"
    case Informational = 'informational'; // "Informational advisory: …"
    case Release = 'release';             // "PHP 8.5.13 Released!" that is a security release
}
