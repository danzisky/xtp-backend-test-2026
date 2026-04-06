<?php

namespace App\Models;

use DateTimeZone;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Campaign extends Model
{
    use HasSlug;

    protected $fillable = [
        'timezone', 'name', 'slug', 'starts_at', 'ends_at',
    ];

    protected $appends = [
        'is_active',
        'is_upcoming',
        'is_ended'
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(Prize::class);
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public static function search($query)
    {
        return empty($query) ? static::query()
            : static::where('name', 'like', '%'.$query.'%')
                ->orWhere('timezone', 'like', '%'.$query.'%')
                ->orWhere('starts_at', 'like', '%'.$query.'%')
                ->orWhere('ends_at', 'like', '%'.$query.'%');
    }

    public function getAvailableTimezones()
    {
        $return[null] = '';

        foreach (DateTimeZone::listIdentifiers(DateTimeZone::ALL) as $timezone) {
            $return[$timezone] = $timezone;
        }

        return $return;
    }

    public function isActive(): Attribute
    {
        $now = now()->setTimezone($this->timezone);

        return Attribute::make(
            get: fn () => ($this->starts_at === null || $now->greaterThanOrEqualTo($this->starts_at))
                && ($this->ends_at === null || $now->lessThanOrEqualTo($this->ends_at))
        );
    }

    public function isUpcoming(): Attribute
    {
        $now = now()->setTimezone($this->timezone);

        return Attribute::make(
            get: fn () => $this->starts_at !== null && $now->lessThan($this->starts_at)
        );
    }

    public function isEnded(): Attribute
    {
        $now = now()->setTimezone($this->timezone);

        return Attribute::make(
            get: fn () => $this->ends_at !== null && $now->greaterThan($this->ends_at)
        );
    }

}
