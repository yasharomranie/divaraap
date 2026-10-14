@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
@endphp
<x-filament-panels::page>
    <style>
        .filemanager-localized button:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; opacity: 1; }
        .filemanager-localized .filemanager-name { text-align: start; max-width: 100%; min-height: 44px; }
        .filemanager-localized { min-width: 0; }
        .filemanager-localized > div { min-width: 0; }
        .filemanager-localized > div:first-child { flex-wrap: wrap; gap: 0.75rem; }
        [dir="rtl"] .filemanager-localized .filemanager-chevron { transform: scaleX(-1); }
        .filemanager-localized nav { min-width: 0; overflow-wrap: anywhere; }
        .filemanager-localized [class~="border-r"] { border-right: 0; border-inline-end: 1px solid #d1d5db; }
        @media (max-width: 640px) {
            .filemanager-localized > div:first-child { padding: 0.75rem; }
            .filemanager-localized > div:first-child > div { flex-wrap: wrap; }
            .filemanager-localized > div:last-child { flex-direction: column; }
            .filemanager-localized [class~="w-64"] { width: 100%; max-height: 9rem; flex: 0 0 auto; border-inline-end: 0; border-bottom: 1px solid #d1d5db; }
        }
    </style>
    @push('styles')
    <style>
        .fi-modal-close-overlay {
            width: 100vw !important;
        }
        html:has(.fi-modal-open) body {
            width: 100vw;
        }
    </style>
    @endpush

    <div class="filemanager-localized flex flex-col h-[calc(100vh-12rem)] border border-gray-300 rounded-xl dark:border-none" x-data="{
        draggedItemId: null,
        isDragging: false,
    }">
        
        <div class="flex items-center justify-between border-b border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 rounded-t-xl">
            
            <nav class="flex items-center space-x-2 text-sm">
                @foreach($this->breadcrumbs as $index => $crumb)
                    @if($index > 0)
                        {!! FileManagerIcon::ChevronRight->render('w-4 h-4 text-gray-400 filemanager-chevron') !!}
                    @endif
                    @if($index === count($this->breadcrumbs) - 1)
                        <span class="font-medium text-gray-900 dark:text-white">{{ $index === 0 ? \App\Support\Filament\FileManagerText::translate($crumb['name']) : $crumb['name'] }}</span>
                    @else
                        @php
                            $crumbId = $crumb['id'];
                        @endphp
                        <button
                            x-on:click="$wire.navigateTo({{ json_encode($crumbId) }})"
                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                        >
                            {{ $index === 0 ? \App\Support\Filament\FileManagerText::translate($crumb['name']) : $crumb['name'] }}
                        </button>
                    @endif
                @endforeach
            </nav>

            
            <div class="flex items-center gap-2">
                @if(!$this->isReadOnly())
                    
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'create-folder-modal' })"
                        size="sm"
                        icon="heroicon-o-folder-plus"
                    >
                        {{ __('admin::filemanager.new_folder') }}
                    </x-filament::button>

                    
                    <x-filament::button
                        x-on:click="$dispatch('open-modal', { id: 'upload-files-modal' })"
                        size="sm"
                        icon="heroicon-o-arrow-up-tray"
                    >
                        {{ __('admin::filemanager.upload') }}
                    </x-filament::button>
                @endif

                @if(count($selectedItems) > 0)
                    
                    <x-filament::button
                        wire:click="clearSelection"
                        size="sm"
                        color="gray"
                        icon="heroicon-o-x-mark"
                        title="{{ __('admin::filemanager.clear_selection') }}"
                    >
                        {{ __('admin::filemanager.clear_count', ['count' => count($selectedItems)]) }}
                    </x-filament::button>
                @endif

                
                <x-filament::button
                    wire:click="refresh"
                    size="sm"
                    color="gray"
                    icon="heroicon-o-arrow-path"
                    title="{{ __('admin::filemanager.refresh') }}"
                />

                
                <div class="flex items-center gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 p-1">
                    <button
                        wire:click="setViewMode('grid')"
                        class="p-1.5 rounded {{ $viewMode === 'grid' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="{{ __('admin::filemanager.grid_view') }}"
                    >
                        {!! FileManagerIcon::Squares2x2->render('w-4 h-4') !!}
                    </button>
                    <button
                        wire:click="setViewMode('list')"
                        class="p-1.5 rounded {{ $viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm' : '' }}"
                        title="{{ __('admin::filemanager.list_view') }}"
                    >
                        {!! FileManagerIcon::ListBullet->render('w-4 h-4') !!}
                    </button>
                </div>
            </div>
        </div>

        
        <div class="flex flex-1 overflow-hidden">
            
            @if($this->shouldShowPageSidebar())
            <aside class="w-64 border-r border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 p-4 overflow-y-auto">
                <h2 class="px-2 text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $this->getSidebarHeading() }}</h2>

                
                <nav class="space-y-1">
                    <div
                        x-data="{ showActions: false }"
                        @mouseenter="showActions = true"
                        @mouseleave="showActions = false"
                        class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === null ? 'font-medium' : '' }}"
                    >
                        
                        <button
                            wire:click="navigateTo(null)"
                            class="flex items-center gap-2 flex-1 min-w-0 text-left"
                        >
                            {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $this->getSidebarRootLabel() }}</span>
                        </button>

                        
                        @php $rootFileCount = $this->rootFileCount; @endphp
                        <div class="relative shrink-0 flex items-center justify-end" style="min-width: 72px;">
                            
                            @if($rootFileCount > 0)
                                <span
                                    class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                                    :class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"
                                >
                                    {{ $rootFileCount }}
                                </span>
                            @endif

                            @if(!$this->isReadOnly())
                                
                                <div
                                    class="flex items-center gap-0.5 transition-opacity duration-100"
                                    :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                                >
                                    
                                    <button
                                        x-on:click.stop="$dispatch('open-modal', { id: 'create-folder-modal' })"
                                        class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                        title="{{ __('admin::filemanager.add_folder') }}"
                                    >
                                        {!! FileManagerIcon::FolderPlus->render('w-3.5 h-3.5') !!}
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    
                    @include('filemanager::filament.pages.partials.folder-tree', ['folders' => $this->folderTree, 'level' => 1, 'currentPath' => $currentPath, 'isReadOnly' => $this->isReadOnly()])
                </nav>
            </aside>
            @endif

            
            <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-900 p-6">
                @if($this->items->isEmpty())
                    
                    <div class="flex flex-col items-center justify-center h-full">
                        {!! FileManagerIcon::FolderOpen->render('w-16 h-16 text-gray-400 dark:text-gray-500 mb-4') !!}
                        <p class="text-lg text-gray-600 dark:text-gray-400">{{ __('admin::filemanager.this_folder_is_empty') }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-500">{{ __('admin::filemanager.create_a_new_folder_or_upload_files_to_get_started') }}</p>
                    </div>
                @else
                    
                    @if(!$this->isReadOnly())
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-4 py-2.5 mb-4">
                            <div class="flex items-center gap-3">
                                
                                @if($this->allSelected())
                                    <button
                                        wire:click="clearSelection"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::XMark->render('w-4 h-4') !!}
                                        <span>{{ __('admin::filemanager.deselect_all') }}</span>
                                    </button>
                                @else
                                    <button
                                        wire:click="selectAll"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::CheckCircle->render('w-4 h-4') !!}
                                        <span>{{ __('admin::filemanager.select_all') }}</span>
                                    </button>
                                @endif

                                @if(count($selectedItems) > 0)
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ __('admin::filemanager.selected', ['count' => count($selectedItems)]) }}
                                    </span>

                                    <div class="h-4 w-px bg-gray-300 dark:bg-gray-600"></div>

                                    
                                    <button
                                        wire:click="openMoveDialogForSelected"
                                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 transition-colors"
                                    >
                                        {!! FileManagerIcon::ArrowRightCircle->render('w-4 h-4') !!}
                                        <span>{{ __('admin::filemanager.move') }}</span>
                                    </button>
                                @endif
                            </div>

                            <div class="flex items-center gap-3">
                                @if(count($selectedItems) > 0)
                                    
                                    <button
                                        wire:click="deleteSelected"
                                        wire:confirm="{{ __('admin::filemanager.delete_confirm_count', ['count' => count($selectedItems)]) }}"
                                        class="flex items-center gap-2 text-sm font-medium text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors"
                                    >
                                        {!! FileManagerIcon::Trash->render('w-4 h-4') !!}
                                        <span>{{ __('admin::filemanager.delete') }}</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($viewMode === 'grid')
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
                            @foreach($this->items as $item)
                                @include('filemanager::filament.pages.partials.file-card', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @else
                        
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->items as $item)
                                @include('filemanager::filament.pages.partials.file-list-item', ['item' => $item, 'isReadOnly' => $this->isReadOnly()])
                            @endforeach
                        </div>
                    @endif
                @endif
            </main>
        </div>
    </div>

    
    <x-filament::modal id="create-folder-modal" width="md">
        <x-slot name="heading">
            {{ __('admin::filemanager.create_new_folder') }}
        </x-slot>

        <x-slot name="description">
            {{ __('admin::filemanager.enter_a_name_for_your_new_folder') }}
        </x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <label for="filemanager-newFolderName" class="text-sm font-medium">{{ __('admin::filemanager.folder_name') }}</label>
