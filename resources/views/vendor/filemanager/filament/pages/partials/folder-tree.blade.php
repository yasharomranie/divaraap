
@php
    use MWGuerra\FileManager\Enums\FileManagerIcon;
    $isReadOnly = $isReadOnly ?? false;
@endphp
@foreach($folders as $folder)
    @php
        $folderId = (string) $folder['id'];
    @endphp
    <div>
        <div
            x-data="{ showActions: false }"
            @mouseenter="showActions = true"
            @mouseleave="showActions = false"
            class="flex w-full items-center gap-1 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-gray-200 dark:hover:bg-gray-700 {{ $currentPath === $folderId ? 'font-medium' : '' }}"
            style="padding-inline-start: {{ (($level - 1) * 12) + 4 }}px"
        >
            
            @if(count($folder['children']) > 0)
                <button
                    x-on:click.stop="$wire.toggleFolder({{ json_encode($folderId) }})"
                    class="flex items-center justify-center w-5 h-5 rounded hover:bg-gray-300 dark:hover:bg-gray-600 shrink-0"
                    title="{{ __('admin::filemanager.expand_collapse') }}"
                >
                    @if($this->isFolderExpanded($folderId))
                        {!! FileManagerIcon::ChevronDown->render('w-3.5 h-3.5') !!}
                    @else
                        {!! FileManagerIcon::ChevronRight->render('w-3.5 h-3.5 filemanager-chevron') !!}
                    @endif
                </button>
            @else
                <span class="w-5 shrink-0"></span>
            @endif

            
            <button
                x-on:click="$wire.navigateTo({{ json_encode($folderId) }})"
                class="flex items-center gap-2 flex-1 min-w-0 text-left"
            >
                {!! FileManagerIcon::Folder->render('w-4 h-4 text-primary-500 shrink-0') !!}
                <span class="truncate text-gray-700 dark:text-gray-300">{{ $folder['name'] }}</span>
            </button>

            
            <div class="relative shrink-0 flex items-center justify-end" style="min-width: {{ $isReadOnly ? '32px' : '72px' }};">
                
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
                            x-on:click.stop="$wire.openCreateSubfolderDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.add_subfolder') }}"
                        >
                            {!! FileManagerIcon::FolderPlus->render('w-3.5 h-3.5') !!}
                        </button>

                        
                        <button
                            x-on:click.stop="$wire.openRenameDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.rename') }}"
                        >
                            {!! FileManagerIcon::Pencil->render('w-3.5 h-3.5') !!}
                        </button>

                        
                        <button
                            x-on:click.stop="$wire.openMoveDialog({{ json_encode($folderId) }})"
                            class="p-1 rounded hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                            title="{{ __('admin::filemanager.move') }}"
                        >
                            {!! FileManagerIcon::ArrowRightCircleMini->render('w-3.5 h-3.5') !!}
                        </button>
                    </div>
                @endif
            </div>
        </div>

        
        @if(count($folder['children']) > 0 && $this->isFolderExpanded($folderId))
            @include('filemanager::filament.pages.partials.folder-tree', ['folders' => $folder['children'], 'level' => $level + 1, 'isReadOnly' => $isReadOnly])
        @endif
    </div>
@endforeach
