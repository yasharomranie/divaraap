<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class PublicFeedOptimizationTest extends TestCase
{
    private function inventory(int $count): void
    {
        $user = User::where('email', 'a@a.com')->firstOrFail();
        $category = Category::where('name', 'Phones')->firstOrFail();
        $rows = [];
        $prefix = bin2hex(random_bytes(8));
        foreach (range(1, $count) as $i) {
            $rows[] = ['title' => 'Scale inventory '.$i, 'description' => 'Scale fixture', 'slug' => $prefix.'-'.$i, 'user_id' => $user->id, 'category_id' => $category->id, 'status' => 'active', 'is_featured' => $i <= 4, 'price' => $i, 'currency' => 'IRR', 'images' => json_encode(['/img/category/phone.png']), 'created_at' => now()->addMinute(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Listing::insert($chunk);
        }
    }

    public function test_large_feed_batches_media_and_reuses_count_without_changing_pagination(): void
    {
        $this->inventory(2000);
        $connection = Listing::resolveConnection();
        $connection->enableQueryLog();
        $connection->flushQueryLog();
        try {
            $response = $this->get('/listings?search=Scale+inventory&page=2')->assertOk();
            $queries = array_column($connection->getQueryLog(), 'query');
            $mediaQueries = array_filter($queries, fn ($sql) => str_contains($sql, 'from "media"') || str_contains($sql, 'from `media`'));
            $countQueries = array_filter($queries, fn ($sql) => preg_match('/^select count\(\*\).*from ["`]listings["`]/i', $sql));
            $this->assertCount(1, $mediaQueries);
            $this->assertCount(2, $countQueries);
            $listings = $response->viewData('listings');
            $this->assertSame(2000, $listings->total());
            $this->assertSame(2, $listings->currentPage());
            $this->assertCount(16, $listings);
            $this->assertSame(2000, $response->viewData('filteredListingsTotal'));
            foreach ($listings as $listing) {
                $this->assertTrue($listing->relationLoaded('media'));
            }
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
    }

    public function test_home_batches_images_and_card_serves_mobile_conversion_with_legacy_fallback(): void
    {
        $this->inventory(20);
        foreach ([Listing::homeFeatured(), Listing::homeRecent()] as $items) {
            foreach ($items as $item) {
                $this->assertTrue($item->relationLoaded('media'));
            }
        }
        $listing = Listing::homeRecent(1)->first();
        $media = new Media(['name' => 'Image fixture', 'file_name' => 'fixture.jpg', 'disk' => 'public', 'conversions_disk' => 'public', 'collection_name' => 'listing-images', 'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => ['card-mobile' => true, 'card-desktop' => true]]);
        $media->id = 987654;
        $media->model_id = $listing->id;
        $media->model_type = Listing::class;
        $media->setRelation('model', $listing);
        $listing->setRelation('media', collect([$media]));
        $html = Blade::render('<x-ui.listing-card :listing="$listing"/>', ['listing' => $listing]);
        $this->assertStringContainsString('<source media="(max-width: 1023px)"', $html);
        $this->assertStringContainsString('card-mobile.webp', $html);
        $this->assertStringContainsString('card-desktop.webp', $html);
        $this->assertStringContainsString('width="1080" height="810"', $html);
        $listing->setRelation('media', collect());
        $legacy = Blade::render('<x-ui.listing-card :listing="$listing"/>', ['listing' => $listing]);
        $this->assertStringContainsString('/img/category/phone.png', $legacy);
        $this->assertStringNotContainsString('<source ', $legacy);
    }
}
