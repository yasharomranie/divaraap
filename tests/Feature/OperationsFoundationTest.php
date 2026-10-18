<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Admin\App\Settings\OperationsSettings;
use Modules\Admin\Filament\Pages\Operations;
use Modules\Admin\Models\ProductEvent;
use Modules\Admin\Support\AdvertisingCapacity;
use Modules\Advertising\Models\Banner;
use Modules\Listing\Models\Listing;
use Modules\Report\Models\Report;
use Modules\User\App\Models\User;
use Modules\User\App\States\SuspendedUserStatus;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

final class OperationsFoundationTest extends TestCase
{
    private function staff(string $role): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::where('email', 'b@b.com')->firstOrFail();
        $user->syncRoles([$role]);
        $this->actingAs($user);

        return $user;
    }

    public function test_advertising_manager_is_restricted_to_advertising_and_operations(): void
    {
        $user = $this->staff('advertising-manager');
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
        $this->get('/admin/operations')->assertSuccessful();
        $this->get('/admin/banners')->assertSuccessful();
        $this->get('/admin/banners/create')->assertSuccessful();
        foreach (['/admin', '/admin/users', '/admin/listings', '/admin/reviews', '/admin/reports', '/admin/categories', '/admin/file-manager', '/admin/manage-general-settings', '/admin/manage-appearance-settings', '/admin/manage-deployment', '/admin/operations-settings'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_suspended_staff_cannot_enter_panel(): void
    {
        $user = $this->staff('support');
        $user->status->transitionTo(SuspendedUserStatus::class);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->get('/admin/operations')->assertForbidden();
    }

    public function test_support_can_resolve_reports_but_cannot_approve_a_listing(): void
    {
        $this->staff('support');
        $report = Report::file('listing', 1, null, 'spam', 'Test report');
        Livewire::test(Operations::class)->set('resolutionNote', 'Reviewed by support')->call('review', 'reports', $report->id, 'approve')->assertHasNoErrors();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertSame('Reviewed by support', $report->fresh()->resolution_note);
        $listing = Listing::where('status', 'pending')->firstOrFail();
        Livewire::test(Operations::class)->call('review', 'listings', $listing->id, 'approve')->assertForbidden();
        $this->assertSame('pending', $listing->fresh()->statusValue());
    }

    public function test_moderator_can_reject_listing_and_the_decision_is_audited(): void
    {
        $user = $this->staff('moderator');
        $listing = Listing::where('status', 'pending')->firstOrFail();
        Livewire::test(Operations::class)->call('review', 'listings', $listing->id, 'reject')->assertHasNoErrors();
        $this->assertSame('rejected', $listing->fresh()->statusValue());
        $this->assertTrue(Activity::where('subject_type', Listing::class)->where('subject_id', $listing->id)->where('causer_id', $user->id)->where('description', 'moderation.reject')->exists());
        Livewire::test(Operations::class)->call('review', 'listings', $listing->id, 'approve')->assertStatus(409);
    }

    public function test_non_admin_banner_changes_require_review_without_changing_approved_legacy_banners(): void
    {
        $this->staff('advertising-manager');
        $banner = Banner::create(['title' => 'Staff campaign', 'alt' => 'Banner', 'image' => 'banners/check.jpg', 'device' => 'both', 'is_active' => true]);
        $this->assertSame('pending', $banner->fresh()->review_status);
        $this->assertFalse($banner->fresh()->is_active);
        $this->assertFalse(Banner::available()->whereKey($banner->id)->exists());
        $this->staff('moderator');
        Livewire::test(Operations::class)->call('review', 'advertising', $banner->id, 'approve')->assertHasNoErrors();
        $this->assertSame('approved', $banner->fresh()->review_status);
        $this->assertTrue($banner->fresh()->is_active);
    }

    public function test_capacities_respect_global_maximum_and_gap_and_duplicate_reservations(): void
    {
        $settings = app(OperationsSettings::class);
        $settings->max_ads_per_page = 2;
        $settings->min_listing_gap = 4;
        $settings->save();
        $capacity = new AdvertisingCapacity;
        $this->assertTrue($capacity->reserve('home-feed', 4));
        $this->assertTrue($capacity->reserve('home-feed', 4));
        $this->assertFalse($capacity->reserve('home-feed', 6));
        $this->assertTrue($capacity->reserve('home-feed', 8));
        $this->assertFalse($capacity->reserve('home-top', 0));
        $settings->max_ads_per_page = 0;
        $settings->save();
        $this->assertFalse((new AdvertisingCapacity)->reserve('home-feed', 1));
    }

    public function test_empty_search_events_are_deduplicated_and_sensitive_queries_are_ignored(): void
    {
        $this->withCookie(config('session.cookie'), str_repeat('s', 40));
        $term = 'کالای آزمایشی ناموجود یک دو سه';
        $this->get('/listings?search='.urlencode($term))->assertSuccessful();
        $this->get('/listings?search='.urlencode($term))->assertSuccessful();
        $this->assertSame(1, ProductEvent::where('kind', 'search_empty')->where('value', $term)->count());
        foreach (['name@example.com', '0912 123 4567', '۰۹۱۲ ۱۲۳ ۴۵۶۷'] as $query) {
            $this->get('/listings?search='.urlencode($query))->assertSuccessful();
            $this->assertFalse(ProductEvent::where('value', $query)->exists());
        }
        $this->assertDatabaseMissing('product_events', ['value' => request()->ip()]);
    }

    public function test_telemetry_can_be_disabled_and_old_events_are_pruned(): void
    {
        $settings = app(OperationsSettings::class);
        $settings->telemetry_enabled = false;
        $settings->save();
        $this->get('/listings?search=disabled-analytics-query')->assertSuccessful();
        $this->assertFalse(ProductEvent::where('value', 'disabled-analytics-query')->exists());
        ProductEvent::create(['kind' => 'search_empty', 'value' => 'Old term', 'fingerprint' => hash('sha256', 'expired'), 'created_at' => now()->subDays(91)]);
        Cache::forget('operations.telemetry.prune');
        $settings->telemetry_enabled = true;
        $settings->save();
        $this->get('/listings?search=new-unique-prune-term')->assertSuccessful();
        $this->assertFalse(ProductEvent::withTrashed()->where('value', 'Old term')->exists());
    }

    public function test_owner_cannot_bypass_moderation_with_republish_or_panel_status_changes(): void
    {
        $listing = Listing::where('status', 'pending')->firstOrFail();
        $stale = Listing::findOrFail($listing->id);
        $listing->moderate(false);
        $this->assertSame('rejected', $listing->fresh()->statusValue());
        $this->assertSame(__('listing::messages.status_rejected'), $listing->fresh()->panelStatusMeta()['label']);
        $stale->republish();
        $this->assertSame('pending', $listing->fresh()->statusValue());
        $stale->updateFromPanel(['title' => 'Corrected listing', 'status' => 'active']);
        $this->assertSame('pending', $listing->fresh()->statusValue());
        $this->expectException(ValidationException::class);
        $stale->markAsSold();
    }

    public function test_members_cannot_access_operations_or_mutate_moderation(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'b@b.com')->firstOrFail());
        $this->get('/admin/operations')->assertForbidden();
        $this->assertFalse(Operations::canAccess());
        Livewire::test(Operations::class)->assertForbidden();
    }
}
