<?php

namespace App\Models;

use App\Enums\PrizeSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Prize extends Model
{
    protected $fillable = [
        'campaign_id',
        'name',
        'description',
        'segment',
        'weight',
        'image',
        'starts_at',
        'daily_limit',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected $with = ['campaign'];

    public static function search($query)
    {
        return empty($query) ? static::query()
            : static::where('name', 'like', '%'.$query.'%');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the daily counters for the prize.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dailyCounters(): HasMany
    {
        return $this->hasMany(DailyPrizeCounter::class);
    }

    /**
     * Get or create the daily counter for the prize.
     *
     * @param \Illuminate\Support\Carbon|null $date The date for which to get the counter (in campaign timezone). Defaults to today.
     * @return DailyPrizeCounter The daily counter for the specified date.
     */
    public function counterForDay(?Carbon $date = null): ?DailyPrizeCounter
    {
        if ($this->daily_limit === null) {
            return null;
        }

        $date = ($date ?? now())->setTimezone($this->campaign->timezone);
        $counterDate = $date->toDateString();

        $counter = $this->dailyCounters()
            ->whereDate('counter_date', $counterDate)
            ->first();

        if ($counter) {
            return $counter;
        }

        return $this->dailyCounters()->create([
            'counter_date' => $counterDate,
            'daily_limit' => (int) $this->daily_limit,
            'reserved_count' => 0,
            'awarded_count' => 0,
        ]);
    }

    public function scopeSegment(Builder $query, string|PrizeSegment $segment)
    {
        return $query->where('segment', $segment instanceof PrizeSegment ? $segment->value : $segment);
    }


    public function scopeWinnable(Builder $query)
    {
        return $query->where('daily_limit', '>', 0);
    }

    public function scopeWinnableForSegment(Builder $query, string|PrizeSegment $segment)
    {
        return $query->segment($segment)->winnable();
    }
}