<x-filament::input id="filemanager-newFolderName" maxlength="255"
                    type="text"
                    wire:model.live="newFolderName" dir="auto" aria-label="{{ __('admin::filemanager.folder_name') }}"
                    placeholder="{{ __('admin::filemanager.my_videos') }}"
                    wire:keydown.enter="createFolder"
                    autofocus
                />
@error('newFolderName')<p class="text-sm text-danger-600" role="alert">{{ $message }}</p>@enderror
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'create-folder-modal' })"
                color="gray"
            >
                {{ __('admin::filemanager.cancel') }}
            </x-filament::button>
            <x-filament::button
                wire:click="createFolder"
            >
                {{ __('admin::filemanager.create_folder') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    
    <x-filament::modal id="move-item-modal" width="md">
        <x-slot name="heading">
            @if(count($itemsToMove) > 0)
                {{ __('admin::filemanager.move_count', ['count' => count($itemsToMove)]) }}
            @else
                {{ __('admin::filemanager.move_to_folder') }}
            @endif
        </x-slot>

        <x-slot name="description">
            {{ __('admin::filemanager.select_a_destination_folder') }}
        </x-slot>

        <div class="max-h-96 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 p-2">
            
            <button
                wire:click="setMoveTarget(null)"
                class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-800 {{ $moveTargetPath === null ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600' : '' }}"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                <span>{{ __('admin::filemanager.root') }}</span>
            </button>

            
            @foreach($this->allFolders as $folder)
                @php
                    $folderId = $folder->getIdentifier();
                    $itemBeingMoved = $this->itemToMove;
                    $isCurrentFolder = $itemBeingMoved && $itemBeingMoved->getParentPath() === $folder->getPath();
                    $isSameItem = $itemToMoveId === $folderId;
                    $isBulkMove = count($itemsToMove) > 0;
                    $isDisabled = $isBulkMove ? in_array($folderId, $itemsToMove) : ($isCurrentFolder || $isSameItem);
                @endphp
                <button
                    x-on:click="$wire.setMoveTarget({{ json_encode($folderId) }})"
                    @if($isDisabled) disabled @endif
                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors
                        {{ $moveTargetPath === $folderId ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600' : 'hover:bg-gray-100 dark:hover:bg-gray-800' }}
                        {{ $isDisabled ? 'opacity-50 cursor-not-allowed' : '' }}"
                    style="padding-inline-start: {{ $folder->getDepth() * 16 + 12 }}px"
                >
                    {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                    <span>{{ $folder->getName() }}</span>
                </button>
            @endforeach
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'move-item-modal' })"
                color="gray"
            >
                {{ __('admin::filemanager.cancel') }}
            </x-filament::button>
            @if(count($itemsToMove) > 0)
                <x-filament::button
                    wire:click="moveSelected"
                >
                    {{ __('admin::filemanager.move_count', ['count' => count($itemsToMove)]) }}
                </x-filament::button>
            @else
                <x-filament::button
                    wire:click="moveItem"
                >
                    {{ __('admin::filemanager.move_here') }}
                </x-filament::button>
            @endif
        </x-slot>
    </x-filament::modal>

    
    <x-filament::modal id="create-subfolder-modal" width="md">
        <x-slot name="heading">
            {{ __('admin::filemanager.create_subfolder') }}
        </x-slot>

        <x-slot name="description">
            @if($this->subfolderParent)
                {{ __('admin::filemanager.subfolder_inside', ['name' => $this->subfolderParent->getName()]) }}
            @else
                {{ __('admin::filemanager.subfolder_hint') }}
            @endif
        </x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <label for="filemanager-subfolderName" class="text-sm font-medium">{{ __('admin::filemanager.folder_name') }}</label>
<x-filament::input id="filemanager-subfolderName" maxlength="255"
                    type="text"
                    wire:model.live="subfolderName" dir="auto" aria-label="{{ __('admin::filemanager.folder_name') }}"
                    placeholder="{{ __('admin::filemanager.new_folder') }}"
                    wire:keydown.enter="createSubfolder"
                    autofocus
                />
@error('subfolderName')<p class="text-sm text-danger-600" role="alert">{{ $message }}</p>@enderror
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'create-subfolder-modal' })"
                color="gray"
            >
                {{ __('admin::filemanager.cancel') }}
            </x-filament::button>
            <x-filament::button
                wire:click="createSubfolder"
            >
                {{ __('admin::filemanager.create_subfolder') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    
    <x-filament::modal id="rename-item-modal" width="md">
        <x-slot name="heading">
            {{ __('admin::filemanager.rename_item') }}
        </x-slot>

        <x-slot name="description">
            @if($this->itemToRename)
                Rename "{{ $this->itemToRename->getName() }}"
            @else
                {{ __('admin::filemanager.rename_hint') }}
            @endif
        </x-slot>

        <div class="space-y-4">
            <x-filament::input.wrapper>
                <label for="filemanager-renameItemName" class="text-sm font-medium">{{ __('admin::filemanager.new_name') }}</label>
<x-filament::input id="filemanager-renameItemName" maxlength="255"
                    type="text"
                    wire:model.live="renameItemName" dir="auto" aria-label="{{ __('admin::filemanager.folder_name') }}"
                    placeholder="{{ __('admin::filemanager.new_name') }}"
                    wire:keydown.enter="renameItem"
                    autofocus
                />
@error('renameItemName')<p class="text-sm text-danger-600" role="alert">{{ $message }}</p>@enderror
            </x-filament::input.wrapper>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'rename-item-modal' })"
                color="gray"
            >
                {{ __('admin::filemanager.cancel') }}
            </x-filament::button>
            <x-filament::button
                wire:click="renameItem"
            >
                {{ __('admin::filemanager.rename') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    
    <x-filament::modal id="upload-files-modal" width="lg">
        <x-slot name="heading">
            {{ __('admin::filemanager.upload_files') }}
        </x-slot>

        <x-slot name="description">
            @php
                $maxSizeMB = round(config('filemanager.upload.max_file_size', 102400) / 1024, 0);
            @endphp
            @if($this->supportsMultipleUploads())
                {{ __('admin::filemanager.upload_many_hint', ['size' => $maxSizeMB]) }}
            @else
                {{ __('admin::filemanager.upload_one_hint', ['size' => $maxSizeMB]) }}
            @endif
        </x-slot>

        <div class="space-y-4">
            <div
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                class="relative border-2 border-dashed rounded-lg p-8 text-center transition-colors"
                :class="isDragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-300 dark:border-gray-600'"
            >
                <input
                    type="file"
                    aria-label="{{ __('admin::filemanager.upload_files') }}"
                    x-ref="fileInput"
                    @if($this->supportsMultipleUploads())
                        wire:model.live="uploadedFiles"
                        multiple
                    @else
                        wire:model.live="uploadedFile"
                    @endif
                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                />
                <div class="space-y-2" wire:loading.remove wire:target="uploadedFiles, uploadedFile">
                    {!! FileManagerIcon::CloudArrowUp->render('w-12 h-12 mx-auto text-gray-400') !!}
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium text-primary-600 dark:text-primary-400">{{ __('admin::filemanager.click_to_upload') }}</span>
                        {{ __('admin::filemanager.drag_drop') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        @if($this->supportsMultipleUploads())
                            {{ __('admin::filemanager.any_file') }}
                        @else
                            {{ __('admin::filemanager.one_at_time') }}
                        @endif
                    </p>
                </div>
                <div class="space-y-2" wire:loading wire:target="uploadedFiles, uploadedFile">
                    <div class="w-12 h-12 mx-auto border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm font-medium text-primary-600 dark:text-primary-400">
                        {{ __('admin::filemanager.processing_files') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500">
                        {{ __('admin::filemanager.please_wait_while_files_are_being_prepared') }}
                    </p>
                </div>
            </div>

            
            @if(count($uploadedFiles) > 0)
                <div class="space-y-2">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('admin::filemanager.ready_count', ['count' => count($uploadedFiles)]) }}
                    </p>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 max-h-32 overflow-y-auto">
                        @foreach($uploadedFiles as $file)
                            <li class="flex items-center gap-2">
                                {!! FileManagerIcon::CheckCircle->render('w-4 h-4 shrink-0 text-success-500') !!}
                                <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                <span class="text-xs text-gray-400">({{ number_format($file->getSize() / 1024, 1) }} KB)</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                x-on:click="$dispatch('close-modal', { id: 'upload-files-modal' })"
                wire:click="clearUploadedFiles"
                color="gray"
            >
                {{ __('admin::filemanager.cancel') }}
            </x-filament::button>
            <x-filament::button
                wire:click="uploadFiles"
                wire:loading.attr="disabled"
                wire:target="uploadFiles"
                :disabled="count($uploadedFiles) === 0"
            >
                <span wire:loading.remove wire:target="uploadedFiles, uploadFiles">
                    @if(count($uploadedFiles) > 0)
                        {{ __('admin::filemanager.upload_count', ['count' => count($uploadedFiles)]) }}
                    @else
                        {{ __('admin::filemanager.select_files_first') }}
                    @endif
                </span>
                <span wire:loading wire:target="uploadedFiles">{{ __('admin::filemanager.processing') }}</span>
                <span wire:loading wire:target="uploadFiles">{{ __('admin::filemanager.uploading') }}</span>
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    
    <x-filament::modal id="preview-modal" width="5xl" :close-by-clicking-away="true">
        @if($this->previewItem)
            @php
                $previewItem = $this->previewItem;
                $fileType = $this->previewFileType;
                $previewUrl = $this->getPreviewUrl();
                $textContent = $this->getTextContent();
                $viewerComponent = $fileType?->viewerComponent();
            @endphp

            <x-slot name="heading">
                <div class="flex items-center gap-3">
                    @if($fileType)
                        <x-dynamic-component
                            :component="$fileType->icon()"
                            class="w-6 h-6 {{ $fileType->iconColor() }}"
                        />
                    @else
                        {!! FileManagerIcon::Document->render('w-6 h-6 text-gray-400') !!}
                    @endif
                    <span class="truncate"><bdi>{{ $previewItem->getName() }}</bdi></span>
                </div>
            </x-slot>

            <x-slot name="description">
                <div class="flex items-center gap-4 text-sm text-gray-500">
                    <span>{{ $previewItem->getPath() }}</span>
                    @if($fileType)
                        <span class="px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs">{{ \App\Support\Filament\FileManagerText::translate($fileType->label()) }}</span>
                    @endif
                    @if($previewItem->getSize())
                        <span>{{ $previewItem->getFormattedSize() }}</span>
                    @endif
                    @if($previewItem->getDuration())
                        <span>{{ $previewItem->getFormattedDuration() }}</span>
                    @endif
                </div>
            </x-slot>

            <div class="min-h-[300px] max-h-[70vh] overflow-auto">
                @if($viewerComponent && $previewUrl)
                    
                    @if($fileType->identifier() === 'text' && $textContent !== null)
                        @include($viewerComponent, ['content' => $textContent, 'url' => $previewUrl, 'item' => $previewItem])
                    @else
                        @include($viewerComponent, ['url' => $previewUrl, 'item' => $previewItem, 'fileType' => $fileType])
                    @endif
                @elseif($fileType && !$fileType->canPreview())
                    
                    @include('filemanager::components.viewers.fallback', [
                        'url' => $previewUrl,
                        'item' => $previewItem,
                        'fileType' => $fileType
                    ])
                @else
                    
                    @include('filemanager::components.viewers.fallback', [
                        'url' => $previewUrl,
                        'item' => $previewItem,
                        'fileType' => $fileType
                    ])
                @endif
            </div>

            <x-slot name="footerActions">
                <div class="flex w-full justify-between">
                    <div class="flex gap-2">
                        @if($previewUrl)
                            <x-filament::button
                                tag="a"
                                href="{{ $previewUrl }}"
                                target="_blank"
                                color="gray"
                                icon="heroicon-o-arrow-down-tray"
                            >
                                {{ __('admin::filemanager.download') }}
                            </x-filament::button>
                        @endif
                        @if(!$this->isReadOnly())
                            <x-filament::button
                                x-on:click="$wire.openMoveDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                icon="heroicon-o-arrow-right-circle"
                            >
                                {{ __('admin::filemanager.move') }}
                            </x-filament::button>
                            <x-filament::button
                                x-on:click="$wire.openRenameDialog({{ json_encode($previewItem->getIdentifier()) }})"
                                color="gray"
                                icon="heroicon-o-pencil"
                            >
                                {{ __('admin::filemanager.rename') }}
                            </x-filament::button>
                        @endif
                    </div>
                    <x-filament::button
                        x-on:click="$dispatch('close-modal', { id: 'preview-modal' })"
                    >
                        {{ __('admin::filemanager.close') }}
                    </x-filament::button>
                </div>
            </x-slot>
        @endif
    </x-filament::modal>
</x-filament-panels::page>
