<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\FavoriteCollection;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ShortlistSharingTest extends TestCase
{
    private User $owner;

    private User $seller;

    private FavoriteCollection $collection;

    private array $listings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->owner->syncRoles([]);
        $this->seller->syncRoles([]);
        $this->collection = FavoriteCollection::saveFor($this->owner, 'PRIVATE COLLECTION NAME')->refresh();
        $this->listings = [$this->listing('First public choice'), $this->listing('Second public choice', Category::where('name', 'Cars')->firstOrFail()->id)];
        foreach ($this->listings as $listing) {
            FavoriteListing::rememberFor($this->owner, $listing->id);
            FavoriteListing::moveFor($this->owner, $listing->id, $this->collection->id);
        }
    }

    private function listing(string $title = 'Another choice', ?int $category = null): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'Public listing details', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => $category ?? Category::where('name', 'Phones')->firstOrFail()->id, 'currency' => 'IRR', 'price' => 10000000]));
    }

    private function action(string $action = 'create', array $extra = []): array
    {
        $this->collection->refresh();

        return array_replace(['action' => $action, 'share_revision' => $this->collection->share_revision, 'consent' => '1', 'signature' => $this->collection->sharingPreview($this->owner)['signature']], $extra);
    }

    private function share(): string
    {
        $this->collection->changeSharingFor($this->owner, $this->action());

        return $this->collection->shortlistUrl();
    }

    public function test_owner_explicitly_shares_existing_collection_and_preserves_favorites(): void
    {
        $this->get(route('shortlists.manage', $this->collection))->assertRedirect(route('login'));
        $this->actingAs($this->owner)->get(route('shortlists.manage', $this->collection))->assertOk()->assertSee('First public choice');
        $this->post(route('shortlists.change', $this->collection), $this->action())->assertRedirect(route('shortlists.manage', $this->collection));
        $this->collection->refresh();
        $this->assertTrue($this->collection->share_enabled);
        $this->assertSame(30, (int) round(now()->diffInDays($this->collection->share_expires_at)));
        $this->assertSame(2, FavoriteListing::where('user_id', $this->owner->id)->count());
        $this->assertSame('PRIVATE COLLECTION NAME', $this->collection->name);
        $this->assertSame($this->listings[0]->id, $this->collection->share_ids[0]);
    }

    public function test_consent_and_preview_revision_are_required_and_posted_ids_are_ignored(): void
    {
        $this->actingAs($this->owner)->post(route('shortlists.change', $this->collection), $this->action(extra: ['consent' => '0']))->assertSessionHasErrors('consent');
        $this->post(route('shortlists.change', $this->collection), $this->action(extra: ['signature' => str_repeat('a', 64)]))->assertSessionHasErrors('signature');
        $this->post(route('shortlists.change', $this->collection), $this->action(extra: ['listing_ids' => [999], 'user_id' => $this->seller->id]))->assertRedirect();
        $this->assertSame(array_map(fn ($listing) => $listing->id, $this->listings), $this->collection->fresh()->share_ids);
        $this->post(route('shortlists.change', $this->collection), ['action' => 'create', 'share_revision' => 0, 'consent' => '1', 'signature' => $this->action()['signature']])->assertStatus(409);
    }

    public function test_public_shortlist_is_cross_category_read_only_and_hides_collection_owner(): void
    {
        $url = $this->share();
        $response = $this->withSession(['listing_comparison' => [123]])->get($url)->assertOk()->assertSee('First public choice')->assertSee('Second public choice')->assertDontSee($this->collection->name)->assertDontSee($this->owner->email)->assertDontSee($this->seller->email)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer')->assertSessionHas('listing_comparison', [123]);
        $response->assertViewMissing('record');
        $this->assertSame(2, FavoriteListing::where('user_id', $this->owner->id)->count());
        $this->assertNotSame($this->collection->share_token, DB::table('favorite_collections')->where('id', $this->collection->id)->value('share_token'));
        $this->assertArrayNotHasKey('share_token', $this->collection->toArray());
    }

    public function test_new_items_require_fresh_approval_and_removed_members_disappear(): void
    {
        $url = $this->share();
        $third = $this->listing('Newly added choice');
        FavoriteListing::rememberFor($this->owner, $third->id);
        FavoriteListing::moveFor($this->owner, $third->id, $this->collection->id);
        $this->get($url)->assertDontSee('Newly added choice');
        FavoriteListing::moveFor($this->owner, $this->listings[0]->id, null);
        $this->get($url)->assertOk()->assertDontSee('First public choice')->assertSee(__('favorite::sharing.missing'));
        $this->collection->changeSharingFor($this->owner, $this->action('refresh'));
        $this->get($url)->assertNotFound();
        $this->get($this->collection->shortlistUrl())->assertOk()->assertSee('Newly added choice')->assertDontSee('First public choice');
    }

    public function test_membership_change_between_preview_and_approval_is_rejected(): void
    {
        $input = $this->action();
        FavoriteListing::moveFor($this->owner, $this->listings[0]->id, null);
        $this->actingAs($this->owner)->post(route('shortlists.change', $this->collection), $input)->assertSessionHasErrors('signature');
        $this->assertFalse($this->collection->fresh()->share_enabled);
    }

    public function test_rotate_revoke_expiry_and_clear_do_not_delete_private_collection(): void
    {
        $old = $this->share();
        $this->collection->changeSharingFor($this->owner, $this->action('revoke'));
        $this->get($old)->assertNotFound();
        $this->collection->changeSharingFor($this->owner, $this->action('rotate'));
        $this->get($old)->assertNotFound();
        $url = $this->collection->shortlistUrl();
        $this->get($url)->assertOk();
        $this->travel(31)->days();
        $this->get($url)->assertNotFound();
        $this->collection->changeSharingFor($this->owner, $this->action('rotate'));
        $this->get($this->collection->shortlistUrl())->assertOk();
        $this->collection->changeSharingFor($this->owner, $this->action('clear'));
        $this->assertNull($this->collection->share_token);
        $this->assertNull($this->collection->share_ids);
        $this->assertNotNull(FavoriteCollection::find($this->collection->id));
        $this->assertSame(2, FavoriteListing::where('user_id', $this->owner->id)->count());
        $this->share();
    }

    public function test_owner_isolation_and_inactive_or_deleted_collections_fail_closed(): void
    {
        $url = $this->share();
        $this->actingAs($this->seller)->get(route('shortlists.manage', $this->collection))->assertNotFound();
        $this->post(route('shortlists.change', $this->collection), $this->action('revoke'))->assertNotFound();
        $this->owner->forceFill(['status' => 'suspended'])->save();
        $this->get($url)->assertNotFound();
        $this->actingAs($this->owner)->get(route('shortlists.manage', $this->collection))->assertForbidden();
        $this->owner->forceFill(['status' => 'active'])->save();
        FavoriteCollection::removeFor($this->owner, $this->collection->id);
        $this->get($url)->assertNotFound();
    }

    public function test_publication_seller_and_category_eligibility_rechecked_without_stale_text(): void
    {
        $url = $this->share();
        $this->listings[0]->forceFill(['title' => 'New public title', 'price' => 20000000])->save();
        $this->get($url)->assertSee('New public title')->assertDontSee('First public choice');
        $this->listings[0]->forceFill(['status' => 'sold'])->save();
        $this->get($url)->assertDontSee('New public title')->assertSee('Second public choice');
        Category::findOrFail($this->listings[1]->category_id)->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
        Category::findOrFail($this->listings[1]->category_id)->update(['is_active' => true]);
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $this->get($url)->assertNotFound();
    }

    public function test_one_to_fifty_member_bound_is_explicit_not_silently_truncated(): void
    {
        for ($i = 0; $i < 49; $i++) {
            $listing = $this->listing();
            FavoriteListing::rememberFor($this->owner, $listing->id);
            FavoriteListing::moveFor($this->owner, $listing->id, $this->collection->id);
        }
        $this->assertFalse($this->collection->sharingPreview($this->owner)['valid']);
        $this->actingAs($this->owner)->get(route('shortlists.manage', $this->collection))->assertSee(__('favorite::sharing.empty_selection'));
        $this->post(route('shortlists.change', $this->collection), $this->action())->assertSessionHasErrors('signature');
        FavoriteListing::where('user_id', $this->owner->id)->delete();
        $this->assertFalse($this->collection->sharingPreview($this->owner)['valid']);
    }

    public function test_shared_pagination_and_escaped_public_titles_leave_private_collections_untouched(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $listing = $this->listing('Extra choice '.$i);
            FavoriteListing::rememberFor($this->owner, $listing->id);
            FavoriteListing::moveFor($this->owner, $listing->id, $this->collection->id);
        }
        $this->listings[0]->forceFill(['title' => '<script>shortlistTest()</script>'])->save();
        $url = $this->share();
        $this->get($url)->assertOk()->assertViewHas('items', fn ($items) => $items->count() === 12 && $items->total() === 13)->assertSee('&lt;script&gt;shortlistTest()&lt;/script&gt;', false)->assertDontSee('<script>shortlistTest()</script>', false);
        $this->get($url.'?page=2')->assertViewHas('items', fn ($items) => $items->count() === 1);
        $this->get('/shared-shortlists/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/shared-shortlists/bad')->assertNotFound();
    }
}
