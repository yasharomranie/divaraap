<?php

declare(strict_types=1);

namespace Modules\Listing\Models;

use App\Support\PersianSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class WantedRequest extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'category_id' => 'integer', 'city_id' => 'integer', 'expires_at' => 'datetime', 'reviewed_at' => 'datetime', 'revision' => 'integer', 'budget' => 'integer'];
    }

    protected static function booted(): void
    {
        self::saving(fn (self $record) => abort_unless($record->writing, 403));
        self::deleting(fn () => abort(403));
    }

    public static function categoryOptions(): array
    {
        return array_diff_key(Category::advertisingFilterOptions(), array_flip(Category::advertisingServiceCategoryIds()));
    }

    public static function cityOptions(): array
    {
        return City::wantedOptions();
    }

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:120', 'regex:/\S/u'],
            'description' => ['required', 'string', 'min:10', 'max:2000', 'regex:/\S/u'],
            'category_id' => ['required', 'integer', Rule::in(array_keys(self::categoryOptions()))],
            'city_id' => ['nullable', 'integer', Rule::in(array_keys(self::cityOptions()))],
            'budget' => ['nullable', 'integer', 'min:1', 'max:1000000000000'],
        ];
    }

    public static function actor(User $user): void
    {
        abort_unless(User::query()->whereKey($user->id)->where('status', 'active')->exists(), 403);
    }

    public static function canModerate(): bool
    {
        return (string) auth()->user()?->status === 'active' && (bool) auth()->user()?->can('moderation.listings');
    }

    private static function content(array $input): array
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }
        $data = Validator::make($input, self::rules())->validate();
        $data['title'] = trim($data['title']);
        $data['description'] = trim($data['description']);
        $data['city_id'] = filled($data['city_id'] ?? null) ? (int) $data['city_id'] : null;
        $data['budget'] = filled($data['budget'] ?? null) ? (int) $data['budget'] : null;

        return $data;
    }

    public static function submit(User $user, array $input): self
    {
        self::actor($user);
        Validator::make($input, ['request_key' => ['required', 'uuid']])->validate();
        $data = self::content($input);

        return DB::transaction(function () use ($user, $input, $data): self {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $existing = self::withTrashed()->where('user_id', $user->id)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                foreach ($data as $key => $value) {
                    if ((string) $existing->$key !== (string) $value) {
                        throw ValidationException::withMessages(['title' => __('listing::wanted.replay_changed')]);
                    }
                }
                abort_if($existing->trashed(), 409);

                return $existing;
            }
            if (self::query()->where('user_id', $user->id)->whereIn('state', ['pending', 'open'])->where('expires_at', '>', now())->count() >= 20) {
                throw ValidationException::withMessages(['title' => __('listing::wanted.limit')]);
            }
            $record = new self;
            $record->writing = true;
            $record->fill([...$data, 'user_id' => $user->id, 'request_key' => $input['request_key'], 'state' => 'pending', 'revision' => 1, 'expires_at' => now()->addDays(30)])->save();
            $record->writing = false;

            return $record;
        });
    }

    public static function owned(User $user, int $id): self
    {
        self::actor($user);

        return self::query()->where('user_id', $user->id)->findOrFail($id);
    }

    public function updateFor(User $user, array $input, int $revision): void
    {
        self::actor($user);
        $data = self::content($input);
        DB::transaction(function () use ($user, $data, $revision): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            self::actor($user);
            $record = self::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->revision === $revision && in_array($record->state, ['pending', 'open', 'rejected'], true) && $record->expires_at->isFuture(), 409);
            if (self::query()->where('user_id', $user->id)->where('id', '!=', $record->id)->whereIn('state', ['pending', 'open'])->where('expires_at', '>', now())->count() >= 20) {
                throw ValidationException::withMessages(['title' => __('listing::wanted.limit')]);
            }
            $record->writing = true;
            $record->fill([...$data, 'state' => 'pending', 'revision' => $revision + 1, 'moderation_note' => null, 'reviewed_at' => null, 'reviewed_by' => null])->save();
        });
        $this->refresh();
    }

    public function closeFor(User $user, string $state, int $revision): void
    {
        self::actor($user);
        abort_unless(in_array($state, ['closed', 'fulfilled'], true), 422);
        DB::transaction(function () use ($user, $state, $revision): void {
            $record = self::query()->where('user_id', $user->id)->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->revision === $revision, 409);
            if ($record->state === $state) {
                return;
            }
            abort_unless(in_array($record->state, ['pending', 'open', 'rejected'], true) && ($state !== 'fulfilled' || ($record->state === 'open' && $record->expires_at->isFuture())), 409);
            $record->writing = true;
            $record->state = $state;
            $record->save();
        });
        $this->refresh();
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('state', 'open')->where('expires_at', '>', now())
            ->whereIn('user_id', User::activeAccountIdsQuery())
            ->whereIn('category_id', array_keys(self::categoryOptions()))
            ->where(fn (Builder $cities) => $cities->whereNull('city_id')->orWhereIn('city_id', array_keys(self::cityOptions())));
    }

    public static function browse(array $filters)
    {
        $query = self::query()->visible();
        foreach (['category_id', 'city_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        $terms = PersianSearch::terms($filters['search'] ?? '');
        if ($terms === null) {
            $query->whereRaw('1 = 0');
        }
        foreach ($terms ?? [] as $term) {
            $query->whereRaw(PersianSearch::sql('title')." LIKE ? ESCAPE '!'", [PersianSearch::pattern($term)]);
        }

        return $query->latest('id')->paginate(12)->withQueryString();
    }

    public static function forOwner(User $owner)
    {
        self::actor($owner);

        return self::query()->where('user_id', $owner->id)->latest('id')->paginate(12);
    }

    public static function published(int $id): self
    {
        return self::query()->visible()->findOrFail($id);
    }

    public function suggestionOptions(User $seller): array
    {
        self::actor($seller);
        if ((int) $seller->id === $this->user_id || ConversationBlock::between((int) $seller->id, $this->user_id)) {
            return [];
        }

        return Listing::wantedCandidates($this->category_id, $this->city_id, (int) $seller->id)->latest('id')->limit(50)->pluck('title', 'id')->all();
    }

    public static function moderationQueue()
    {
        return self::query()->where('state', 'pending')->where('expires_at', '>', now())->oldest('id')->limit(20)->get();
    }

    public function moderate(bool $approve, ?string $note = null, ?int $revision = null): void
    {
        abort_unless(self::canModerate(), 403);
        Validator::make(['note' => $note], ['note' => ['nullable', 'string', 'max:1000']])->validate();
        $expected = $revision ?? $this->revision;
        DB::transaction(function () use ($approve, $note, $expected): void {
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->state === 'pending' && $record->revision === $expected && $record->expires_at->isFuture(), 409);
            if ($approve) {
                self::content($record->only(['title', 'description', 'category_id', 'city_id', 'budget']));
                abort_unless(User::query()->whereKey($record->user_id)->where('status', 'active')->exists(), 409);
            }
            $record->writing = true;
            $record->fill(['state' => $approve ? 'open' : 'rejected', 'moderation_note' => $note, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
        });
        $this->refresh();
    }

    public function retract(string $note, int $revision): void
    {
        abort_unless(self::canModerate(), 403);
        Validator::make(['note' => $note], ['note' => ['required', 'string', 'max:1000', 'regex:/\\S/u']])->validate();
        DB::transaction(function () use ($note, $revision): void {
            $record = self::query()->lockForUpdate()->findOrFail($this->id);
            abort_unless($record->state === 'open' && $record->revision === $revision, 409);
            $record->writing = true;
            $record->fill(['state' => 'rejected', 'moderation_note' => $note, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()])->save();
        });
        $this->refresh();
    }

    public function stateLabel(): string
    {
        return __('listing::wanted.'.(in_array($this->state, ['pending', 'open', 'rejected'], true) && $this->expires_at->lessThanOrEqualTo(now()) ? 'expired' : $this->state));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['user_id', 'category_id', 'city_id', 'state', 'revision', 'reviewed_by', 'reviewed_at', 'expires_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
