<x-filament-panels::page>
    <style>
        .wma-doc-app { display: flex; flex-direction: column; gap: 0.75rem; }
        .wma-doc-status {
            padding: 3rem 1rem; text-align: center; color: rgb(107 114 128);
            font-size: 0.875rem;
        }
        .wma-doc-status--error { color: rgb(220 38 38); }
        .wma-doc-switcher {
            display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;
        }
        .wma-doc-switcher label { color: rgb(107 114 128); white-space: nowrap; }
        .wma-doc-switcher select {
            border-radius: 0.5rem; border: 1px solid rgb(209 213 219);
            padding: 0.375rem 0.5rem; font-size: 0.875rem; background: white; min-width: 16rem;
        }
        .dark .wma-doc-switcher select {
            background: rgb(31 41 55); border-color: rgb(75 85 99); color: rgb(229 231 235);
        }
        .wma-doc-filemanager { height: 75vh; min-height: 32rem; border-radius: 0.75rem; overflow: hidden; }
    </style>

    <div
        id="document-manager-root"
        data-props="{{ json_encode([
            'apiBase' => url('/panel-api/documents'),
            'currentUserId' => auth()->id(),
            'isAdmin' => auth()->user()->isAdmin(),
        ]) }}"
    ></div>

    @vite('resources/js/documents.jsx')
</x-filament-panels::page>
