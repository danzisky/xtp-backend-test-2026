<?php

namespace App\Enums;

enum GameMessage: string
{
    case CAMPAIGN_NOT_STARTED = 'Campaign has not started yet.';
    case CAMPAIGN_ENDED = 'Campaign has ended.';
    case FRONTEND_GENERIC_ERROR = 'An error occurred. Please try again.';
    case GAME_NOT_FOUND = 'Game not found.';
    case GAME_ALREADY_FINISHED = 'Game has already finished.';
    case GAME_INVALID = 'Game is not valid. Please contact support.';
    case GAME_CAMPAIGN_INACTIVE = 'Game is not valid. Campaign is not active.';
    case TILE_PROCESSING_ERROR = 'An error occurred while processing the tile. Please contact support.';
    case API_GENERIC_ERROR = 'An error occurred. Please contact support.';
    case PRIZE_WON = 'You won a prize! 🏆';
    case NO_MORE_TRIES = 'No more tries left. You lost. 😢';
    case FRONTEND_GAME_LOST = 'Game over. Better luck next time!';
    case FRONTEND_GAME_IN_PROGRESS = 'You need %d matches to win. You have %d tries left. 🫡';

    public static function ongoingProgress(int $matchesToWin, int $triesLeft): string
    {
        return sprintf(self::FRONTEND_GAME_IN_PROGRESS->value, $matchesToWin, $triesLeft);
    }
}