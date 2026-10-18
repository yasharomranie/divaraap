<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;

final class ListingComparison
{
    public function listings(): Collection
    {
        $ids = array_slice(array_values(array_unique(array_map('intval', (array) session('listing_comparison', [])))), 0, 3);
        $found = Listing::comparisonListings($ids)->keyBy('id');
        $listings = collect($ids)->map(fn (int $id) => $found->get($id))->filter()->values();
        if ($listings->isNotEmpty()) {
            $categoryId = (int) $listings->first()->category_id;
            $listings = $listings->filter(fn (Listing $listing) => (int) $listing->category_id === $categoryId)->values();
        }
        session(['listing_comparison' => $listings->pluck('id')->all()]);

        return $listings;
    }

    public function add(Listing $listing): void
    {
        abort_unless(Listing::comparisonListings([(int) $listing->id])->isNotEmpty(), 404);
        $current = $this->listings();
        if ($current->contains('id', $listing->id)) {
            return;
        }
        if ($current->count() >= 3) {
            throw ValidationException::withMessages(['comparison' => __('favorite::comparison.limit')]);
        }
        if ($current->isNotEmpty() && (int) $current->first()->category_id !== (int) $listing->category_id) {
            throw ValidationException::withMessages(['comparison' => __('favorite::comparison.category_mismatch')]);
        }
        session(['listing_comparison' => $current->pluck('id')->push((int) $listing->id)->all()]);
    }

    public function remove(int $id): void
    {
        session(['listing_comparison' => $this->listings()->pluck('id')->reject(fn (int $selected) => $selected === $id)->values()->all()]);
    }

    public function rows(Collection $listings): array
    {
        $rows = [];
        foreach (['price', 'place', 'category'] as $field) {
            $values = $listings->map(fn (Listing $listing) => match ($field) {
                'price' => $listing->panelPriceLabel(),
                'place' => $listing->comparisonPlaceLabel(),
                default => $listing->category?->displayName() ?? '—',
            })->all();
            $rows[] = ['key' => $field, 'label' => __('favorite::comparison.'.$field), 'values' => $values, 'different' => count(array_unique($values)) > 1];
        }
        $fields = $listings->map(fn (Listing $listing) => collect(ListingCustomFieldSchemaBuilder::presentableValues($listing->category_id ? (int) $listing->category_id : null, $listing->custom_fields ?? [], true))->keyBy('key'));
        $keys = $fields->flatMap(fn (Collection $values) => $values->keys())->unique();
        foreach ($keys as $key) {
            $values = $fields->map(fn (Collection $values) => $values->get($key)['value'] ?? '—')->all();
            $label = $fields->first(fn (Collection $values) => $values->has($key))->get($key)['label'];
            $rows[] = ['key' => 'custom:'.$key, 'label' => $label, 'values' => $values, 'different' => count(array_unique($values)) > 1];
        }

        return $rows;
    }
}
