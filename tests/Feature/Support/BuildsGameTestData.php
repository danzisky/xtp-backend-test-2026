<?php

namespace Tests\Feature\Support;

use App\Enums\GameStatus;
use App\Enums\PrizeSegment;
use App\Models\Campaign;
use App\Models\Game;
use App\Models\GameTile;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;

trait BuildsGameTestData
{
    protected function registerSqliteMathFunctions(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();

        $pdo->sqliteCreateFunction('RAND', fn (): float => mt_rand() / mt_getrandmax(), 0);
        $pdo->sqliteCreateFunction('LOG', fn (float $value): float => log($value), 1);
    }

    protected function createCampaign(array $overrides = []): Campaign
    {
        return Campaign::query()->create(
            array_merge(
                [
                'timezone' => $this->timezone(),
                'name' => 'Campaign '.str()->random(8),
                'slug' => 'campaign-'.str()->random(8),
                'starts_at' => now($this->timezone())->subHour(),
                'ends_at' => now($this->timezone())->addDay(),
                'matches_to_win' => 3,
                'max_tries' => 10,
                ], $overrides
            )
        );
    }

    protected function createPrize(Campaign $campaign, string $segment, array $overrides = []): Prize
    {
        return Prize::query()->create(
            array_merge(
                [
                'campaign_id' => $campaign->id,
                'name' => 'Prize '.str()->random(8),
                'description' => 'Test prize',
                'segment' => $segment,
                'weight' => 10,
                'image' => 'https://example.test/'.str()->random(8).'.png',
                'starts_at' => now($campaign->timezone)->subHour(),
                'ends_at' => now($campaign->timezone)->addDay(),
                'daily_limit' => 10,
                ], $overrides
            )
        );
    }

    protected function createGame(Campaign $campaign, array $overrides = []): Game
    {
        return Game::query()->create(
            array_merge(
                [
                'campaign_id' => $campaign->id,
                'prize_id' => null,
                'status' => GameStatus::ONGOING->value,
                'account' => 'player-'.str()->random(5),
                'segment' => PrizeSegment::Low->value,
                'finished_at' => null,
                'matches_to_win' => 3,
                'max_tries' => 10,
                ], $overrides
            )
        );
    }

    protected function createTile(Game $game, Prize $prize, int $tileIndex, array $overrides = []): GameTile
    {
        return GameTile::query()->create(
            array_merge(
                [
                'game_id' => $game->id,
                'tile_index' => $tileIndex,
                'display_index' => null,
                'prize_id' => $prize->id,
                'tile_image' => $prize->image,
                'revealed_at' => null,
                ], $overrides
            )
        );
    }

    protected function timezone(): string
    {
        return 'America/New_York';
    }
}
