<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\ListingRiskReview;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingRiskSignals;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingRiskReviewTest extends TestCase
{
    private User $seller;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::create(['name' => 'Risk fixture', 'email' => Str::uuid().'@example.test', 'password' => 'risk-fixture-password', 'status' => 'active']);
        $this->categoryId = Category::where('slug', 'electronics-phones')->firstOrFail()->id;
    }

    private function listing(array $attributes = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Risk review phone fixture', 'description' => 'Ordinary description', 'slug' => (string) Str::uuid(), 'user_id' => $this->seller->id, 'category_id' => $this->categoryId, 'price' => 100000, 'currency' => 'IRR', 'status' => 'pending'], $attributes)));
    }

    private function signals(Listing $listing): array
    {
        return Listing::riskReviewSnapshot()['items'][$listing->id]['signals'] ?? [];
    }

    public function test_same_owner_category_and_normalized_persian_title_have_bounded_evidence(): void
    {
        $first = $this->listing(['title' => 'گوشي مدل ۱۲۳']);
        $peers = [];
        for ($i = 0; $i < 7; $i++) {
            $peers[] = $this->listing(['title' => 'گوشی مدل 123'])->id;
        }
        $signals = $this->signals($first);
        $this->assertArrayHasKey('repeated_title', $signals);
        $this->assertCount(5, $signals['repeated_title']);
        $this->assertNotContains($first->id, $signals['repeated_title']);
        $this->assertEmpty(array_diff($signals['repeated_title'], $peers));
        $this->assertSame('pending', $first->fresh()->statusValue());
    }

    public function test_different_categories_and_short_or_single_word_titles_are_not_duplicate_signals(): void
    {
        $otherCategory = Category::where('slug', 'vehicles-cars')->firstOrFail()->id;
        $first = $this->listing();
        $this->listing(['category_id' => $otherCategory]);
        $this->assertArrayNotHasKey('repeated_title', $this->signals($first));
        $short = $this->listing(['title' => 'TV']);
        $this->listing(['title' => 'TV']);
        $this->assertSame([], $this->signals($short));
        $single = $this->listing(['title' => 'Smartphones']);
        $this->listing(['title' => 'Smartphones']);
        $this->assertSame([], $this->signals($single));
    }

    public function test_identical_long_content_across_accounts_is_flagged_but_short_generic_text_is_not(): void
    {
        $body = str_repeat('This is a detailed description of this specific item with matching content. ', 3);
        $first = $this->listing(['description' => $body]);
        $otherSeller = User::where('email', 'a@a.com')->firstOrFail();
        $copy = $this->listing(['description' => $body, 'user_id' => $otherSeller->id]);
        $this->assertContains($copy->id, $this->signals($first)['copied_content']);
        $this->assertArrayNotHasKey('repeated_title', $this->signals($first));
        $short = $this->listing(['title' => 'Generic item title', 'description' => 'Good item']);
        $this->listing(['title' => 'Generic item title', 'description' => 'Good item', 'user_id' => $otherSeller->id]);
        $this->assertSame([], $this->signals($short));
    }

    public function test_sensitive_and_advance_requests_are_explained_without_rejecting_or_notifying(): void
    {
        $first = $this->listing(['title' => 'درخواست آزمایشی', 'description' => 'لطفاً کد تایید را ارسال کنید. برای رزرو بیعانه واریز کنید.']);
        $signals = $this->signals($first);
        $this->assertArrayHasKey('sensitive_request', $signals);
        $this->assertArrayHasKey('advance_request', $signals);
        $this->assertSame([], $signals['sensitive_request']);
        $english = $this->listing(['title' => 'English risk fixture', 'description' => 'Please send your verification code. Pay a deposit before viewing.']);
        $this->assertArrayHasKey('sensitive_request', $this->signals($english));
        $this->assertArrayHasKey('advance_request', $this->signals($english));
        $this->assertSame('pending', $first->fresh()->statusValue());
        $this->assertSame(0, DB::table('user_notifications')->where('user_id', $this->seller->id)->count());
    }

    public function test_negated_safety_advice_and_plain_deposit_terms_are_not_request_signals(): void
    {
        $safe = $this->listing(['title' => 'Safety advice fixture', 'description' => 'Never send your verification code. Do not pay a deposit before viewing. هرگز کد تایید را ارسال نکنید. بدون پیش پرداخت برای رزرو اقدام کنید.']);
        $this->assertSame([], $this->signals($safe));
        $deposit = $this->listing(['title' => 'Rental terms fixture', 'description' => 'The rental deposit is negotiable. بیعانه قابل مذاکره است.']);
        $this->assertSame([], $this->signals($deposit));
    }

    public function test_scan_excludes_old_future_deleted_paused_scheduled_sold_and_expired_records(): void
    {
        foreach (['paused', 'scheduled', 'sold', 'expired', 'rejected'] as $status) {
            $record = $this->listing(['status' => $status, 'publish_at' => $status === 'scheduled' ? now()->addDay() : null, 'description' => 'Send your verification code']);
            $this->assertSame([], $this->signals($record));
        }
        $old = $this->listing(['created_at' => now()->subDays(31), 'description' => 'Send your verification code']);
        $future = $this->listing(['created_at' => now()->addDay(), 'description' => 'Send your verification code']);
        $deleted = $this->listing(['description' => 'Send your verification code']);
        $deleted->delete();
        $expired = $this->listing(['expires_at' => now()->subMinute(), 'description' => 'Send your verification code']);
        foreach ([$old, $future, $deleted, $expired] as $record) {
            $this->assertSame([], $this->signals($record));
        }
    }

    public function test_newest_200_limit_and_description_limit_are_real_database_bounds(): void
    {
        $first = $this->listing(['description' => 'Send your verification code']);
        $rows = [];
        for ($i = 0; $i < 201; $i++) {
            $rows[] = ['title' => 'Bounded review item '.$i, 'slug' => (string) Str::uuid(), 'description' => str_repeat('x', 8100), 'user_id' => $this->seller->id, 'category_id' => $this->categoryId, 'status' => 'active', 'currency' => 'IRR', 'created_at' => now(), 'updated_at' => now()];
        }
        Listing::query()->insert($rows);
        $snapshot = Listing::riskReviewSnapshot();
        $this->assertSame(200, $snapshot['scanned']);
        $this->assertArrayNotHasKey($first->id, $snapshot['items']);
        $this->assertSame([], $snapshot['items']);
        $bounded = $this->listing(['title' => 'Bounded sensitive item', 'description' => 'Send your verification code '.str_repeat('x', 8100)]);
        $description = Listing::riskReviewSnapshot()['items'][$bounded->id]['description'];
        $this->assertSame(8000, mb_strlen($description));
        $this->assertStringStartsWith('Send your verification code', $description);
    }

    public function test_permissions_require_active_listing_moderator_and_recheck_refresh(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $url = '/admin/listing-risk-review';
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($this->seller)->get($url)->assertForbidden();
        foreach (['support', 'advertising-manager'] as $role) {
            $this->seller->syncRoles([$role]);
            $this->get($url)->assertForbidden();
        }
        $this->seller->syncRoles(['moderator']);
        $this->get($url)->assertOk();
        $component = Livewire::test(ListingRiskReview::class);
        $this->seller->syncRoles(['support']);
        $component->call('refreshSignals')->assertForbidden();
        $this->seller->syncRoles(['moderator']);
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $this->get($url)->assertForbidden();
    }

    public function test_page_escapes_ad_text_and_analysis_refresh_tracks_edits_without_changing_decisions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        $record = $this->listing(['title' => '<script>bad()</script>', 'description' => 'Send your verification code <script>private()</script>']);
        $this->get('/admin/listing-risk-review')->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false)->assertDontSee('<script>private()</script>', false);
        $record->update(['description' => 'Ordinary text without a request']);
        $this->assertSame([], $this->signals($record));
        $this->assertSame('pending', $record->fresh()->statusValue());
        $this->get('/admin/operations')->assertOk()->assertSee('/admin/listing-risk-review', false);
    }

    public function test_normalization_canonicalizes_persian_arabic_digits_letters_and_spacing(): void
    {
        $this->assertSame(ListingRiskSignals::normalize('گوشي مدل ۱۲۳'), ListingRiskSignals::normalize('گوشی مدل 123'));
        $this->assertSame('test model 123', ListingRiskSignals::normalize('TEST---Model 123!'));
    }
}
