<?php

namespace Tests\Feature\Frontend;

use App\Enums\PrizeSegment;
use App\Models\DailyPrizeCounter;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Support\BuildsGameTestData;
use Tests\TestCase;

class FrontendGameFlowTest extends TestCase
{
    use BuildsGameTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerSqliteMathFunctions();
    }

    public function test_frontend_rejects_invalid_segment(): void
    {
        $campaign = $this->createCampaign();

        $response = $this->getJson('/'.$campaign->slug.'?a=player-1&segment=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['segment']);
    }

    public function test_frontend_returns_upcoming_campaign_message(): void
    {
        $campaign = $this->createCampaign(
            [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            ]
        );

        $response = $this->get('/'.$campaign->slug.'?a=player-1&segment=low');

        $response->assertOk();

        $config = $this->extractConfig($response);

        $this->assertNull($config['gameId']);
        $this->assertSame('Campaign has not started yet.', $config['message']);
    }

    public function test_frontend_returns_ended_campaign_message(): void
    {
        $campaign = $this->createCampaign(
            [
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
            ]
        );

        $response = $this->get('/'.$campaign->slug.'?a=player-1&segment=low');

        $response->assertOk();

        $config = $this->extractConfig($response);

        $this->assertNull($config['gameId']);
        $this->assertSame('Campaign has ended.', $config['message']);
    }

    public function test_frontend_creates_game_and_embeds_runtime_config(): void
    {
        $campaign = $this->createCampaign();

        foreach (range(1, 13) as $index) {
            $this->createPrize(
                $campaign, PrizeSegment::Low->value, [
                'name' => 'Low Prize '.$index,
                'image' => 'https://example.test/prize-'.$index.'.png',
                'weight' => 10,
                'daily_limit' => 25,
                ]
            );
        }

        $response = $this->get('/'.$campaign->slug.'?a=player-1&segment=low');

        $response->assertOk();

        $config = $this->extractConfig($response);
        $game = Game::query()->findOrFail($config['gameId']);

        $this->assertSame('/api/flip', $config['apiPath']);
        $this->assertSame([], $config['revealedTiles']);
        $this->assertSame($config['revealedTiles'], $config['reveledTiles']);
        $this->assertNull($config['message']);
        $this->assertSame('player-1', $game->account);
        $this->assertSame(PrizeSegment::Low->value, $game->segment);
        $this->assertCount(25, $game->tiles);
    }

    public function test_frontend_resumes_existing_game_with_revealed_tiles(): void
    {
        $campaign = $this->createCampaign();
        $prize = $this->createPrize($campaign, PrizeSegment::Low->value);
        $game = $this->createGame(
            $campaign, [
            'account' => 'player-1',
            'segment' => PrizeSegment::Low->value,
            ]
        );

        $this->createTile(
            $game, $prize, 0, [
            'display_index' => 8,
            'tile_image' => 'https://example.test/already-revealed.png',
            'revealed_at' => now(),
            ]
        );

        $response = $this->get('/'.$campaign->slug.'?a=player-1&segment=low');

        $response->assertOk();

        $config = $this->extractConfig($response);

        $this->assertSame($game->id, $config['gameId']);
        $this->assertCount(1, $config['revealedTiles']);
        $this->assertSame(8, $config['revealedTiles'][0]['index']);
        $this->assertSame('https://example.test/already-revealed.png', $config['revealedTiles'][0]['image']);
        $this->assertSame($config['revealedTiles'], $config['reveledTiles']);
    }

    public function test_frontend_creates_losing_game_when_daily_caps_are_exhausted(): void
    {
        $campaign = $this->createCampaign();

        $prizes = collect();
        foreach (range(1, 13) as $index) {
            $prizes->push(
                $this->createPrize(
                    $campaign, PrizeSegment::Low->value, [
                    'name' => 'Capped Prize '.$index,
                    'daily_limit' => 1,
                    'weight' => 10,
                    ]
                )
            );
        }

        $counterDate = now($campaign->timezone)->toDateString();
        foreach ($prizes as $prize) {
            DailyPrizeCounter::query()->create(
                [
                'prize_id' => $prize->id,
                'counter_date' => $counterDate,
                'daily_limit' => 1,
                'reserved_count' => 1,
                'awarded_count' => 0,
                ]
            );
        }

        $response = $this->get('/'.$campaign->slug.'?a=player-1&segment=low');
        $response->assertOk();

        $config = $this->extractConfig($response);
        $game = Game::query()->findOrFail($config['gameId']);

        $this->assertNull($game->prize_id);
        $this->assertNull($game->finished_at);
        $this->assertCount(25, $game->tiles);
    }

    private function extractConfig(TestResponse $response): array
    {
        preg_match('/var config = (.*?);/s', $response->getContent(), $matches);

        $this->assertArrayHasKey(1, $matches, 'Unable to locate frontend config payload.');

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }
}
