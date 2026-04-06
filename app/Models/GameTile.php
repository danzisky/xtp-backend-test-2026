<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameTile extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'tile_index',
        'display_index',
        'prize_id',
        'tile_image',
        'revealed_at',
    ];

    protected $casts = [
        'revealed_at' => 'datetime',
    ];

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    protected function isRevealed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->revealed_at !== null,
        );
    }

    public function scopeRevealed($query)
    {
        return $query->whereNotNull('revealed_at');
    }

    public function scopeUnrevealed($query)
    {
        return $query->whereNull('revealed_at');
    }
}
