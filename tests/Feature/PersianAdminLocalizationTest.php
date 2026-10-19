<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Filament\LocalAvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Support\Arr;
use Livewire\Livewire;
use Modules\Category\Filament\Admin\Resources\CategoryResource;
use Modules\Category\Filament\Admin\Resources\CategoryResource\Pages\CreateCategory;
use Modules\Category\Models\Category;
use Modules\Listing\Models\ListingCustomField;
use Modules\Listing\States\ListingStatus;
use Modules\Listing\Support\ListingCustomFieldSchemaBuilder;
use Modules\Location\Models\Country;
use Modules\Page\Filament\Admin\Resources\PageResource;
use Modules\Page\Filament\Admin\Resources\PageResource\Pages\CreatePageResourceRecord;
use Modules\Page\Filament\Admin\Resources\PageResource\Pages\EditPageResourceRecord;
use Modules\Page\Models\Page;
use Modules\User\App\Models\User;
use Modules\User\App\States\UserStatus;
use Modules\Video\Enums\VideoStatus;
use Tests\TestCase;

class PersianAdminLocalizationTest extends TestCase
{
    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail())->withSession(['locale' => 'fa']);
        app()->setLocale('fa');
    }

    public function test_admin_pages_render_persian_headings_and_labels(): void
    {
        $this->admin();
        foreach ([
            'pages' => 'صفحات', 'categories' => 'دسته‌بندی‌ها', 'users' => 'کاربران',
            'listings' => 'آگهی‌ها', 'listing-custom-fields' => 'ویژگی‌های اختصاصی',
            'countries' => 'کشورها', 'cities' => 'شهرها', 'districts' => 'مناطق',
            'videos' => 'ویدیوها', 'offers' => 'پیشنهادهای قیمت', 'reviews' => 'نظرات',
            'reports' => 'گزارش‌های تخلف', 'promotion-plans' => 'طرح‌های ارتقای آگهی',
            'user-notifications' => 'اعلان‌ها', 'manage-general-settings' => 'تنظیمات عمومی',
        ] as $uri => $heading) {
            $this->get('/admin/'.$uri)->assertSuccessful()->assertSee($heading)->assertSee('dir="rtl"', false)->assertDontSee('admin::admin.', false);
        }
        $this->get('/admin/pages/create')->assertSee('نامک')->assertSee('متن')->assertSee('جایگاه نمایش')->assertSee('dir="ltr"', false);
    }

    public function test_member_cannot_access_admin_content_editor(): void
    {
        $this->actingAs(User::query()->where('email', 'b@b.com')->firstOrFail())
            ->get('/admin/pages/create')->assertForbidden();
    }

    public function test_resource_metadata_changes_with_locale(): void
    {
        app()->setLocale('fa');
        $this->assertSame('صفحه', PageResource::getModelLabel());
        $this->assertSame('صفحات', PageResource::getPluralModelLabel());
        $this->assertSame('دسته‌بندی‌ها', CategoryResource::getNavigationLabel());
        app()->setLocale('en');
        $this->assertSame('Page', PageResource::getModelLabel());
        $this->assertSame('Pages', PageResource::getPluralModelLabel());
        $this->assertSame('Categories', CategoryResource::getNavigationLabel());
    }

    public function test_page_creation_and_editing_store_persian_content(): void
    {
        $this->admin();
        $title = 'راهنمای اختصاصی فارسی';
        $slug = Page::uniqueSlug($title);
        Livewire::test(CreatePageResourceRecord::class)->fillForm([
            'title' => $title, 'slug' => $slug, 'body' => 'متن فارسی ایجاد صفحه',
            'placement' => 'help', 'sort_order' => 20, 'is_published' => true,
        ])->call('create')->assertHasNoFormErrors();
        $page = Page::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame($title, $page->title);
        $this->assertSame('متن فارسی ایجاد صفحه', $page->body);
        $this->get('/pages/'.$slug)->assertSee('متن فارسی ایجاد صفحه');
        Livewire::test(EditPageResourceRecord::class, ['record' => $page->getKey()])
            ->fillForm(['title' => 'عنوان فارسی ویرایش‌شده', 'body' => 'محتوای ویرایش‌شده'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('محتوای ویرایش‌شده', $page->fresh()->body);
    }

    public function test_persian_category_creation_gets_a_nonempty_unique_slug(): void
    {
        $this->admin();
        $name = 'دسته‌بندی اختصاصی فارسی';
        $slug = Category::uniqueSlug($name);
        Livewire::test(CreateCategory::class)->fillForm([
            'name' => $name, 'slug' => $slug, 'sort_order' => 20, 'is_active' => true,
        ])->call('create')->assertHasNoFormErrors();
        $this->assertSame($name, Category::query()->where('slug', $slug)->firstOrFail()->name);
        $this->assertNotSame('', $slug);
        $this->assertNotSame($slug, Category::uniqueSlug($name));
    }

    public function test_default_cms_pages_translate_without_modifying_database_content(): void
    {
        $english = require base_path('Modules/Page/lang/en/content.php');
        $persian = require base_path('Modules/Page/lang/fa/content.php');
        foreach ($english as $slug => $fields) {
            $page = Page::query()->where('slug', $slug)->firstOrFail();
            $this->withSession(['locale' => 'fa'])->get('/pages/'.$slug)
                ->assertSuccessful()->assertSee($persian[$slug]['title'])
                ->assertSee($persian[$slug]['meta_description']);
            $this->assertSame($fields['body'], $page->fresh()->body);
            $this->assertSame(explode("\n\n", $persian[$slug]['body'])[0], $page->bodyParagraphs()[0]);
            $this->withSession(['locale' => 'en'])->get('/pages/'.$slug)->assertSee($fields['title']);
        }
    }

    public function test_custom_cms_content_is_preserved_and_safely_escaped(): void
    {
        $page = Page::query()->where('slug', 'about')->firstOrFail();
        $page->update(['title' => 'عنوان اختصاصی', 'body' => 'Custom content <script>alert(1)</script>', 'meta_description' => 'Custom metadata']);
        $this->withSession(['locale' => 'fa'])->get('/pages/about')->assertSee('عنوان اختصاصی')
            ->assertSee('Custom content')->assertSee('Custom metadata')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_status_options_keep_machine_values_and_translate_labels(): void
    {
        app()->setLocale('fa');
        $this->assertSame(['active', 'pending', 'rejected', 'paused', 'sold', 'expired'], array_keys(ListingStatus::options()));
        $this->assertSame('فعال', ListingStatus::options()['active']);
        $this->assertSame('متوقف‌شده', ListingStatus::options()['paused']);
        $this->assertSame('مسدود', UserStatus::options()['banned']);
        $this->assertSame('آماده', VideoStatus::Ready->label());
        app()->setLocale('en');
        $this->assertSame('Active', ListingStatus::options()['active']);
        $this->assertSame('Paused', ListingStatus::options()['paused']);
        $this->assertSame('Ready', VideoStatus::Ready->label());
    }

    public function test_country_options_use_persian_labels_and_original_storage_keys(): void
    {
        app()->setLocale('fa');
        $this->assertSame('ایران', Country::nameOptions()['Iran']);
        $this->assertSame('Iran', Country::query()->where('code', 'IR')->firstOrFail()->name);
        app()->setLocale('en');
        $this->assertSame('Iran', Country::nameOptions()['Iran']);
    }

    public function test_custom_field_schema_keeps_option_values_and_translates_labels(): void
    {
        $category = Category::query()->where('name', 'Phones')->firstOrFail();
        ListingCustomField::query()->create([
            'name' => 'phase5_condition', 'label' => 'Condition', 'type' => 'select',
            'category_id' => $category->getKey(), 'options' => ['New', 'Used'],
            'is_active' => true, 'is_required' => false,
        ]);
        app()->setLocale('fa');
        $field = collect(ListingCustomFieldSchemaBuilder::formComponents((int) $category->getKey()))
            ->first(fn ($component): bool => $component->getName() === 'custom_fields.phase5_condition');
        $this->assertSame('وضعیت کالا', $field->getLabel());
        $this->assertSame(['New' => 'نو', 'Used' => 'کارکرده'], $field->getOptions());
    }

    public function test_default_admin_avatar_is_local_and_escapes_user_text(): void
    {
        $this->admin();
        $user = new User(['name' => 'مدیر آزمایشی']);
        $provider = new LocalAvatarProvider;
        $uri = $provider->get($user);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $uri);
        $this->assertStringContainsString('مآ', base64_decode(explode(',', $uri, 2)[1]));
        $user->name = '<script> test';
        $this->assertStringNotContainsString('<script>', base64_decode(explode(',', $provider->get($user), 2)[1]));
    }

    public function test_module_translation_keys_and_placeholders_match(): void
    {
        foreach (glob(base_path('Modules/*/lang/en/admin.php')) as $path) {
            $english = Arr::dot(require $path);
            $persian = Arr::dot(require str_replace('/en/', '/fa/', $path));
            $this->assertSame(array_keys($english), array_keys($persian), $path);
            foreach ($english as $key => $value) {
                preg_match_all('/:[a-zA-Z_]+/', $value, $en);
                preg_match_all('/:[a-zA-Z_]+/', $persian[$key], $fa);
                $this->assertSame($en[0], $fa[0], "$path:$key");
                $this->assertNotSame('', $persian[$key]);
            }
        }
        foreach (['Page', 'Demo'] as $module) {
            $this->assertSame(array_keys(require base_path("Modules/$module/lang/en/messages.php")), array_keys(require base_path("Modules/$module/lang/fa/messages.php")));
        }
    }
}
