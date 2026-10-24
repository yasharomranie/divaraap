<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingCustomField;
use Modules\Listing\Support\PriceGuide;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class PriceGuideTest extends TestCase
{
    private int $category;

    private array $sellers;

    private array $listings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->category = Category::where('name', 'Phones')->firstOrFail()->id;
        ListingCustomField::updateOrCreate(['name' => 'condition'], ['category_id' => $this->category, 'label' => 'Condition', 'type' => ListingCustomField::TYPE_SELECT, 'options' => ['New', 'Used'], 'is_active' => true]);
        $this->sellers = User::factory()->count(5)->create(['status' => 'active'])->all();
        $this->listings = [];
        for ($i = 1; $i <= 10; $i++) {
            $this->listings[] = $this->listing(['price' => $i * 1000, 'user_id' => $this->sellers[($i - 1) % 5]->id]);
        }
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'GuideFixture Phone 256', 'description' => 'Public price guide fixture', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->sellers[0]->id, 'category_id' => $this->category, 'price' => 1000, 'currency' => 'IRR', 'custom_fields' => ['condition' => 'Used'], 'created_at' => now()->subDay(), 'expires_at' => now()->addDay()], $data)));
    }

    private function filters(array $data = []): array
    {
        return array_replace(['category_id' => $this->category, 'search' => 'GuideFixture', 'currency' => 'IRR'], $data);
    }

    public function test_report_calculates_exact_median_nearest_rank_quartiles_and_sample_counts(): void
    {
        $report = PriceGuide::report($this->filters());
        $this->assertTrue($report['sufficient']);
        $this->assertSame(10, $report['count']);
        $this->assertSame(5, $report['sellers']);
        $this->assertFalse($report['limited']);
        $this->assertSame(['median' => '5500.00', 'lower' => '3000.00', 'upper' => '8000.00', 'min' => '1000.00', 'max' => '10000.00'], $report['stats']);
        $this->listing(['price' => 11000]);
        $this->assertSame('6000.00', PriceGuide::report($this->filters())['stats']['median']);
    }

    public function test_currency_minor_units_and_large_prices_do_not_use_floating_point(): void
    {
        config(['app.default_currency' => 'USD']);
        foreach ($this->listings as $index => $listing) {
            $listing->forceFill(['currency' => 'USD', 'price' => '90071992547409.'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)])->save();
        }
        $report = PriceGuide::report($this->filters(['currency' => 'USD']));
        $this->assertSame('90071992547409.06', $report['stats']['median']);
        $this->assertSame('90071992547409.03', $report['stats']['lower']);
        $this->assertSame('USD', $report['currency']);
    }

    public function test_only_current_public_recent_positive_single_currency_prices_are_used(): void
    {
        foreach ([['status' => 'sold'], ['status' => 'pending'], ['expires_at' => now()->subSecond()], ['price' => 0], ['price' => null], ['price' => -5], ['price' => '4.50'], ['currency' => 'USD'], ['created_at' => now()->subDays(31)], ['created_at' => now()->addDay()], ['category_id' => Category::where('name', 'Cars')->firstOrFail()->id]] as $invalid) {
            $this->listing($invalid);
        }
        $this->listing()->delete();
        $inactive = User::factory()->create(['status' => 'suspended']);
        $this->listing(['user_id' => $inactive->id]);
        $this->assertSame(10, PriceGuide::report($this->filters())['count']);
        $this->listings[0]->forceFill(['status' => 'sold'])->save();
        $report = PriceGuide::report($this->filters());
        $this->assertFalse($report['sufficient']);
        $this->assertNull($report['stats']);
    }

    public function test_exact_city_and_country_alias_filters_do_not_mix_locations(): void
    {
        $city = City::query()->active()->with('country')->firstOrFail();
        foreach ($this->listings as $index => $listing) {
            $listing->forceFill(['city' => $city->name, 'country' => $index % 2 ? $city->country->name : $city->country->code])->save();
        }
        $this->listing(['city' => 'Other City', 'country' => $city->country->name]);
        $this->listing(['city' => $city->name, 'country' => 'ZZ']);
        $this->assertSame(10, PriceGuide::report($this->filters(['city_id' => $city->id]))['count']);
        $city->update(['is_active' => false]);
        $this->get(route('price-guide.index', $this->filters(['city_id' => $city->id])))->assertSessionHasErrors('city_id');
    }

    public function test_configured_condition_filters_exact_values_and_unset_values_do_not_match(): void
    {
        $this->assertArrayHasKey('Used', ListingCustomField::priceGuideConditions($this->category));
        $this->listing(['custom_fields' => ['condition' => 'New']]);
        $this->listing(['custom_fields' => []]);
        $this->assertSame(10, PriceGuide::report($this->filters(['condition' => 'Used']))['count']);
        $this->get(route('price-guide.index', $this->filters(['condition' => 'invented'])))->assertSessionHasErrors('condition');
        $this->get(route('price-guide.index', $this->filters(['condition' => ['Used']])))->assertSessionHasErrors('condition');
    }

    public function test_low_sample_or_few_sellers_hides_all_price_statistics_and_caps_each_seller(): void
    {
        $this->listings[0]->delete();
        $report = PriceGuide::report($this->filters());
        $this->assertSame(9, $report['count']);
        $this->assertNull($report['stats']);
        foreach ($this->listings as $listing) {
            DB::table('listings')->where('id', $listing->id)->update(['user_id' => $this->sellers[0]->id]);
        }
        for ($i = 0; $i < 8; $i++) {
            $this->listing();
        }
        $report = PriceGuide::report($this->filters());
        $this->assertSame(3, $report['count']);
        $this->assertSame(1, $report['sellers']);
        $this->assertNull($report['stats']);
        foreach (array_slice($this->sellers, 1, 3) as $seller) {
            for ($i = 0; $i < 3; $i++) {
                $this->listing(['user_id' => $seller->id]);
            }
        }
        $report = PriceGuide::report($this->filters());
        $this->assertSame(12, $report['count']);
        $this->assertSame(4, $report['sellers']);
        $this->assertFalse($report['sufficient']);
    }

    public function test_query_is_bounded_to_newest_thousand_and_declares_truncation(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1001; $i++) {
            $rows[] = ['title' => 'BoundedGuide', 'description' => 'Bounded sample', 'slug' => (string) Str::uuid(), 'user_id' => $this->sellers[$i % 5]->id, 'category_id' => $this->category, 'status' => 'active', 'price' => $i, 'currency' => 'IRR', 'created_at' => now()->subDay(), 'updated_at' => now()];
        }
        DB::table('listings')->insert($rows);
        $report = PriceGuide::report($this->filters(['search' => 'BoundedGuide']));
        $this->assertTrue($report['limited']);
        $this->assertSame(15, $report['count']);
        $this->assertSame(5, $report['sellers']);
        $this->assertTrue($report['sufficient']);
        $this->assertSame('987.00', $report['stats']['min']);
    }

    public function test_product_leaf_active_category_validation_excludes_services_and_parent_groups(): void
    {
        $services = Category::advertisingServiceCategoryIds();
        $this->assertNotEmpty($services);
        foreach ([$services[0], Category::findOrFail($this->category)->parent_id, 99999999] as $invalid) {
            $this->get(route('price-guide.index', $this->filters(['category_id' => $invalid])))->assertSessionHasErrors('category_id');
        }
        Category::whereKey($this->category)->update(['is_active' => false]);
        $this->get(route('price-guide.index', $this->filters()))->assertSessionHasErrors('category_id');
    }

    public function test_persian_search_normalization_and_literal_wildcards_are_reused(): void
    {
        foreach ($this->listings as $listing) {
            $listing->forceFill(['title' => 'گوشی مدل آکبند ۱۲۸'])->save();
        }
        $this->assertSame(10, PriceGuide::report($this->filters(['search' => 'گوشي 128 اكـبند']))['count']);
        $this->assertSame(0, PriceGuide::report($this->filters(['search' => '%%']))['count']);
        $this->get(route('price-guide.index', $this->filters(['search' => implode(' ', range(1, 9))])))->assertSessionHasErrors('search');
        $this->get(route('price-guide.index', $this->filters(['currency' => 'USD'])))->assertSessionHasErrors('currency');
        $this->get(route('price-guide.index', $this->filters(['search' => ['bad']])))->assertSessionHasErrors('search');
    }

    public function test_rial_median_rounds_half_up_and_window_includes_exact_thirty_day_boundary(): void
    {
        foreach ($this->listings as $index => $listing) {
            $listing->forceFill(['price' => $index + 1, 'created_at' => now()->subDays(30)])->save();
        }
        $report = PriceGuide::report($this->filters());
        $this->assertSame(10, $report['count']);
        $this->assertSame('6.00', $report['stats']['median']);
        $this->listings[0]->forceFill(['created_at' => now()->subDays(30)->subSecond()])->save();
        $this->assertFalse(PriceGuide::report($this->filters())['sufficient']);
    }

    public function test_public_result_has_private_noindex_headers_and_no_listing_or_account_payloads(): void
    {
        $this->get(route('price-guide.index'))->assertOk()->assertViewHas('report', null);
        $response = $this->get(route('price-guide.index', $this->filters()))->assertOk()->assertSee(__('listing::prices.disclaimer'))->assertSee('data-price-median', false)->assertDontSee($this->sellers[0]->email)->assertDontSee($this->listings[0]->description)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertArrayNotHasKey('samples', $response->viewData('report'));
        $this->get(route('price-guide.index', $this->filters(['search' => '<script>priceGuide()</script>'])))->assertOk()->assertSee('&lt;script&gt;priceGuide()&lt;/script&gt;', false)->assertDontSee('<script>priceGuide()</script>', false)->assertSee('data-price-insufficient', false)->assertDontSee('data-price-median', false);
    }
}
