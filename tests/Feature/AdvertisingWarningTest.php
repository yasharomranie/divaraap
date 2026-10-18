<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Pages\AdvertisingWarnings;
use Modules\Advertising\Models\AdvertisingWarning;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\Campaign;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingWarningTest extends TestCase
{
    private function admin(): User
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $user = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($user);
        Storage::fake('public');

        return $user;
    }

    private function banner(array $data = []): Banner
    {
        return Banner::create(array_replace(['title' => 'Health banner', 'image' => 'banners/health.jpg', 'alt' => 'Banner', 'device' => 'both', 'is_active' => true], $data));
    }

    private function notificationCount(User $user): int
    {
        return UserNotification::where('user_id', $user->id)->where('type', UserNotification::TYPE_ADVERTISING)->count();
    }

    public function test_persistent_issue_notifies_once_and_recurrence_notifies_again(): void
    {
        $user = $this->admin();
        $banner = $this->banner();
        $this->assertSame(1, AdvertisingWarning::scan($user));
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $this->assertSame(1, $this->notificationCount($user));
        $this->assertSame('image_missing', AdvertisingWarning::forUser($user->id)->sole()->code);
        Storage::disk('public')->put($banner->image, 'present');
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $this->assertCount(0, AdvertisingWarning::forUser($user->id));
        $this->assertNotNull(AdvertisingWarning::firstOrFail()->resolved_at);
        Storage::disk('public')->delete($banner->image);
        $this->assertSame(1, AdvertisingWarning::scan($user));
        $this->assertSame(2, $this->notificationCount($user));
        $this->assertSame(1, AdvertisingWarning::count());
    }

    public function test_mobile_fallback_and_optional_link_are_valid_and_invalid_links_do_not_leak(): void
    {
        $user = $this->admin();
        $banner = $this->banner();
        Storage::disk('public')->put($banner->image, 'present');
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $banner->update(['mobile_image' => 'missing-mobile.jpg', 'url' => 'javascript:secret-token']);
        $this->assertSame(2, AdvertisingWarning::scan($user));
        $this->assertEqualsCanonicalizing(['mobile_image_missing', 'link_invalid'], AdvertisingWarning::forUser($user->id)->pluck('code')->all());
        foreach (UserNotification::where('type', UserNotification::TYPE_ADVERTISING)->get() as $notification) {
            $this->assertStringNotContainsString('secret-token', $notification->body);
            $this->assertSame(url('/admin/banners/'.$banner->id.'/edit'), $notification->action_url);
        }
        $banner->update(['mobile_image' => null, 'url' => 'https://example.com']);
        AdvertisingWarning::scan($user);
        $this->assertCount(0, AdvertisingWarning::forUser($user->id));
    }

    public function test_campaign_deadline_warning_transitions_to_ended_without_repeated_notifications(): void
    {
        $user = $this->admin();
        $campaign = Campaign::create(['title' => 'Soon', 'is_active' => true, 'ends_at' => now()->addHour()]);
        Campaign::create(['title' => 'Later', 'is_active' => true, 'ends_at' => now()->addDays(2)]);
        $this->assertSame(1, AdvertisingWarning::scan($user));
        $this->assertSame('campaign_ending', AdvertisingWarning::forUser($user->id)->sole()->code);
        $this->travel(2)->hours();
        $this->assertSame(1, AdvertisingWarning::scan($user));
        $this->assertSame('campaign_ended', AdvertisingWarning::forUser($user->id)->sole()->code);
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $this->assertSame(2, $this->notificationCount($user));
        $campaign->update(['is_active' => false]);
        AdvertisingWarning::scan($user);
        $this->assertCount(0, AdvertisingWarning::forUser($user->id));
    }

    public function test_inactive_unapproved_deleted_and_expired_banners_are_not_alerted(): void
    {
        $user = $this->admin();
        $this->banner(['is_active' => false]);
        $this->banner(['review_status' => 'pending']);
        $this->banner(['ends_at' => now()->subMinute()]);
        $this->banner()->delete();
        $campaign = Campaign::create(['title' => 'Paused', 'is_active' => false]);
        $this->banner(['campaign_id' => $campaign->id]);
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $this->assertSame(0, $this->notificationCount($user));
    }

    public function test_warnings_are_isolated_per_authorized_viewer_and_page_rechecks_are_deduplicated(): void
    {
        $admin = $this->admin();
        $this->banner(['title' => '"><script>alert(1)</script>']);
        Livewire::test(AdvertisingWarnings::class)->assertSee(__('advertising::warnings.image_missing'))->assertDontSee('<script>alert(1)</script>', false)->call('scan')->assertSuccessful();
        $this->assertSame(1, $this->notificationCount($admin));
        $manager = User::factory()->create(['status' => 'active']);
        $manager->assignRole('advertising-manager');
        $this->actingAs($manager);
        $this->assertCount(0, AdvertisingWarning::forUser($manager->id));
        $this->get(AdvertisingWarnings::getUrl())->assertSuccessful();
        $this->assertSame(1, $this->notificationCount($manager));
        $this->assertSame(1, $this->notificationCount($admin));
    }

    public function test_deleted_subject_resolves_and_read_notification_does_not_trigger_duplicate(): void
    {
        $user = $this->admin();
        $banner = $this->banner();
        AdvertisingWarning::scan($user);
        UserNotification::where('user_id', $user->id)->where('type', UserNotification::TYPE_ADVERTISING)->firstOrFail()->markRead();
        $this->assertSame(0, AdvertisingWarning::scan($user));
        $banner->delete();
        AdvertisingWarning::scan($user);
        $this->assertCount(0, AdvertisingWarning::forUser($user->id));
        $this->assertSame(1, $this->notificationCount($user));
    }

    public function test_unprivileged_and_inactive_users_cannot_access_warnings(): void
    {
        $this->admin();
        $user = User::factory()->create(['status' => 'active']);
        $this->actingAs($user);
        $this->get(AdvertisingWarnings::getUrl())->assertForbidden();
        $user->assignRole('advertising-manager');
        $user->update(['status' => 'suspended']);
        $this->get(AdvertisingWarnings::getUrl())->assertForbidden();
    }
}
