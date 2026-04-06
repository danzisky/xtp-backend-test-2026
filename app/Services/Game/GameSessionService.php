<?php

namespace App\Services\Game;

use App\Data\Game\GameContextData;
use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GameSessionService
{
    public function __construct(
        private PrizeAvailabilityService $prizeAvailability,
        private GameBoardPlannerService $boardPlanner
    ) {
    }

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
                if ($game->prize_id !== null) {
                    $prize = Prize::query()->find($game->prize_id);

                    if ($prize && $won) {
                        $this->prizeAvailability->markAwarded($prize, $game->campaign);
                        $game->status = GameStatus::WON->value;
                    } elseif ($prize && ! $won) {
                        $this->prizeAvailability
                            ->releaseReservation($prize, $game->campaign);
                        $game->status = GameStatus::LOST->value;
                    }
                } else {
                    $game->status = $won
                        ? GameStatus::WON->value
                        : GameStatus::LOST->value;
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

        if ($resolvedMessage === null && $game->finished_at !== null && $game->prize_id !== null) {
            $resolvedMessage = 'You won a prize!';
        }

        return [
            'apiPath' => '/api/flip',
            'gameId' => $game->id,
            'revealedTiles' => $revealedTiles,
            'reveledTiles' => $revealedTiles,
            'message' => $resolvedMessage,
        ];
    }
}
