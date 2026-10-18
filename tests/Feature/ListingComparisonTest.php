<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingComparisonTest extends TestCase
{
    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Comparison phone', 'description' => 'Public comparison description', 'slug' => (string) Str::uuid(), 'price' => '1000', 'currency' => 'IRR', 'status' => 'active', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::firstOrFail()->id], $data)));
    }

    public function test_guest_can_add_compare_remove_and_clear_with_idempotent_add(): void
    {
        $first = $this->listing(['title' => 'First comparison']);
        $second = $this->listing(['title' => 'Second comparison', 'price' => '2000']);
        $this->post(route('comparison.add', $first))->assertRedirect(route('comparison.index'));
        $this->post(route('comparison.add', $first))->assertSessionHas('listing_comparison', [$first->id]);
        $this->post(route('comparison.add', $second))->assertSessionHas('listing_comparison', [$first->id, $second->id]);
        $this->get(route('comparison.index'))->assertOk()->assertSee('First comparison')->assertSee('Second comparison')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->delete(route('comparison.remove', $first->id))->assertSessionHas('listing_comparison', [$second->id]);
        $this->delete(route('comparison.remove', $first->id))->assertSessionHas('listing_comparison', [$second->id]);
        $this->delete(route('comparison.clear'))->assertRedirect(route('comparison.index'))->assertSessionMissing('listing_comparison');
        $this->get(route('comparison.index'))->assertOk()->assertViewHas('listings', fn ($items) => $items->isEmpty());
    }

    public function test_limit_and_category_compatibility_are_enforced_without_losing_selection(): void
    {
        $first = $this->listing();
        $this->post(route('comparison.add', $first));
        $different = $this->listing(['category_id' => Category::whereKeyNot($first->category_id)->firstOrFail()->id]);
        $this->post(route('comparison.add', $different))->assertSessionHasErrors('comparison')->assertSessionHas('listing_comparison', [$first->id]);
        $second = $this->listing();
        $third = $this->listing();
        $fourth = $this->listing();
        $this->post(route('comparison.add', $second));
        $this->post(route('comparison.add', $third));
        $this->post(route('comparison.add', $fourth))->assertSessionHasErrors('comparison')->assertSessionHas('listing_comparison', [$first->id, $second->id, $third->id]);
    }

    public function test_private_expired_and_deleted_listings_cannot_leak_through_selection(): void
    {
        $live = $this->listing(['title' => 'Live selection']);
        $this->post(route('comparison.add', $live));
        foreach (['pending', 'sold', 'rejected', 'expired'] as $status) {
            $private = $this->listing(['title' => 'Hidden '.$status, 'status' => $status]);
            $this->post(route('comparison.add', $private))->assertNotFound();
        }
        $expired = $this->listing(['expires_at' => now()->subSecond()]);
        $this->post(route('comparison.add', $expired))->assertNotFound();
        $live->forceFill(['status' => 'pending'])->save();
        $this->get(route('comparison.index'))->assertOk()->assertDontSee('Live selection')->assertSessionHas('listing_comparison', []);
        $deleted = $this->listing();
        $this->post(route('comparison.add', $deleted));
        $deleted->delete();
        $this->get(route('comparison.index'))->assertViewHas('listings', fn ($items) => $items->isEmpty());
    }

    public function test_only_differences_uses_shared_custom_field_labels_and_handles_missing_values(): void
    {
        $first = $this->listing(['custom_fields' => ['condition' => 'new', 'storage' => '128'], 'city' => 'Tehran']);
        $second = $this->listing(['custom_fields' => ['condition' => 'used', 'storage' => '128', 'color' => 'black'], 'city' => 'Tehran']);
        $this->post(route('comparison.add', $first));
        $this->post(route('comparison.add', $second));
        $response = $this->get(route('comparison.index', ['differences' => 1]))->assertOk();
        $rows = collect($response->viewData('rows'));
        $this->assertSame(['custom:condition', 'custom:color'], $rows->pluck('key')->all());
        $this->assertSame(['—', 'black'], $rows->last()['values']);
        $this->assertTrue($response->viewData('differences'));
        $second->update(['custom_fields' => $first->custom_fields]);
        $this->get(route('comparison.index', ['differences' => 1]))->assertViewHas('rows', []);
    }

    public function test_selection_is_session_private_and_all_output_is_escaped(): void
    {
        $listing = $this->listing(['title' => '<script>privateCompare()</script>', 'custom_fields' => ['sample' => '<img src=x onerror=privateCompare()>']]);
        $this->post(route('comparison.add', $listing));
        $response = $this->get(route('comparison.index'))->assertOk()->assertSee($listing->title)->assertDontSee($listing->title, false);
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        session()->forget('listing_comparison');
        $this->get(route('comparison.index'))->assertDontSee('privateCompare');
        $this->get(route('comparison.index', ['ids' => [$listing->id]]))->assertViewHas('listings', fn ($items) => $items->isEmpty());
    }

    public function test_one_selection_keeps_fields_visible_and_prunes_changed_categories(): void
    {
        $first = $this->listing();
        $second = $this->listing();
        $this->post(route('comparison.add', $first));
        $this->get(route('comparison.index', ['differences' => 1]))->assertViewHas('differences', false)->assertViewHas('rows', fn ($rows) => count($rows) >= 3);
        $this->post(route('comparison.add', $second));
        $second->update(['category_id' => Category::whereKeyNot($first->category_id)->firstOrFail()->id]);
        $this->get(route('comparison.index'))->assertViewHas('listings', fn ($items) => $items->pluck('id')->all() === [$first->id]);
    }
}
