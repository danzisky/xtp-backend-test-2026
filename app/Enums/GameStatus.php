<?php

namespace App\Enums;

/**
 * Statuses for games.
 */
enum GameStatus: string
{
    case ONGOING = 'ongoing';
    case WON = 'won';
    case LOST = 'lost';
    case ABANDONED = 'abandoned';
    case EXPIRED = 'expired';
    case ERROR = 'error';
}
