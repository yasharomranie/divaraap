<?php

declare(strict_types=1);

namespace Modules\User\App\Models;

use App\Support\PersianSearch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Location\Models\City;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class NeighborhoodShop extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['shop_user_id' => 'integer', 'city_id' => 'integer', 'priority' => 'integer', 'is_active' => 'boolean', 'expires_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(fn (self $record) => abort_unless($record->writing, 403));
        self::deleting(fn (self $record) => abort_unless($record->writing, 403));
    }

    public static function canManage(): bool
    {
        $actor = auth()->user();

        return $actor && (string) $actor->status === 'active' && $actor->hasRole('admin');
    }

    private static function authorizeActor(User $actor): void
    {
        $fresh = User::query()->find($actor->id);
        abort_unless($fresh && (string) $fresh->status === 'active' && $fresh->hasRole('admin'), 403);
    }

    public static function currentShopHash(int $owner): string
    {
        $shop = Profile::advertisingShops([$owner])[$owner] ?? null;

        return hash('sha256', json_encode($shop, JSON_THROW_ON_ERROR));
    }

    public function version(): string
    {
        return hash('sha256', json_encode($this->only(['shop_user_id', 'city_id', 'neighborhood', 'priority', 'is_active', 'shop_hash', 'expires_at', 'updated_at']), JSON_THROW_ON_ERROR));
    }

    public function formData(): array
    {
        return [...$this->only(['shop_user_id', 'city_id', 'neighborhood', 'priority', 'is_active']), 'version' => $this->version(), 'shop_version' => self::currentShopHash($this->shop_user_id), 'review_until' => $this->expires_at->copy()->timezone('Asia/Tehran')->subDay()->toDateString()];
    }

    public static function saveSelection(User $actor, array $input, ?int $id = null): self
    {
        self::authorizeActor($actor);
        if (is_string($input['neighborhood'] ?? null)) {
            $input['neighborhood'] = trim($input['neighborhood']);
        }
        $data = Validator::make($input, [
            'shop_user_id' => ['required', 'integer', 'min:1'],
            'city_id' => ['required', 'integer', Rule::in(array_keys(City::wantedOptions()))],
            'neighborhood' => ['required', 'string', 'min:2', 'max:80', 'regex:/\S/u'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'is_active' => ['required', 'boolean'],
            'review_until' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Tehran')->toDateString(), 'before_or_equal:'.now('Asia/Tehran')->addYear()->toDateString()],
            'shop_version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ...($id ? ['version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']] : []),
        ])->validate();

        return DB::transaction(function () use ($actor, $data, $id): self {
            User::query()->whereKey([$actor->id, $data['shop_user_id']])->orderBy('id')->lockForUpdate()->get();
            self::authorizeActor($actor);
            City::query()->whereKey($data['city_id'])->lockForUpdate()->firstOrFail();
            abort_unless(array_key_exists((int) $data['city_id'], City::wantedOptions()), 422);
            $record = $id ? self::query()->lockForUpdate()->findOrFail($id) : new self;
            if ($id) {
                abort_unless($record->shop_user_id === (int) $data['shop_user_id'], 422);
                abort_unless(hash_equals($record->version(), $data['version']), 409);
            }
            $shop = Profile::advertisingShops([(int) $data['shop_user_id']]);
            if (! isset($shop[$data['shop_user_id']]) || ! hash_equals(self::currentShopHash((int) $data['shop_user_id']), $data['shop_version'])) {
                throw ValidationException::withMessages(['shop_user_id' => __('user::neighborhoods.shop_changed')]);
            }
            $duplicate = self::query()->where('shop_user_id', $data['shop_user_id'])->where('city_id', $data['city_id'])->when($id, fn ($query) => $query->where('id', '!=', $id))->lockForUpdate()->first(['id']) !== null;
            if ($duplicate) {
                throw ValidationException::withMessages(['shop_user_id' => __('user::neighborhoods.duplicate')]);
            }
            if (self::query()->where('city_id', $data['city_id'])->when($id, fn ($query) => $query->where('id', '!=', $id))->lockForUpdate()->get(['id'])->count() >= 200) {
                throw ValidationException::withMessages(['city_id' => __('user::neighborhoods.limit')]);
            }
            $record->writing = true;
            $record->fill([
                'shop_user_id' => $data['shop_user_id'], 'city_id' => $data['city_id'], 'neighborhood' => $data['neighborhood'], 'priority' => $data['priority'],
                'is_active' => (bool) $data['is_active'], 'shop_hash' => $data['shop_version'],
                'expires_at' => CarbonImmutable::createFromFormat('!Y-m-d', $data['review_until'], 'Asia/Tehran')->addDay()->utc(),
            ])->save();
            $record->writing = false;

            return $record;
        });
    }

    public function changeFor(User $actor, string $action, string $version): void
    {
        self::authorizeActor($actor);
        abort_unless(in_array($action, ['pause', 'remove'], true), 422);
        DB::transaction(function () use ($actor, $action, $version): void {
            User::query()->whereKey([$actor->id, $this->shop_user_id])->orderBy('id')->lockForUpdate()->get();
            self::authorizeActor($actor);
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless(hash_equals($record->version(), $version), 409);
            $record->writing = true;
            if ($action === 'remove') {
                $record->delete();
            } else {
                $record->is_active = false;
                $record->save();
            }
        });
    }

    public static function browse(array $filters): LengthAwarePaginator
    {
        $city = (int) ($filters['city_id'] ?? 0);
        $terms = PersianSearch::terms($filters['search'] ?? '');
        $rows = collect();
        if (isset(City::wantedOptions()[$city]) && $terms !== null) {
            $selections = self::query()->where('city_id', $city)->where('is_active', true)->where('expires_at', '>', now())->orderBy('priority')->orderBy('id')->limit(200)->get();
            $shops = Profile::advertisingShops($selections->pluck('shop_user_id')->all());
            foreach ($selections as $selection) {
                $shop = $shops[$selection->shop_user_id] ?? null;
                if (! $shop || ! hash_equals($selection->shop_hash, hash('sha256', json_encode($shop, JSON_THROW_ON_ERROR)))) {
                    continue;
                }
                $haystack = mb_strtolower(strtr($shop['name'].' '.$selection->neighborhood, PersianSearch::replacements()));
                if (collect($terms)->contains(fn (string $term): bool => ! str_contains($haystack, $term))) {
                    continue;
                }
                $rows->push(['id' => $selection->id, 'owner' => $selection->shop_user_id, 'neighborhood' => $selection->neighborhood, ...$shop]);
            }
        }
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($rows->forPage($page, 12)->values(), $rows->count(), 12, $page, ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => request()->query()]);
    }

    public function stateLabel(): string
    {
        $state = ! $this->is_active ? 'paused' : ($this->expires_at->lessThanOrEqualTo(now()) ? 'expired' : (hash_equals($this->shop_hash, self::currentShopHash($this->shop_user_id)) && isset(Profile::advertisingShops([$this->shop_user_id])[$this->shop_user_id]) && isset(City::wantedOptions()[$this->city_id]) ? 'visible' : 'needs_review'));

        return __('user::neighborhoods.'.$state);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['shop_user_id', 'city_id', 'priority', 'is_active', 'expires_at', 'deleted_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
