<?php

namespace App\Http\Controllers;

use App\Data\Game\GameContextData;
use App\Http\Requests\Frontend\LoadCampaignRequest;
use App\Models\Campaign;
use App\Services\Game\GameSessionService;
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

        if ($campaign->is_upcoming) {
            return $this->renderWithConfig([
                'apiPath' => '/api/flip',
                'gameId' => null,
                'message' => 'Campaign has not started yet.',
            ]);
        }

        if ($campaign->is_ended) {
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

        $game = $this->gameSessionService->getOrCreateOpenGame($context);
        $config = $this->gameSessionService->buildFrontendConfig($game);

        return $this->renderWithConfig($config);
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
