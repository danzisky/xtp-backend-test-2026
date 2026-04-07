<?php

namespace Database\Seeders;

use App\Data\Game\GameContextData;
use App\Models\Game;
use App\Models\GameTile;
use App\Models\Prize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class GameSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Game::truncate();
        Schema::enableForeignKeyConstraints();

        $prizeImages = Prize::query()->pluck('image', 'id');
        Game::factory()->count(10000)->create()->each(function (Game $game) use ($prizeImages) {
            $gameBoardPlannerService = app()->make(\App\Services\Game\GameBoardPlannerService::class);
            $boardPlan = $gameBoardPlannerService->plan(new GameContextData(
                campaign: $game->campaign,
                account: $game->account,
                segment: $game->segment,
            ));


            $rows = [];
            foreach ($boardPlan->tiles as $index => $prizeId) {
                $rows[] = [
                    'prize_id' => $prizeId,
                    'tile_index' => $index,
                    'tile_image' => (string) $prizeImages->get($prizeId, ''),
                ];
            }

            $game->tiles()->createMany($rows);
        });
    }
}
