<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FlipRequest;
use App\Models\Game;
use App\Models\GameTile;
use App\Services\Game\GameSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        $result = DB::transaction(function () use ($gameId, $tileIndex): array {
            $game = Game::query()
                ->with('campaign')
                ->lockForUpdate()
                ->find($gameId);

            if (! $game) {
                return $this->response(Response::HTTP_NOT_FOUND, ['message' => 'Game not found.']);
            }

            if ($game->is_finished) {
                return $this->response(Response::HTTP_OK, ['message' => 'Game has already finished.']);
            }

            if (! $game->is_valid) {
                $message = $game->campaign?->is_active
                    ? 'Game is not valid. Please contact support.'
                    : 'Game is not valid. Campaign is not active.';

                return $this->response(Response::HTTP_UNPROCESSABLE_ENTITY, ['message' => $message]);
            }

            $tile = $this->resolveTile($game, $tileIndex);

            if (! $tile) {
                return $this->response(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['message' => 'An error occurred while processing the tile. Please contact support.']
                );
            }

            $this->revealTileIfNeeded($tile, $tileIndex);

            $matchCount = $game->tiles()
                ->where('prize_id', $tile->prize_id)
                ->revealed()
                ->count();

            $body = ['tileImage' => $tile->tile_image];

            if ($matchCount >= $game->matches_to_win) {
                $this->gameSessionService->finalizeGame($game, true);
                $body['message'] = 'You won a prize!';

                return $this->response(Response::HTTP_OK, $body);
            }

            if (! $game->can_scratch_tiles) {
                $this->gameSessionService->finalizeGame($game, false);
                $body['message'] = 'No more tries left. Game has ended.';
            }

            return $this->response(Response::HTTP_OK, $body);
        });

        return response()->json($result['body'], $result['status']);
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
