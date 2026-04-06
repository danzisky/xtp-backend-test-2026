<?php

namespace App\Http\Controllers;

use App\Data\Game\GameContextData;
use App\Http\Requests\Frontend\LoadCampaignRequest;
use App\Models\Campaign;
use App\Services\Game\GameSessionService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class FrontendController extends Controller
{
    public function __construct(private GameSessionService $gameSessionService)
    {
    }

    public function loadCampaign(LoadCampaignRequest $request, Campaign $campaign): View
    {
        $account = $request->account();
        $segment = $request->prizeSegment();

        Log::info('Campaign load requested', [
            'campaign_id' => $campaign->id,
            'account' => $account,
            'segment' => $segment,
        ]);

        if ($campaign->is_upcoming) {
            Log::info('Campaign not started yet', [
                'campaign_id' => $campaign->id,
            ]);

            return $this->renderWithConfig([
                'apiPath' => '/api/flip',
                'gameId' => null,
                'message' => 'Campaign has not started yet.',
            ]);
        }

        if ($campaign->is_ended) {
            Log::info('Campaign ended', [
                'campaign_id' => $campaign->id,
            ]);

            return $this->renderWithConfig([
                'apiPath' => '/api/flip',
                'gameId' => null,
                'message' => 'Campaign has ended.',
            ]);
        }

        $context = new GameContextData(
            campaign: $campaign,
            account: $account,
            segment: $segment,
        );

        try {
            $game = $this->gameSessionService->getOrCreateOpenGame($context);

            Log::info('Game created or retrieved for frontend', [
                'game_id' => $game->id,
                'campaign_id' => $campaign->id,
            ]);

            $config = $this->gameSessionService->buildFrontendConfig($game);

            return $this->renderWithConfig($config);
        } catch (\Throwable $exception) {
            Log::error('Failed to load campaign frontend state', [
                'campaign_id' => $campaign->id,
                'account' => $account,
                'segment' => $segment,
                'error' => $exception->getMessage(),
            ]);

            return $this->renderWithConfig([
                'apiPath' => '/api/flip',
                'gameId' => null,
                'message' => 'An error occurred. Please try again.',
            ]);
        }
    }

    public function placeholder(): View
    {
        return view('frontend.placeholder');
    }

    private function renderWithConfig(array $config): View
    {
        return view('frontend.index', [
            'config' => json_encode($config, JSON_UNESCAPED_SLASHES),
        ]);
    }
}
