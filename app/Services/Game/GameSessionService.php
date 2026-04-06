<?php

namespace App\Services\Game;

use App\Data\Game\GameContextData;
use App\Models\Game;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;

final class GameSessionService
{
    public function __construct(
        private PrizeAvailabilityService $prizeAvailability,
        private GameBoardPlannerService $boardPlanner
    ) {}

    public function getOrCreateOpenGame(GameContextData $context): Game {
        $game = $this->findOpenGame($context);

        if ($game) {
            return $game;
        }

        return $this->createGame($context);
    }

    public function findOpenGame(GameContextData $context): ?Game {
        return Game::query()
            ->where('campaign_id', $context->campaign->id)
            ->where('account', $context->account)
            ->where('segment', $context->segment)
            ->whereNull('finished_at')
            ->with('tiles')
            ->first();
    }

    public function createGame(GameContextData $context): Game {
        return DB::transaction(function () use ($context): Game {
            $boardPlan = $this->boardPlanner->plan($context);

            $winningPrize = null;
            if ($boardPlan->isWinner && $boardPlan->winningPrizeId !== null) {
                $winningPrize = Prize::query()->findOrFail($boardPlan->winningPrizeId);
                $this->prizeAvailability->reservePrize($winningPrize, $context->campaign);
            }
    
            $game = Game::query()->create([
                'campaign_id' => $context->campaign->id,
                'account' => $context->account,
                'segment' => $context->segment,
                'prize_id' => $winningPrize?->id,
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

            return $game->load('tiles');
        });
    }

    public function finalizeGame(Game $game): void {
        if ($game->finished_at !== null) {
            return;
        }

        $game->finished_at = now();
        $game->save();

        if ($game->prize_id !== null) {
            $prize = Prize::query()->find($game->prize_id);
            if ($prize) {
                $this->prizeAvailability->markAwarded($prize, $game->campaign);
            }
        }
    }

    public function buildFrontendConfig(Game $game, ?string $message = null): array {
        $revealedTiles = $game->tiles
            ->whereNotNull('revealed_at')
            ->map(fn ($tile) => [
                'index' => $tile->tile_index,
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
            // Keeping backward compatibility with typo in key. for now
            'reveledTiles' => $revealedTiles,
            'message' => $resolvedMessage,
        ];
    }
}
