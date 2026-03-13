<?php
// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

/** @var \App\Base\Database\Livewire\Tables\Show $this */
?>

<div>
    <x-slot name="title">{{ $this->tableName }}</x-slot>

    <div class="space-y-section-gap">
        <x-ui.page-header
            :title="$this->tableName"
            :subtitle="trans_choice(':count row|:count rows', $rowCount, ['count' => number_format($rowCount)])"
            :pinnable="[
                'pinnableId' => 'system.table.' . $this->tableName,
                'label' => $this->tableName,
                'icon' => 'heroicon-o-table-cells',
            ]"
        >
            <x-slot name="actions">
                <x-ui.button variant="ghost" size="sm" href="{{ route('admin.system.tables.index') }}">
                    <x-icon name="heroicon-o-arrow-left" class="w-4 h-4" />
                    {{ __('Back to Registry') }}
                </x-ui.button>
            </x-slot>
        </x-ui.page-header>

        <x-ui.card>
            <div class="mb-2 flex items-center justify-between gap-4">
                <div class="flex-1">
                    <x-ui.search-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search across text columns...') }}"
                    />
                </div>
                <span class="text-xs text-muted whitespace-nowrap tabular-nums">
                    {{ trans_choice(':count column|:count columns', count($columns), ['count' => count($columns)]) }}
                </span>
            </div>

            <div class="overflow-x-auto -mx-card-inner px-card-inner">
                <table class="min-w-full divide-y divide-border-default text-sm">
                    <thead class="bg-surface-subtle/80">
                        <tr>
                            @foreach($columns as $col)
                                <th
                                    wire:click="sort('{{ $col['name'] }}')"
                                    class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider cursor-pointer select-none hover:text-ink transition-colors"
                                    title="{{ $col['type_name'] }}{{ $col['nullable'] ? ', nullable' : '' }}"
                                >
                                    <span class="inline-flex items-center gap-1">
                                        {{ $col['name'] }}
                                        @if($this->sortColumn === $col['name'])
                                            @if($this->sortDirection === 'asc')
                                                <x-icon name="heroicon-m-chevron-up" class="w-3 h-3" />
                                            @else
                                                <x-icon name="heroicon-m-chevron-down" class="w-3 h-3" />
                                            @endif
                                        @endif
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="bg-surface-card divide-y divide-border-default">
                        @forelse($rows as $row)
                            <tr wire:key="row-{{ $loop->index }}" class="hover:bg-surface-subtle/50 transition-colors">
                                @foreach($columns as $col)
                                    @php
                                        $value = data_get((array) $row, $col['name']);
                                        $formatted = $this->formatCell($value, $col['type_name']);
                                        $isLong = $value !== null && mb_strlen((string) $value) > 120;
                                    @endphp
                                    <td
                                        class="px-table-cell-x py-table-cell-y text-sm font-mono whitespace-nowrap {{ $value === null ? 'text-muted' : 'text-ink' }}"
                                        @if($isLong) title="{{ Str::limit((string) $value, 500) }}" @endif
                                    >{{ $formatted }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) }}" class="px-table-cell-x py-8 text-center text-sm text-muted">
                                    {{ $this->search ? __('No rows match your search.') : __('This table is empty.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-2">
                {{ $rows->links() }}
            </div>
        </x-ui.card>
    </div>
</div>
