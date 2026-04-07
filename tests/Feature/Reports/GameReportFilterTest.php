<?php

namespace Tests\Feature\Reports;

use App\Enums\PrizeSegment;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\BuildsGameTestData;
use Tests\TestCase;

class GameReportFilterTest extends TestCase
{
    use BuildsGameTestData;
    use RefreshDatabase;

    public function test_filter_respects_campaign_timezone_date_boundaries(): void
    {
        $campaign = $this->createCampaign([
            'timezone' => 'America/New_York',
        ]);

        session(['activeCampaign' => $campaign->id]);

        $included = $this->createGame($campaign, [
            'account' => 'included',
            'segment' => PrizeSegment::Low->value,
            'finished_at' => now('America/New_York')
                ->setDate(2026, 4, 6)
                ->setTime(12, 0)
                ->utc(),
        ]);

        $this->createGame($campaign, [
            'account' => 'before-boundary',
            'segment' => PrizeSegment::Low->value,
            // Local 2026-04-05 23:30 should be excluded from local 2026-04-06 range.
            'finished_at' => now('America/New_York')
                ->setDate(2026, 4, 5)
                ->setTime(23, 30)
                ->utc(),
        ]);

        $this->createGame($campaign, [
            'account' => 'after-boundary',
            'segment' => PrizeSegment::Low->value,
            // Local 2026-04-07 00:30 should be excluded from local 2026-04-06 range.
            'finished_at' => now('America/New_York')
                ->setDate(2026, 4, 7)
                ->setTime(0, 30)
                ->utc(),
        ]);

        $rows = Game::filter(
            account: null,
            prizeId: null,
            fromDate: '2026-04-06',
            tillDate: '2026-04-06'
        )->pluck('games.id');

        $this->assertSame([$included->id], $rows->all());
    }
}
