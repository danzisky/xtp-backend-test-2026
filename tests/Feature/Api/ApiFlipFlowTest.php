<?php

namespace Tests\Feature\Api;

use App\Enums\GameMessage;
use App\Enums\GameStatus;
use App\Enums\PrizeSegment;
use App\Models\DailyPrizeCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Support\BuildsGameTestData;
use Tests\TestCase;

class ApiFlipFlowTest extends TestCase
{
    use BuildsGameTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerSqliteMathFunctions();
    }

    public function test_flip_is_idempotent_for_the_same_tile_index(): void
    {
        $campaign = $this->createCampaign();
        $prize = $this->createPrize(
            $campaign, PrizeSegment::Low->value, [
            'image' => 'https://example.test/idempotent.png',
            ]
        );
        $game = $this->createGame(
            $campaign, [
            'account' => 'player-1',
            'segment' => PrizeSegment::Low->value,
            ]
        );

        foreach (range(0, 2) as $index) {
            $this->createTile(
                $game, $prize, $index, [
                'tile_image' => 'https://example.test/idempotent.png',
                ]
            );
        }

        $first = $this->postJson(
            route('api.flip'), [
            'gameId' => $game->id,
            'tileIndex' => 7,
            ]
        );

        $second = $this->postJson(
            route('api.flip'), [
            'gameId' => $game->id,
            'tileIndex' => 7,
            ]
        );

        $first->assertOk();
        $second->assertOk();
        $second->assertJson(
            [
            'tileImage' => $first->json('tileImage'),
            ]
        );

        $this->assertSame(1, $game->fresh()->tiles()->revealed()->count());
        $this->assertDatabaseCount('game_tiles', 3);
        $this->assertSame(1, $game->fresh()->tiles()->where('display_index', 7)->count());
    }

    public function test_flip_marks_game_won_on_third_match_and_updates_counter(): void
    {
        $campaign = $this->createCampaign();
        $prize = $this->createPrize(
            $campaign, PrizeSegment::Low->value, [
            'daily_limit' => 5,
            'image' => 'https://example.test/winner.png',
            ]
        );
        $game = $this->createGame(
            $campaign, [
            'account' => 'player-1',
            'segment' => PrizeSegment::Low->value,
            'prize_id' => $prize->id,
            'matches_to_win' => 3,
            'max_tries' => 3,
            ]
        );

        foreach (range(0, 2) as $index) {
            $this->createTile(
                $game, $prize, $index, [
                'tile_image' => 'https://example.test/winner.png',
                ]
            );
        }

        $this->postJson(route('api.flip'), ['gameId' => $game->id, 'tileIndex' => 0])->assertOk();
        $this->postJson(route('api.flip'), ['gameId' => $game->id, 'tileIndex' => 1])->assertOk();

        // check status is still in progress after 2 matches
        $game->refresh();
        $this->assertSame(GameStatus::ONGOING->value, $game->status);

        $final = $this->postJson(route('api.flip'), ['gameId' => $game->id, 'tileIndex' => 2]);

        $final->assertOk()->assertJson(
            [
            'tileImage' => 'https://example.test/winner.png',
            'message' => GameMessage::PRIZE_WON->value,
            ]
        );

        $game->refresh();

        $this->assertNotNull($game->finished_at);
        $this->assertSame(GameStatus::WON->value, $game->status);
        $this->assertSame(1, DailyPrizeCounter::query()->where('prize_id', $prize->id)->value('awarded_count'));
    }

    public function test_flip_marks_game_lost_when_no_more_tries_remain(): void
    {
        $campaign = $this->createCampaign();
        $firstPrize = $this->createPrize(
            $campaign, PrizeSegment::Low->value, [
            'image' => 'https://example.test/first.png',
            ]
        );
        $secondPrize = $this->createPrize(
            $campaign, PrizeSegment::Low->value, [
            'image' => 'https://example.test/second.png',
            ]
        );
        $game = $this->createGame(
            $campaign, [
            'account' => 'player-1',
            'segment' => PrizeSegment::Low->value,
            'prize_id' => null,
            'matches_to_win' => 3,
            'max_tries' => 2,
            ]
        );

        $this->createTile($game, $firstPrize, 0, ['tile_image' => 'https://example.test/first.png']);
        $this->createTile($game, $secondPrize, 1, ['tile_image' => 'https://example.test/second.png']);

        $this->postJson(route('api.flip'), ['gameId' => $game->id, 'tileIndex' => 0])->assertOk();

        // check status is still in progress after first try
        $game->refresh();
        $this->assertSame(GameStatus::ONGOING->value, $game->status);

        $final = $this->postJson(route('api.flip'), ['gameId' => $game->id, 'tileIndex' => 1]);

        $final->assertOk()->assertJson(
            [
            'message' => GameMessage::NO_MORE_TRIES->value,
            ]
        );

        $game->refresh();

        $this->assertNotNull($game->finished_at);
        $this->assertSame(GameStatus::LOST->value, $game->status);
    }

    public function test_flip_rejects_invalid_payload(): void
    {
        $response = $this->postJson(
            route('api.flip'), [
            'gameId' => 999999,
            'tileIndex' => 25,
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gameId', 'tileIndex']);
    }

    public function test_flip_returns_message_when_game_is_already_finished(): void
    {
        $campaign = $this->createCampaign();
        $game = $this->createGame(
            $campaign, [
            'finished_at' => now(),
            'status' => GameStatus::WON->value,
            ]
        );

        $response = $this->postJson(
            route('api.flip'), [
            'gameId' => $game->id,
            'tileIndex' => 0,
            ]
        );

        $response->assertOk()->assertJson(
            [
            'message' => GameMessage::GAME_ALREADY_FINISHED->value,
            ]
        );
    }

    public function test_flip_rejects_game_when_campaign_is_not_active(): void
    {
        $campaign = $this->createCampaign(
            [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDay(),
            ]
        );
        $game = $this->createGame(
            $campaign, [
            'finished_at' => null,
            'status' => GameStatus::ONGOING->value,
            ]
        );

        $response = $this->postJson(
            route('api.flip'), [
            'gameId' => $game->id,
            'tileIndex' => 0,
            ]
        );

        $response->assertStatus(422)->assertJson(
            [
            'message' => GameMessage::GAME_CAMPAIGN_INACTIVE->value,
            ]
        );
    }
}
