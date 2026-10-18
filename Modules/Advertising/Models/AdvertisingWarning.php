<?php

declare(strict_types=1);

namespace Modules\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;

final class AdvertisingWarning extends Model
{
    use SoftDeletes;

    protected $table = 'advertising_warnings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_open' => 'boolean', 'notified_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function actionUrl(): string
    {
        return url('/admin/'.($this->subject_type === 'banner' ? 'banners' : 'campaigns').'/'.$this->subject_id.'/edit');
    }

    public static function scan(User $user): int
    {
        abort_unless((string) $user->status === 'active' && $user->can('advertising.manage'), 403);
        $issues = array_merge(Banner::healthIssues(), Campaign::healthIssues());
        $indexed = [];
        foreach ($issues as $issue) {
            $indexed[hash('sha256', $user->id.'|'.$issue['subject_type'].'|'.$issue['subject_id'].'|'.$issue['code'])] = $issue;
        }
        ksort($indexed);

        return DB::transaction(function () use ($user, $indexed): int {
            $count = 0;
            foreach ($indexed as $fingerprint => $issue) {
                self::query()->insertOrIgnore($issue + ['fingerprint' => $fingerprint, 'user_id' => $user->id, 'is_open' => true, 'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $warning = self::withTrashed()->where('fingerprint', $fingerprint)->lockForUpdate()->firstOrFail();
                if (! $warning->is_open || $warning->notified_at === null) {
                    UserNotification::publish((int) $user->id, UserNotification::TYPE_ADVERTISING, __('advertising::warnings.notification_title'), __('advertising::warnings.'.$issue['code']).' · '.$issue['subject_title'], $warning->actionUrl());
                    $warning->notified_at = now();
                    $count++;
                }
                $warning->forceFill(['subject_title' => $issue['subject_title'], 'is_open' => true, 'last_seen_at' => now(), 'resolved_at' => null, 'deleted_at' => null])->save();
            }
            self::query()->where('user_id', $user->id)->where('is_open', true)->when($indexed !== [], fn ($query) => $query->whereNotIn('fingerprint', array_keys($indexed)))
                ->update(['is_open' => false, 'resolved_at' => now(), 'updated_at' => now()]);

            return $count;
        }, 3);
    }

    public static function forUser(int $userId): Collection
    {
        return self::query()->where('user_id', $userId)->where('is_open', true)->latest('last_seen_at')->orderBy('id')->limit(100)->get();
    }
}
