<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OperationalIncident extends Model
{
    use SoftDeletes;

    protected $guarded = ['*'];

    protected $hidden = ['note'];

    protected function casts(): array
    {
        return ['note' => 'encrypted', 'opened_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime', 'acknowledged_at' => 'datetime', 'version' => 'integer'];
    }

    public static function history(): LengthAwarePaginator
    {
        OperationalMonitor::authorizeAdmin();

        return self::query()->latest('id')->paginate(20);
    }

    public static function acknowledge(int $id, int $version, string $note): void
    {
        $actor = OperationalMonitor::authorizeAdmin();
        $note = trim($note);
        validator(['note' => $note], ['note' => 'required|string|max:500'])->validate();
        DB::transaction(function () use ($id, $version, $note, $actor): void {
            $incident = self::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($incident->acknowledged_by === $actor->id && $incident->note === $note && ! $incident->resolved_at) {
                return;
            }
            if ($incident->version !== $version || $incident->resolved_at || $incident->acknowledged_at) {
                throw ValidationException::withMessages(['note' => __('admin::monitor.stale')]);
            }
            $incident->forceFill(['acknowledged_by' => $actor->id, 'acknowledged_at' => now(), 'note' => $note, 'version' => $version + 1])->saveOrFail();
        }, 3);
    }
}
