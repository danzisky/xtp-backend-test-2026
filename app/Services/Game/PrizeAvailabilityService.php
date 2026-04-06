<?php

namespace App\Services\Game;

use App\Models\Campaign;
use App\Models\DailyPrizeCounter;
use App\Models\Prize;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for managing prize availability, reservations, and awards based on daily limits.
 */
final class PrizeAvailabilityService
{
    /**
     * Check if a prize is available for awarding based on its daily limit and the current reserved count for the day.
     *
     * @param  Prize    $prize
     * @param  Campaign $campaign
     * @return bool
     */
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

    /**
     * Reserve a prize for a game session. This increments the reserved count for the prize's daily counter. If the daily limit has been reached, an exception is thrown.
     *
     * @param  Prize    $prize
     * @param  Campaign $campaign
     * @return void
     * @throws RuntimeException if the prize cannot be reserved due to daily limits.
     */
    public function reservePrize(Prize $prize, Campaign $campaign): void
    {
        if ($prize->daily_limit === null) {
            return;
        }

        DB::transaction(
            function () use ($prize, $campaign) {
                try {
                    $counterDate = $this->campaignDate($campaign);

                    $counter = DailyPrizeCounter::query()
                        ->where('prize_id', $prize->id)
                        ->whereDate('counter_date', $counterDate)
                        ->lockForUpdate()
                        ->first();

                    if (! $counter) {
                        $counter = DailyPrizeCounter::query()->create(
                            [
                                'prize_id' => $prize->id,
                                'counter_date' => $counterDate,
                                'daily_limit' => (int) $prize->daily_limit,
                                'reserved_count' => 0,
                                'awarded_count' => 0,
                            ]
                        );
                    }

                    if ($counter->reserved_count >= (int) $counter->daily_limit) {
                        Log::warning(
                            'Prize daily limit reached',
                            [
                                'prizeId' => $prize->id,
                                'dailyLimit' => $counter->daily_limit,
                                'counterDate' => $counterDate,
                            ]
                        );
                        throw new RuntimeException('Prize daily limit reached.');
                    }

                    $counter->increment('reserved_count');
                    Log::info(
                        'Prize reserved',
                        [
                            'prizeId' => $prize->id,
                            'counterDate' => $counterDate,
                        ]
                    );
                } catch (RuntimeException $e) {
                    Log::error(
                        'Prize reservation failed',
                        [
                            'prizeId' => $prize->id,
                            'error' => $e->getMessage(),
                        ]
                    );
                    throw $e;
                }
            }
        );
    }

    /**
     * Mark a prize as awarded by incrementing the awarded count for the prize's daily counter. This should be called when a game is finalized with a win.
     *
     * @param  Prize    $prize
     * @param  Campaign $campaign
     * @return void
     */
    public function markAwarded(Prize $prize, Campaign $campaign): void
    {
        DB::transaction(
            function () use ($prize, $campaign) {
                $counter = $prize->counterForDay(now($campaign->timezone));
                if ($counter) {
                    $counter->increment('awarded_count');
                    Log::info(
                        'Prize marked as awarded',
                        [
                            'prizeId' => $prize->id,
                            'awardedCount' => $counter->awarded_count,
                        ]
                    );
                }
            }
        );
    }

    /**
     * Release a reserved prize by decrementing the reserved count for the prize's daily counter. This should be called when a game is finalized with a loss after a prize was reserved.
     *
     * @param  Prize    $prize
     * @param  Campaign $campaign
     * @return void
     */
    public function releaseReservation(Prize $prize, Campaign $campaign): void
    {
        DB::transaction(
            function () use ($prize, $campaign) {
                $counter = $prize->counterForDay(now($campaign->timezone));
                if ($counter && $counter->reserved_count > 0) {
                    $counter->decrement('reserved_count');
                    Log::info(
                        'Prize reservation released',
                        [
                            'prizeId' => $prize->id,
                            'reservedCount' => $counter->reserved_count,
                        ]
                    );
                } else if ($counter === null) {
                    Log::warning(
                        'Cannot release reservation: counter not found',
                        [
                            'prizeId' => $prize->id,
                        ]
                    );
                }
            }
        );
    }

    /**
     * Get the current date in the campaign's timezone to ensure that daily counters are accurate based on the campaign's local time.
     *
     * @param  Campaign $campaign
     * @return string
     */
    public function campaignDate(Campaign $campaign): string
    {
        return now($campaign->timezone)->toDateString();
    }
}
