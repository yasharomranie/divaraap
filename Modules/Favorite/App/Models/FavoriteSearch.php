<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use App\Support\Money;
use App\Support\PersianSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Category\Models\Category;
use Modules\Location\Models\Country;
use Modules\User\App\Models\User;

class FavoriteSearch extends Model
{
    use SoftDeletes;

    protected $fillable = ['user_id', 'label', 'search_term', 'category_id', 'filters', 'signature'];

    protected $casts = ['filters' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public static function browseFilters(array $data): array
    {
        $location = Country::browseSelection(! empty($data['country']) ? (int) $data['country'] : null, ! empty($data['city']) ? (int) $data['city'] : null);

        return self::normalizeFilters([
            'search' => trim((string) ($data['search'] ?? '')),
            'category' => ! empty($data['category']) ? (int) $data['category'] : null,
            'country' => $location['country_id'], 'city' => $location['city_id'],
            'user' => ! empty($data['user']) ? (int) $data['user'] : null,
            'min_price' => Money::valid($data['min_price'] ?? null, 'IRR') ? Money::decimal($data['min_price']) : null,
            'max_price' => Money::valid($data['max_price'] ?? null, 'IRR') ? Money::decimal($data['max_price']) : null,
        ]);
    }

    public static function normalizeFilters(array $filters): array
    {
        return collect($filters)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
            ->sortKeys()
            ->all();
    }

    public static function signatureFor(array $filters): string
    {
        $normalized = static::normalizeFilters($filters);
        if (isset($normalized['search']) && ($terms = PersianSearch::terms((string) $normalized['search'])) !== null) {
            sort($terms, SORT_STRING);
            $normalized['search'] = implode(' ', $terms);
        }
        $payload = json_encode($normalized);

        return hash('sha256', is_string($payload) ? $payload : '');
    }

    public static function labelFor(array $filters, ?string $categoryName = null): string
    {
        $labelParts = [];

        if (! empty($filters['search'])) {
            $labelParts[] = '"'.$filters['search'].'"';
        }

        if (filled($categoryName)) {
            $labelParts[] = $categoryName;
        }

        return $labelParts !== [] ? implode(' · ', $labelParts) : __('favorite::messages.filtered_search');
    }

    public static function isSavedForUser(User $user, array $filters): bool
    {
        $normalized = static::normalizeFilters($filters);

        if ($normalized === []) {
            return false;
        }

        return self::findForUser($user, $normalized) !== null;
    }

    private static function findForUser(User $user, array $filters): ?self
    {
        $signature = self::signatureFor($filters);
        $exact = self::where('user_id', $user->id)->where('signature', $signature)->first();
        if ($exact) {
            return $exact;
        }
        foreach (self::where('user_id', $user->id)->lazyById(200) as $search) {
            if (self::signatureFor($search->filters ?? []) === $signature) {
                return $search;
            }
        }

        return null;
    }

    public static function storeForUser(User $user, array $filters): self
    {
        $normalized = static::normalizeFilters($filters);
        $existing = self::findForUser($user, $normalized);
        if ($existing) {
            return $existing;
        }
        $signature = static::signatureFor($normalized);
        $categoryName = isset($normalized['category'])
            ? Category::query()->whereKey($normalized['category'])->value('name')
            : null;

        return $user->favoriteSearches()->firstOrCreate(
            ['signature' => $signature],
            [
                'label' => static::labelFor($normalized, is_string($categoryName) ? $categoryName : null),
                'search_term' => $normalized['search'] ?? null,
                'category_id' => $normalized['category'] ?? null,
                'filters' => $normalized,
            ]
        );
    }
}
