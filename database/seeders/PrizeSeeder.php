<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Prize;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PrizeSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        Prize::truncate();
        Schema::enableForeignKeyConstraints();

        $campaigns = Campaign::all();

        foreach ($campaigns as $campaign) {
            Prize::insert([
                ...array_map(fn($i) => [
                    'campaign_id' => $campaign->id,
                    'name' => "Low $i",
                    'segment' => 'low',
                    'weight' => $i % 3 ? '50.00' : '25.00',
                    'image' => "/assets/$i.png",
                    'daily_limit' => rand(3, 10),
                    'starts_at' => now()->subDays(10)->startOfDay(),
                    'ends_at' => now()->addDays(7)->endOfDay(),
                ], range(1, 7)),
                ...array_map(fn($i) => [
                    'campaign_id' => $campaign->id,
                    'name' => "Med $i",
                    'segment' => 'med',
                    'weight' => $i % 3 ? '50.00' : '25.00',
                    'image' => "/assets/$i.png",
                    'daily_limit' => rand(3, 10),
                    'starts_at' => now()->subDays(10)->startOfDay(),
                    'ends_at' => now()->addDays(7)->endOfDay(),
                ], range(1, 7)),
                ...array_map(fn($i) => [
                    'campaign_id' => $campaign->id,
                    'name' => "High $i",
                    'segment' => 'high',
                    'weight' => $i % 3 ? '50.00' : '25.00',
                    'image' => "/assets/$i.png",
                    'daily_limit' => rand(3, 10),
                    'starts_at' => now()->subDays(10)->startOfDay(),
                    'ends_at' => now()->addDays(7)->endOfDay(),
                ], range(1, 7)), 
            ]);
        }
    }
}
