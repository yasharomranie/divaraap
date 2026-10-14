<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Filament\FileManagerText;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Admin\Filament\Pages\LocalizedFileManager;
use Modules\User\App\Models\User;
use MWGuerra\FileManager\Models\FileSystemItem;
use Tests\TestCase;

class PersianFileManagerTest extends TestCase
{
    private function admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::query()->where('email', 'a@a.com')->firstOrFail())->withSession(['locale' => 'fa']);
        app()->setLocale('fa');
    }

    public function test_file_manager_route_and_modals_are_persian(): void
    {
        $this->admin();
        $this->get('/admin/file-manager')->assertSuccessful()->assertSee('پوشهٔ جدید')->assertSee('بارگذاری فایل‌ها')->assertSee('انتقال به پوشه')
            ->assertDontSee('Create New Folder')->assertDontSee('Upload Files')->assertDontSee('Select Files First');
    }

    public function test_folder_create_rename_move_and_delete_preserve_persian_names(): void
    {
        $this->admin();
        $component = Livewire::test(LocalizedFileManager::class)->set('newFolderName', 'پوشهٔ فارسی آزمون')->call('createFolder')->assertHasNoErrors();
        $folder = FileSystemItem::query()->where('name', 'پوشهٔ فارسی آزمون')->firstOrFail();
        Notification::assertNotified('پوشه ایجاد شد.');
        $component->call('openRenameDialog', (string) $folder->getKey())->set('renameItemName', 'نام فارسی تازه')->call('renameItem')->assertHasNoErrors();
        $this->assertSame('نام فارسی تازه', $folder->fresh()->name);
        $destination = FileSystemItem::query()->create(['name' => 'مقصد فارسی', 'type' => 'folder']);
        $component->call('openMoveDialog', (string) $folder->getKey())->call('setMoveTarget', (string) $destination->getKey())->call('moveItem');
        $this->assertSame($destination->getKey(), $folder->fresh()->parent_id);
        $component->call('deleteItem', (string) $folder->getKey());
        $this->assertDatabaseMissing('file_system_items', ['id' => $folder->getKey()]);
    }

    public function test_empty_and_duplicate_folder_names_show_localized_errors(): void
    {
        $this->admin();
        Livewire::test(LocalizedFileManager::class)->call('createFolder')->assertHasErrors(['newFolderName' => 'required'])->assertSee('نام پوشه');
        FileSystemItem::query()->create(['name' => 'نام تکراری فارسی', 'type' => 'folder']);
        Livewire::test(LocalizedFileManager::class)->set('newFolderName', 'نام تکراری فارسی')->call('createFolder');
        $this->assertSame(1, FileSystemItem::query()->where('name', 'نام تکراری فارسی')->count());
        Notification::assertNotified('پوشه‌ای با این نام وجود دارد.');
    }

    public function test_upload_and_preview_keep_file_contents_and_names(): void
    {
        $this->admin();
        Storage::fake('public');
        $file = UploadedFile::fake()->createWithContent('persian.txt', 'متن فارسی داخل فایل');
        Livewire::test(LocalizedFileManager::class)->set('uploadedFiles', [$file])->call('uploadFiles')->assertHasNoErrors();
        $item = FileSystemItem::query()->where('name', 'persian.txt')->firstOrFail();
        Storage::disk('public')->assertExists($item->storage_path);
        $component = Livewire::test(LocalizedFileManager::class)->call('openPreview', (string) $item->getKey())->assertSee('فایل متنی');
        $this->assertSame('متن فارسی داخل فایل', $component->instance()->getTextContent());
    }

    public function test_translations_preserve_counts_unknown_names_and_english(): void
    {
        app()->setLocale('fa');
        $this->assertSame('3 مورد حذف شد.', FileManagerText::translate('3 item(s) deleted'));
        $this->assertSame('نام شخصی انگلیسی.txt', FileManagerText::translate('نام شخصی انگلیسی.txt'));
        $this->assertSame('Report 2026.txt', FileManagerText::translate('Report 2026.txt'));
        app()->setLocale('en');
        $this->assertSame('Folder created successfully', FileManagerText::translate('Folder created successfully'));
        $english = require base_path('Modules/Admin/lang/en/filemanager.php');
        $persian = require base_path('Modules/Admin/lang/fa/filemanager.php');
        $this->assertSame(array_keys($english), array_keys($persian));
        foreach ($english as $key => $text) {
            preg_match_all('/:[a-z_]+/', $text, $en);
            preg_match_all('/:[a-z_]+/', $persian[$key], $fa);
            $this->assertSame($en[0], $fa[0], $key);
        }
    }

    public function test_other_notifications_are_unchanged_after_file_manager_validation_failure(): void
    {
        $this->admin();
        Livewire::test(LocalizedFileManager::class)->call('createFolder')->assertHasErrors(['newFolderName']);
        Notification::make()->title('Folder created successfully')->send();
        Notification::assertNotified('Folder created successfully');
    }

    public function test_published_templates_match_the_recorded_upstream_sources(): void
    {
        $source = json_decode(file_get_contents(resource_path('views/vendor/filemanager/SOURCE.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($source['sha256'] as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', base_path('vendor/mwguerra/filemanager/resources/views/'.$path)), 'Review localized overrides after an upstream template change: '.$path);
        }
    }

    public function test_member_cannot_enter_admin_file_manager(): void
    {
        $this->actingAs(User::query()->where('email', 'b@b.com')->firstOrFail())->get('/admin/file-manager')->assertForbidden();
    }
}
