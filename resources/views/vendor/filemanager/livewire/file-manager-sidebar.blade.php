@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
@endphp
<div class="fi-sidebar-nav-filemanager">
    <div class="px-3 py-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
            {{ $this->heading }}
        </h3>
    </div>

    <nav class="space-y-0.5 px-2">
        
        @php $isReadOnly = $this->isReadOnly(); @endphp
        <div
            x-data="{ showActions: false }"
            @mouseenter="showActions = true"
            @mouseleave="showActions = false"
            class="group"
        >
            <div class="flex w-full items-center gap-1 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-white/5 {{ $currentPath === null ? 'bg-gray-100 dark:bg-white/5 font-medium' : '' }}">
                
                <button
                    wire:click="navigateTo(null)"
                    class="flex items-center gap-2 flex-1 min-w-0 text-left"
                >
                    {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $this->rootLabel }}</span>
                </button>

                
                <div class="relative shrink-0 flex items-center justify-end" style="min-width: {{ $isReadOnly ? '32px' : '40px' }};">
                    
                    @if($this->rootFileCount > 0)
                        <span
                            class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                            @if(!$isReadOnly):class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"@endif
                        >
                            {{ $this->rootFileCount }}
                        </span>
                    @endif

                    @if(!$isReadOnly)
                        
                        <div
                            class="flex items-center gap-0.5 transition-opacity duration-100"
                            :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                        >
                            
                            <button
                                wire:click.stop="openCreateSubfolderDialog(null)"
                                class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                title="{{ __('admin::filemanager.add_folder') }}"
                            >
                                {!! FileManagerIcon::FolderPlus->render('w-3 h-3') !!}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        
        @include('filemanager::livewire.partials.sidebar-folder-tree', [
            'folders' => $this->folderTree,
            'level' => 1,
            'currentPath' => $currentPath,
            'isReadOnly' => $isReadOnly
        ])
    </nav>

    
    @if(!$isReadOnly)
        
        <x-filament::modal id="sidebar-create-subfolder-modal" width="md">
            <x-slot name="heading">
                {{ __('admin::filemanager.create_new_folder') }}
            </x-slot>

            <x-slot name="description">
                @if($this->subfolderParent)
                    {{ __('admin::filemanager.subfolder_inside', ['name' => $this->subfolderParent->getName()]) }}
                @else
                    {{ __('admin::filemanager.create_in_root') }}
                @endif
            </x-slot>

            <form wire:submit="createSubfolder">
                <x-filament::input.wrapper>
                    <label for="filemanager-subfolderName" class="text-sm font-medium">{{ __('admin::filemanager.folder_name') }}</label>
<x-filament::input id="filemanager-subfolderName" maxlength="255"
                        type="text"
                        wire:model="subfolderName" dir="auto" aria-label="{{ __('admin::filemanager.folder_name') }}"
                        placeholder="{{ __('admin::filemanager.folder_name') }}"
                        autofocus
                    />
@error('subfolderName')<p class="text-sm text-danger-600" role="alert">{{ $message }}</p>@enderror
                </x-filament::input.wrapper>

                <div class="mt-4 flex justify-end gap-3">
                    <x-filament::button
                        type="button"
                        color="gray"
                        x-on:click="$dispatch('close-modal', { id: 'sidebar-create-subfolder-modal' })"
                    >
                        {{ __('admin::filemanager.cancel') }}
                    </x-filament::button>

                    <x-filament::button type="submit">
                        {{ __('admin::filemanager.create') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::modal>

        
        <x-filament::modal id="sidebar-rename-modal" width="md">
            <x-slot name="heading">
                {{ __('admin::filemanager.rename_folder') }}
            </x-slot>

            <x-slot name="description">
                @if($this->itemToRename)
                    Rename "{{ $this->itemToRename->getName() }}"
                @endif
            </x-slot>

            <form wire:submit="renameItem">
                <x-filament::input.wrapper>
                    <label for="filemanager-renameItemName" class="text-sm font-medium">{{ __('admin::filemanager.new_name') }}</label>
<x-filament::input id="filemanager-renameItemName" maxlength="255"
                        type="text"
                        wire:model="renameItemName" dir="auto" aria-label="{{ __('admin::filemanager.folder_name') }}"
                        placeholder="{{ __('admin::filemanager.new_name') }}"
                        autofocus
                    />
@error('renameItemName')<p class="text-sm text-danger-600" role="alert">{{ $message }}</p>@enderror
                </x-filament::input.wrapper>

                <div class="mt-4 flex justify-end gap-3">
                    <x-filament::button
                        type="button"
                        color="gray"
                        x-on:click="$dispatch('close-modal', { id: 'sidebar-rename-modal' })"
                    >
                        {{ __('admin::filemanager.cancel') }}
                    </x-filament::button>

                    <x-filament::button type="submit">
                        {{ __('admin::filemanager.rename') }}
                    </x-filament::button>
                </div>
            </form>
        </x-filament::modal>

        
        <x-filament::modal id="sidebar-move-modal" width="md">
            <x-slot name="heading">
                {{ __('admin::filemanager.move_folder') }}
            </x-slot>

            <x-slot name="description">
                @if($this->itemToMove)
                    {{ __('admin::filemanager.destination_for', ['name' => $this->itemToMove->getName()]) }}
                @endif
            </x-slot>

            <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                
                <button
                    type="button"
                    wire:click="setMoveTarget(null)"
                    class="flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {{ $moveTargetPath === null ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' }}"
                >
                    {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                    <span>{{ $this->rootLabel }}</span>
                </button>

                
                @foreach($this->allFolders as $folder)
                    @if($folder->getIdentifier() !== $itemToMoveId)
                        <button
                            type="button"
                            wire:click="setMoveTarget('{{ $folder->getIdentifier() }}')"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 {{ $moveTargetPath === $folder->getIdentifier() ? 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400' : '' }}"
                        >
                            {!! FileManagerIcon::Folder->render('w-4 h-4') !!}
                            <span>{{ $folder->getName() }}</span>
                        </button>
                    @endif
                @endforeach
            </div>

            <div class="mt-4 flex justify-end gap-3">
                <x-filament::button
                    type="button"
                    color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'sidebar-move-modal' })"
                >
                    {{ __('admin::filemanager.cancel') }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    wire:click="moveItem"
                >
                    {{ __('admin::filemanager.move') }}
                </x-filament::button>
            </div>
        </x-filament::modal>
    @endif
</div>
