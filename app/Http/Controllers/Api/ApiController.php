<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\FlipRequest;
use App\Models\Game;
use App\Services\Game\GameBoardPlannerService;
use App\Services\Game\GameSessionService;
use App\Services\Game\PrizeAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiController extends Controller
{
    public function __construct(
        private GameSessionService $gameSessionService,
        private GameBoardPlannerService $gameBoardPlannerService
    ) {}

    public function flip(FlipRequest $request): JsonResponse
    {
        $gameId = $request->gameId();
        $tileIndex = $request->tileIndex();

        $result = DB::transaction(function () use ($gameId, $tileIndex): array {
            $game = Game::query()
                ->with('tiles')
                ->lockForUpdate()
                ->find($gameId);

            if (! $game) {
                return [
                    'status' => Response::HTTP_NOT_FOUND,
                    'body' => ['message' => 'Game not found.'],
                ];
            }

            if ($game->is_finished) {
                return [
                    'status' => Response::HTTP_OK,
                    'body' => ['message' => 'Game has already finished.'],
                ];
            }

            if (!$game->is_valid) {
                return [
                    'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    'body' => ['message' => 'Game is not valid. '. ($game->campaign?->is_active ? 'Please contact support.' : 'Campaign is not active.')],
                ];
            }

            $tile = $game->tiles->firstWhere('tile_index', $tileIndex);
            if (! $tile) {
                return [
                    'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    'body' => ['message' => 'An error occurred while processing the tile. Please contact support.'],
                ];
            }

            if ($game->finished_at !== null) {
                return [
                    'status' => Response::HTTP_OK,
                    'body' => [
                        'tileImage' => $tile->tile_image,
                        'message' => $game->prize_id ? 'You won a prize!' : 'Game has ended.',
                    ],
                ];
            }

            if ($tile->revealed_at === null) {
                $tile->revealed_at = now();
                $tile->save();
            }

            $matchCount = $game->tiles()
                ->where('prize_id', $tile->prize_id)
                ->whereNotNull('revealed_at')
                ->count();

            $revealedCount = $game->tiles()
                ->whereNotNull('revealed_at')
                ->count();

            $response = [
                'tileImage' => $tile->tile_image,
            ];

            if ($matchCount >= 3) {
                $this->gameSessionService->finalizeGame($game);

                $response['message'] = 'You won a prize!';
            } else if ($revealedCount === $game->tiles->count()) {
                $this->gameSessionService->finalizeGame($game);

                $response['message'] = 'Game has ended.';
            }

            return [
                'status' => Response::HTTP_OK,
                'body' => $response,
            ];
        });

        return response()->json($result['body'], $result['status']);
    }
}
