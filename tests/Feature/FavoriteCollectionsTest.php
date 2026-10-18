<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\FavoriteCollection;
use Modules\Favorite\App\Models\FavoriteListing;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class FavoriteCollectionsTest extends TestCase
{
    private function listing(): Listing
    {
        return Listing::unguarded(fn () => Listing::create(['title' => 'Collection listing', 'slug' => (string) Str::uuid(), 'description' => 'Collection test', 'status' => 'active', 'price' => '5000', 'currency' => 'IRR', 'category_id' => Category::firstOrFail()->id, 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id]));
    }

    public function test_create_rename_duplicate_validation_delete_and_name_reuse(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('collections.store'), ['name' => '  Work  '])->assertRedirect(route('collections.index'));
        $collection = FavoriteCollection::where('user_id', $user->id)->sole();
        $this->assertSame('Work', $collection->name);
        $this->post(route('collections.store'), ['name' => 'work'])->assertSessionHasErrors('name');
        $this->put(route('collections.update', $collection->id), ['name' => 'خانه'])->assertRedirect();
        $this->assertSame('خانه', $collection->fresh()->name);
        $this->put(route('collections.update', $collection->id), ['name' => 'خانه'])->assertSessionHasNoErrors();
        $this->delete(route('collections.destroy', $collection->id))->assertRedirect();
        $this->assertSoftDeleted($collection);
        $this->post(route('collections.store'), ['name' => 'خانه'])->assertSessionHasNoErrors();
        $this->post(route('collections.store'), ['name' => str_repeat('x', 61)])->assertSessionHasErrors('name');
        $this->post(route('collections.store'), ['name' => '   '])->assertSessionHasErrors('name');
    }

    public function test_move_between_collections_and_unfiled_preserves_favorite_and_price_alert(): void
    {
        $user = User::factory()->create();
        $listing = $this->listing();
        $user->rememberListing($listing);
        BuyerAlert::configure($user, 'price', $listing->id, true);
        $first = FavoriteCollection::saveFor($user, 'First');
        $second = FavoriteCollection::saveFor($user, 'Second');
        foreach ([$first->id, $second->id, null] as $id) {
            $this->actingAs($user)->post(route('collections.move', $listing->id), ['collection_id' => $id])->assertRedirect();
            $user->rememberListing($listing);
            $this->assertSame($id, FavoriteListing::where('user_id', $user->id)->where('listing_id', $listing->id)->sole()->collection_id);
            $this->assertSame(1, $user->savedListingsCount());
            $this->assertTrue(BuyerAlert::where('user_id', $user->id)->sole()->enabled);
        }
        FavoriteListing::moveFor($user, $listing->id, $first->id);
        $this->delete(route('collections.destroy', $first->id))->assertRedirect();
        $this->assertNull(FavoriteListing::where('user_id', $user->id)->sole()->collection_id);
        $this->assertSame(1, $user->savedListingsCount());
        $this->assertTrue(BuyerAlert::where('user_id', $user->id)->sole()->enabled);
    }

    public function test_collection_filters_combine_with_status_and_existing_pagination(): void
    {
        $user = User::factory()->create();
        $first = $this->listing();
        $second = $this->listing();
        $user->rememberListing($first);
        $user->rememberListing($second);
        $collection = FavoriteCollection::saveFor($user, 'Saved choice');
        FavoriteListing::moveFor($user, $first->id, $collection->id);
        $this->actingAs($user)->get(route('favorites.index', ['collection' => $collection->id]))->assertOk()->assertViewHas('favoriteListings', fn ($items) => $items->pluck('id')->all() === [$first->id]);
        $this->get(route('favorites.index', ['collection' => 'none']))->assertViewHas('favoriteListings', fn ($items) => $items->pluck('id')->all() === [$second->id]);
        $first->forceFill(['status' => 'sold'])->save();
        $this->get(route('favorites.index', ['collection' => $collection->id, 'status' => 'active']))->assertViewHas('favoriteListings', fn ($items) => $items->isEmpty());
        $this->get(route('favorites.index'))->assertViewHas('favoriteListings', fn ($items) => $items->total() === 2);
    }

    public function test_another_user_cannot_read_rename_delete_assign_or_filter_owned_collections(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $collection = FavoriteCollection::saveFor($owner, 'Private collection name');
        $listing = $this->listing();
        $owner->rememberListing($listing);
        $this->get(route('collections.index'))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('collections.index'))->assertOk()->assertDontSee('Private collection name');
        $this->put(route('collections.update', $collection->id), ['name' => 'Stolen'])->assertNotFound();
        $this->delete(route('collections.destroy', $collection->id))->assertNotFound();
        $this->get(route('favorites.index', ['collection' => $collection->id]))->assertNotFound();
        $this->post(route('collections.move', $listing->id), ['collection_id' => $collection->id])->assertNotFound();
        $otherCollection = FavoriteCollection::saveFor($other, 'Other collection');
        $this->post(route('collections.move', $listing->id), ['collection_id' => $otherCollection->id])->assertNotFound();
        $this->assertSame('Private collection name', $collection->fresh()->name);
        $this->assertNull(FavoriteListing::where('user_id', $owner->id)->sole()->collection_id);
    }

    public function test_unfavorite_does_not_revive_old_collection_on_refavorite_and_deleted_folders_cannot_be_assigned(): void
    {
        $user = User::factory()->create();
        $listing = $this->listing();
        $user->rememberListing($listing);
        $collection = FavoriteCollection::saveFor($user, 'Temporary');
        FavoriteListing::moveFor($user, $listing->id, $collection->id);
        $user->toggleFavoriteListing($listing);
        $this->actingAs($user)->post(route('collections.move', $listing->id), ['collection_id' => $collection->id])->assertNotFound();
        $user->rememberListing($listing);
        $this->assertNull(FavoriteListing::where('user_id', $user->id)->sole()->collection_id);
        FavoriteListing::moveFor($user, $listing->id, $collection->id);
        $user->toggleFavoriteListing($listing);
        FavoriteCollection::removeFor($user, $collection->id);
        $this->assertSame(0, FavoriteListing::withTrashed()->where('user_id', $user->id)->where('collection_id', $collection->id)->count());
        $user->rememberListing($listing);
        $this->assertNull(FavoriteListing::where('user_id', $user->id)->sole()->collection_id);
        $this->post(route('collections.move', $listing->id), ['collection_id' => $collection->id])->assertNotFound();
    }

    public function test_limit_inactive_user_and_escaped_names(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 50; $i++) {
            FavoriteCollection::saveFor($user, 'Folder '.$i);
        }
        $this->actingAs($user)->post(route('collections.store'), ['name' => 'Too many'])->assertSessionHasErrors('name');
        $collection = FavoriteCollection::where('user_id', $user->id)->firstOrFail();
        $this->put(route('collections.update', $collection->id), ['name' => '<script>collectionPayload()</script>'])->assertSessionHasNoErrors();
        $this->get(route('collections.index'))->assertOk()->assertSee('<script>collectionPayload()</script>')->assertDontSee('<script>collectionPayload()</script>', false);
        $user->forceFill(['status' => 'suspended'])->save();
        $this->delete(route('collections.destroy', $collection->id))->assertForbidden();
        $this->post(route('collections.store'), ['name' => 'Not allowed'])->assertForbidden();
    }
}
