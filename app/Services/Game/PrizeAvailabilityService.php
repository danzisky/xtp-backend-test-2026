<?php

namespace App\Services\Game;

use App\Models\Campaign;
use App\Models\DailyPrizeCounter;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PrizeAvailabilityService
{
    public function isPrizeAvailable(Prize $prize, Campaign $campaign): bool
    {
        if ($prize->daily_limit === null) {
            return true;
        }

        if ((int) $prize->daily_limit <= 0) {
            return false;
        }

        $counter = $prize->counterForDay(now($campaign->timezone));

        return $counter->reserved_count < (int) $counter->daily_limit;
    }

    public function reservePrize(Prize $prize, Campaign $campaign): void {
        if ($prize->daily_limit === null) {
            return;
        }

        DB::transaction(function () use ($prize, $campaign) {
            $counterDate = $this->campaignDate($campaign);

            $counter = DailyPrizeCounter::query()
                ->where('prize_id', $prize->id)
                ->where('counter_date', $counterDate)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = DailyPrizeCounter::query()->create([
                    'prize_id' => $prize->id,
                    'counter_date' => $counterDate,
                    'daily_limit' => (int) $prize->daily_limit,
                    'reserved_count' => 0,
                    'awarded_count' => 0,
                ]);
            }

            if ($counter->reserved_count >= (int) $counter->daily_limit) {
                throw new RuntimeException('Prize daily limit reached.');
            }

            $counter->increment('reserved_count');
        });
    }

    public function markAwarded(Prize $prize, Campaign $campaign): void {
        DB::transaction(function () use ($prize, $campaign) {
            $counter = $prize->counterForDay(now($campaign->timezone));
            if ($counter) {
                $counter->increment('awarded_count');
            }
        });
    }

    public function releaseReservation(Prize $prize, Campaign $campaign): void {
        DB::transaction(function () use ($prize, $campaign) {
            $counter = $prize->counterForDay(now($campaign->timezone));
            if ($counter && $counter->reserved_count > 0) {
                $counter->decrement('reserved_count');
            }
        });
    }

    public function campaignDate(Campaign $campaign): string {
        return now($campaign->timezone)->toDateString();
    }
}
