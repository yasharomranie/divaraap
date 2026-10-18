<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Listing\Models\Listing;

final class ListingHistory
{
    private function synchronize(): void
    {
        $identity = auth()->id() ? 'user:'.auth()->id() : 'guest';
        if (session('listing_history_identity') !== $identity) {
            session(['listing_history_identity' => $identity, 'listing_history' => [], 'listing_history_enabled' => (bool) session('listing_history_enabled', true)]);
        }
        $entries = array_filter((array) session('listing_history', []), fn ($at, $id) => is_numeric($id) && (int) $id > 0 && is_int($at) && $at >= now()->subDays(30)->timestamp && $at <= now()->timestamp, ARRAY_FILTER_USE_BOTH);
        session(['listing_history' => array_slice($entries, 0, 50, true)]);
    }

    public function enabled(): bool
    {
        $this->synchronize();

        return (bool) session('listing_history_enabled', true);
    }

    public function record(Listing $listing): void
    {
        if (! $this->enabled() || (auth()->check() && ((int) auth()->id() === (int) $listing->user_id || (string) auth()->user()->status !== 'active'))) {
            return;
        }
        if (Listing::comparisonListings([(int) $listing->id])->isEmpty()) {
            return;
        }
        $entries = (array) session('listing_history', []);
        unset($entries[$listing->id]);
        session(['listing_history' => array_slice([$listing->id => now()->timestamp] + $entries, 0, 50, true)]);
    }

    public function page(): array
    {
        $enabled = $this->enabled();
        $entries = (array) session('listing_history', []);
        $found = Listing::comparisonListings(array_keys($entries))->keyBy('id');
        $entries = array_intersect_key($entries, $found->all());
        session(['listing_history' => $entries]);
        $ordered = collect(array_keys($entries))->map(fn ($id) => $found->get($id));
        $page = LengthAwarePaginator::resolveCurrentPage();
        $listings = new LengthAwarePaginator($ordered->forPage($page, 12)->values(), $ordered->count(), 12, $page, ['path' => route('history.index')]);

        return compact('enabled', 'entries', 'listings');
    }

    public function remove(int $id): void
    {
        $this->synchronize();
        $entries = (array) session('listing_history', []);
        unset($entries[$id]);
        session(['listing_history' => $entries]);
    }

    public function clear(): void
    {
        $this->synchronize();
        session(['listing_history' => []]);
    }

    public function configure(bool $enabled): void
    {
        $this->synchronize();
        session(['listing_history_enabled' => $enabled]);
    }
}
