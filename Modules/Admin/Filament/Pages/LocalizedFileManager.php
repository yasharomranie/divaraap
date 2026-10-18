<?php

declare(strict_types=1);

namespace Modules\Admin\Filament\Pages;

use App\Support\Filament\FileManagerText;
use Filament\Panel;
use MWGuerra\FileManager\Filament\Pages\FileManager;

final class LocalizedFileManager extends FileManager
{
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'file-manager';
    }

    public function getSidebarRootLabel(): string
    {
        return FileManagerText::translate(parent::getSidebarRootLabel());
    }

    public function getSidebarHeading(): string
    {
        return FileManagerText::translate(parent::getSidebarHeading());
    }

    protected function validationAttributes(): array
    {
        return [
            'newFolderName' => __('admin::filemanager.folder_name'),
            'subfolderName' => __('admin::filemanager.folder_name'),
            'renameItemName' => __('admin::filemanager.new_name'),
            'uploadedFiles' => __('admin::filemanager.files'),
            'uploadedFiles.*' => __('admin::filemanager.file'),
        ];
    }

    public function updatedUploadedFiles(): void
    {
        FileManagerText::run(fn () => parent::updatedUploadedFiles());
    }

    public function handleItemClick(string $itemId, bool $ctrlKey = false): void
    {
        FileManagerText::run(fn () => parent::handleItemClick($itemId, $ctrlKey));
    }

    public function createFolder(): void
    {
        FileManagerText::run(fn () => parent::createFolder());
    }

    public function deleteSelected(): void
    {
        FileManagerText::run(fn () => parent::deleteSelected());
    }

    public function deleteItem(string $itemId): void
    {
        FileManagerText::run(fn () => parent::deleteItem($itemId));
    }

    public function moveSelected(): void
    {
        FileManagerText::run(fn () => parent::moveSelected());
    }

    public function createSubfolder(): void
    {
        FileManagerText::run(fn () => parent::createSubfolder());
    }

    public function renameItem(): void
    {
        FileManagerText::run(fn () => parent::renameItem());
    }

    public function uploadFiles(): void
    {
        FileManagerText::run(fn () => parent::uploadFiles());
    }

    public function moveItem(): void
    {
        FileManagerText::run(fn () => parent::moveItem());
    }

    public function refresh(): void
    {
        FileManagerText::run(fn () => parent::refresh());
    }
}
