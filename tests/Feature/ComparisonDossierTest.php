<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Category\Models\Category;
use Modules\Favorite\App\Models\ComparisonDossier;
use Modules\Listing\Models\Listing;
use Modules\User\App\Models\User;
use Tests\TestCase;

final class ComparisonDossierTest extends TestCase
{
    private User $owner;

    private User $seller;

    private int $category;

    private Collection $selection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['status' => 'active']);
        $this->seller = User::factory()->create(['status' => 'active']);
        $this->owner->syncRoles([]);
        $this->seller->syncRoles([]);
        $this->category = Category::where('name', 'Phones')->firstOrFail()->id;
        $this->selection = collect([$this->listing(['title' => 'First phone', 'price' => 1000000]), $this->listing(['title' => 'Second phone', 'price' => 2000000])]);
    }

    private function listing(array $data = []): Listing
    {
        return Listing::unguarded(fn () => Listing::create(array_replace(['title' => 'Another phone', 'description' => 'Public phone details', 'slug' => (string) Str::uuid(), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => $this->category, 'currency' => 'IRR', 'price' => 1000000], $data)));
    }

    private function input(array $data = []): array
    {
        return array_replace(['label' => 'Private family shortlist', 'request_key' => (string) Str::uuid(), 'consent' => '1'], $data);
    }

    private function dossier(array $input = []): ComparisonDossier
    {
        return ComparisonDossier::createFor($this->owner, $this->input($input), $this->selection);
    }

    public function test_authenticated_creation_uses_session_selection_ignores_spoofing_and_preserves_comparison(): void
    {
        $this->get(route('dossiers.index'))->assertRedirect(route('login'));
        $input = $this->input(['user_id' => $this->seller->id, 'listing_ids' => [999], 'sharing' => false, 'expires_at' => now()->addYear()]);
        $this->actingAs($this->owner)->withSession(['listing_comparison' => $this->selection->pluck('id')->all()])->post(route('dossiers.store'), $input)->assertRedirect(route('dossiers.index'));
        $record = ComparisonDossier::where('user_id', $this->owner->id)->firstOrFail();
        $this->assertSame($this->selection->pluck('id')->all(), $record->listing_ids);
        $this->assertTrue($record->sharing);
        $this->assertSame(30, (int) round(now()->diffInDays($record->expires_at)));
        $this->get(route('comparison.index'))->assertViewHas('listings', fn ($items) => $items->count() === 2);
        $this->get(route('dossiers.index'))->assertOk()->assertSee($record->label)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_consent_bounded_private_name_and_two_to_three_same_category_are_required(): void
    {
        $this->actingAs($this->owner)->withSession(['listing_comparison' => $this->selection->pluck('id')->all()]);
        foreach ([['consent' => '0'], ['label' => '   '], ['label' => str_repeat('x', 101)]] as $bad) {
            $this->post(route('dossiers.store'), $this->input($bad))->assertSessionHasErrors(array_keys($bad));
        }
        $this->withSession(['listing_comparison' => [$this->selection->first()->id]])->post(route('dossiers.store'), $this->input())->assertSessionHasErrors('comparison');
        $other = $this->listing(['category_id' => Category::where('name', 'Cars')->firstOrFail()->id]);
        $this->expectException(ValidationException::class);
        ComparisonDossier::createFor($this->owner, $this->input(), collect([$this->selection->first(), $other]));
    }

    public function test_identical_retry_does_not_reenable_revoked_link_and_changed_replay_is_rejected(): void
    {
        $input = $this->input();
        $record = ComparisonDossier::createFor($this->owner, $input, $this->selection);
        $record->changeFor($this->owner, 'revoke', 1);
        $replayed = ComparisonDossier::createFor($this->owner, $input, $this->selection);
        $this->assertSame($record->id, $replayed->id);
        $this->assertFalse($replayed->sharing);
        $this->assertSame(1, ComparisonDossier::where('user_id', $this->owner->id)->count());
        $this->expectException(ValidationException::class);
        ComparisonDossier::createFor($this->owner, [...$input, 'label' => 'Changed'], $this->selection);
    }

    public function test_guest_share_is_read_only_has_live_public_values_and_never_private_name_or_identity(): void
    {
        $record = $this->dossier(['label' => 'PRIVATE_LABEL_SECRET']);
        $this->get($record->shareUrl())->assertOk()->assertSee('First phone')->assertSee('Second phone')->assertDontSee('PRIVATE_LABEL_SECRET')->assertDontSee($this->owner->email)->assertDontSee($this->seller->email)->assertDontSee(route('comparison.remove', $this->selection->first()), false)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->selection->first()->forceFill(['title' => 'Edited public phone', 'price' => 3500000])->save();
        $this->get($record->shareUrl())->assertOk()->assertSee('Edited public phone')->assertDontSee('First phone');
        $this->get($record->shareUrl().'?differences=1')->assertOk()->assertViewHas('rows', fn ($rows) => count($rows) === 1 && $rows[0]['key'] === 'price');
        $this->assertNotSame($record->share_token, DB::table('comparison_dossiers')->where('id', $record->id)->value('share_token'));
        $this->assertArrayNotHasKey('share_token', $record->toArray());
        $this->get('/shared-comparisons/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/shared-comparisons/bad')->assertNotFound();
    }

    public function test_unavailable_or_recategorized_listings_never_leak_saved_details(): void
    {
        $third = $this->listing(['title' => 'Third phone']);
        $this->selection->push($third);
        $record = $this->dossier();
        $third->forceFill(['status' => 'sold'])->save();
        $this->get($record->shareUrl())->assertOk()->assertDontSee('Third phone')->assertSee(__('favorite::dossier.missing'));
        $this->selection->first()->forceFill(['category_id' => Category::where('name', 'Cars')->firstOrFail()->id])->save();
        $this->get($record->shareUrl())->assertNotFound();
        $this->selection->first()->forceFill(['category_id' => $this->category])->save();
        $this->selection->last()->delete();
        $this->get($record->shareUrl())->assertOk();
        $this->selection->first()->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->get($record->shareUrl())->assertNotFound();
    }

    public function test_owner_seller_and_category_activation_are_rechecked_for_each_shared_visit(): void
    {
        $record = $this->dossier();
        $this->owner->forceFill(['status' => 'suspended'])->save();
        $this->get($record->shareUrl())->assertNotFound();
        $this->owner->forceFill(['status' => 'active'])->save();
        $this->seller->forceFill(['status' => 'suspended'])->save();
        $this->get($record->shareUrl())->assertNotFound();
        $this->seller->forceFill(['status' => 'active'])->save();
        Category::whereKey($this->category)->update(['is_active' => false]);
        $this->get($record->shareUrl())->assertNotFound();
    }

    public function test_rotation_revokes_old_link_extends_expiry_and_rejects_stale_actions(): void
    {
        $record = $this->dossier();
        $old = $record->shareUrl();
        $this->actingAs($this->owner)->post(route('dossiers.change', $record), ['action' => 'revoke', 'revision' => 1])->assertRedirect();
        $this->get($old)->assertNotFound();
        $this->post(route('dossiers.change', $record), ['action' => 'rotate', 'revision' => 1])->assertStatus(409);
        $this->post(route('dossiers.change', $record), ['action' => 'rotate', 'revision' => 2])->assertRedirect();
        $this->assertNotSame($old, $record->fresh()->shareUrl());
        $this->get($old)->assertNotFound();
        $this->get($record->fresh()->shareUrl())->assertOk();
        $this->travel(31)->days();
        $this->get($record->fresh()->shareUrl())->assertNotFound();
        $record->refresh()->changeFor($this->owner, 'rotate', 3);
        $this->get($record->shareUrl())->assertOk();
        $this->assertSame(30, (int) round(now()->diffInDays($record->expires_at)));
    }

    public function test_other_accounts_cannot_manage_and_inactive_accounts_cannot_create(): void
    {
        $record = $this->dossier();
        $this->actingAs($this->seller)->post(route('dossiers.change', $record), ['action' => 'remove', 'revision' => 1])->assertNotFound();
        $this->get(route('dossiers.index'))->assertDontSee($record->label);
        $this->owner->forceFill(['status' => 'suspended'])->save();
        $this->actingAs($this->owner)->get(route('dossiers.create'))->assertForbidden();
        $this->post(route('dossiers.store'), $this->input())->assertForbidden();
    }

    public function test_catalog_limit_includes_disabled_and_expired_and_remove_frees_slot(): void
    {
        $record = $this->dossier();
        $record->changeFor($this->owner, 'revoke', 1);
        for ($i = 0; $i < 19; $i++) {
            $this->dossier();
        }
        DB::table('comparison_dossiers')->where('user_id', $this->owner->id)->update(['expires_at' => now()->subSecond()]);
        $this->actingAs($this->owner)->withSession(['listing_comparison' => $this->selection->pluck('id')->all()])->post(route('dossiers.store'), $this->input())->assertSessionHasErrors('label');
        $record->changeFor($this->owner, 'remove', 2);
        $this->assertNotNull(ComparisonDossier::withTrashed()->findOrFail($record->id)->deleted_at);
        $this->dossier();
        $this->assertSame(20, ComparisonDossier::where('user_id', $this->owner->id)->count());
        $this->get($record->shareUrl())->assertNotFound();
    }

    public function test_shared_output_and_private_labels_are_escaped_without_touching_viewer_selection(): void
    {
        $this->selection->first()->forceFill(['title' => '<script>dossierTest()</script>', 'custom_fields' => ['sample' => '<img src=x onerror=dossierTest()>']])->save();
        $record = $this->dossier(['label' => '<script>privateTest()</script>']);
        $this->withSession(['listing_comparison' => [123]])->get($record->shareUrl())->assertOk()->assertSee('&lt;script&gt;dossierTest()&lt;/script&gt;', false)->assertDontSee('<script>dossierTest()</script>', false)->assertSessionHas('listing_comparison', [123]);
        $this->actingAs($this->owner)->get(route('dossiers.index'))->assertSee('&lt;script&gt;privateTest()&lt;/script&gt;', false)->assertDontSee('<script>privateTest()</script>', false);
    }
}
