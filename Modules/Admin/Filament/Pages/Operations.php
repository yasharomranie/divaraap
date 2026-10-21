<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use App\Support\Filament\HasAccessControlledPage;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Modules\Admin\Models\OperationActivity;
use Modules\Admin\Models\ProductEvent;
use Modules\Advertising\Models\Banner;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedRequest;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\Report\Models\Report;
use Modules\Review\Models\Review;
use UnitEnum;

final class Operations extends Page
{
    use HasAccessControlledPage;

    protected string $view = 'admin::operations';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public string $resolutionNote = '';

    protected static function getAccessPermission(): string
    {
        return 'operations.view';
    }

    public static function getNavigationLabel(): string
    {
        return __('admin::operations.title');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin::operations.group');
    }

    public function getTitle(): string
    {
        return __('admin::operations.title');
    }

    public static function subjects(): array
    {
        return ['listings' => Listing::class, 'reviews' => Review::class, 'reports' => Report::class, 'advertising' => Banner::class, 'wanted' => WantedRequest::class];
    }

    private static function reviewPermission(string $kind): string
    {
        return $kind === 'wanted' ? 'moderation.listings' : 'moderation.'.$kind;
    }

    #[Computed]
    public function queues(): array
    {
        abort_unless(self::canAccess(), 403);
        $result = [];
        foreach (self::subjects() as $kind => $model) {
            if (auth()->user()->can(self::reviewPermission($kind))) {
                $result[$kind] = $model::moderationQueue();
            }
        }

        return $result;
    }

    #[Computed]
    public function funnel(): array
    {
        abort_unless(self::canAccess(), 403);

        return auth()->user()?->can('operations.analytics') ? ProductEvent::funnel() : [];
    }

    #[Computed]
    public function searches(): array
    {
        abort_unless(self::canAccess(), 403);
        if (! auth()->user()?->can('operations.analytics')) {
            return [];
        }
        $rows = collect(ProductEvent::emptySearches());
        $categories = Category::namesForOperations($rows->pluck('category_id')->filter()->all());
        $countries = Country::namesForOperations($rows->pluck('country_id')->filter()->all());
        $cities = City::namesForOperations($rows->pluck('city_id')->filter()->all());

        return $rows->map(fn ($row) => [...$row, 'category' => isset($categories[$row['category_id']]) ? Category::localizedName($categories[$row['category_id']]) : '—', 'country' => $countries[$row['country_id']] ?? '—', 'city' => $cities[$row['city_id']] ?? '—'])->all();
    }

    #[Computed]
    public function history(): array
    {
        abort_unless(self::canAccess(), 403);

        return auth()->user()?->can('operations.audit') ? OperationActivity::recentSummary() : [];
    }

    public function review(string $kind, int $id, string $decision, ?int $revision = null): void
    {
        abort_unless(self::canAccess() && array_key_exists($kind, self::subjects()) && auth()->user()->can(self::reviewPermission($kind)), 403);
        abort_unless(in_array($decision, ['approve', 'reject'], true), 422);
        $this->validate(['resolutionNote' => ['nullable', 'string', 'max:1000']]);
        $model = self::subjects()[$kind];
        DB::transaction(function () use ($model, $kind, $id, $decision, $revision): void {
            $record = $model::query()->lockForUpdate()->findOrFail($id);
            abort_unless($model::moderationQueue()->contains('id', $id), 409);
            if ($kind === 'wanted') {
                abort_unless($revision !== null && $record->revision === $revision, 409);
            }
            $record->moderate($decision === 'approve', $this->resolutionNote ?: null);
            activity('moderation')->performedOn($record)->causedBy(auth()->user())->withProperties(['decision' => $decision, 'kind' => $kind])->log('moderation.'.$decision);
        });
        $this->resolutionNote = '';
        unset($this->queues, $this->history);
        Notification::make()->title(__('admin::operations.saved'))->success()->send();
    }
}
