<x-filament-panels::page>
    {{ $this->content }}

    @if (filled($output))
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-6">
            <div class="fi-section-header flex items-center justify-between gap-3 px-6 py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                    Output
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Exit code: {{ $lastExitCode ?? '—' }}
                </span>
            </div>
            <div class="fi-section-content px-6 pb-6">
                <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-sm leading-6 text-gray-100 whitespace-pre-wrap">{{ $output }}</pre>
            </div>
        </div>
    @endif
</x-filament-panels::page>
