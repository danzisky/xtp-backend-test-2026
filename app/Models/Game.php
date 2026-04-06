<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = ['campaign_id', 'prize_id', 'status', 'account', 'segment', 'finished_at', 'matches_to_win', 'max_tries'];

    protected function casts(): array
    {
        return [
            'finished_at' => 'datetime',
        ];
    }

    protected $appends = [
        'is_finished',
        'is_valid',
        'can_sratch_tiles',
    ];

    public static function filter(?string $account = null, ?int $prizeId = null, ?string $fromDate = null, ?string $tillDate = null)
    {
        $query = self::query();
        $campaign = Campaign::find(session('activeCampaign'));

        // When filtering by dates, keep in mind `finished_at` should be stored in Campaign timezone

        return $query;
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function tiles(): HasMany
    {
        return $this->hasMany(GameTile::class);
    }

    public function unscratchedTiles(): HasMany
    {
        return $this->tiles()->unrevealed();
    }

    public function scratchedTiles(): HasMany
    {
        return $this->tiles()->revealed();
    }

    public function canScratchTiles(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->scratchedTiles()->count() < $this->max_tries,
        );
    }

    public function isFinished(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->finished_at !== null,
        );
    }

    public function isValid(): Attribute
    {
        return Attribute::make(
            get: fn () => !$this->finished_at && $this?->campaign?->is_active,
        );
    }
}
