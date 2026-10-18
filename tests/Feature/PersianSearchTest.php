<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\PersianSearch;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\BuyerAlert;
use Modules\Favorite\App\Models\FavoriteSearch;
use Modules\Listing\Models\Listing;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class PersianSearchTest extends TestCase
{
    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'گوشي کاركرده مدل ١٢٨', 'description' => 'دست‌دوم تمیز', 'slug' => (string) Str::uuid(), 'status' => 'active', 'price' => '1000000', 'currency' => 'IRR', 'user_id' => User::where('email', 'a@a.com')->firstOrFail()->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'city' => 'تهران', 'country' => 'Iran'], $data)));
    }

    public function test_letter_digit_and_half_space_variants_match_legacy_content_without_rewriting_it(): void
    {
        $listing = $this->listing();
        foreach (['گوشی کارکرده ۱۲۸', '١٢٨ کارکرده گوشی', '128 گوشي', 'دست دوم', 'دستدوم', 'دست‌دوم', 'تهران IRAN'] as $search) {
            $this->assertTrue(Listing::whereKey($listing->id)->searchTerm($search)->exists(), $search);
        }
        $this->assertSame('گوشي کاركرده مدل ١٢٨', $listing->fresh()->title);
        $this->assertSame('دست‌دوم تمیز', $listing->fresh()->description);
    }

    public function test_diacritics_tatweel_whitespace_and_alef_variants_are_normalized(): void
    {
        $listing = $this->listing(['title' => 'آیـفُون ۱۲', 'description' => "کار\tکرده\nتمیز"]);
        foreach (['ايفون 12', 'آیفون ۱۲', 'کارکرده تمیز', 'كار كرده'] as $search) {
            $this->assertTrue(Listing::whereKey($listing->id)->searchTerm($search)->exists(), $search);
        }
        $this->assertSame(['0'], PersianSearch::terms('۰'));
        $this->assertSame([], PersianSearch::terms('   '));
        $this->assertNull(PersianSearch::terms("\u{200c}"));
    }

    public function test_every_word_is_required_and_punctuation_is_literal_not_sql_wildcards(): void
    {
        $listing = $this->listing(['title' => 'Sale 50% model_A!', 'description' => 'Plain text']);
        foreach (['50%', 'model_A!', 'sale PLAIN', '%', '_', '!'] as $search) {
            $this->assertTrue(Listing::whereKey($listing->id)->searchTerm($search)->exists(), $search);
        }
        foreach (['50_', 'model%B', "' OR 1=1 --", 'sale missing'] as $search) {
            $this->assertFalse(Listing::whereKey($listing->id)->searchTerm($search)->exists(), $search);
        }
        $ordinary = $this->listing(['title' => 'Ordinary item', 'description' => 'No punctuation']);
        foreach (['%', '_', '!'] as $search) {
            $this->assertFalse(Listing::whereKey($ordinary->id)->searchTerm($search)->exists(), $search);
        }
    }

    public function test_browse_preserves_category_price_and_public_status_filters(): void
    {
        $listing = $this->listing();
        $this->listing(['status' => 'pending']);
        $this->listing(['price' => '2000000']);
        $this->listing(['category_id' => Category::whereKeyNot($listing->category_id)->firstOrFail()->id]);
        $response = $this->get(route('listings.index', ['search' => '۱۲۸ گوشی', 'category' => $listing->category_id, 'max_price' => '1000000']))->assertOk();
        $this->assertSame([$listing->id], $response->viewData('listings')->pluck('id')->all());
        $this->assertSame($listing->category_id, $response->viewData('categoryId'));
    }

    public function test_legacy_saved_searches_and_reordered_normalized_queries_deduplicate_without_data_migration(): void
    {
        $this->assertSame(FavoriteSearch::signatureFor(['search' => '01 1']), FavoriteSearch::signatureFor(['search' => '1 01']));
        $user = User::factory()->create();
        $filters = ['search' => 'گوشي ١٢٨'];
        $legacy = FavoriteSearch::storeForUser($user, $filters);
        $legacy->forceFill(['signature' => hash('sha256', json_encode(FavoriteSearch::normalizeFilters($filters)))])->save();
        $equivalent = ['search' => '۱۲۸ گوشی'];
        $this->assertTrue(FavoriteSearch::isSavedForUser($user, $equivalent));
        $same = FavoriteSearch::storeForUser($user, $equivalent);
        $this->assertSame($legacy->id, $same->id);
        $this->assertSame('گوشي ١٢٨', $legacy->fresh()->search_term);
        $this->assertSame(1, FavoriteSearch::where('user_id', $user->id)->count());
        $legacy->delete();
        $replacement = FavoriteSearch::storeForUser($user, $equivalent);
        $this->assertNotSame($legacy->id, $replacement->id);
    }

    public function test_buyer_alerts_use_the_same_normalized_search_and_filter_rules(): void
    {
        $buyer = User::factory()->create();
        $search = FavoriteSearch::storeForUser($buyer, ['search' => '۱۲۸ گوشی']);
        BuyerAlert::configure($buyer, 'search', $search->id, true);
        $listing = $this->listing();
        $this->listing(['title' => 'گوشی ۲۵۶']);
        BuyerAlert::refreshFor($buyer);
        BuyerAlert::refreshFor($buyer);
        $notification = UserNotification::where('user_id', $buyer->id)->where('type', UserNotification::TYPE_SAVED_SEARCH)->sole();
        $this->assertSame(route('listings.show', $listing), $notification->action_url);
    }

    public function test_query_limits_fail_closed_show_actionable_error_and_cannot_create_unsupported_saved_search(): void
    {
        $this->listing();
        foreach ([str_repeat('ک', 121), 'one two three four five six seven eight nine'] as $search) {
            $this->assertNull(PersianSearch::terms($search));
            $response = $this->withSession(['locale' => 'fa'])->get(route('listings.index', ['search' => $search]))->assertOk()->assertSee('حداکثر ۸ واژه')->assertSee('aria-describedby="search-error"', false);
            $this->assertSame(0, $response->viewData('listings')->total());
        }
        $this->get(route('listings.index', ['search' => ['invalid']]))->assertStatus(422);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('favorites.searches.store'), ['search' => 'one two three four five six seven eight nine'])->assertSessionHasErrors('search');
        $this->assertSame(0, FavoriteSearch::where('user_id', $user->id)->count());
    }
}
