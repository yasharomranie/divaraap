<?php

declare(strict_types=1);

namespace Modules\Location\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Location\Support\IranianCities;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class City extends Model
{
    use LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'country_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public static function namesForOperations(array $ids): array
    {
        return static::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    public static function ensureIranianCapitals(): void
    {
        $country = Country::withTrashed()->firstOrCreate(['code' => 'IR'], ['name' => 'Iran', 'phone_code' => '+98', 'is_active' => true]);

        foreach (IranianCities::CAPITALS as $name => $persianName) {
            $exists = static::withTrashed()->where('country_id', $country->getKey())->whereIn('name', [$name, $persianName])->exists();

            if (! $exists) {
                static::query()->create(['country_id' => $country->getKey(), 'name' => $name, 'is_active' => true]);
            }
        }
    }

    public function displayName(): string
    {
        return $this->country?->code === 'IR' ? IranianCities::label((string) $this->name) : (string) $this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    public static function nameOptions(?string $countryName = null, bool $onlyActive = true): array
    {
        return static::query()
            ->when($onlyActive, fn (Builder $query): Builder => $query->active())
            ->when(
                $countryName && trim($countryName) !== '',
                fn (Builder $query): Builder => $query->whereHas(
                    'country',
                    fn (Builder $countryQuery): Builder => $countryQuery->where('name', trim($countryName)),
                ),
            )
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    public static function quickCreateOptions(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->with('country')->get(['id', 'name', 'country_id'])
            ->map(fn (self $city): array => [
                'id' => (int) $city->id,
                'name' => (string) $city->name,
                'display_name' => $city->displayName(),
                'country_id' => (int) $city->country_id,
            ])
            ->all();
    }

    public function districtPayloads(): array
    {
        return $this->districts()
            ->orderBy('name')
            ->get()
            ->map(fn (District $district): array => [
                'id' => (int) $district->id,
                'name' => (string) $district->name,
                'city_id' => (int) $district->city_id,
            ])
            ->all();
    }
}
