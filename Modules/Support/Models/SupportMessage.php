<?php

declare(strict_types=1);

namespace Modules\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class SupportMessage extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = [];

    protected $hidden = ['body', 'operation_key'];

    private bool $writing = false;

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    protected static function booted(): void
    {
        self::saving(function (self $message): void {
            if (! $message->writing || $message->exists) {
                throw ValidationException::withMessages(['ticket' => __('support::messages.immutable')]);
            }
        });
        self::deleting(fn () => throw ValidationException::withMessages(['ticket' => __('support::messages.immutable')]));
    }

    public static function recordEntry(SupportTicket $ticket, string $kind, string $body, string $key): self
    {
        abort_unless(DB::transactionLevel() > 0, 403);
        $actor = SupportTicket::actor();
        abort_unless(in_array($kind, ['customer', 'staff', 'internal', 'resolved', 'closed', 'reopened'], true), 422);
        abort_unless($ticket->state !== 'closed' && ($ticket->state !== 'resolved' || in_array($kind, ['reopened', 'closed', 'internal'], true)), 422);
        abort_unless(in_array($kind, ['customer', 'reopened', 'closed'], true) && (int) $actor->id === (int) $ticket->user_id || $ticket->canWork($actor), 403);
        $message = new self(['ticket_id' => $ticket->id, 'author_id' => $actor->id, 'kind' => $kind, 'body' => $body, 'operation_key' => $key]);
        $message->writing = true;
        $message->save();
        $message->writing = false;

        return $message;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['ticket_id', 'author_id', 'kind'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
