<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingRecommendationsTest extends TestCase
{
    private function category(?Category $parent = null, array $data = []): Category
    {
        return Category::create(array_replace(['name' => 'Recommendation category', 'slug' => (string) Str::uuid(), 'is_active' => true, 'parent_id' => $parent?->id], $data));
    }

    private function listing(Category $category, array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Recommendation sample', 'description' => 'Recommendation test', 'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => 1000, 'currency' => 'IRR', 'country' => 'Recommendation land', 'city' => 'Test city', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => $category->id], $data)));
    }

    public function test_category_then_city_then_comparable_price_determine_order(): void
    {
        $root = $this->category();
        $leaf = $this->category($root);
        $child = $this->category($leaf);
        $sibling = $this->category($root);
        $source = $this->listing($leaf);
        $same = $this->listing($leaf, ['price' => 1100]);
        $near = $this->listing($leaf, ['price' => 1010]);
        $otherCity = $this->listing($leaf, ['city' => 'Another city', 'price' => 1000]);
        $descendant = $this->listing($child, ['price' => 1000]);
        $family = $this->listing($sibling, ['price' => 1000]);
        $otherCurrency = $this->listing($leaf, ['currency' => 'USD', 'price' => 1000]);
        $result = $source->relatedSuggestions(12);
        $this->assertSame([$near->id, $same->id, $otherCurrency->id, $otherCity->id, $descendant->id, $family->id], $result->modelKeys());
        $this->assertSame(['same_category', 'same_city', 'close_price'], $source->recommendationReasonKeys($near));
        $this->assertSame(['same_category', 'same_city'], $source->recommendationReasonKeys($otherCurrency));
        $this->assertSame(['related_category', 'same_city', 'close_price'], $source->recommendationReasonKeys($family));
    }

    public function test_unavailable_expired_deleted_unrelated_and_foreign_listings_are_excluded(): void
    {
        $category = $this->category();
        $source = $this->listing($category);
        $eligible = $this->listing($category);
        foreach (['pending', 'sold', 'expired', 'rejected'] as $status) {
            $this->listing($category, ['status' => $status]);
        }
        $this->listing($category, ['expires_at' => now()->subSecond()]);
        $this->listing($category, ['expires_at' => now()]);
        $deleted = $this->listing($category);
        $deleted->delete();
        $this->listing($category, ['country' => 'Different country']);
        $this->listing($category, ['country' => null]);
        $this->listing($this->category());
        $this->assertSame([$eligible->id], $source->relatedSuggestions()->modelKeys());
    }

    public function test_inactive_deleted_and_cyclic_category_branches_are_safe(): void
    {
        $root = $this->category();
        $leaf = $this->category($root);
        $source = $this->listing($leaf);
        $inactive = $this->category($root, ['is_active' => false]);
        $this->listing($this->category($inactive));
        $deleted = $this->category($root);
        $deleted->delete();
        $this->listing($this->category($deleted));
        $this->assertCount(0, $source->relatedSuggestions());
        $root->update(['is_active' => false]);
        $this->assertSame(['primary' => [], 'family' => []], Category::recommendationGroups($leaf->id));
        $root->update(['is_active' => true, 'parent_id' => $leaf->id]);
        $this->assertSame(['primary' => [], 'family' => []], Category::recommendationGroups($leaf->id));
        $this->assertSame(['primary' => [], 'family' => []], Category::recommendationGroups(null));
    }

    public function test_limits_ties_and_missing_context_have_predictable_results(): void
    {
        $category = $this->category();
        $source = $this->listing($category, ['country' => null, 'city' => null, 'price' => 0]);
        $ids = [];
        for ($i = 0; $i < 14; $i++) {
            $ids[] = $this->listing($category, ['created_at' => now()->startOfDay()])->id;
        }
        $this->assertSame(array_slice(array_reverse($ids), 0, 12), $source->relatedSuggestions(100)->modelKeys());
        $this->assertCount(0, $source->relatedSuggestions(0));
        $this->assertCount(0, $source->relatedSuggestions(-5));
        $this->assertSame(['same_category'], $source->recommendationReasonKeys(Listing::findOrFail($ids[0])));
        $source->category_id = null;
        $this->assertCount(0, $source->relatedSuggestions());
    }

    public function test_country_aliases_and_bound_location_input_work(): void
    {
        $category = $this->category();
        $source = $this->listing($category, ['country' => 'Iran', 'city' => "Test ' OR 1=1"]);
        $eligible = $this->listing($category, ['country' => 'ایران', 'city' => "Test ' OR 1=1"]);
        $this->listing($category, ['country' => 'Elsewhere', 'city' => "Test ' OR 1=1"]);
        $this->assertSame([$eligible->id], $source->relatedSuggestions()->modelKeys());
        $this->assertContains('same_city', $source->recommendationReasonKeys($eligible));
    }

    public function test_detail_renders_explanations_and_hides_empty_recommendations(): void
    {
        $category = $this->category();
        $source = $this->listing($category);
        $candidate = $this->listing($category, ['title' => '<script>recommendation</script>']);
        $this->withSession(['locale' => 'fa'])->get(route('listings.show', $source))->assertOk()
            ->assertViewHas('relatedListings', fn ($items) => $items->modelKeys() === [$candidate->id])
            ->assertSee('آگهی‌های مرتبط')->assertSee('هم‌دسته')->assertSee('قیمت نزدیک')
            ->assertSee('data-related-id="'.$candidate->id.'"', false)->assertDontSee('<script>recommendation</script>', false);
        $candidate->delete();
        $this->get(route('listings.show', $source))->assertOk()->assertDontSee('data-related-listings', false);
    }
}
