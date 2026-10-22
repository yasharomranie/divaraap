<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Models;

use App\Support\PersianSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class RareItemWatch extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['filters' => 'array', 'expires_at' => 'datetime', 'user_id' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(fn (self $watch) => abort_unless($watch->writing, 403));
        self::deleting(fn (self $watch) => abort_unless($watch->writing, 403));
    }

    public static function categoryOptions(): array
    {
        return array_diff_key(Category::advertisingFilterOptions(), array_flip(Category::advertisingServiceCategoryIds()));
    }

    public static function rules(): array
    {
        return [
            'search' => ['required', 'string', 'min:2', 'max:120', 'regex:/\S/u'],
            'category' => ['required', 'integer', Rule::in(array_keys(self::categoryOptions()))],
            'city' => ['nullable', 'integer', Rule::in(array_keys(City::wantedOptions()))],
            'max_price' => ['nullable', 'integer', 'min:1', 'max:1000000000000'],
        ];
    }

    private static function criteria(array $input): array
    {
        if (is_string($input['search'] ?? null)) {
            $input['search'] = trim($input['search']);
        }
        $data = Validator::make($input, self::rules())->validate();
        $terms = PersianSearch::terms($data['search']);
        if ($terms === null || $terms === []) {
            throw ValidationException::withMessages(['search' => __('favorite::rare.terms')]);
        }
        $data['category'] = (int) $data['category'];
        $data['city'] = filled($data['city'] ?? null) ? (int) $data['city'] : null;
        $data['max_price'] = filled($data['max_price'] ?? null) ? (string) $data['max_price'] : null;

        return FavoriteSearch::normalizeFilters($data);
    }

    public static function actor(User $user): void
    {
        abort_unless(User::query()->whereKey($user->id)->where('status', 'active')->exists(), 403);
    }

    public static function createFor(User $user, array $input): self
    {
        self::actor($user);
        $filters = self::criteria($input);
        $signature = FavoriteSearch::signatureFor($filters);

        return DB::transaction(function () use ($user, $filters, $signature): self {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $existing = self::query()->where('user_id', $user->id)->where('signature', $signature)->first();
            if ($existing) {
                return $existing;
            }
            if (self::query()->where('user_id', $user->id)->count() >= 20) {
                throw ValidationException::withMessages(['search' => __('favorite::rare.catalog_limit')]);
            }
            $watch = new self;
            $watch->writing = true;
            $watch->fill(['user_id' => $user->id, 'signature' => $signature, 'filters' => $filters, 'expires_at' => now()->addDays(30)])->save();
            $watch->writing = false;
            BuyerAlert::configure($user, 'rare', $watch->id, true);

            return $watch;
        });
    }

    public static function owned(User $user, int $id): self
    {
        self::actor($user);

        return self::query()->where('user_id', $user->id)->findOrFail($id);
    }

    public static function availableFor(int $owner, int $id): ?self
    {
        $watch = self::query()->where('user_id', $owner)->where('expires_at', '>', now())->find($id);
        if (! $watch || ! array_key_exists($watch->filters['category'] ?? 0, self::categoryOptions()) || (isset($watch->filters['city']) && ! array_key_exists($watch->filters['city'], City::wantedOptions()))) {
            return null;
        }

        return $watch;
    }

    public static function unexpiredIds(int $owner): array
    {
        return self::query()->where('user_id', $owner)->where('expires_at', '>', now())->pluck('id')->all();
    }

    public static function forOwner(User $owner)
    {
        self::actor($owner);

        return self::query()->where('user_id', $owner->id)->latest('id')->paginate(12);
    }

    public function matches(array $ids): iterable
    {
        return Listing::rareAlertListings($ids, $this->filters, $this->user_id);
    }

    public function changeFor(User $user, string $action): void
    {
        self::actor($user);
        abort_unless(in_array($action, ['pause', 'resume', 'renew', 'remove'], true), 422);
        DB::transaction(function () use ($user, $action): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $watch = self::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($this->id);
            if ($action === 'pause') {
                BuyerAlert::disableSource($user->id, 'rare', $watch->id);
            } elseif ($action === 'remove') {
                BuyerAlert::disableSource($user->id, 'rare', $watch->id);
                $watch->writing = true;
                $watch->delete();
            } else {
                if ($action === 'renew') {
                    abort_unless($watch->expires_at->lessThanOrEqualTo(now()), 409);
                    self::criteria($watch->filters);
                    $watch->writing = true;
                    $watch->expires_at = now()->addDays(30);
                    $watch->save();
                    BuyerAlert::disableSource($user->id, 'rare', $watch->id);
                }
                BuyerAlert::configure($user, 'rare', $watch->id, true);
            }
        });
        if ($action !== 'remove') {
            $this->refresh();
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'expires_at', 'deleted_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
