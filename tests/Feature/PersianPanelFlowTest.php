<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Category\Models\Category;
use Modules\Conversation\App\Events\InboxMessageCreated;
use Modules\Conversation\App\Models\Conversation;
use Modules\Conversation\App\Support\QuickMessageCatalog;
use Modules\Listing\Models\Listing;
use Modules\Location\Models\Country;
use Modules\Notification\Models\UserNotification;
use Modules\Panel\App\Livewire\PanelQuickListingForm;
use Modules\User\App\Models\User;
use Modules\Video\Models\Video;
use Tests\TestCase;

class PersianPanelFlowTest extends TestCase
{
    private function user(string $email = 'b@b.com'): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function conversation(): Conversation
    {
        $listing = Listing::query()->ownedByUser($this->user('a@a.com')->getKey())->firstOrFail();

        return Conversation::query()->create([
            'listing_id' => $listing->getKey(),
            'seller_id' => $listing->user_id,
            'buyer_id' => $this->user()->getKey(),
        ]);
    }

    public function test_panel_pages_have_persian_navigation_and_english_still_works(): void
    {
        $this->actingAs($this->user())->withSession(['locale' => 'fa']);
        foreach (['/panel', '/panel/my-listings', '/panel/offers', '/panel/inbox', '/panel/videos', '/panel/my-profile', '/panel/notifications'] as $uri) {
            $this->get($uri)->assertSuccessful()->assertSee('آگهی‌های من')->assertSee('صندوق پیام‌ها')->assertSee('dir="rtl"', false);
        }
        $this->get('/favorites')->assertSuccessful()->assertSee('آگهی‌های ذخیره‌شده');
        $this->withSession(['locale' => 'en'])->get('/panel')->assertSee('My listings')->assertSee('dir="ltr"', false);
    }

    public function test_wizard_reports_localized_missing_photos_and_category(): void
    {
        $this->actingAs($this->user())->withSession(['locale' => 'fa']);
        Livewire::test(PanelQuickListingForm::class)
            ->call('publishListing')->assertHasErrors(['photos'])
            ->assertSet('currentStep', 1)->assertSee('عکس‌ها');
        Livewire::test(PanelQuickListingForm::class)
            ->set('currentStep', 2)->call('goToDetailsStep')->assertHasErrors(['selectedCategoryId' => 'required'])
            ->assertSee('یک دسته‌بندی انتخاب کنید.');
    }

    public function test_wizard_searches_persian_category_names_and_keeps_ids(): void
    {
        $this->actingAs($this->user())->withSession(['locale' => 'fa']);
        $parent = Category::query()->where('name', 'Electronics')->firstOrFail();
        $phone = Category::query()->where('name', 'Phones')->firstOrFail();
        $component = Livewire::test(PanelQuickListingForm::class)
            ->set('currentStep', 2)->call('enterCategory', (int) $parent->getKey())
            ->set('categorySearch', 'تلفن')->assertSee('تلفن همراه');
        $this->assertSame([(int) $phone->getKey()], array_column($component->instance()->currentCategories, 'id'));
    }

    public function test_wizard_publishes_persian_listing_with_photo_and_raw_location(): void
    {
        $this->actingAs($this->user())->withSession(['locale' => 'fa']);
        $country = Country::query()->where('code', 'IR')->firstOrFail();
        $category = Category::query()->where('name', 'Phones')->firstOrFail();
        $title = 'آگهی فارسی با تصویر آزمایشی';
        $component = Livewire::test(PanelQuickListingForm::class)
            ->set('photos', [UploadedFile::fake()->image('phone.jpg')])
            ->set('selectedCategoryId', (int) $category->getKey())
            ->set('selectedCountryId', (int) $country->getKey())
            ->set('selectedCityId', null)->set('listingTitle', $title)
            ->set('description', 'توضیحات فارسی ثبت آگهی')->set('price', '1500')
            ->call('goToPreviewStep')->assertHasNoErrors()->assertSet('currentStep', 4)->assertSee('پیش‌نمایش')->assertSee('ایران')
            ->call('publishListing')->assertHasNoErrors()->assertSet('publishError', null);
        $listing = Listing::query()->where('title', $title)->firstOrFail();
        $component->assertRedirect(route('panel.listings.edit', $listing));
        $this->assertSame('Iran', $listing->country);
        $this->assertSame('pending', $listing->statusValue());
        $this->assertCount(1, $listing->getMedia('listing-images'));
        $listing->clearMediaCollection('listing-images');
    }

    public function test_listing_edit_saves_persian_content_and_localizes_errors(): void
    {
        $user = $this->user();
        $listing = Listing::query()->ownedByUser($user->getKey())->firstOrFail();
        $this->actingAs($user)->withSession(['locale' => 'fa']);
        $this->get(route('panel.listings.edit', $listing))->assertSee('در انتظار تأیید')->assertSee('فروخته‌شده');
        $this->put(route('panel.listings.update', $listing), ['title' => '', 'status' => 'invalid', 'price' => 'abc'])
            ->assertSessionHasErrors(['title', 'status', 'price']);
        $this->assertSame('عنوان آگهی را وارد کنید.', session('errors')->first('title'));
        $this->put(route('panel.listings.update', $listing), ['title' => 'عنوان ویرایش‌شدهٔ فارسی', 'description' => 'توضیح فارسی', 'status' => 'active', 'price' => 1200])
            ->assertRedirect()->assertSessionHas('success', 'آگهی به‌روز شد.');
        $this->assertSame('عنوان ویرایش‌شدهٔ فارسی', $listing->fresh()->title);
    }

