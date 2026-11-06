<?php

declare(strict_types=1);

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class SupportTicket extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const CATEGORIES = ['account', 'listing', 'billing', 'technical', 'other'];

    public const STATES = ['open', 'waiting_customer', 'resolved', 'closed'];

    protected $guarded = [];

    protected $hidden = ['subject', 'form_key', 'creation_digest'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['subject' => 'encrypted', 'version' => 'integer', 'reopen_count' => 'integer', 'resolved_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $ticket): void {
            if (! $ticket->writing) {
                throw ValidationException::withMessages(['ticket' => __('support::messages.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['ticket' => __('support::messages.immutable')]));
    }

    public static function actor(): User
    {
        $user = auth()->id() ? User::find(auth()->id()) : null;
        abort_unless($user && (string) $user->status === 'active', 403);

        return $user;
    }

    public static function staff(?User $user): bool
    {
        return $user && (string) $user->status === 'active' && ($user->hasRole('admin') || $user->hasRole('support') && $user->can('admin-panel.access'));
    }

    public static function staffQuery(): Builder
    {
        $user = self::actor();
        abort_unless(self::staff($user), 403);

        return self::query()->when(! $user->hasRole('admin'), fn ($query) => $query->where('user_id', '!=', $user->id)->where(fn ($query) => $query->whereNull('assignee_id')->orWhere('assignee_id', $user->id)));
    }

    public function canWork(?User $user = null): bool
    {
        $user ??= auth()->user();

        return self::staff($user) && (int) $user->id !== (int) $this->user_id && ($user->hasRole('admin') || (int) $this->assignee_id === (int) $user->id);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id');
    }

    public static function openTicket(string $subject, string $category, string $body, string $key): self
    {
        $actor = self::actor();
        $subject = self::text($subject, 150);
        $body = self::text($body);
        self::key($key);
        if (! in_array($category, self::CATEGORIES, true)) {
            throw ValidationException::withMessages(['category' => __('support::messages.invalid')]);
        }
        $digest = hash_hmac('sha256', json_encode([$subject, $category, $body], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), config('app.key'));
        try {
            return DB::transaction(function () use ($actor, $subject, $category, $body, $key, $digest): self {
                User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $existing = self::withTrashed()->where('form_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || (int) $existing->user_id !== (int) $actor->id || ! hash_equals($existing->creation_digest, $digest)) {
                        throw ValidationException::withMessages(['form_key' => __('support::messages.replay')]);
                    }

                    return $existing;
                }
                $tickets = self::withTrashed()->where('user_id', $actor->id)->lockForUpdate()->get(['id', 'state', 'created_at']);
                if ($tickets->whereIn('state', ['open', 'waiting_customer'])->count() >= 5 || $tickets->where('created_at', '>=', now()->subDay())->count() >= 10) {
                    throw ValidationException::withMessages(['ticket' => __('support::messages.limit')]);
                }
                $ticket = new self(['reference' => 'SUP-'.Str::ulid(), 'user_id' => $actor->id, 'subject' => $subject, 'category' => $category, 'state' => 'open', 'version' => 0, 'reopen_count' => 0, 'form_key' => $key, 'creation_digest' => $digest, 'last_activity_at' => now()]);
                $ticket->persist();
                SupportMessage::recordEntry($ticket, 'customer', $body, $key);

                return $ticket;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['form_key' => __('support::messages.replay')]);
        }
    }

    public function write(string $action, string $body, int $version, string $key, bool $staffSide = false): self
    {
        $actor = self::actor();
        self::key($key);
        $body = self::text($body);
        $allowed = $staffSide ? ['staff', 'internal', 'resolved', 'closed'] : ['customer', 'reopened', 'closed'];
        abort_unless(in_array($action, $allowed, true), 403);
        try {
            return DB::transaction(function () use ($actor, $action, $body, $version, $key, $staffSide): self {
                if ($action === 'reopened') {
                    User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                }
                $ticket = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
                abort_unless($staffSide ? $ticket->canWork($actor) : (int) $ticket->user_id === (int) $actor->id, 404);
                $existing = SupportMessage::withTrashed()->where('operation_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    if ($existing->trashed() || (int) $existing->ticket_id !== (int) $ticket->id || (int) $existing->author_id !== (int) $actor->id || $existing->kind !== $action || ! hash_equals($existing->body, $body)) {
                        throw ValidationException::withMessages(['operation_key' => __('support::messages.replay')]);
                    }

                    return $ticket;
                }
                if ($ticket->version !== $version || $ticket->state === 'closed' || $ticket->state === 'resolved' && ! in_array($action, ['reopened', 'closed', 'internal'], true)) {
                    throw ValidationException::withMessages(['version' => __('support::messages.stale')]);
                }
                if ($action === 'reopened') {
                    $open = self::query()->where('user_id', $actor->id)->whereIn('state', ['open', 'waiting_customer'])->lockForUpdate()->get(['id'])->count();
                    if ($ticket->state !== 'resolved' || ! $ticket->resolved_at || $ticket->resolved_at->lt(now()->subDays(7)) || $ticket->reopen_count >= 3 || $open >= 5) {
                        throw ValidationException::withMessages(['ticket' => __('support::messages.reopen_limit')]);
                    }
                    $ticket->reopen_count++;
                }
                if ($ticket->messages()->withTrashed()->lockForUpdate()->get(['id'])->count() >= 1000 && ! in_array($action, ['closed', 'resolved'], true)) {
                    throw ValidationException::withMessages(['ticket' => __('support::messages.thread_limit')]);
                }
                SupportMessage::recordEntry($ticket, $action, $body, $key);
                if ($action !== 'internal') {
                    $ticket->state = match ($action) {
                        'staff' => 'waiting_customer', 'customer', 'reopened' => 'open', default => $action
                    };
                    $ticket->resolved_at = $ticket->state === 'resolved' ? now() : null;
                }
                $ticket->version++;
                $ticket->last_activity_at = now();
                $ticket->persist();
                if ($staffSide && $action !== 'internal') {
                    UserNotification::publish($ticket->user_id, 'support', __('support::messages.updated'), null, route('support.show', $ticket));
                } elseif (! $staffSide && $ticket->assignee_id && self::staff(User::find($ticket->assignee_id))) {
                    UserNotification::publish($ticket->assignee_id, 'support', __('support::messages.updated'), null, url('/admin/support-tickets/'.$ticket->id));
                }

                return $ticket;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['operation_key' => __('support::messages.replay')]);
        }
    }

    public function assign(?int $assigneeId, int $version): self
    {
        $actor = self::actor();
        abort_unless(self::staff($actor), 403);

        return DB::transaction(function () use ($actor, $assigneeId, $version): self {
            $ticket = self::staffQuery()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $admin = $actor->hasRole('admin');
            abort_unless($admin || $ticket->assignee_id === null && $assigneeId === (int) $actor->id, 403);
            $assignee = $assigneeId ? User::find($assigneeId) : null;
            if ($ticket->version !== $version || $ticket->state === 'closed' || $assigneeId !== null && (! self::staff($assignee) || (int) $assigneeId === (int) $ticket->user_id)) {
                throw ValidationException::withMessages(['version' => __('support::messages.stale')]);
            }
            $ticket->assignee_id = $assigneeId;
            $ticket->version++;
            $ticket->last_activity_at = now();
            $ticket->persist();
            if ($assigneeId && $assigneeId !== (int) $actor->id) {
                UserNotification::publish($assigneeId, 'support', __('support::messages.assigned_notice'), null, url('/admin/support-tickets/'.$ticket->id));
            }

            return $ticket;
        }, 3);
    }

    public static function assigneeOptions(): array
    {
        return User::query()->where('status', 'active')->role(['admin', 'support'])->limit(100)->get()->filter(fn (User $user) => self::staff($user))->mapWithKeys(fn (User $user) => [$user->id => $user->name.' #'.$user->id])->all();
    }

    public static function text(string $text, int $max = 4000): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > $max) {
            throw ValidationException::withMessages([$max === 150 ? 'subject' : 'body' => __('support::messages.invalid')]);
        }

        return $text;
    }

    private static function key(string $key): void
    {
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['operation_key' => __('support::messages.invalid')]);
        }
    }

    private function persist(): void
    {
        $this->writing = true;
        try {
            $this->save();
        } finally {
            $this->writing = false;
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['reference', 'user_id', 'assignee_id', 'category', 'state', 'version', 'reopen_count', 'resolved_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
