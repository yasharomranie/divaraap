<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingCustomField;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Location\Models\Country;
use Modules\Page\Models\Page;
use Modules\User\App\Models\User;
use Tests\TestCase;

class PublicPersianMarketplaceTest extends TestCase
{
    private function listing(): Listing
    {
        return Listing::unguarded(fn (): Listing => Listing::query()->create([
            'title' => 'گوشی آزمایشی فارسی',
            'description' => 'توضیحات واقعی کاربر برای آزمون جست‌وجو',
            'slug' => 'phase3-'.Str::uuid(),
            'price' => 1250,
            'currency' => 'USD',
            'status' => 'active',
            'country' => 'Iran',
            'city' => 'Tehran',
            'category_id' => Category::query()->where('name', 'Phones')->firstOrFail()->getKey(),
            'user_id' => User::query()->where('email', 'a@a.com')->firstOrFail()->getKey(),
        ]));
    }

    public function test_stock_categories_are_localized_without_mutating_names(): void
    {
        $category = Category::query()->where('name', 'Electronics')->firstOrFail();
        $this->withSession(['locale' => 'fa'])->get('/')->assertSuccessful()->assertSee('کالای دیجیتال');
        $this->get('/categories')->assertSuccessful()->assertSee('تلفن همراه')->assertSee('مسیر صفحه');
        $this->assertSame('Electronics', $category->fresh()->name);
        $this->withSession(['locale' => 'en'])->get('/categories')->assertSee('Electronics')->assertSee('Phones');
    }

    public function test_custom_category_names_are_preserved(): void
    {
        app()->setLocale('fa');
        $this->assertSame('دستهٔ اختصاصی من', Category::localizedName('دستهٔ اختصاصی من'));
        $this->assertSame('Custom Collectibles', Category::localizedName('Custom Collectibles'));
    }

    public function test_persian_search_and_category_filter_keep_stable_ids(): void
    {
        $listing = $this->listing();
        $response = $this->withSession(['locale' => 'fa'])->get(route('listings.index', [
            'search' => 'گوشی آزمایشی', 'category' => $listing->category_id,
        ]));
        $response->assertSuccessful()->assertSee($listing->title)->assertSee('تلفن همراه');
        $this->assertSame(1, $response->viewData('filteredListingsTotal'));
        $this->assertSame((int) $listing->category_id, $response->viewData('categoryId'));
    }

    public function test_price_filter_and_empty_results_work_in_persian(): void
    {
        $this->listing();
        $response = $this->withSession(['locale' => 'fa'])->get(route('listings.index', [
            'search' => 'گوشی آزمایشی', 'min_price' => 2000,
        ]));
        $response->assertSuccessful()->assertSee('آگهی‌ای پیدا نشد')->assertSee('بازهٔ قیمت');
        $this->assertSame(0, $response->viewData('filteredListingsTotal'));
    }

    public function test_country_labels_do_not_change_filter_values(): void
    {
        $listing = $this->listing();
        $country = Country::query()->where('code', 'IR')->firstOrFail();
        $response = $this->withSession(['locale' => 'fa'])->get(route('listings.index', [
            'search' => 'گوشی آزمایشی', 'country' => $country->getKey(),
        ]));
        $response->assertSuccessful()->assertSee('ایران')->assertSee($listing->title);
        $this->assertSame('Iran', Country::browseSelection((int) $country->getKey(), null)['selected_country_name']);
        $this->assertSame('Iran', $country->fresh()->name);
    }

    public function test_authenticated_listing_actions_are_persian(): void
    {
        $listing = $this->listing();
        $buyer = User::query()->where('email', 'b@b.com')->firstOrFail();
        $this->actingAs($buyer)->withSession(['locale' => 'fa'])->get(route('listings.show', $listing))
            ->assertSuccessful()->assertSee('پیشنهاد قیمت')->assertSee('گزارش این آگهی')
            ->assertSee('هرزنامه')->assertSee('اشتراک‌گذاری')->assertSee('بدون تصویر')
            ->assertSee('dir="auto"', false)->assertDontSee('src=""', false);
    }

    public function test_custom_fields_translate_booleans_and_selects_without_rewriting_text(): void
    {
        $category = Category::query()->where('name', 'Phones')->firstOrFail();
        foreach ([['phase3_choice', 'Condition', 'select', ['New', 'Used']], ['phase3_bool', 'Warranty Available', 'boolean', null], ['phase3_text', 'Owner note', 'text', null]] as [$name, $label, $type, $options]) {
            ListingCustomField::query()->create(['category_id' => $category->getKey(), 'name' => $name, 'label' => $label, 'type' => $type, 'options' => $options, 'is_active' => true]);
        }
        app()->setLocale('fa');
        $fields = ListingCustomFieldSchemaBuilder::presentableValues((int) $category->getKey(), ['phase3_choice' => 'Used', 'phase3_bool' => true, 'phase3_text' => 'New']);
        $this->assertSame(['وضعیت کالا', 'دارای ضمانت', 'Owner note'], array_column($fields, 'label'));
        $this->assertSame(['کارکرده', 'بله', 'New'], array_column($fields, 'value'));
    }

    public function test_default_footer_links_are_localized_and_custom_titles_preserved(): void
    {
        $this->withSession(['locale' => 'fa'])->get('/')->assertSee('حریم خصوصی')->assertSee('راهنمای استفاده');
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $page->update(['title' => 'عنوان اختصاصی تیم']);
        $this->get('/')->assertSee('عنوان اختصاصی تیم');
    }

    public function test_free_and_state_labels_follow_the_selected_locale(): void
    {
        $listing = $this->listing();
        $listing->price = null;
        $listing->status = 'sold';
        app()->setLocale('fa');
        $this->assertSame('رایگان', $listing->panelPriceLabel());
        $this->assertSame('فروخته‌شده', $listing->statusLabel());
        app()->setLocale('en');
        $this->assertSame('Free', $listing->panelPriceLabel());
        $this->assertSame('Sold', $listing->statusLabel());
    }

    public function test_public_translation_keys_and_placeholders_match_english(): void
    {
        foreach (['Listing', 'Category', 'Offer', 'Report', 'Review', 'Promotion', 'Notification'] as $module) {
            $english = Arr::dot(require base_path("Modules/{$module}/lang/en/messages.php"));
            $persian = Arr::dot(require base_path("Modules/{$module}/lang/fa/messages.php"));
            $this->assertEqualsCanonicalizing(array_keys($english), array_keys($persian));
            foreach ($english as $key => $text) {
                preg_match_all('/:([a-z_]+)/i', $text, $expected);
                preg_match_all('/:([a-z_]+)/i', $persian[$key], $actual);
                $this->assertEqualsCanonicalizing($expected[1], $actual[1], "{$module}:{$key}");
            }
        }
    }
}
