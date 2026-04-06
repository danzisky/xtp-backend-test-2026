<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPrizeCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'campaign_id',
        'prize_id',
        'counter_date',
        'reserved_count',
        'awarded_count',
        'daily_limit',
    ];

    protected function casts(): array
    {
        return [
            'counter_date' => 'date',
        ];
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    protected function isAwardable(): Attribute
    {
        return Attribute::make(
            // get: fn () => $this->reserved_count < $this->prize->daily_limit,
             get: fn () => $this->reserved_count < $this->daily_limit,
        );
    }
}
