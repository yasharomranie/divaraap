<?php

declare(strict_types=1);

namespace Tests\Feature;

use Modules\Site\App\Settings\GeneralSettings;
use Modules\Site\App\Support\HomeSlideDefaults;
use Tests\TestCase;

final class HomePresentationTest extends TestCase
{
    public function test_home_omits_retired_showcase_without_deleting_saved_settings(): void
    {
        $settings = app(GeneralSettings::class);
        $slide = HomeSlideDefaults::defaults()[0];
        $slide['title'] = 'A custom shop window';
        $slide['subtitle'] = 'A description saved by the administrator';
        $slide['badge'] = 'A custom badge';
        $slide['primary_button_text'] = 'Custom browse';
        $slide['secondary_button_text'] = 'Custom sell';
        $slide['image_path'] = 'home-slides/custom.jpg';
        $settings->home_slides = [$slide];
        $settings->save();
        $response = $this->get('/')->assertSuccessful();
        foreach (['A custom shop window', 'A description saved by the administrator', 'A custom badge', 'Custom browse', 'Custom sell', 'home-slides/custom.jpg'] as $text) {
            $response->assertDontSee($text, false);
        }
        $response->assertDontSee('data-home-showcase', false)->assertDontSee('data-home-controls', false);
        $this->assertSame([$slide], app(GeneralSettings::class)->refresh()->home_slides);
    }

    public function test_normalization_keeps_one_to_five_slides_and_the_saved_order(): void
    {
        $slides = [];
        for ($i = 0; $i < 6; $i++) {
            $slides[] = array_replace(HomeSlideDefaults::defaults()[0], ['title' => 'Slide '.$i]);
        }
        $this->assertCount(1, HomeSlideDefaults::normalize([$slides[0]]));
        $this->assertSame(['Slide 0', 'Slide 1', 'Slide 2', 'Slide 3', 'Slide 4'], array_column(HomeSlideDefaults::normalize($slides), 'title'));
        $this->assertSame(HomeSlideDefaults::defaults(), HomeSlideDefaults::normalize([['title' => '']]));
    }

    public function test_localization_translates_only_default_copy_and_keeps_custom_values(): void
    {
        app()->setLocale('fa');
        $default = HomeSlideDefaults::defaults()[0];
        $default['subtitle'] = 'My own subtitle';
        $slides = HomeSlideDefaults::forDisplay([$default]);
        $this->assertSame(__('site::home.slide_0_title'), $slides[0]['title']);
        $this->assertSame('My own subtitle', $slides[0]['subtitle']);
    }

    public function test_retired_slide_markup_is_not_rendered(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->home_slides = [array_replace(HomeSlideDefaults::defaults()[0], ['title' => '<script>window.injected=true</script>'])];
        $settings->save();
        $this->get('/')->assertSuccessful()->assertDontSee('<script>window.injected=true</script>', false)
            ->assertDontSee('&lt;script&gt;window.injected=true&lt;/script&gt;', false);
    }
}
