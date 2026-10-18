<?php

declare(strict_types=1);

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Admin\App\Settings\OperationsSettings;

final class ProductEvent extends Model
{
    use SoftDeletes;

    protected $table = 'product_events';

    protected $guarded = ['id'];

    public static function record(string $kind, string $value, array $filters = []): void
    {
        try {
            self::ingest($kind, $value, $filters);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private static function ingest(string $kind, string $value, array $filters): void
    {
        if (! request()->hasSession() || ! app(OperationsSettings::class)->telemetry_enabled || ! in_array($kind, ['listing_step', 'search_empty'], true)) {
            return;
        }
        $value = str_replace(['ك', 'ي', 'ى'], ['ک', 'ی', 'ی'], $value);
        $value = trim(Str::limit(preg_replace('/\s+/u', ' ', strip_tags($value)), 120, ''));
        if ($value === '' || ($kind === 'search_empty' && (filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/(?:[\d۰-۹٠-٩][\s()+.\-]*){7,}|@|https?:/iu', $value)))) {
            return;
        }
        $filters = collect(['category_id', 'country_id', 'city_id'])->mapWithKeys(fn (string $key): array => [$key => max(0, (int) ($filters[$key] ?? 0))])->all();
        $fingerprint = hash_hmac('sha256', request()->session()->getId().'|'.now()->toDateString().'|'.$kind.'|'.$value.'|'.json_encode($filters), config('app.key'));
        self::query()->firstOrCreate(['fingerprint' => $fingerprint], ['kind' => $kind, 'value' => $value, ...$filters]);
        if (Cache::add('operations.telemetry.prune', true, now()->addDay())) {
            self::query()->withTrashed()->where('created_at', '<', now()->subDays(90))->forceDelete();
        }
    }

    public static function funnel(): array
    {
        return self::query()->where('kind', 'listing_step')->where('created_at', '>=', now()->subDays(30))->select('value')->selectRaw('COUNT(*) as total')->groupBy('value')->orderBy('value')->get()->toArray();
    }

    public static function emptySearches(): array
    {
        return self::query()->where('kind', 'search_empty')->where('created_at', '>=', now()->subDays(30))->select('value', 'category_id', 'country_id', 'city_id')->selectRaw('COUNT(*) as total')->groupBy('value', 'category_id', 'country_id', 'city_id')->orderByDesc('total')->limit(20)->get()->toArray();
    }
}
