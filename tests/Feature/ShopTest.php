<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\Profile;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ShopTest extends TestCase
{
    private function owner(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    private function payload(User $user, array $overrides = []): array
    {
        return array_replace(['shop_enabled' => '1', 'shop_name' => 'فروشگاه آزمایشی', 'shop_description' => 'معرفی فروشگاه', 'shop_hours' => '۹ تا ۱۸', 'version' => Profile::shopVersion($user)], $overrides);
    }

    public function test_owner_can_enable_edit_and_disable_shop_without_changing_identity(): void
    {
        $user = $this->owner();
        $name = $user->name;
        $this->actingAs($user)->get(route('shop.edit'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->put(route('shop.update'), $this->payload($user))->assertRedirect(route('shop.edit'));
        $this->get(route('sellers.show', $user))->assertOk()->assertSee('فروشگاه آزمایشی')->assertSee('معرفی فروشگاه')->assertSee('۹ تا ۱۸');
        $this->assertSame($name, $user->fresh()->name);
        $this->put(route('shop.update'), $this->payload($user, ['shop_enabled' => '0']))->assertRedirect();
        $this->get(route('sellers.show', $user))->assertOk()->assertDontSee('فروشگاه آزمایشی')->assertDontSee('معرفی فروشگاه');
        $this->assertSame('فروشگاه آزمایشی', Profile::shopFor($user)['shop_name']);
    }

    public function test_guests_cannot_manage_and_other_account_cannot_overwrite_owner_shop(): void
    {
        $user = $this->owner();
        $data = $this->payload($user);
        $this->get(route('shop.edit'))->assertRedirect(route('login'));
        $this->put(route('shop.update'), $data)->assertRedirect(route('login'));
        Profile::saveShopFor($user, $data, $data['version']);
        $other = User::whereKeyNot($user->id)->firstOrFail();
        $otherData = $this->payload($other, ['shop_name' => 'Other shop', 'user_id' => $user->id]);
        $this->actingAs($other)->put(route('shop.update'), $otherData)->assertRedirect();
        $this->assertSame('فروشگاه آزمایشی', Profile::shopFor($user)['shop_name']);
        $this->assertSame('Other shop', Profile::shopFor($other)['shop_name']);
    }

    public function test_validation_stale_window_and_suspended_account_are_rejected(): void
    {
        $user = $this->owner();
        $data = $this->payload($user);
        $this->actingAs($user)->put(route('shop.update'), array_replace($data, ['shop_name' => '']))->assertSessionHasErrors('shop_name');
        $this->put(route('shop.update'), array_replace($data, ['shop_description' => ['bad']]))->assertSessionHasErrors('shop_description');
        $this->put(route('shop.update'), array_replace($data, ['shop_hours' => str_repeat('x', 161)]))->assertSessionHasErrors('shop_hours');
        $this->put(route('shop.update'), $data)->assertRedirect();
        $this->put(route('shop.update'), array_replace($data, ['shop_name' => 'Stale']))->assertSessionHasErrors('version');
        $this->assertSame('فروشگاه آزمایشی', Profile::shopFor($user)['shop_name']);
        $user->forceFill(['status' => 'suspended'])->save();
        $this->put(route('shop.update'), $this->payload($user))->assertForbidden();
        $this->get(route('sellers.show', $user))->assertNotFound();
    }

    public function test_public_shop_escapes_text_and_never_exposes_private_profile_fields(): void
    {
        $user = $this->owner();
        Profile::savePhoneForUser($user, '09123456789');
        $data = $this->payload($user, ['shop_name' => '<script>alert(1)</script>']);
        Profile::saveShopFor($user, $data, $data['version']);
        $this->get(route('sellers.show', $user))->assertOk()->assertSee($data['shop_name'])->assertDontSee($data['shop_name'], false)->assertDontSee('09123456789')->assertDontSee($user->email);
    }

    public function test_shop_search_is_owner_scoped_and_excludes_non_public_and_expired_listings(): void
    {
        $user = $this->owner();
        $category = Category::firstOrFail();
        $make = fn (string $title, string $status, int $owner, $expires = null) => Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'Unique shop fixture', 'slug' => (string) Str::uuid(), 'price' => 100, 'currency' => 'IRR', 'category_id' => $category->id, 'user_id' => $owner, 'status' => $status, 'expires_at' => $expires]));
        $visible = $make('Storefixture visible', 'active', $user->id);
        $make('Storefixture hidden', 'pending', $user->id);
        $make('Storefixture paused', 'paused', $user->id);
        $make('Storefixture expired', 'active', $user->id, now()->subDay());
        $make('Storefixture other', 'active', User::whereKeyNot($user->id)->firstOrFail()->id);
        $response = $this->get(route('sellers.show', $user).'?search=Storefixture');
        $response->assertOk()->assertSee($visible->title)->assertDontSee('Storefixture hidden')->assertDontSee('Storefixture paused')->assertDontSee('Storefixture expired')->assertDontSee('Storefixture other');
        $response->assertViewHas('listings', fn ($listings) => $listings->total() === 1);
        $this->get(route('sellers.show', $user).'?search[]=bad')->assertSessionHasErrors('search');
    }
}
