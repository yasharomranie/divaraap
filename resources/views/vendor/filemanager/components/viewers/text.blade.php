
@php
    $content = $content ?? null;
    $url = $url ?? null;
    $item = $item ?? null;

    $extension = $item ? strtolower(pathinfo($item->name, PATHINFO_EXTENSION)) : '';

    $languageMap = [
        'php' => 'php',
        'js' => 'javascript',
        'ts' => 'typescript',
        'jsx' => 'jsx',
        'tsx' => 'tsx',
        'json' => 'json',
        'xml' => 'xml',
        'html' => 'html',
        'css' => 'css',
        'py' => 'python',
        'rb' => 'ruby',
        'java' => 'java',
        'c' => 'c',
        'cpp' => 'cpp',
        'go' => 'go',
        'rs' => 'rust',
        'sql' => 'sql',
        'sh' => 'bash',
        'bash' => 'bash',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'md' => 'markdown',
    ];

    $language = $languageMap[$extension] ?? 'plaintext';
@endphp

<div class="bg-gray-100 dark:bg-gray-900 rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-4 py-2 bg-gray-200 dark:bg-gray-800 border-b border-gray-300 dark:border-gray-700">
        <span class="text-sm text-gray-600 dark:text-gray-400">
            {{ $item?->name ?? __('admin::filemanager.text_file') }}
        </span>
        <span class="text-xs text-gray-500 uppercase">{{ $extension }}</span>
    </div>
    <pre dir="auto" class="p-4 text-sm text-gray-800 dark:text-gray-100 overflow-auto max-h-[60vh] font-mono" data-language="{{ $language }}"><code>{{ $content }}</code></pre>
</div>
