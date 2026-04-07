<?php

namespace App\Enums;

/**
 * Segments for prize distribution.
 */
enum PrizeSegment: string
{
    case Low = 'low';
    case Med = 'med';
    case High = 'high';
}
