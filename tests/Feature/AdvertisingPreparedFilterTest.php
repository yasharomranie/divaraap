<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Advertising\Filament\Admin\Resources\BannerResource\Pages\EditBanner;
use Modules\Advertising\Models\AdvertisingEvent;
use Modules\Advertising\Models\Banner;
use Modules\Advertising\Models\BannerPlacement;
use Modules\Advertising\Models\Campaign;
use Modules\Advertising\Support\AdvertisingToken;
use Modules\Advertising\Support\BannerRenderer;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\City;
use Modules\Location\Models\Country;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class AdvertisingPreparedFilterTest extends TestCase
{
    private Category $category;

    private City $city;

    private array $choices;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $image = UploadedFile::fake()->image('filters.jpg');
        Storage::disk('public')->put('banners/filters.jpg', file_get_contents($image->getRealPath()));
        $this->category = Category::create(['name' => 'Prepared filter fixture', 'slug' => (string) Str::uuid(), 'parent_id' => Category::where('slug', 'electronics')->firstOrFail()->id, 'is_active' => true]);
        $this->city = City::create(['name' => 'Prepared filter city', 'country_id' => Country::where('is_active', true)->firstOrFail()->id, 'is_active' => true]);
        $this->choices = [
            ['label' => 'Affordable nearby', 'category_id' => $this->category->id, 'city_id' => $this->city->id, 'min_price' => '۱٬۰۰۰', 'max_price' => '۲۰۰۰', 'sort' => 'price_asc'],
            ['label' => 'More choices', 'category_id' => $this->category->id, 'sort' => 'newest'],
        ];
    }

    private function card(array $data = []): Banner
    {
        $card = Banner::create(array_replace(['title' => 'Prepared choices fixture', 'alt' => 'Filter cover', 'image' => 'banners/filters.jpg', 'creative_format' => 'filter', 'filter_choices' => $this->choices, 'device' => 'both', 'is_active' => true, 'review_status' => 'approved'], $data));
        BannerPlacement::where('key', 'home-top')->firstOrFail()->assignments()->create(['banner_id' => $card->id, 'priority' => 1]);

        return $card;
    }

    private function token(Banner $card): string
    {
        $this->get('/')->assertOk();
        $token = AdvertisingToken::issue($card, 'home-top', 'both', []);
        $this->withCookie(config('session.cookie'), request()->session()->getId());

        return $token;
    }

    public function test_ordered_choices_build_only_native_internal_whitelisted_search_links(): void
    {
        $card = $this->card(['url' => 'https://unrelated.example']);
        $options = array_values($card->preparedFilters());
        $this->assertNull($card->destination());
        $this->assertSame(['Affordable nearby', 'More choices'], array_column($options, 'label'));
        parse_str(parse_url($options[0]['url'], PHP_URL_QUERY), $query);
        $this->assertSame((string) $this->category->id, $query['category']);
        $this->assertSame((string) $this->city->id, $query['city']);
        $this->assertSame((string) $this->city->country_id, $query['country']);
        $this->assertSame('1000.00', $query['min_price']);
        $this->assertSame('2000.00', $query['max_price']);
        $this->assertSame('price_asc', $query['sort']);
        $this->get('/')->assertOk()->assertSee('data-ad-filters="'.$card->id.'"', false)->assertSee('Affordable nearby')->assertSee($this->city->displayName())->assertDontSee('https://unrelated.example');
        $this->assertSame('1000.00', $card->fresh()->filter_choices[0]['min_price']);
    }

    public function test_result_page_reuses_real_category_city_currency_price_and_sort_filters(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $make = fn (string $title, int $price, string $currency = 'IRR', ?string $city = null): Listing => Listing::unguarded(fn () => Listing::create(['title' => $title, 'description' => 'FILTER RESULT FIXTURE', 'slug' => (string) Str::uuid(), 'category_id' => $this->category->id, 'user_id' => $user->id, 'status' => 'active', 'price' => $price, 'currency' => $currency, 'city' => $city ?? $this->city->name, 'country' => Country::findOrFail($this->city->country_id)->name]));
        $low = $make('Matching lower price', 1200);
        $high = $make('Matching higher price', 1800);
        $make('Too expensive fixture', 3000);
        $make('Different currency fixture', 1500, 'USD');
        $make('Different city fixture', 1400, city: 'Another place');
        $url = array_values($this->card()->preparedFilters())[0]['url'];
        $response = $this->get($url)->assertOk()->assertSee('Matching lower price')->assertSee('Matching higher price')->assertDontSee('Too expensive fixture')->assertDontSee('Different currency fixture')->assertDontSee('Different city fixture');
        $this->assertSame([$low->id, $high->id], $response->viewData('listings')->pluck('id')->all());
    }

    public function test_invalid_counts_empty_duplicate_and_unknown_filters_are_rejected(): void
    {
        foreach ([[], [$this->choices[0]], array_fill(0, 5, $this->choices[0]), [$this->choices[0], $this->choices[0]], [['label' => 'Empty'], $this->choices[0]], [array_merge($this->choices[0], ['url' => 'https://evil.example']), $this->choices[1]]] as $choices) {
            try {
                $this->card(['filter_choices' => $choices]);
                $this->fail('Invalid choices were accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('filter_choices', $exception->errors());
            }
        }
    }

    public function test_malformed_values_price_bounds_and_search_limits_are_rejected(): void
    {
        foreach ([['label' => []], ['category_id' => 999999999], ['city_id' => ['bad']], ['min_price' => '2001'], ['min_price' => '1.5'], ['max_price' => '9999999999999999999'], ['search' => str_repeat('a', 121)], ['search' => 'one two three four five six seven eight nine'], ['sort' => 'random']] as $changes) {
            $choices = $this->choices;
            $choices[0] = array_replace($choices[0], $changes);
            try {
                $this->card(['filter_choices' => $choices]);
                $this->fail('Malformed filters were accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('filter_choices', $exception->errors());
            }
        }
    }

    public function test_unavailable_category_ancestry_city_or_country_hides_and_warns_but_can_pause(): void
    {
        $card = $this->card();
        $parent = Category::findOrFail($this->category->parent_id);
        $parent->update(['is_active' => false]);
        $this->assertSame([], $card->fresh()->preparedFilters());
        $this->assertSame([], (new BannerRenderer)->at('home-top'));
        $this->assertContains('filters_unavailable', array_column(Banner::healthIssues(), 'code'));
        $card->update(['is_active' => false]);
        $parent->update(['is_active' => true]);
        $this->city->update(['is_active' => false]);
        $this->assertSame([], $card->fresh()->preparedFilters());
        $this->city->update(['is_active' => true]);
        Country::findOrFail($this->city->country_id)->update(['is_active' => false]);
        $this->assertSame([], $card->fresh()->preparedFilters());
    }

    public function test_labels_and_search_summaries_are_escaped_and_zero_price_is_a_valid_filter(): void
    {
        $card = $this->card(['filter_choices' => [['label' => '<script>label()</script>', 'search' => '<img src=x>'], ['label' => 'Free items', 'max_price' => '۰']]]);
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;label()&lt;/script&gt;', false)->assertSee('&lt;img src=x&gt;', false)->assertDontSee('<script>label()</script>', false)->assertDontSee('<img src=x>', false);
        $this->assertSame('0.00', $card->fresh()->filter_choices[1]['max_price']);
    }

    public function test_clicks_require_served_choice_and_deduplicate_without_faking_listing_attribution(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        foreach ([null, str_repeat('0', 64)] as $choice) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile', 'choice' => $choice])->assertNoContent();
        }
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        foreach (array_keys($card->preparedFilters()) as $choice) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile', 'choice' => $choice])->assertNoContent()->assertCookieMissing('advertising_attribution');
        }
        $event = AdvertisingEvent::where('banner_id', $card->id)->sole();
        $this->assertSame('click', $event->kind);
        $this->assertNull($event->target_listing_id);
    }

    public function test_changed_filters_or_format_invalidate_old_tokens_for_clicks_and_impressions(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $choice = array_key_first($card->preparedFilters());
        $choices = $card->filter_choices;
        $choices[0]['max_price'] = '3000';
        $card->update(['filter_choices' => $choices]);
        $this->travel(2)->seconds();
        foreach (['click', 'impression'] as $kind) {
            $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => $kind, 'device' => 'mobile', 'choice' => $choice])->assertNoContent();
        }
        $card->update(['creative_format' => 'image', 'url' => 'https://example.com']);
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile', 'choice' => $choice])->assertNoContent();
        $this->assertSame(0, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_new_tokens_accept_viewable_impressions_and_current_choice_clicks(): void
    {
        $card = $this->card();
        $token = $this->token($card);
        $this->travel(2)->seconds();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'impression', 'device' => 'mobile'])->assertNoContent();
        $this->withCredentials()->postJson(route('advertising.events'), ['token' => $token, 'kind' => 'click', 'device' => 'mobile', 'choice' => array_key_first($card->preparedFilters())])->assertNoContent();
        $this->assertSame(2, AdvertisingEvent::where('banner_id', $card->id)->count());
        $this->travelBack();
    }

    public function test_staff_edit_reenters_moderation_and_approval_retains_choices(): void
    {
        $card = $this->card();
        $staff = User::factory()->create(['status' => 'active']);
        $staff->syncRoles(['advertising-manager']);
        $this->actingAs($staff);
        $choices = $card->filter_choices;
        $choices[1]['label'] = 'Changed choice';
        $card->update(['filter_choices' => $choices]);
        $this->assertSame('pending', $card->fresh()->review_status);
        $this->assertFalse($card->fresh()->is_active);
        $staff->syncRoles(['moderator']);
        Banner::moderationQueue()->firstWhere('id', $card->id)->moderate(true);
        $this->assertSame('filter', $card->fresh()->creative_format);
        $this->assertSame('Changed choice', $card->fresh()->filter_choices[1]['label']);
        $this->assertTrue($card->fresh()->creativeEligible());
    }

    public function test_filament_save_preview_permissions_and_existing_campaign_controls(): void
    {
        $campaign = Campaign::create(['title' => 'Filter campaign', 'is_active' => true, 'page_paths' => ['/allowed']]);
        $card = $this->card(['campaign_id' => $campaign->id, 'device' => 'mobile']);
        $this->assertFalse($card->eligibleForCampaign(['path' => 'other']));
        $result = (new BannerRenderer)->at('home-top', context: ['path' => 'allowed']);
        $this->assertSame($card->id, $result['mobile']->id);
        $this->assertArrayNotHasKey('desktop', $result);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditBanner::class, ['record' => $card->id])->call('save')->assertHasNoFormErrors();
        $this->assertCount(2, $card->fresh()->filter_choices);
        $this->get(route('advertising.preview-render', ['banner' => $card->id, 'device' => 'mobile']))->assertOk()->assertSee('Affordable nearby')->assertDontSee('data-ad-choice=', false)->assertDontSee('data-event-token=', false);
        $this->actingAs(User::factory()->create(['status' => 'active']))->get('/admin/banners/'.$card->id.'/edit')->assertForbidden();
    }
}
