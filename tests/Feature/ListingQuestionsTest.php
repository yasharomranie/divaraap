<?php

declare(strict_types=1);

namespace Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Listing\Filament\Admin\Resources\ListingQuestionResource\Pages\ListListingQuestions;
use Modules\Listing\Models\Listing;
use Modules\Listing\Models\ListingQuestion;
use Modules\Notification\Models\UserNotification;
use Modules\User\App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ListingQuestionsTest extends TestCase
{
    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['name' => 'PRIVATE SELLER NAME', 'status' => 'active']);
        $this->buyer = User::factory()->create(['name' => 'PRIVATE BUYER NAME', 'status' => 'active']);
        $this->seller->syncRoles([]);
        $this->buyer->syncRoles([]);
        $this->listing = Listing::unguarded(fn () => Listing::create(['title' => 'Question test listing', 'description' => 'Question fixture', 'slug' => bin2hex(random_bytes(12)), 'status' => 'active', 'user_id' => $this->seller->id, 'category_id' => Category::where('name', 'Phones')->firstOrFail()->id, 'price' => 1000, 'currency' => 'IRR']));
    }

    private function ask(string $text = 'آیا این کالا هنوز ضمانت دارد؟'): ListingQuestion
    {
        return ListingQuestion::ask($this->buyer, $this->listing, $text, true);
    }

    private function admin(): void
    {
        $this->actingAs(User::where('email', 'a@a.com')->firstOrFail());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function published(string $text = 'آیا این کالا هنوز ضمانت دارد؟'): ListingQuestion
    {
        $record = $this->ask($text);
        $record->answerFor($this->seller, 'بله، تا پایان سال ضمانت دارد.', 1);
        $this->admin();
        $record->fresh()->moderate(true, null, 2);

        return $record->fresh();
    }

    private function invalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected validation rejection.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_real_routes_require_consent_active_buyer_and_valid_text_without_mass_assignment(): void
    {
        $url = route('listing-questions.store', $this->listing);
        $this->get(route('listing-questions.index', $this->listing))->assertOk()->assertSee(__('listing::questions.empty'));
        $this->post($url, ['question' => 'A proper public question'])->assertRedirect(route('login'));
        $this->actingAs($this->buyer);
        $this->postJson($url, ['question' => 'A proper public question'])->assertUnprocessable()->assertJsonValidationErrors('public_consent');
        $this->postJson($url, ['question' => 'short', 'public_consent' => 1])->assertUnprocessable();
        $this->post($url, ['question' => 'A proper public question', 'public_consent' => 1, 'state' => 'published', 'answer' => 'Forged answer', 'asker_id' => $this->seller->id])->assertRedirect(route('listing-questions.mine'));
        $record = ListingQuestion::firstOrFail();
        $this->assertSame('pending', $record->state);
        $this->assertNull($record->answer);
        $this->assertSame($this->buyer->id, $record->asker_id);
        $this->actingAs($this->seller)->postJson($url, ['question' => 'A proper public question', 'public_consent' => 1])->assertForbidden();
        $this->buyer->update(['status' => 'suspended']);
        $this->actingAs($this->buyer)->postJson($url, ['question' => 'Another proper public question', 'public_consent' => 1])->assertForbidden();
    }

    public function test_duplicate_submission_is_idempotent_and_pending_limit_does_not_block_replay(): void
    {
        $record = $this->ask();
        $this->assertSame('pending', $record->state);
        $this->assertSame(1, $record->revision);
        $duplicate = $this->ask('  آیا   این کالا هنوز ضمانت دارد؟  ');
        $this->assertSame($record->id, $duplicate->id);
        $this->ask('آیا این کالا همراه جعبه است؟');
        $this->ask('آیا رنگ دیگری از این کالا موجود است؟');
        $this->invalid(fn () => $this->ask('آیا ارسال به شهر دیگری هم دارید؟'));
        $this->assertSame($record->id, $this->ask()->id);
        $this->assertSame(3, UserNotification::where('user_id', $this->seller->id)->where('title', __('listing::questions.new_notice'))->count());
        $this->invalid(fn () => ListingQuestion::ask($this->buyer, $this->listing, '  کوتاه  ', true));
    }

    public function test_only_reviewed_pair_is_public_and_private_identity_and_audit_text_are_not_exposed(): void
    {
        $record = $this->ask('<script>privateQuestion()</script> Is there a warranty?');
        $url = route('listing-questions.index', $this->listing);
        $this->get($url)->assertDontSee('privateQuestion');
        $record->answerFor($this->seller, '<script>privateAnswer()</script> Yes, one year.', 1);
        $this->get($url)->assertDontSee('privateAnswer');
        $this->admin();
        $record->fresh()->moderate(true, null, 2);
        $this->get($url)->assertOk()->assertSee('&lt;script&gt;privateQuestion()', false)->assertSee('&lt;script&gt;privateAnswer()', false)->assertDontSee('<script>privateQuestion()', false)->assertDontSee('PRIVATE BUYER NAME')->assertDontSee($this->buyer->email)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer->id)->where('title', __('listing::questions.published_notice'))->count());
        $logs = Activity::where('subject_type', ListingQuestion::class)->get()->toJson();
        $this->assertStringNotContainsString('privateQuestion', $logs);
        $this->assertStringNotContainsString('privateAnswer', $logs);
    }

    public function test_answer_edit_hides_previous_public_pair_and_stale_moderation_is_rejected(): void
    {
        $record = $this->published();
        $record->answerFor($this->seller, 'پاسخ تازه و بررسی‌نشده', 3);
        $this->assertSame('answered', $record->fresh()->state);
        $this->get(route('listing-questions.index', $this->listing))->assertDontSee($record->question)->assertDontSee('پاسخ تازه');
        $this->invalid(fn () => $record->moderate(true, null, 3));
        $record->fresh()->moderate(true, null, 4);
        $this->get(route('listing-questions.index', $this->listing))->assertSee('پاسخ تازه');
    }

    public function test_withdrawal_requires_author_confirmation_and_fresh_revision_and_allows_fresh_submission(): void
    {
        $record = $this->published();
        $this->actingAs($this->buyer);
        $url = route('listing-questions.withdraw', $record);
        $this->deleteJson($url, ['revision' => 3])->assertUnprocessable();
        $this->deleteJson($url, ['revision' => 2, 'confirm_withdraw' => 1])->assertUnprocessable();
        $this->delete($url, ['revision' => 3, 'confirm_withdraw' => 1])->assertRedirect();
        $this->assertSoftDeleted($record);
        $this->get(route('listing-questions.index', $this->listing))->assertDontSee($record->question);
        $this->assertNotSame($record->id, $this->ask()->id);
    }

    public function test_other_users_and_seller_cannot_withdraw_and_author_cannot_answer(): void
    {
        $record = $this->ask();
        $answer = route('listing-questions.answer', $record);
        $this->actingAs($this->buyer)->postJson($answer, ['answer' => 'Forged seller response', 'revision' => 1, 'public_consent' => 1])->assertForbidden();
        $this->actingAs($this->seller)->deleteJson(route('listing-questions.withdraw', $record), ['revision' => 1, 'confirm_withdraw' => 1])->assertForbidden();
        $this->postJson($answer, ['answer' => 'Seller response', 'revision' => 1])->assertUnprocessable();
        $this->post($answer, ['answer' => 'Seller response', 'revision' => 1, 'public_consent' => 1])->assertRedirect();
        $this->postJson($answer, ['answer' => 'Stale response', 'revision' => 1, 'public_consent' => 1])->assertUnprocessable();
    }

    public function test_ineligible_listing_and_inactive_accounts_cannot_publish_or_show_public_content(): void
    {
        $record = $this->published();
        $url = route('listing-questions.index', $this->listing);
        foreach (['paused', 'pending', 'sold', 'rejected', 'scheduled'] as $state) {
            $this->listing->forceFill(['status' => $state])->saveQuietly();
            $this->get($url)->assertNotFound();
        }
        $this->listing->forceFill(['status' => 'active', 'expires_at' => now()->subDay()])->saveQuietly();
        $this->get($url)->assertNotFound();
        $this->listing->forceFill(['expires_at' => null])->saveQuietly();
        $this->buyer->update(['status' => 'suspended']);
        $this->get($url)->assertOk()->assertDontSee($record->question);
        $this->seller->update(['status' => 'suspended']);
        $this->get($url)->assertNotFound();
    }

    public function test_current_seller_binding_is_required_and_pending_question_cannot_be_published(): void
    {
        $record = $this->ask();
        $this->admin();
        $this->invalid(fn () => $record->moderate(true, null, 1));
        $record->answerFor($this->seller, 'پاسخ صاحب قبلی آگهی', 1);
        $record->fresh()->moderate(true, null, 2);
        $nextSeller = User::factory()->create(['status' => 'active']);
        $this->listing->forceFill(['user_id' => $nextSeller->id])->saveQuietly();
        $this->get(route('listing-questions.index', $this->listing))->assertDontSee('پاسخ صاحب قبلی');
        $record->fresh()->answerFor($nextSeller, 'پاسخ صاحب فعلی آگهی', 3);
        $record->fresh()->moderate(true, null, 4);
        $this->get(route('listing-questions.index', $this->listing))->assertSee('پاسخ صاحب فعلی');
    }

    public function test_moderation_requires_role_reject_reason_and_never_self_approves(): void
    {
        $record = $this->ask();
        $record->answerFor($this->seller, 'پاسخ آماده بررسی', 1);
        $this->actingAs($this->seller);
        try {
            $record->fresh()->moderate(true, null, 2);
            $this->fail('Expected denial.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->seller->assignRole('admin');
        try {
            $record->fresh()->moderate(true, null, 2);
            $this->fail('Expected self-review denial.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->admin();
        $this->invalid(fn () => $record->fresh()->moderate(false, ' ', 2));
        $record->fresh()->moderate(false, 'نیاز به اصلاح متن', 2);
        $this->assertSame('rejected', $record->fresh()->state);
        $this->get(route('listing-questions.index', $this->listing))->assertDontSee($record->question)->assertDontSee('نیاز به اصلاح متن');
    }

    public function test_private_lists_and_filament_moderation_are_isolated(): void
    {
        $record = $this->ask();
        $this->actingAs($this->buyer)->get(route('listing-questions.mine'))->assertSee($record->question)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs($this->seller)->get(route('listing-questions.mine'))->assertDontSee($record->question);
        $this->get(route('listing-questions.mine', ['role' => 'received']))->assertSee($record->question);
        $this->get('/admin/listing-questions')->assertForbidden();
        $moderator = User::factory()->create(['status' => 'active']);
        $moderator->syncRoles(['moderator']);
        $this->actingAs($moderator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/admin/listing-questions')->assertOk();
        $record->answerFor($this->seller, 'پاسخ برای بررسی در مدیریت', 1);
        Livewire::test(ListListingQuestions::class)->callTableAction('publish', $record->fresh(), ['revision' => 2])->assertHasNoTableActionErrors();
        $this->assertSame('published', $record->fresh()->state);
    }

    public function test_public_pagination_is_bounded_and_hidden_records_do_not_leak(): void
    {
        $rows = [];
        foreach (range(1, 16) as $i) {
            $rows[] = ['listing_id' => $this->listing->id, 'asker_id' => $this->buyer->id, 'question' => 'Public paginated question '.$i, 'answer' => 'Reviewed answer '.$i, 'answered_by' => $this->seller->id, 'fingerprint' => hash('sha256', 'page-'.$i), 'state' => 'published', 'revision' => 3, 'created_at' => now(), 'updated_at' => now()];
        }
        ListingQuestion::insert($rows);
        $response = $this->get(route('listing-questions.index', $this->listing))->assertOk();
        $this->assertCount(15, $response->viewData('records'));
        $this->assertSame(16, $response->viewData('records')->total());
        $this->get(route('listing-questions.index', ['listing' => $this->listing, 'page' => 2]))->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1);
        $category = Category::findOrFail($this->listing->category_id);
        $category->update(['is_active' => false]);
        $this->get(route('listing-questions.index', $this->listing))->assertNotFound();
    }

    public function test_support_advertising_staff_and_suspended_admin_cannot_moderate(): void
    {
        foreach (['support', 'advertising-manager'] as $role) {
            $user = User::factory()->create(['status' => 'active']);
            $user->syncRoles([$role]);
            $this->actingAs($user)->get('/admin/listing-questions')->assertForbidden();
        }
        $this->admin();
        auth()->user()->update(['status' => 'suspended']);
        $this->get('/admin/listing-questions')->assertForbidden();
    }
}
