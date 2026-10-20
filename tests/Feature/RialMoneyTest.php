<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Rules\MonetaryAmount;
use App\Support\Money;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Modules\Listing\Models\Listing;
use Modules\Listing\Support\ListingPanelHelper;
use Modules\Offer\Models\Offer;
use Modules\Promotion\Filament\Admin\Resources\PromotionPlanResource\Pages\CreatePromotionPlanResourceRecord;
use Modules\Promotion\Models\PromotionOrder;
use Modules\Promotion\Models\PromotionPlan;
use Modules\Site\App\Settings\GeneralSettings;
use Modules\User\App\Models\User;
use Tests\TestCase;

class RialMoneyTest extends TestCase
{
    private function user(): User
    {
        return User::query()->where('email', 'b@b.com')->firstOrFail();
    }

    private function listing(string $price = '123456789012345678'): Listing
    {
        return Listing::createFromFrontend(['title' => 'آگهی تست مبلغ ریالی', 'price' => $price, 'currency' => 'USD'], $this->user()->id);
    }

    public function test_rial_default_storage_and_display_are_exact_without_conversion(): void
    {
        app()->setLocale('fa');
        $this->assertSame('IRR', ListingPanelHelper::defaultCurrency());
        $this->assertSame(['IRR' => 'ریال'], ListingPanelHelper::currencyOptions());
        $listing = $this->listing();
        $listing->refresh();
        $this->assertSame('123456789012345678.00', $listing->price);
        $this->assertSame('IRR', $listing->currency);
        $this->assertSame('۱۲۳٬۴۵۶٬۷۸۹٬۰۱۲٬۳۴۵٬۶۷۸ ریال', $listing->panelPriceLabel());
        $listing->updateFromPanel(['price' => '۱۲۳٬۴۵۶٬۷۸۹٬۰۱۲٬۳۴۵٬۶۷۸', 'currency' => 'USD']);
        $listing->refresh();
        $this->assertSame('123456789012345678.00', $listing->price);
        $this->assertSame('IRR', $listing->currency);
        app()->setLocale('en');
        $this->assertSame('123,456,789,012,345,678 IRR', $listing->panelPriceLabel());
    }

