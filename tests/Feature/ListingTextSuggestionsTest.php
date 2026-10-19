<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingTextSuggestions;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ListingTextSuggestionsTest extends TestCase
{
    private function user(): User
    {
        return User::where('email', 'a@a.com')->firstOrFail();
    }

    public function test_templates_use_only_entered_facts_and_do_not_generate_claims_from_empty_input(): void
    {
        $empty = ListingTextSuggestions::generate(['category' => 'Phones', 'city' => 'Tehran']);
        $this->assertSame(['titles' => [], 'descriptions' => []], $empty);
        $result = ListingTextSuggestions::generate(['title' => 'گوشی مدل ۱۲۸', 'description' => 'فقط خود دستگاه ارائه می‌شود.', 'category' => 'تلفن همراه', 'city' => 'تهران']);
        $this->assertCount(3, $result['titles']);
        $this->assertCount(2, $result['descriptions']);
        $texts = implode(' ', array_column($result['titles'], 'text')).implode(' ', array_column($result['descriptions'], 'text'));
        $this->assertStringContainsString('۱۲۸', $texts);
        $this->assertStringContainsString('فقط خود دستگاه ارائه می‌شود.', $texts);
        foreach (['گارانتی', 'سالم', 'تخفیف', 'guarantee', 'warranty'] as $invented) {
            $this->assertStringNotContainsString($invented, $texts);
        }
    }

    public function test_unicode_limits_sanitization_and_deduplication_are_bounded(): void
    {
        $result = ListingTextSuggestions::generate(['title' => '<b>'.str_repeat('گوشی فارسی ', 30).'</b>', 'description' => '<p>'.str_repeat('توضیح فارسی واقعی ', 400).'</p>', 'category' => str_repeat('گروه', 30), 'city' => 'تهران'], 1450);
        foreach ($result['titles'] as $option) {
            $this->assertLessThanOrEqual(70, mb_strlen($option['text']));
            $this->assertStringNotContainsString('<b>', $option['text']);
        }
        foreach ($result['descriptions'] as $option) {
            $this->assertLessThanOrEqual(1450, mb_strlen($option['text']));
            $this->assertStringNotContainsString('<p>', $option['text']);
        }
        $this->assertSame(count($result['titles']), count(array_unique(array_column($result['titles'], 'text'))));
        $same = ListingTextSuggestions::generate(['title' => 'تهران', 'category' => 'تهران', 'city' => 'تهران']);
        $this->assertCount(1, $same['titles']);
        $plain = ListingTextSuggestions::generate(['title' => 'نام کالا', 'description' => '&lt;script&gt;alert(1)&lt;/script&gt;<br>متن اصلی', 'city' => 'Other']);
        $this->assertCount(1, $plain['titles']);
        $this->assertStringNotContainsString('<script>', $plain['descriptions'][0]['text']);
        $this->assertStringContainsString("\n", $plain['descriptions'][0]['text']);
    }

    public function test_endpoint_requires_authentication_and_rejects_invalid_shapes_and_limits(): void
    {
        $url = route('panel.listings.text-suggestions');
        $this->postJson($url, ['title' => 'Sample title'])->assertUnauthorized();
        $this->actingAs($this->user());
        foreach ([['title' => ['bad']], ['description' => str_repeat('x', 5001)], ['category_id' => -1], ['city' => ['bad']], ['description_limit' => 5000]] as $payload) {
            $this->postJson($url, $payload)->assertUnprocessable();
        }
    }

    public function test_endpoint_is_private_read_only_and_does_not_send_text_to_external_services(): void
    {
        Http::preventStrayRequests();
        $listing = Listing::where('user_id', $this->user()->id)->firstOrFail();
        $before = $listing->getAttributes();
        $count = Listing::count();
        $category = Category::where('name', 'Phones')->firstOrFail();
        $response = $this->actingAs($this->user())->withSession(['locale' => 'fa'])->postJson(route('panel.listings.text-suggestions'), [
            'title' => 'گوشی آزمایشی مدل جدید', 'description' => 'خط اول واقعی. خط دوم واقعی.', 'category_id' => $category->id,
            'city' => 'تهران', 'description_limit' => 1450, 'contact_email' => 'never-include@example.test', 'listing_id' => $listing->id, 'status' => 'active',
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertJsonStructure(['titles' => [['key', 'text']], 'descriptions' => [['key', 'text']]])->assertDontSee('never-include@example.test');
        $this->assertSame($before, $listing->fresh()->getAttributes());
        $this->assertSame($count, Listing::count());
        Http::assertNothingSent();
    }

    public function test_missing_inactive_and_deleted_categories_do_not_supply_labels(): void
    {
        $category = Category::where('name', 'Phones')->firstOrFail();
        $category->update(['is_active' => false]);
        $this->assertSame('', Category::textSuggestionLabel($category->id));
        $category->delete();
        $this->assertSame('', Category::textSuggestionLabel($category->id));
        $this->assertSame('', Category::textSuggestionLabel(null));
        $this->assertSame('', Category::textSuggestionLabel(999999999));
    }
}
