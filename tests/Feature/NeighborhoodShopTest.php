<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Location\Models\City;
use Modules\User\App\Models\NeighborhoodShop;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Modules\User\Filament\Admin\Resources\NeighborhoodShopResource\Pages\ListNeighborhoodShops;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class NeighborhoodShopTest extends TestCase
{
    private User $admin;

    private User $seller;

    private int $city;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->seller->syncRoles([]);
        $this->city = (int) array_key_first(City::wantedOptions());
        Profile::saveShopFor($this->seller, ['shop_enabled' => true, 'shop_name' => 'Public Selected Shop', 'shop_description' => 'Public shop introduction', 'shop_hours' => '9–18'], Profile::shopVersion($this->seller));
    }

    private function data(array $data = []): array
    {
        return array_replace(['shop_user_id' => $this->seller->id, 'city_id' => $this->city, 'neighborhood' => 'محلهٔ مرکزی', 'priority' => 100, 'is_active' => true, 'review_until' => now('Asia/Tehran')->addDays(30)->toDateString(), 'shop_version' => NeighborhoodShop::currentShopHash($this->seller->id)], $data);
    }

    private function pick(array $data = []): NeighborhoodShop
    {
        return NeighborhoodShop::saveSelection($this->admin, $this->data($data));
    }

    public function test_admin_selection_links_existing_public_shop_without_exposing_private_profile_location(): void
    {
        Profile::detailsForUser($this->seller)->forceFill(['city' => 'PRIVATE_CITY', 'phone' => 'PRIVATE_PHONE', 'bio' => 'PRIVATE_BIO'])->save();
        $record = $this->pick();
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertOk()->assertSee('Public Selected Shop')->assertSee('محلهٔ مرکزی')->assertSee(route('sellers.show', $this->seller), false)->assertDontSee('PRIVATE_CITY')->assertDontSee('PRIVATE_PHONE')->assertDontSee('PRIVATE_BIO')->assertDontSee($this->seller->email)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(__('user::neighborhoods.visible'), $record->stateLabel());
        $this->get(route('neighborhood-shops.index'))->assertOk()->assertDontSee('Public Selected Shop');
    }

    public function test_nonadmin_and_inactive_admin_cannot_manage_even_with_advertising_permissions(): void
    {
        $this->seller->syncRoles(['advertising-manager']);
        $this->actingAs($this->seller)->get('/admin/neighborhood-shops')->assertForbidden();
        try {
            NeighborhoodShop::saveSelection($this->seller, $this->data());
            $this->fail('Expected denial.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->admin->forceFill(['status' => 'suspended'])->save();
        $this->expectException(HttpException::class);
        $this->pick();
    }

    public function test_changed_shop_requires_fresh_review_and_form_snapshot_guards_approval(): void
    {
        $record = $this->pick();
        $stale = $record->formData();
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => 'Changed Public Shop'])->save();
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertDontSee('Public Selected Shop')->assertDontSee('Changed Public Shop');
        $this->assertSame(__('user::neighborhoods.needs_review'), $record->stateLabel());
        try {
            NeighborhoodShop::saveSelection($this->admin, $stale, $record->id);
            $this->fail('Expected stale shop failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shop_user_id', $e->errors());
        }
        NeighborhoodShop::saveSelection($this->admin, $record->formData(), $record->id);
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertSee('Changed Public Shop');
    }

    public function test_disabling_shop_owner_or_city_and_expiry_remove_selection_immediately(): void
    {
        $record = $this->pick();
        $url = route('neighborhood-shops.index', ['city_id' => $this->city]);
        $profile = Profile::detailsForUser($this->seller);
        $profile->forceFill(['shop_enabled' => false])->save();
        $this->get($url)->assertDontSee('Public Selected Shop');
        $profile->forceFill(['shop_enabled' => true])->save();
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $this->get($url)->assertDontSee('Public Selected Shop');
        $this->seller->forceFill(['status' => 'active'])->save();
        City::findOrFail($this->city)->update(['is_active' => false]);
        $this->get($url)->assertSessionHasErrors('city_id');
        City::findOrFail($this->city)->update(['is_active' => true]);
        $this->travelTo($record->expires_at);
        $this->get($url)->assertDontSee('Public Selected Shop');
        $this->assertSame(__('user::neighborhoods.expired'), $record->stateLabel());
    }

    public function test_review_date_uses_inclusive_tehran_day_and_rejects_out_of_bounds(): void
    {
        $record = $this->pick(['review_until' => '2026-12-20']);
        $this->assertSame('2026-12-20 20:30:00', $record->expires_at->utc()->format('Y-m-d H:i:s'));
        foreach ([['priority' => -1], ['neighborhood' => '   '], ['review_until' => '2020-01-01'], ['review_until' => now()->addYears(2)->toDateString()], ['city_id' => 9999999]] as $invalid) {
            try {
                NeighborhoodShop::saveSelection($this->admin, [...$record->formData(), ...$invalid], $record->id);
                $this->fail('Expected validation failure.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_duplicate_is_prevented_and_soft_removal_allows_new_selection(): void
    {
        $record = $this->pick();
        try {
            $this->pick();
            $this->fail('Expected duplicate rejection.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('shop_user_id', $e->errors());
        }
        $record->changeFor($this->admin, 'remove', $record->version());
        $this->assertTrue(NeighborhoodShop::withTrashed()->findOrFail($record->id)->trashed());
        $this->assertNotSame($record->id, $this->pick()->id);
    }

    public function test_priority_search_and_city_filters_are_applied_before_pagination(): void
    {
        $this->pick(['neighborhood' => 'كوی آفتاب']);
        $another = User::factory()->create(['status' => 'active']);
        Profile::saveShopFor($another, ['shop_enabled' => true, 'shop_name' => 'First in order'], Profile::shopVersion($another));
        NeighborhoodShop::saveSelection($this->admin, $this->data(['shop_user_id' => $another->id, 'shop_version' => NeighborhoodShop::currentShopHash($another->id), 'priority' => 1]));
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertSeeInOrder(['First in order', 'Public Selected Shop']);
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city, 'search' => 'کوی افتاب']))->assertSee('Public Selected Shop')->assertDontSee('First in order');
        $otherCity = (int) array_keys(City::wantedOptions())[1];
        $this->get(route('neighborhood-shops.index', ['city_id' => $otherCity]))->assertDontSee('Public Selected Shop');
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city, 'search' => '%%']))->assertDontSee('Public Selected Shop');
    }

    public function test_stale_edit_and_pause_actions_are_version_guarded(): void
    {
        $record = $this->pick();
        $stale = $record->formData();
        $new = NeighborhoodShop::saveSelection($this->admin, [...$stale, 'priority' => 2], $record->id);
        $new->changeFor($this->admin, 'pause', $new->version());
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertDontSee('Public Selected Shop');
        $this->expectException(HttpException::class);
        NeighborhoodShop::saveSelection($this->admin, $stale, $record->id);
    }

    public function test_real_filament_creation_review_and_pause_use_guarded_model_actions(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListNeighborhoodShops::class)->callAction('add', $this->data())->assertHasNoErrors();
        $record = NeighborhoodShop::firstOrFail();
        Livewire::test(ListNeighborhoodShops::class)->callTableAction('review', $record, [...$record->formData(), 'priority' => 7])->assertHasNoErrors();
        $this->assertSame(7, $record->fresh()->priority);
        Livewire::test(ListNeighborhoodShops::class)->callTableAction('pause', $record, ['version' => $record->fresh()->version()])->assertHasNoErrors();
        $this->assertFalse($record->fresh()->is_active);
    }

    public function test_city_catalog_cap_counts_paused_records_and_soft_removal_frees_capacity(): void
    {
        $owners = User::factory()->count(200)->create(['status' => 'active']);
        $rows = $owners->map(fn (User $user): array => ['shop_user_id' => $user->id, 'city_id' => $this->city, 'neighborhood' => 'Existing area', 'priority' => 100, 'is_active' => false, 'shop_hash' => str_repeat('a', 64), 'expires_at' => now()->subDay(), 'created_at' => now(), 'updated_at' => now()])->all();
        DB::table('neighborhood_shops')->insert($rows);
        try {
            $this->pick();
            $this->fail('Expected city cap failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('city_id', $e->errors());
        }
        $record = NeighborhoodShop::firstOrFail();
        $record->changeFor($this->admin, 'remove', $record->version());
        $this->assertNotNull($this->pick()->id);
        $this->assertSame(200, NeighborhoodShop::where('city_id', $this->city)->count());
    }

    public function test_public_text_is_escaped_and_invalid_filters_fail_validation(): void
    {
        Profile::detailsForUser($this->seller)->forceFill(['shop_name' => '<script>shopTest()</script>'])->save();
        $this->pick(['neighborhood' => '<img src=x onerror=shopTest()>']);
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city]))->assertSee('&lt;script&gt;shopTest()&lt;/script&gt;', false)->assertDontSee('<script>shopTest()</script>', false)->assertDontSee('<img src=x onerror=shopTest()>', false);
        $this->get(route('neighborhood-shops.index', ['city_id' => $this->city, 'search' => ['bad']]))->assertSessionHasErrors('search');
        $this->get(route('neighborhood-shops.index', ['search' => 'store']))->assertSessionHasErrors('city_id');
    }
}