    public function test_http_wizard_remains_rial_when_legacy_settings_contain_foreign_currency(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->currencies = ['USD'];
        $settings->save();
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])->get(route('panel.listings.create'))->assertSuccessful();
        $this->assertSame('IRR', ListingPanelHelper::defaultCurrency());
        $this->assertSame(['IRR'], ListingPanelHelper::currencyCodes());
    }

    public function test_whole_rials_grouping_and_storage_limit_are_validated(): void
    {
        foreach (['۰', '۱۲۳٬۴۵۶', '١٢٣٤٥٦', '123,456', '0012300.00', '999999999999999999'] as $value) {
            $this->assertTrue(Validator::make(['price' => $value], ['price' => [new MonetaryAmount]])->passes(), $value);
        }
        foreach (['100.5', '-1', '1e10', '1000000000000000000', '12,34', '۱۲۳٫۴۵', 'NaN', '1.000'] as $value) {
            $this->assertFalse(Validator::make(['price' => $value], ['price' => [new MonetaryAmount]])->passes(), $value);
        }
        $this->assertSame('12300.00', Money::decimal('0012300.00'));
        $this->assertTrue(Money::valid('123.45', 'USD'));
        app()->setLocale('en');
        $this->assertSame('123.45 USD', Money::format('123.45', 'USD'));
    }

    public function test_legacy_currency_is_preserved_when_editing(): void
    {
        $listing = $this->listing('1200');
        $listing->forceFill(['currency' => 'TRY', 'price' => '123.45'])->save();
        $this->actingAs($this->user())->put(route('panel.listings.update', $listing), ['title' => $listing->title, 'status' => 'active', 'price' => '۲۳۴٫۵۶'])->assertSessionHasNoErrors()->assertRedirect();
        $listing->refresh();
        $this->assertSame('TRY', $listing->currency);
        $this->assertSame('234.56', $listing->price);
    }

    public function test_rial_edit_rejects_fraction_and_accepts_persian_large_amount(): void
    {
        $listing = $this->listing('100');
        $this->actingAs($this->user())->put(route('panel.listings.update', $listing), ['title' => $listing->title, 'status' => 'active', 'price' => '۱۰۰٫۵'])->assertSessionHasErrors('price');
        $this->assertSame('100.00', $listing->fresh()->price);
        $this->put(route('panel.listings.update', $listing), ['title' => $listing->title, 'status' => 'active', 'price' => '۱۲۳٬۴۵۶٬۷۸۹٬۰۱۲٬۳۴۵٬۶۷۸'])->assertSessionHasNoErrors();
        $this->assertSame('123456789012345678.00', $listing->fresh()->price);
    }

    public function test_offer_uses_listing_currency_and_exact_amount(): void
    {
        $listing = $this->listing();
        $listing->forceFill(['status' => 'active'])->save();
        $buyer = User::query()->where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($buyer)->post(route('offers.store', $listing), ['amount' => '۱۲۳٬۴۵۶٬۷۸۹٬۰۱۲٬۳۴۵٬۶۷۸', 'currency' => 'USD'])->assertSessionHasNoErrors()->assertRedirect();
        $offer = Offer::query()->where('listing_id', $listing->id)->firstOrFail();
        $this->assertSame('IRR', $offer->currency);
        $this->assertSame('123456789012345678.00', $offer->amount);
        $this->post(route('offers.store', $listing), ['amount' => '۱۲۳٫۵'])->assertSessionHasErrors('amount');
        $this->post(route('offers.store', $listing), ['amount' => '۰'])->assertSessionHasErrors('amount');
    }

    public function test_legacy_offer_ignores_forged_currency_and_keeps_decimal_precision(): void
    {
        $listing = $this->listing('1000');
        $listing->forceFill(['currency' => 'USD', 'status' => 'active'])->save();
        $buyer = User::query()->where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($buyer)->post(route('offers.store', $listing), ['amount' => '۱۲۳٫۴۵', 'currency' => 'IRR'])->assertSessionHasNoErrors();
        $offer = Offer::query()->where('listing_id', $listing->id)->firstOrFail();
        $this->assertSame('USD', $offer->currency);
        $this->assertSame('123.45', $offer->amount);
    }

    public function test_rial_filter_does_not_compare_foreign_currency(): void
    {
        $rial = $this->listing('1000');
        $foreign = $this->listing('1000');
        $foreign->forceFill(['currency' => 'USD'])->save();
        $ids = Listing::query()->whereIn('id', [$rial->id, $foreign->id])->forBrowseFilters(['min_price' => '1000', 'max_price' => '1000'])->pluck('id')->all();
        $this->assertSame([$rial->id], $ids);
        $this->assertSame([$rial->id], Listing::query()->whereIn('id', [$rial->id, $foreign->id])->applyBrowseSort('price_asc')->pluck('id')->all());
    }

    public function test_admin_rial_plan_creation_and_fraction_validation(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail());
        app()->setLocale('fa');
        $component = Livewire::test(CreatePromotionPlanResourceRecord::class)->fillForm(['name' => 'طرح تست ریالی', 'slug' => 'rial-money-test', 'price' => '۱۲۳٫۵']);
        $component->call('create')->assertHasFormErrors(['price']);
        $component->fillForm(['price' => '۱۲۳٬۴۵۶٬۷۸۹٬۰۱۲٬۳۴۵٬۶۷۸'])->call('create')->assertHasNoFormErrors();
        $plan = PromotionPlan::query()->where('slug', 'rial-money-test')->firstOrFail();
        $this->assertSame('IRR', $plan->currency);
        $this->assertSame('123456789012345678.00', $plan->price);
        $listing = $this->listing();
        $order = PromotionOrder::open($plan, $this->user()->id, $listing->id);
        $order->refresh();
        $this->assertSame('IRR', $order->currency);
        $this->assertSame('123456789012345678.00', $order->amount);
    }
}
