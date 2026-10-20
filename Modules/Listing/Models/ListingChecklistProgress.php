<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\User\App\Models\User;

final class ListingChecklistProgress extends Model
{
    use SoftDeletes;

    protected $table = 'listing_checklist_progress';

    protected $guarded = ['id'];

    protected $casts = ['checked_keys' => 'array'];

    public static function assertVisible(Listing $listing, ?User $actor): void
    {
        abort_unless(in_array($listing->statusValue(), ['active', 'sold', 'expired'], true)
            || ($actor && (int) $listing->user_id === (int) $actor->id), 404);
    }

    public static function stateFor(Listing $listing, ?User $actor): array
    {
        self::assertVisible($listing, $actor);
        $definition = Category::tradeChecklistDefinition($listing->category_id ? (int) $listing->category_id : null);
        $progress = $actor ? self::query()->where('user_id', $actor->id)->where('listing_id', $listing->id)->first() : null;
        $current = $progress && hash_equals($definition['revision'], $progress->revision);

        return $definition + ['checked' => $current ? $progress->checked_keys : [], 'version' => $progress?->version ?? 'none', 'changed' => $progress && ! $current];
    }

    public static function saveFor(Listing $listing, User $actor, string $revision, string $version, array $checked): void
    {
        DB::transaction(function () use ($listing, $actor, $revision, $version, $checked): void {
            $owner = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless((string) $owner->status === 'active', 403);
            $locked = Listing::query()->lockForUpdate()->findOrFail($listing->id);
            self::assertVisible($locked, $owner);
            $definition = Category::tradeChecklistDefinition($locked->category_id ? (int) $locked->category_id : null, true);
            if ($definition['items'] === [] || ! hash_equals($definition['revision'], $revision)) {
                throw ValidationException::withMessages(['revision' => __('category::checklists.changed')]);
            }
            $keys = array_column($definition['items'], 'key');
            if (count($checked) > 20 || array_diff($checked, $keys) !== [] || count(array_unique($checked)) !== count($checked)) {
                throw ValidationException::withMessages(['checked' => __('category::checklists.invalid')]);
            }
            $progress = self::query()->where('user_id', $owner->id)->where('listing_id', $locked->id)->lockForUpdate()->first();
            if (! hash_equals($progress?->version ?? 'none', $version)) {
                throw ValidationException::withMessages(['version' => __('category::checklists.stale')]);
            }
            $progress ??= new self(['user_id' => $owner->id, 'listing_id' => $locked->id]);
            $progress->forceFill(['revision' => $revision, 'version' => (string) Str::uuid(), 'checked_keys' => array_values($checked)])->save();
        });
    }
}
