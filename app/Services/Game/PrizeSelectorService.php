<?php

namespace App\Services\Game;

use App\Enums\PrizeSegment;
use App\Models\Campaign;
use App\Models\Prize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service responsible for selecting eligible and winnable prizes for game sessions based on campaign context and prize constraints.
 */
final class PrizeSelectorService
{
    /**
     * Get the eligible prizes for a given campaign and segment.
     *
     * @param Campaign $campaign
     * @param PrizeSegment|string $segment
     * @return Collection<int, Prize> A collection of eligible prizes.
     */
    public function getEligiblePrizes(Campaign $campaign, PrizeSegment|string $segment): Collection
    {
        $now = now($campaign->timezone);

        return $campaign->prizes()
            ->segment($segment)
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->get();
    }

    /**
     * Get the winnable prizes for a given campaign and segment, considering daily limits and availability.
     *
     * @param Campaign $campaign
     * @param PrizeSegment|string $segment
     * @return Collection<int, Prize> A collection of winnable prizes.
     */
    public function getWinnablePrizes(Campaign $campaign, PrizeSegment|string $segment): Collection
    {
        $counterDate = now($campaign->timezone)->toDateString();

        return $campaign->prizes()
            ->segment($segment)
            ->leftJoin('daily_prize_counters as dpc', function ($join) use ($counterDate) {
                $join->on('dpc.prize_id', '=', 'prizes.id')
                    ->where('dpc.counter_date', '=', $counterDate);
            })
            ->where('prizes.daily_limit', '>', 0)
            ->whereRaw('COALESCE(dpc.reserved_count, 0) < prizes.daily_limit')
            ->select('prizes.*')
            ->get();
    }

    /**
     * Select a prize based on weighted random selection.
     *
     * @param Collection<int, Prize> $prizes A collection of prizes to select from.
     * @return Prize|null The selected prize or null if no prize is available.
     */
    public function selectWeightedPrize(Campaign $campaign, PrizeSegment|string $segment): ?Prize
    {
        return $this->getWinnablePrizes($campaign, $segment)
            ->where('weight', '>', 0)
            ->toQuery()
            ->orderByRaw('-LOG(RAND()) / weight')
            ->first();
    }
}