    public function test_persian_messages_use_api_contract_and_broadcast_to_both_participants(): void
    {
        Event::fake([InboxMessageCreated::class]);
        $conversation = $this->conversation();
        $body = 'سلام، موجود است؟ email@example.com';
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])
            ->postJson(route('conversations.messages.send', $conversation), ['message' => $body])
            ->assertSuccessful()->assertJsonPath('message.body', $body)->assertJsonPath('message.is_mine', true)
            ->assertJsonStructure(['message' => ['id', 'created_at', 'sender_id']]);
        $this->assertSame($body, $conversation->messages()->latest('id')->firstOrFail()->body);
        Event::assertDispatched(InboxMessageCreated::class, fn ($event): bool => $event->userId === (int) $conversation->seller_id && $event->payload['message']['body'] === $body && ! $event->payload['message']['is_mine']);
        Event::assertDispatchedTimes(InboxMessageCreated::class, 2);
        $this->actingAs($this->user('a@a.com'))->get(route('panel.inbox.index', ['conversation' => $conversation->getKey()]))
            ->assertSee($body)->assertSee('این آگهی هنوز موجود است؟')->assertSee('dir="auto"', false);
        $this->assertSame(0, $conversation->unreadCountForParticipant((int) $conversation->seller_id));
    }

    public function test_empty_message_is_rejected_and_other_accounts_cannot_send(): void
    {
        $conversation = $this->conversation();
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])->postJson(route('conversations.messages.send', $conversation), ['message' => '   '])
            ->assertStatus(422)->assertJsonPath('message', 'پیام نمی‌تواند خالی باشد.');
        $this->actingAs($this->user('c@c.com'))->postJson(route('conversations.messages.send', $conversation), ['message' => 'سلام'])->assertForbidden();
        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_private_inbox_channel_only_authorizes_owner(): void
    {
        $callback = Broadcast::getChannels()['users.{id}.inbox'];
        $user = $this->user();
        $this->assertTrue($callback($user, (string) $user->getKey()));
        $this->assertFalse($callback($user, (string) $this->user('c@c.com')->getKey()));
    }

    public function test_notifications_and_favorites_actions_are_localized(): void
    {
        $user = $this->user();
        UserNotification::publish((int) $user->getKey(), UserNotification::TYPE_MESSAGE, 'پیام فارسی', 'متن فارسی');
        $this->actingAs($user)->withSession(['locale' => 'fa'])->get('/panel/notifications')->assertSee('پیام فارسی')->assertSee('خوانده‌نشده');
        $this->post(route('panel.notifications.read-all'))->assertSessionHas('success', 'همهٔ اعلان‌ها خوانده‌شده علامت خوردند.');
        $this->assertSame(0, UserNotification::unreadCountForUser((int) $user->getKey()));
        $this->post(route('favorites.searches.store'), ['search' => 'گوشی فارسی'])->assertRedirect()->assertSessionHas('success', 'جست‌وجو ذخیره شد.');
    }

    public function test_video_upload_and_edit_use_the_owner_listing_and_persian_feedback(): void
    {
        Queue::fake();
        $user = $this->user();
        $listing = Listing::query()->ownedByUser($user->getKey())->firstOrFail();
        $this->actingAs($user)->withSession(['locale' => 'fa'])
            ->post(route('panel.videos.store'), [
                'listing_id' => $listing->getKey(), 'title' => 'ویدیو آزمایشی فارسی',
                'video_file' => UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4'),
            ])->assertRedirect()->assertSessionHas('success', 'ویدیو بارگذاری شد.');
        $video = Video::query()->where('title', 'ویدیو آزمایشی فارسی')->firstOrFail();
        $this->get(route('panel.videos.edit', $video))->assertSuccessful()->assertSee('name="listing_id"', false);
        $this->put(route('panel.videos.update', $video), [
            'listing_id' => $listing->getKey(), 'title' => 'ویدیو ویرایش‌شده', 'is_active' => 0,
        ])->assertRedirect()->assertSessionHas('success', 'ویدیو به‌روز شد.');
        $this->assertSame('ویدیو ویرایش‌شده', $video->fresh()->title);
        $this->assertFalse($video->fresh()->is_active);
        $this->post(route('panel.videos.destroy', $video), ['_method' => 'DELETE'])->assertRedirect();
        $this->assertSoftDeleted($video);
    }

    public function test_video_upload_errors_are_localized(): void
    {
        $this->actingAs($this->user())->withSession(['locale' => 'fa'])
            ->post(route('panel.videos.store'), [])->assertSessionHasErrors(['listing_id', 'video_file']);
        $this->assertSame('فایل ویدیو را انتخاب کنید.', session('errors')->first('video_file'));
    }

    public function test_quick_messages_and_panel_translation_keys_match_english(): void
    {
        app()->setLocale('fa');
        $this->assertSame('سلام', QuickMessageCatalog::all()[0]);
        foreach (['Panel', 'Conversation', 'Favorite'] as $module) {
            $english = Arr::dot(require base_path("Modules/$module/lang/en/messages.php"));
            $persian = Arr::dot(require base_path("Modules/$module/lang/fa/messages.php"));
            $this->assertSame(array_keys($english), array_keys($persian));
            foreach ($english as $key => $value) {
                preg_match_all('/:[a-zA-Z_]+/', $value, $en);
                preg_match_all('/:[a-zA-Z_]+/', $persian[$key], $fa);
                $this->assertSame($en[0], $fa[0], "$module.$key");
            }
        }
    }
}
