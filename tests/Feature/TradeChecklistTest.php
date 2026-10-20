<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Category\Filament\Admin\Resources\CategoryResource\Pages\EditCategory;
use Modules\Category\Models\Category;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingChecklistProgress;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class TradeChecklistTest extends TestCase
{
    private User $buyer;

    private Listing $listing;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buyer = User::create(['name' => 'Checklist fixture', 'email' => Str::uuid().'@example.test', 'password' => 'checklist-password', 'status' => 'active']);
        $this->category = Category::create(['name' => 'Checklist fixture', 'slug' => (string) Str::uuid(), 'is_active' => true, 'trade_checklist_mode' => 'custom', 'trade_checklist' => [['fa' => 'بررسی مدرک', 'en' => 'Check documentation'], ['fa' => 'بررسی کالا', 'en' => 'Inspect item']]]);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Checklist listing', 'description' => 'Fixture', 'slug' => (string) Str::uuid(), 'user_id' => User::where('email', 'b@b.com')->firstOrFail()->id, 'category_id' => $this->category->id, 'price' => 100, 'currency' => 'IRR', 'status' => 'active']));
    }

    private function payload(array $checked = []): array
    {
        $state = ListingChecklistProgress::stateFor($this->listing->fresh(), $this->buyer);

        return ['revision' => $state['revision'], 'version' => $state['version'], 'checked' => $checked];
    }

    public function test_guest_gets_guide_without_personal_data_and_cannot_save(): void
    {
        $response = $this->get(route('listings.checklist', $this->listing))->assertOk()->assertSee('Check documentation')->assertDontSee('Save my checklist');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post(route('listings.checklist.save', $this->listing), $this->payload())->assertRedirect(route('login'));
        $this->assertSame(0, ListingChecklistProgress::where('listing_id', $this->listing->id)->count());
        $this->get(route('listings.show', $this->listing))->assertOk()->assertSee(route('listings.checklist', $this->listing), false);
    }

    public function test_progress_is_private_server_owned_and_empty_submission_clears_it(): void
    {
        $state = ListingChecklistProgress::stateFor($this->listing, $this->buyer);
        $key = $state['items'][0]['key'];
        $url = route('listings.checklist.save', $this->listing);
        $this->actingAs($this->buyer)->post($url, $this->payload([$key]) + ['user_id' => $this->listing->user_id])->assertRedirect(route('listings.checklist', $this->listing));
        $row = ListingChecklistProgress::where('listing_id', $this->listing->id)->sole();
        $this->assertSame($this->buyer->id, (int) $row->user_id);
        $this->assertSame([$key], $row->checked_keys);
        $seller = User::findOrFail($this->listing->user_id);
        $this->assertSame([], ListingChecklistProgress::stateFor($this->listing, $seller)['checked']);
        $this->assertSame('none', ListingChecklistProgress::stateFor($this->listing, null)['version']);
        $this->post($url, array_diff_key($this->payload(), ['checked' => true]))->assertRedirect();
        $this->assertSame([], $row->fresh()->checked_keys);
        $this->assertSame('active', $this->listing->fresh()->statusValue());
    }

    public function test_stale_tab_cannot_overwrite_newer_progress(): void
    {
        $old = $this->payload();
        $key = ListingChecklistProgress::stateFor($this->listing, $this->buyer)['items'][0]['key'];
        $url = route('listings.checklist.save', $this->listing);
        $this->actingAs($this->buyer)->post($url, array_replace($old, ['checked' => [$key]]))->assertRedirect();
        $this->postJson($url, $old)->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame([$key], ListingChecklistProgress::stateFor($this->listing, $this->buyer)['checked']);
        $this->assertSame(1, ListingChecklistProgress::where('listing_id', $this->listing->id)->count());
    }

    public function test_content_or_listing_category_change_resets_checks_and_rejects_old_revision(): void
    {
        $state = ListingChecklistProgress::stateFor($this->listing, $this->buyer);
        $url = route('listings.checklist.save', $this->listing);
        $this->actingAs($this->buyer)->post($url, $this->payload([$state['items'][0]['key']]))->assertRedirect();
        $old = $this->payload();
        $this->category->update(['trade_checklist' => [['fa' => 'مورد تازه', 'en' => 'New item']]]);
        $fresh = ListingChecklistProgress::stateFor($this->listing, $this->buyer);
        $this->assertTrue($fresh['changed']);
        $this->assertSame([], $fresh['checked']);
        $this->postJson($url, $old)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->post($url, $this->payload([$fresh['items'][0]['key']]))->assertRedirect();
        $this->assertFalse(ListingChecklistProgress::stateFor($this->listing, $this->buyer)['changed']);
        $other = Category::create(['name' => 'Other category', 'slug' => (string) Str::uuid(), 'is_active' => true, 'trade_checklist_mode' => 'custom', 'trade_checklist' => $this->category->trade_checklist]);
        $this->listing->forceFill(['category_id' => $other->id])->save();
        $this->assertTrue(ListingChecklistProgress::stateFor($this->listing->fresh(), $this->buyer)['changed']);
    }

    public function test_inheritance_override_disabling_and_inactive_or_cyclic_categories_fail_closed(): void
    {
        $child = Category::create(['name' => 'Child', 'slug' => (string) Str::uuid(), 'parent_id' => $this->category->id, 'is_active' => true]);
        $inherited = Category::tradeChecklistDefinition($child->id);
        $this->assertSame('Check documentation', $inherited['items'][0]['en']);
        $this->category->update(['trade_checklist_mode' => 'disabled']);
        $this->assertSame([], Category::tradeChecklistDefinition($child->id)['items']);
        $child->update(['trade_checklist_mode' => 'custom', 'trade_checklist' => [['fa' => 'ویژه', 'en' => 'Override']]]);
        $this->assertSame('Override', Category::tradeChecklistDefinition($child->id)['items'][0]['en']);
        $child->update(['trade_checklist_mode' => 'inherit']);
        $this->category->update(['trade_checklist_mode' => 'inherit', 'parent_id' => $child->id]);
        $this->assertSame([], Category::tradeChecklistDefinition($child->id)['items']);
        $this->category->update(['parent_id' => null, 'is_active' => false]);
        $this->assertSame([], Category::tradeChecklistDefinition($child->id)['items']);
    }

    public function test_default_guides_are_category_specific_and_revision_is_locale_independent(): void
    {
        $phone = Category::where('slug', 'electronics-phones')->firstOrFail();
        $car = Category::where('slug', 'vehicles-cars')->firstOrFail();
        app()->setLocale('fa');
        $definition = Category::tradeChecklistDefinition($phone->id);
        $this->assertCount(6, $definition['items']);
        $this->assertStringContainsString('device', implode(' ', array_column($definition['items'], 'en')));
        $this->assertStringContainsString('vehicle', implode(' ', array_column(Category::tradeChecklistDefinition($car->id)['items'], 'en')));
        app()->setLocale('en');
        $this->assertSame($definition['revision'], Category::tradeChecklistDefinition($phone->id)['revision']);
        $this->assertCount(3, Category::tradeChecklistDefinition(null)['items']);
    }

    public function test_unknown_keys_duplicates_and_nested_values_do_not_change_saved_progress(): void
    {
        $this->actingAs($this->buyer);
        $url = route('listings.checklist.save', $this->listing);
        $key = ListingChecklistProgress::stateFor($this->listing, $this->buyer)['items'][0]['key'];
        foreach ([[str_repeat('a', 64)], [$key, $key], [['nested']], array_fill(0, 21, $key)] as $checked) {
            $this->postJson($url, $this->payload($checked))->assertUnprocessable();
        }
        $this->assertSame(0, ListingChecklistProgress::where('listing_id', $this->listing->id)->count());
    }

    public function test_disabled_or_deleted_category_and_unpublished_listing_are_not_exposed(): void
    {
        $this->category->update(['trade_checklist_mode' => 'disabled']);
        $this->get(route('listings.checklist', $this->listing))->assertNotFound();
        $this->actingAs($this->buyer)->postJson(route('listings.checklist.save', $this->listing), ['revision' => str_repeat('a', 64), 'version' => 'none'])->assertUnprocessable();
        $this->category->update(['trade_checklist_mode' => 'inherit']);
        $this->listing->forceFill(['status' => 'paused'])->save();
        $this->get(route('listings.checklist', $this->listing))->assertNotFound();
        $this->postJson(route('listings.checklist.save', $this->listing), ['revision' => str_repeat('a', 64), 'version' => 'none'])->assertNotFound();
        $this->actingAs(User::findOrFail($this->listing->user_id))->get(route('listings.checklist', $this->listing))->assertOk();
        $this->category->delete();
        $this->get(route('listings.checklist', $this->listing))->assertNotFound();
    }

    public function test_inactive_actor_cannot_save_and_soft_deleted_progress_can_be_recreated(): void
    {
        $this->actingAs($this->buyer)->post(route('listings.checklist.save', $this->listing), $this->payload())->assertRedirect();
        $row = ListingChecklistProgress::where('listing_id', $this->listing->id)->sole();
        $row->delete();
        $this->post(route('listings.checklist.save', $this->listing), $this->payload())->assertRedirect();
        $this->assertSame(1, ListingChecklistProgress::where('listing_id', $this->listing->id)->count());
        $this->assertSame(2, ListingChecklistProgress::withTrashed()->where('listing_id', $this->listing->id)->count());
        $this->buyer->forceFill(['status' => 'suspended'])->save();
        $this->postJson(route('listings.checklist.save', $this->listing), $this->payload())->assertForbidden();
    }

    public function test_admin_can_configure_bilingual_items_and_regular_user_cannot(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = User::where('email', 'a@a.com')->firstOrFail();
        $this->actingAs($admin);
        Livewire::test(EditCategory::class, ['record' => $this->category->id])->fillForm(['trade_checklist_mode' => 'custom', 'trade_checklist' => [['fa' => 'آزمایش مدیریت', 'en' => 'Admin checklist']]])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Admin checklist', Category::tradeChecklistDefinition($this->category->id)['items'][0]['en']);
        $this->actingAs($this->buyer)->get('/admin/categories/'.$this->category->id.'/edit')->assertForbidden();
    }

    public function test_admin_validation_rejects_missing_translation_and_excessive_items_and_labels_are_escaped(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Livewire::test(EditCategory::class, ['record' => $this->category->id])->fillForm(['trade_checklist_mode' => 'custom', 'trade_checklist' => [['fa' => 'بدون ترجمه', 'en' => '']]])->call('save')->assertHasFormErrors();
        Livewire::test(EditCategory::class, ['record' => $this->category->id])->fillForm(['trade_checklist_mode' => 'custom', 'trade_checklist' => array_fill(0, 21, ['fa' => 'بیش از حد', 'en' => 'Too many'])])->call('save')->assertHasFormErrors();
        $this->category->update(['trade_checklist' => [['fa' => '<script>bad()</script>', 'en' => '<script>bad()</script>']]]);
        $this->get(route('listings.checklist', $this->listing))->assertOk()->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false)->assertDontSee('<script>bad()</script>', false);
    }
}
