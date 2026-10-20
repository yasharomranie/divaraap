<?php

declare(strict_types=1);

namespace Modules\Report\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Conversation\App\Models\Conversation;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Report extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const SUBJECT_LISTING = 'listing';

    public const SUBJECT_USER = 'user';

    public const STATUS_OPEN = 'open';

    public const STATUS_REVIEWING = 'reviewing';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_DISMISSED = 'dismissed';

    protected static function booted(): void
    {
        static::saving(function (self $report): void {
            if ($report->exists && $report->isDirty(['subject_type', 'subject_id', 'reporter_id'])) {
                $report->conversation_id = null;
                $report->reported_message_id = null;
                $report->conversation_evidence = null;
            }
        });
    }

    public static function moderationQueue(): Collection
    {
        return static::query()->whereIn('status', [self::STATUS_OPEN, self::STATUS_REVIEWING])->oldest()->limit(20)->get(['id', 'reason', 'details', 'created_at']);
    }

    public function moderate(bool $approve, ?string $note = null): void
    {
        $approve ? $this->resolve($note) : $this->dismiss($note);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'resolved_at', 'reason'])->logOnlyDirty()->dontLogEmptyChanges();
    }

    protected $fillable = [
        'subject_type',
        'subject_id',
        'reporter_id',
        'reason',
        'details',
        'status',
        'resolution_note',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'conversation_evidence' => 'encrypted',
    ];

    public static function conversationReasons(): array
    {
        return ['spam', 'fraud', 'prohibited', 'offensive', 'other'];
    }

    public static function fileConversationFor(User $reporter, Conversation $conversation, ?int $messageId, string $reason, ?string $details): self
    {
        return DB::transaction(function () use ($reporter, $conversation, $messageId, $reason, $details): self {
            $owner = User::query()->lockForUpdate()->findOrFail($reporter->getKey());
            abort_unless((string) $owner->status === 'active', 403);
            $thread = Conversation::query()->findOrFail($conversation->getKey());
            abort_unless($thread->hasParticipant((int) $owner->getKey()), 403);
            $target = (int) ((int) $thread->buyer_id === (int) $owner->getKey() ? $thread->seller_id : $thread->buyer_id);
            abort_if($target === (int) $owner->getKey(), 422);
            if (! in_array($reason, self::conversationReasons(), true)) {
                throw ValidationException::withMessages(['reason' => __('conversation::safety.invalid_report')]);
            }
            $message = $messageId ? $thread->messages()->where('sender_id', $target)->where('created_at', '<=', now())->find($messageId) : null;
            if ($messageId && ! $message) {
                throw ValidationException::withMessages(['message_id' => __('conversation::safety.invalid_message')]);
            }
            $existing = static::query()->where('reporter_id', $owner->getKey())->where('conversation_id', $thread->getKey())
                ->when($messageId === null, fn (Builder $query) => $query->whereNull('reported_message_id'), fn (Builder $query) => $query->where('reported_message_id', $messageId))->first();
            if ($existing) {
                return $existing;
            }
            $report = static::query()->make(['subject_type' => self::SUBJECT_USER, 'subject_id' => $target, 'reporter_id' => $owner->getKey(), 'reason' => $reason, 'details' => $details, 'status' => self::STATUS_OPEN]);
            $report->forceFill(['conversation_id' => $thread->getKey(), 'reported_message_id' => $messageId, 'conversation_evidence' => $message ? mb_substr((string) $message->body, 0, 2000) : null])->save();

            return $report;
        });
    }

    public static function reasons(): array
    {
        return [
            'spam',
            'fraud',
            'prohibited',
            'wrong_category',
            'duplicate',
            'offensive',
            'sold_elsewhere',
            'other',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_OPEN,
            self::STATUS_REVIEWING,
            self::STATUS_RESOLVED,
            self::STATUS_DISMISSED,
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForSubject(Builder $query, string $type, int $id): Builder
    {
        return $query->where('subject_type', $type)->where('subject_id', $id);
    }

    public static function file(string $subjectType, int $subjectId, ?int $reporterId, string $reason, ?string $details): self
    {
        $report = static::query()->make([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'reporter_id' => $reporterId,
            'reason' => in_array($reason, self::reasons(), true) ? $reason : 'other',
            'details' => $details,
            'status' => self::STATUS_OPEN,
        ]);

        $report->save();

        return $report;
    }

    public static function alreadyFiledBy(?int $reporterId, string $subjectType, int $subjectId): bool
    {
        if ($reporterId === null) {
            return false;
        }

        return static::query()
            ->forSubject($subjectType, $subjectId)
            ->where('reporter_id', $reporterId)
            ->exists();
    }

    public static function openCount(): int
    {
        return (int) static::query()->open()->count();
    }

    public function resolve(?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_RESOLVED,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ])->save();
    }

    public function dismiss(?string $note = null): void
    {
        $this->forceFill([
            'status' => self::STATUS_DISMISSED,
            'resolution_note' => $note,
            'resolved_at' => now(),
        ])->save();
    }
}
