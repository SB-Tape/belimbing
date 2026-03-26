<div class="grid gap-4 xl:grid-cols-2">
    <x-ui.card>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-medium tracking-tight text-ink">{{ __('Outgoing references') }}</h2>
                <p class="text-sm text-muted">{{ __('Columns on this table that reference records elsewhere.') }}</p>
            </div>
            <x-ui.badge variant="default">{{ count($foreignKeys['outgoing']) }}</x-ui.badge>
        </div>

        <div class="overflow-x-auto -mx-card-inner px-card-inner">
            <table class="min-w-full divide-y divide-border-default text-sm">
                <thead class="bg-surface-subtle/80">
                    <tr>
                        <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Column') }}</th>
                        <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('References') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-surface-card divide-y divide-border-default">
                    @forelse($foreignKeys['outgoing'] as $fk)
                        <tr wire:key="relationship-outgoing-{{ $fk['column'] }}-{{ $fk['foreign_table'] }}" class="hover:bg-surface-subtle/50 transition-colors">
                            <td class="px-table-cell-x py-table-cell-y text-sm font-mono text-ink">{{ $fk['column'] }}</td>
                            <td class="px-table-cell-x py-table-cell-y text-sm text-ink">
                                <a
                                    href="{{ route('admin.system.database-tables.show', $fk['foreign_table']) }}"
                                    wire:navigate
                                    class="inline-flex items-center gap-1 font-mono text-link hover:underline"
                                >
                                    <x-icon name="heroicon-o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                    {{ $fk['foreign_table'] }}.{{ $fk['foreign_column'] }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-table-cell-x py-8 text-center text-sm text-muted">
                                {{ __('This table does not reference any other registered table.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <x-ui.card>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-medium tracking-tight text-ink">{{ __('Incoming references') }}</h2>
                <p class="text-sm text-muted">{{ __('Other registered tables that point back to this one.') }}</p>
            </div>
            <x-ui.badge variant="default">{{ count($foreignKeys['incoming']) }}</x-ui.badge>
        </div>

        <div class="overflow-x-auto -mx-card-inner px-card-inner">
            <table class="min-w-full divide-y divide-border-default text-sm">
                <thead class="bg-surface-subtle/80">
                    <tr>
                        <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Table') }}</th>
                        <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Column') }}</th>
                        <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Local column') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-surface-card divide-y divide-border-default">
                    @forelse($foreignKeys['incoming'] as $fk)
                        <tr wire:key="relationship-incoming-{{ $fk['table'] }}-{{ $fk['column'] }}" class="hover:bg-surface-subtle/50 transition-colors">
                            <td class="px-table-cell-x py-table-cell-y text-sm text-ink">
                                <a
                                    href="{{ route('admin.system.database-tables.show', $fk['table']) }}"
                                    wire:navigate
                                    class="inline-flex items-center gap-1 font-mono text-link hover:underline"
                                >
                                    <x-icon name="heroicon-o-arrow-uturn-left" class="w-3.5 h-3.5" />
                                    {{ $fk['table'] }}
                                </a>
                            </td>
                            <td class="px-table-cell-x py-table-cell-y text-sm font-mono text-ink">{{ $fk['column'] }}</td>
                            <td class="px-table-cell-x py-table-cell-y text-sm font-mono text-ink">{{ $fk['local_column'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-table-cell-x py-8 text-center text-sm text-muted">
                                {{ __('No other registered table currently references this one.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
