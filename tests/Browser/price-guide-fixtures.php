<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingCustomField;
use Modules\Location\Models\City;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment('production')) {
    throw new RuntimeException('Development only.');
}
$path = storage_path('framework/price-guide-review.json');
$action = $argv[1] ?? '';
if ($action !== '') {
    $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if ($action === 'cleanup') {
        Listing::whereKey($state['listings'])->get()->each->forceDelete();
        if ($state['field_before']) {
            DB::table('listing_custom_fields')->where('id', $state['field_id'])->update($state['field_before']);
        } else {
            ListingCustomField::withTrashed()->findOrFail($state['field_id'])->forceDelete();
        }
        Category::findOrFail($state['category'])->forceDelete();
        User::whereKey($state['sellers'])->get()->each->forceDelete();
        foreach ([Listing::class => $state['listings'], Category::class => [$state['category']], User::class => $state['sellers']] as $type => $ids) {
            Activity::where('subject_type', $type)->whereIn('subject_id', $ids)->delete();
        }
        unlink($path);
        echo 'Price guide fixtures cleaned.'.PHP_EOL;
        exit;
    }
    if ($action === 'change-price') {
        Listing::findOrFail($state['listings'][0])->forceFill(['price' => 21000000])->save();
        echo 'Guide price changed.'.PHP_EOL;
        exit;
    }
    if ($action === 'hide-listing') {
        Listing::findOrFail($state['listings'][0])->forceFill(['status' => 'pending'])->save();
        echo 'Guide listing hidden.'.PHP_EOL;
        exit;
    }
    throw new RuntimeException('Unknown fixture action.');
}
$category = Category::create(['name' => 'آزمایش راهنمای قیمت', 'slug' => 'guide-fixture-'.Str::uuid(), 'parent_id' => Category::where('name', 'Electronics')->firstOrFail()->id, 'is_active' => true]);
$field = ListingCustomField::withTrashed()->where('name', 'condition')->first();
$before = $field?->getAttributes();
$field ??= new ListingCustomField;
$field->forceFill(['name' => 'condition', 'category_id' => $category->id, 'label' => 'Condition', 'type' => ListingCustomField::TYPE_SELECT, 'options' => ['New', 'Used'], 'is_active' => true, 'deleted_at' => null])->save();
$sellers = User::factory()->count(5)->create(['status' => 'active']);
$sellers->each(fn (User $user) => $user->syncRoles([]));
$city = City::query()->active()->with('country')->firstOrFail();
$listings = [];
for ($i = 1; $i <= 10; $i++) {
    $listings[] = Listing::unguarded(fn () => Listing::create(['title' => 'گوشی آکبند ۱۲۸ قیمت‌آزمایش', 'description' => 'آگهی عمومی آزمایش قیمت', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $sellers[($i - 1) % 5]->id, 'category_id' => $category->id, 'currency' => 'IRR', 'price' => $i * 1000000, 'city' => $city->name, 'country' => $city->country->code, 'custom_fields' => ['condition' => $i <= 2 ? 'New' : 'Used'], 'created_at' => now()->subDay(), 'expires_at' => now()->addDays(7)]))->id;
}
file_put_contents($path, json_encode(['category' => $category->id, 'city' => $city->id, 'listings' => $listings, 'sellers' => $sellers->pluck('id')->all(), 'field_id' => $field->id, 'field_before' => $before], JSON_THROW_ON_ERROR));
echo 'Price guide fixtures ready.'.PHP_EOL;
