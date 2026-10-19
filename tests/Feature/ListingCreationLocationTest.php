<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\Country;
use Modules\Panel\App\Livewire\PanelQuickListingForm;
use Modules\User\App\Models\User;
use Tests\TestCase;

class ListingCreationLocationTest extends TestCase
{
    private function wizard()
    {
        $this->actingAs(User::query()->where('email', 'b@b.com')->firstOrFail())->withSession(['locale' => 'fa']);

        return Livewire::test(PanelQuickListingForm::class)
            ->set('selectedCategoryId', (int) Category::query()->where('name', 'Phones')->firstOrFail()->getKey())
            ->set('selectedCountryId', (int) Country::query()->where('code', 'IR')->firstOrFail()->getKey())
            ->set('listingTitle', 'موقعیت اختیاری آزمایشی')
            ->set('description', 'آگهی آزمایشی موقعیت اختیاری')
            ->set('price', '1500');
    }

    public function test_listing_can_be_published_without_a_map_location(): void
    {
        $this->wizard()->set('photos', [UploadedFile::fake()->image('optional.jpg')])
            ->call('publishListing')->assertHasNoErrors();
        $listing = Listing::query()->where('title', 'موقعیت اختیاری آزمایشی')->firstOrFail();
        $this->assertNull($listing->latitude);
        $this->assertNull($listing->longitude);
        $listing->clearMediaCollection('listing-images');
    }

    public function test_selected_location_is_rounded_and_saved_with_pending_listing(): void
    {
        $this->wizard()->set('photos', [UploadedFile::fake()->image('location.jpg')])
            ->set('latitude', '35.689234')->set('longitude', '51.389876')
            ->call('goToPreviewStep')->assertHasNoErrors()->assertSee('موقعیت تقریبی روی نقشه انتخاب شده است.')
            ->call('publishListing')->assertHasNoErrors();
        $listing = Listing::query()->where('title', 'موقعیت اختیاری آزمایشی')->firstOrFail();
        $this->assertEquals(35.689, $listing->latitude);
        $this->assertEquals(51.390, $listing->longitude);
        $this->assertSame('pending', $listing->statusValue());
        $listing->clearMediaCollection('listing-images');
    }

    public function test_partial_invalid_and_out_of_range_coordinates_cannot_pass_details(): void
    {
        foreach ([['35', '', 'longitude'], ['', '51', 'latitude'], ['91', '51', 'latitude'], ['35', '181', 'longitude'], ['bad', '51', 'latitude']] as [$lat, $lng, $error]) {
            $this->wizard()->set('latitude', $lat)->set('longitude', $lng)->set('currentStep', 3)
                ->call('goToPreviewStep')->assertHasErrors([$error])->assertSet('currentStep', 3);
        }
    }

    public function test_location_is_restored_in_draft_and_can_be_cleared(): void
    {
        $this->wizard()->set('latitude', '0')->set('longitude', '0')->call('goToPreviewStep')->assertHasNoErrors();
        Livewire::test(PanelQuickListingForm::class)->assertSet('latitude', '0')->assertSet('longitude', '0')
            ->set('latitude', '')->set('longitude', '')->call('goToPreviewStep')->assertHasNoErrors();
        Livewire::test(PanelQuickListingForm::class)->assertSet('latitude', '')->assertSet('longitude', '');
    }
}
