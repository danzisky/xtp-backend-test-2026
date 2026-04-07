<?php

namespace App\Services\Game;

use App\Data\Game\GameContextData;
use App\Enums\GameMessage;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for managing game sessions, including creation, retrieval, and finalization of games based on the provided context.
 */
final class GameSessionService
{
    public function __construct(
        private PrizeAvailabilityService $prizeAvailability,
        private GameBoardPlannerService $boardPlanner
    ) {
    }

    /**
     * Get an open game session for the given context or create a new one if none exists. This ensures that a player can only have one active game session per campaign and segment.
     *
     * @param GameContextData $context
     * @return Game
     * @throws \Throwable if game creation fails due to prize availability or board planning issues.
     */
    public function getOrCreateOpenGame(GameContextData $context): Game
    {
        $game = $this->findOpenGame($context);

        if ($game) {
            Log::info('Open game reused', [
                'game_id' => $game->id,
                'campaign_id' => $context->campaign->id,
                'account' => $context->account,
                'segment' => $context->segment,
            ]);

            return $game;
        }

        return $this->createGame($context);
    }

    /**
     * Find an open game session for the given context. An open game is defined as a game that matches the campaign, account, and segment, and has not been finished yet.
     *
     * @param GameContextData $context
     * @return Game|null
     */
    public function findOpenGame(GameContextData $context): ?Game
    {
        return Game::query()
            ->where('campaign_id', $context->campaign->id)
            ->where('account', $context->account)
            ->where('segment', $context->segment)
            ->whereNull('finished_at')
            ->with('tiles')
            ->first();
    }

    /**
     * Create a new game session based on the provided context. This includes planning the game board and reserving any prizes if necessary.
     *
     * @param GameContextData $context
     * @return Game
     * @throws \Throwable if game creation fails due to prize availability or board planning issues.
     */
    public function createGame(GameContextData $context): Game
    {
        try {
            return DB::transaction(function () use ($context): Game {
                $boardPlan = $this->boardPlanner->plan($context);

                $winningPrize = null;
                if ($boardPlan->isWinner && $boardPlan->winningPrizeId !== null) {
                    $winningPrize = Prize::query()
                        ->findOrFail($boardPlan->winningPrizeId);

                    $this->prizeAvailability
                        ->reservePrize($winningPrize, $context->campaign);
                }

                $game = Game::query()->create([
                    'campaign_id' => $context->campaign->id,
                    'account' => $context->account,
                    'segment' => $context->segment,
                    'prize_id' => $winningPrize?->id,
                    'matches_to_win' => $context->campaign->matches_to_win,
                    'max_tries' => $context->campaign->max_tries,
                ]);

                $prizeImages = Prize::query()
                    ->whereIn('id', $boardPlan->tiles)
                    ->pluck('image', 'id');

                $rows = [];
                foreach ($boardPlan->tiles as $index => $prizeId) {
                    $rows[] = [
                        'prize_id' => $prizeId,
                        'tile_index' => $index,
                        'tile_image' => (string) $prizeImages->get($prizeId, ''),
                    ];
                }

                $game->tiles()->createMany($rows);

                Log::info('Game created', [
                    'game_id' => $game->id,
                    'campaign_id' => $context->campaign->id,
                    'account' => $context->account,
                    'segment' => $context->segment,
                    'is_winner' => $boardPlan->isWinner,
                    'prize_id' => $winningPrize?->id,
                ]);

                return $game->load('tiles');
            });
        } catch (\Throwable $exception) {
            Log::error('Game creation failed', [
                'campaign_id' => $context->campaign->id,
                'account' => $context->account,
                'segment' => $context->segment,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Finalize the game by setting the finished_at timestamp and updating the status.
     * If the game is won and has a prize, mark the prize as awarded. If lost, release any reserved prize.
     *
     * @param Game $game
     * @param bool $won
     * @return void
     */
    public function finalizeGame(Game $game, bool $won = false): void
    {
        if ($game->finished_at !== null) {
            Log::info('Finalize skipped for already finished game', [
                'game_id' => $game->id,
            ]);

            return;
        }

        try {
            DB::transaction(function () use ($game, $won) {
                $shouldWin = $won && $game->prize_id !== null;

                if ($won && $game->prize_id === null) {
                    Log::warning('Game marked won without reserved prize; forcing loss status', [
                        'game_id' => $game->id,
                    ]);
                }

                if ($game->prize_id !== null) {
                    $prize = Prize::query()->find($game->prize_id);

                    if ($prize && $shouldWin) {
                        $this->prizeAvailability->markAwarded($prize, $game->campaign);
                        $game->status = GameStatus::WON->value;
                    } elseif ($prize) {
                        $this->prizeAvailability
                            ->releaseReservation($prize, $game->campaign);
                        $game->status = GameStatus::LOST->value;
                    } else {
                        $game->status = GameStatus::LOST->value;
                    }
                } else {
                    $game->status = GameStatus::LOST->value;
                }

                $game->finished_at = now();
                $game->save();
            });

            Log::info('Game finalized', [
                'game_id' => $game->id,
                'status' => $game->status,
                'won' => $won,
                'prize_id' => $game->prize_id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Game finalization failed', [
                'game_id' => $game->id,
                'won' => $won,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Build the frontend configuration for the given game, including revealed tiles and any messages.
     *
     * @param Game $game
     * @param string|null $message
     * @return array
     */
    public function buildFrontendConfig(Game $game, ?string $message = null): array
    {
        $revealedTiles = $game->tiles
            ->whereNotNull('revealed_at')
            ->map(fn ($tile) => [
                'index' => $tile?->display_index ?? $tile->tile_index,
                'image' => $tile->tile_image,
            ])
            ->values()
            ->all();

        $resolvedMessage = $message;

        if ($resolvedMessage === null) {
            if ($game->is_finished) {
                $resolvedMessage = match ($game->status) {
                    GameStatus::WON->value => GameMessage::PRIZE_WON->value,
                    GameStatus::LOST->value => GameMessage::FRONTEND_GAME_LOST->value,
                    default => null,
                };
            } else {
                $revealedCount = count($revealedTiles);
                $matchesToWin = $game->matches_to_win;
                $triesLeft = $game->max_tries - $revealedCount;
                $resolvedMessage = GameMessage::ongoingProgress($matchesToWin, $triesLeft);
            }
        }

        return [
            'apiPath' => '/api/flip',
            'gameId' => $game->id,
            'revealedTiles' => $revealedTiles,
            'message' => $resolvedMessage,
            'messageTimeout' => !$game->is_finished ? 3000 : null,
        ];
    }
}
