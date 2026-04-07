<?php

namespace App\Http\Controllers\Api;

use App\Enums\GameMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FlipRequest;
use App\Models\Game;
use App\Models\GameTile;
use App\Services\Game\GameSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function __construct(
        private GameSessionService $gameSessionService
    ) {}

    public function flip(FlipRequest $request): JsonResponse
    {
        $gameId = $request->gameId();
        $tileIndex = $request->tileIndex();

        Log::info('Flip request received', ['gameId' => $gameId, 'tileIndex' => $tileIndex]);

        try {
            $result = DB::transaction(function () use ($gameId, $tileIndex): array {
                $game = Game::query()
                    ->with('campaign')
                    ->lockForUpdate()
                    ->find($gameId);

                if (! $game) {
                    Log::warning('Game not found', ['gameId' => $gameId]);
                    return $this->response(Response::HTTP_NOT_FOUND, ['message' => GameMessage::GAME_NOT_FOUND->value]);
                }

                if ($game->is_finished) {
                    Log::info('Game already finished', ['gameId' => $gameId]);
                    return $this->response(Response::HTTP_OK, ['message' => GameMessage::GAME_ALREADY_FINISHED->value]);
                }

                if (! $game->is_valid) {
                    $message = $game->campaign?->is_active
                        ? GameMessage::GAME_INVALID->value
                        : GameMessage::GAME_CAMPAIGN_INACTIVE->value;

                    Log::warning('Invalid game detected', ['gameId' => $gameId, 'campaignActive' => $game->campaign?->is_active]);
                    return $this->response(Response::HTTP_UNPROCESSABLE_ENTITY, ['message' => $message]);
                }

                $tile = $this->resolveTile($game, $tileIndex);

                if (! $tile) {
                    Log::error('Failed to resolve tile', ['gameId' => $gameId, 'tileIndex' => $tileIndex]);
                    return $this->response(
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                        ['message' => GameMessage::TILE_PROCESSING_ERROR->value]
                    );
                }

                $this->revealTileIfNeeded($tile, $tileIndex);

                $matchCount = $game->tiles()
                    ->where('prize_id', $tile->prize_id)
                    ->revealed()
                    ->count();

                $body = ['tileImage' => $tile->tile_image];

                if (
                    $game->prize_id !== null
                    && (int) $tile->prize_id === (int) $game->prize_id
                    && $matchCount >= $game->matches_to_win
                ) {
                    $this->gameSessionService->finalizeGame($game, true);
                    Log::info('Player won game', ['gameId' => $gameId, 'prizeId' => $game->prize_id, 'account' => $game->account]);
                    $body['message'] = GameMessage::PRIZE_WON->value;

                    return $this->response(Response::HTTP_OK, $body);
                }

                if (! $game->can_scratch_tiles) {
                    $this->gameSessionService->finalizeGame($game, false);
                    Log::info('Player lost game', ['gameId' => $gameId, 'account' => $game->account]);
                    $body['message'] = GameMessage::NO_MORE_TRIES->value;
                }

                return $this->response(Response::HTTP_OK, $body);
            });

            return response()->json($result['body'], $result['status']);
        } catch (\Throwable $e) {
            Log::error('Flip request failed with exception', [
                'gameId' => $gameId,
                'tileIndex' => $tileIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(
                ['message' => GameMessage::API_GENERIC_ERROR->value],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function response(int $status, array $body): array
    {
        return [
            'status' => $status,
            'body' => $body,
        ];
    }

    private function resolveTile(Game $game, int $tileIndex): ?GameTile
    {
        return $game->tiles()->where('display_index', $tileIndex)->first()
            ?? $game->tiles()->unrevealed()->inRandomOrder()->first();
    }

    private function revealTileIfNeeded(GameTile $tile, int $tileIndex): void
    {
        if ($tile->revealed_at !== null) {
            return;
        }

        $tile->revealed_at = now();
        $tile->display_index = $tileIndex;
        $tile->save();
    }
}
