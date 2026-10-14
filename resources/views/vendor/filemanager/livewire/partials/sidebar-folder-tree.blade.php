
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $isReadOnly = $isReadOnly ?? false;
@endphp
@foreach($folders as $folder)
    @php
        $folderId = (string) $folder['id'];
        $hasChildren = $folder['has_children'] ?? (count($folder['children'] ?? []) > 0);
    @endphp
    <div>
        <div
            x-data="{ showActions: false }"
            @mouseenter="showActions = true"
            @mouseleave="showActions = false"
            class="flex w-full items-center gap-1 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-gray-100 dark:hover:bg-white/5 {{ $currentPath === $folderId ? 'bg-gray-100 dark:bg-white/5 font-medium' : '' }}"
            style="padding-inline-start: {{ (($level - 1) * 12) + 8 }}px"
        >
            
            @if($hasChildren)
                <button
                    wire:click.stop="toggleFolder('{{ $folderId }}')"
                    class="flex items-center justify-center w-4 h-4 rounded hover:bg-gray-200 dark:hover:bg-gray-600 shrink-0"
                    title="{{ __('admin::filemanager.expand_collapse') }}"
                >
                    @if($this->isFolderExpanded($folderId))
                        {!! FileManagerIcon::ChevronDown->render('w-3 h-3 text-gray-500') !!}
                    @else
                        {!! FileManagerIcon::ChevronRight->render('w-3 h-3 text-gray-500 filemanager-chevron') !!}
                    @endif
                </button>
            @else
                <span class="w-4 shrink-0"></span>
            @endif

            
            <button
                wire:click="navigateTo('{{ $folderId }}')"
                class="flex items-center gap-2 flex-1 min-w-0 text-left"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                <span class="truncate text-gray-700 dark:text-gray-300">{{ $folder['name'] }}</span>
            </button>

            
            <div class="relative shrink-0 flex items-center justify-end" style="min-width: {{ $isReadOnly ? '32px' : '60px' }};">
                
                @if($folder['file_count'] > 0)
                    <span
                        class="absolute right-0 text-xs font-medium font-mono text-primary-600 dark:text-primary-400 transition-opacity duration-100"
                        @if(!$isReadOnly):class="showActions ? 'opacity-0 pointer-events-none' : 'opacity-100'"@endif
                    >
                        {{ $folder['file_count'] }}
                    </span>
                @endif

                @if(!$isReadOnly)
                    
                    <div
                        class="flex items-center gap-0.5 transition-opacity duration-100"
                        :class="showActions ? 'opacity-100' : 'opacity-0 pointer-events-none'"
                    >
                        
                        <button
                            wire:click.stop="openCreateSubfolderDialog('{{ $folderId }}')"
                            class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.add_subfolder') }}"
                        >
                            {!! FileManagerIcon::FolderPlus->render('w-3 h-3') !!}
                        </button>
                        
                        <button
                            wire:click.stop="openRenameDialog('{{ $folderId }}')"
                            class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.rename') }}"
                        >
                            {!! FileManagerIcon::Pencil->render('w-3 h-3') !!}
                        </button>
                        
                        <button
                            wire:click.stop="openMoveDialog('{{ $folderId }}')"
                            class="p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.move') }}"
                        >
                            {!! FileManagerIcon::ArrowRightCircleMini->render('w-3 h-3') !!}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        
        @if($hasChildren && $this->isFolderExpanded($folderId))
            @include('filemanager::livewire.partials.sidebar-folder-tree', [
                'folders' => $folder['children'],
                'level' => $level + 1,
                'currentPath' => $currentPath,
                'isReadOnly' => $isReadOnly
            ])
        @endif
    </div>
@endforeach
