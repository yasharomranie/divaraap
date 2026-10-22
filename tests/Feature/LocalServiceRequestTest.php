<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\Operations;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Models\ConversationBlock;
use Modules\Listing\Filament\Admin\Resources\WantedRequestResource\Pages\ListWantedRequests;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\WantedRequest;
use Modules\Listing\Models\WantedResponse;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class LocalServiceRequestTest extends TestCase
{
    private User $buyer;

    private User $provider;

    private User $admin;

    private int $category;

    private int $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::factory()->create(['status' => 'active']);
        $this->provider = User::factory()->create(['status' => 'active']);
        $this->buyer->syncRoles([]);
        $this->provider->syncRoles([]);
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->category = (int) array_key_first(WantedRequest::categoryOptions('service'));
        $this->city = City::query()->active()->firstOrFail()->id;
    }

    private function input(array $data = []): array
    {
        return array_replace(['request_key' => (string) Str::uuid(), 'title' => 'تعمیر وسایل خانه در تهران', 'description' => 'برای تعمیر وسایل خانه به متخصص نیاز دارم.', 'category_id' => $this->category, 'city_id' => $this->city, 'budget' => 20000000, 'preferred_time' => 'آخر هفته، عصرها'], $data);
    }

    private function service(array $data = [], bool $publish = true): WantedRequest
    {
        $record = WantedRequest::submit($this->buyer, $this->input($data), 'service');
        if ($publish) {
            $this->actingAs($this->admin);
            $record->moderate(true);
        }

        return $record;
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'خدمات تعمیر خانه', 'description' => 'تعمیرات تخصصی وسایل خانه', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->provider->id, 'category_id' => $this->category, 'city' => City::findOrFail($this->city)->name, 'country' => City::findOrFail($this->city)->country->name, 'currency' => 'IRR', 'price' => 1000000, 'expires_at' => now()->addDays(5)], $data)));
    }

    public function test_service_validation_requires_active_city_and_service_category(): void
    {
        $this->actingAs($this->buyer);
        foreach ([['city_id' => null], ['category_id' => array_key_first(WantedRequest::categoryOptions())], ['preferred_time' => str_repeat('a', 121)], ['budget' => 1.5]] as $invalid) {
            $this->post(route('service-requests.store'), $this->input($invalid))->assertSessionHasErrors(array_keys($invalid));
        }
        $this->post(route('wanted.store'), $this->input())->assertSessionHasErrors('category_id');
        City::whereKey($this->city)->update(['is_active' => false]);
        $this->post(route('service-requests.store'), $this->input())->assertSessionHasErrors('city_id');
    }

    public function test_server_kind_and_review_state_cannot_be_spoofed_and_retry_is_idempotent(): void
    {
        $input = $this->input(['kind' => 'product', 'state' => 'open', 'user_id' => $this->provider->id]);
        $this->actingAs($this->buyer)->post(route('service-requests.store'), $input)->assertRedirect();
        $record = WantedRequest::where('request_key', $input['request_key'])->firstOrFail();
        $this->assertSame('service', $record->kind);
        $this->assertSame('pending', $record->state);
        $this->assertSame($this->buyer->id, $record->user_id);
        $this->assertSame('آخر هفته، عصرها', $record->preferred_time);
        $this->assertSame($record->id, WantedRequest::submit($this->buyer, $input, 'service')->id);
        $this->assertSame(30, (int) round(now()->diffInDays($record->expires_at)));
        $this->expectException(ValidationException::class);
        WantedRequest::submit($this->buyer, [...$input, 'category_id' => array_key_first(WantedRequest::categoryOptions())]);
    }

    public function test_directories_and_owner_routes_are_isolated_and_private(): void
    {
        $service = $this->service();
        $product = WantedRequest::submit($this->buyer, $this->input(['category_id' => array_key_first(WantedRequest::categoryOptions())]));
        $product->moderate(true);
        $this->get(route('service-requests.index'))->assertOk()->assertSee('data-wanted="'.$service->id.'"', false)->assertDontSee('data-wanted="'.$product->id.'"', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('wanted.index'))->assertOk()->assertDontSee('data-wanted="'.$service->id.'"', false);
        $this->get(route('wanted.show', $service))->assertNotFound();
        $this->get(route('service-requests.show', $product))->assertNotFound();
        $this->actingAs($this->buyer)->get(route('wanted.own', $service))->assertNotFound();
        $this->get(route('service-requests.own', $product))->assertNotFound();
        $this->get(route('service-requests.mine'))->assertOk()->assertSee('data-wanted="'.$service->id.'"', false)->assertDontSee('data-wanted="'.$product->id.'"', false)->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->provider)->get(route('service-requests.own', $service))->assertNotFound();
    }

    public function test_optional_time_is_escaped_and_not_a_booking_and_product_drops_service_fields(): void
    {
        $service = $this->service(['preferred_time' => '<script>alert(1)</script>']);
        $this->get(route('service-requests.show', $service))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs($this->buyer)->get(route('service-requests.create'))->assertOk()->assertSee(__('listing::services.time_hint'))->assertSee('name="preferred_time"', false);
        $this->get(route('wanted.create'))->assertOk()->assertDontSee('name="preferred_time"', false);
        $product = WantedRequest::submit($this->buyer, $this->input(['kind' => 'service', 'category_id' => array_key_first(WantedRequest::categoryOptions())]));
        $this->assertSame('product', $product->kind);
        $this->assertNull($product->preferred_time);
    }

    public function test_editing_time_preserves_kind_and_expiry_and_requires_fresh_review(): void
    {
        $service = $this->service();
        $expiry = $service->expires_at->toIso8601String();
        $this->actingAs($this->buyer)->put(route('service-requests.update', $service), $this->input(['revision' => 1, 'preferred_time' => 'صبح دوشنبه', 'kind' => 'product']))->assertRedirect(route('service-requests.own', $service));
        $service->refresh();
        $this->assertSame('service', $service->kind);
        $this->assertSame('pending', $service->state);
        $this->assertSame(2, $service->revision);
        $this->assertSame('صبح دوشنبه', $service->preferred_time);
        $this->assertSame($expiry, $service->expires_at->toIso8601String());
        $this->get(route('service-requests.show', $service))->assertNotFound();
        $this->actingAs($this->admin);
        Livewire::test(Operations::class)->call('review', 'wanted', $service->id, 'approve', 1)->assertStatus(409);
        Livewire::test(Operations::class)->call('review', 'wanted', $service->id, 'approve', 2)->assertHasNoErrors();
        $this->assertSame('open', $service->fresh()->state);
    }

    public function test_only_owned_live_service_in_exact_category_and_city_can_be_suggested(): void
    {
        $record = $this->service();
        $good = $this->listing();
        $bad = [
            $this->listing(['city' => 'Shiraz']),
            $this->listing(['country' => 'ZZ']),
            $this->listing(['user_id' => $this->buyer->id]),
            $this->listing(['category_id' => array_key_first(WantedRequest::categoryOptions())]),
            $this->listing(['expires_at' => now()->subMinute()]),
            $this->listing(['status' => 'pending']),
        ];
        $this->assertSame([$good->id], array_keys($record->suggestionOptions($this->provider)));
        $this->actingAs($this->provider);
        foreach ($bad as $listing) {
            $this->post(route('service-requests.suggest', $record), ['listing_id' => $listing->id, 'revision' => 1])->assertNotFound();
        }
        $this->post(route('wanted.suggest', $record), ['listing_id' => $good->id, 'revision' => 1])->assertNotFound();
        $this->post(route('service-requests.suggest', $record), ['listing_id' => $good->id, 'revision' => 1])->assertRedirect();
        $this->post(route('service-requests.suggest', $record), ['listing_id' => $good->id, 'revision' => 1])->assertRedirect();
        $this->assertSame(1, WantedResponse::where('wanted_request_id', $record->id)->count());
    }

    public function test_private_suggestion_reuses_existing_conversation_and_closing_is_final(): void
    {
        $record = $this->service();
        $listing = $this->listing();
        $response = WantedResponse::submit($this->provider, $record->id, $listing->id, 1, 'service');
        $this->actingAs($this->provider)->get(route('service-requests.show', $record))->assertDontSee('data-wanted-response', false);
        $this->actingAs($this->buyer)->get(route('service-requests.own', $record))->assertSee('data-wanted-response="'.$response->id.'"', false);
        $this->post(route('conversations.start', $listing))->assertRedirect();
        $this->post(route('conversations.start', $listing))->assertRedirect();
        $this->assertSame(1, Conversation::where('listing_id', $listing->id)->where('buyer_id', $this->buyer->id)->count());
        $this->post(route('service-requests.close', $record), ['state' => 'fulfilled', 'revision' => 1])->assertRedirect();
        $this->assertSame(__('listing::services.fulfilled'), $record->fresh()->stateLabel());
        $this->get(route('service-requests.show', $record))->assertNotFound();
        $this->put(route('service-requests.update', $record), $this->input(['revision' => 1]))->assertStatus(409);
    }

    public function test_blocking_and_changed_revision_prevent_provider_response(): void
    {
        $record = $this->service();
        $listing = $this->listing();
        ConversationBlock::setFor($this->buyer, $this->provider->id, true);
        $this->assertSame([], $record->suggestionOptions($this->provider));
        $this->actingAs($this->provider)->post(route('service-requests.suggest', $record), ['listing_id' => $listing->id, 'revision' => 1])->assertSessionHasErrors('message');
        ConversationBlock::setFor($this->buyer, $this->provider->id, false);
        WantedResponse::submit($this->provider, $record->id, $listing->id, 1, 'service');
        $record->updateFor($this->buyer, $this->input(['preferred_time' => 'صبح‌ها']), 1);
        $this->assertSame(0, WantedResponse::forOwner($record, $this->buyer)->total());
        $this->actingAs($this->admin);
        $record->moderate(true);
        $this->actingAs($this->provider)->post(route('service-requests.suggest', $record), ['listing_id' => $listing->id, 'revision' => 1])->assertStatus(409);
        $this->post(route('service-requests.suggest', $record), ['listing_id' => $listing->id, 'revision' => 2])->assertRedirect();
        $this->assertSame(1, WantedResponse::forOwner($record, $this->buyer)->total());
    }

    public function test_shared_twenty_request_limit_cannot_be_bypassed_by_type(): void
    {
        for ($i = 0; $i < 19; $i++) {
            WantedRequest::submit($this->buyer, $this->input(['category_id' => array_key_first(WantedRequest::categoryOptions())]));
        }
        $this->service([], false);
        $this->actingAs($this->buyer)->post(route('service-requests.store'), $this->input())->assertSessionHasErrors('title');
        $this->post(route('wanted.store'), $this->input(['category_id' => array_key_first(WantedRequest::categoryOptions())]))->assertSessionHasErrors('title');
        $this->assertSame(20, WantedRequest::where('user_id', $this->buyer->id)->count());
    }

    public function test_existing_moderation_resource_reviews_service_and_retracts_with_reason(): void
    {
        $record = $this->service([], false);
        $this->actingAs($this->provider)->get('/admin/wanted-requests')->assertForbidden();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListWantedRequests::class)->assertCanSeeTableRecords([$record])->callTableAction('approve', $record, ['revision' => 1])->assertHasNoErrors();
        $this->assertSame('open', $record->fresh()->state);
        Livewire::test(ListWantedRequests::class)->callTableAction('retract', $record, ['revision' => 1, 'note' => 'نیاز به اصلاح محتوا'])->assertHasNoErrors();
        $this->assertSame('rejected', $record->fresh()->state);
        $this->get(route('service-requests.show', $record))->assertNotFound();
    }

    public function test_expiry_inactive_accounts_and_category_changes_fail_closed(): void
    {
        $record = $this->service();
        DB::table('wanted_requests')->where('id', $record->id)->update(['expires_at' => now()->subSecond()]);
        $this->get(route('service-requests.show', $record))->assertNotFound();
        $record = $this->service();
        $this->buyer->forceFill(['status' => 'suspended'])->save();
        $this->get(route('service-requests.show', $record))->assertNotFound();
        $this->actingAs($this->buyer)->post(route('service-requests.store'), $this->input())->assertForbidden();
        $this->buyer->forceFill(['status' => 'active'])->save();
        Category::whereKey($this->category)->update(['is_active' => false]);
        $this->get(route('service-requests.show', $record))->assertNotFound();
    }
}
