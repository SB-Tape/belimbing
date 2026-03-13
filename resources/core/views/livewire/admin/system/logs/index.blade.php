<div>
    <x-slot name="title">{{ __('Logs') }}</x-slot>

    <div class="space-y-section-gap">
        <x-ui.page-header :title="__('Logs')" :subtitle="__('Application log files')" />

        <x-ui.card>
            <div class="overflow-x-auto -mx-card-inner px-card-inner">
                <table class="min-w-full divide-y divide-border-default text-sm">
                    <thead class="bg-surface-subtle/80">
                        <tr>
                            <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('File') }}</th>
                            <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Size') }}</th>
                            <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Last Modified') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface-card divide-y divide-border-default">
                        @forelse($files as $file)
                            <tr wire:key="log-{{ $file->getFilename() }}" class="hover:bg-surface-subtle/50 transition-colors cursor-pointer {{ $selectedFile === $file->getFilename() ? 'bg-surface-subtle' : '' }}" wire:click="selectFile('{{ $file->getFilename() }}')">
                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-accent font-medium">{{ $file->getFilename() }}</td>
                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums">{{ Number::fileSize($file->getSize()) }}</td>
                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted">{{\Carbon\Carbon::createFromTimestamp($file->getMTime())->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-table-cell-x py-8 text-center text-sm text-muted">{{ __('No log files found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        @if ($selectedFile && $tailContent !== null)
            <x-ui.card>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-ink">{{ $selectedFile }}</h3>
                    <x-ui.button variant="ghost" size="sm" wire:click="selectFile('{{ $selectedFile }}')" wire:loading.class="animate-spin" wire:target="selectFile">
                        <x-icon name="heroicon-o-arrow-path" class="w-4 h-4" />
                        {{ __('Refresh') }}
                    </x-ui.button>
                </div>
                <pre class="text-xs font-mono text-ink bg-surface-subtle rounded-lg p-4 overflow-x-auto max-h-[32rem] overflow-y-auto">{{ $tailContent }}</pre>
            </x-ui.card>
        @endif
    </div>
</div>
