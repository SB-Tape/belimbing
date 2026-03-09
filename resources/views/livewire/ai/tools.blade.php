<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>
//
// Tool Workspace orchestrator — routes between catalog and per-tool workspace.

use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /** @var string|null null = catalog view, tool name = workspace view */
    public ?string $selectedTool = null;

    #[On('tool-selected')]
    public function onToolSelected(string $toolName): void
    {
        $this->selectedTool = $toolName;
    }

    #[On('tool-back-to-catalog')]
    public function onBackToCatalog(): void
    {
        $this->selectedTool = null;
    }
}; ?>

<div>
    <x-slot name="title">{{ __('Tools') }}</x-slot>

    @if($selectedTool)
        <livewire:ai.tools.workspace :tool-name="$selectedTool" :key="'workspace-' . $selectedTool" />
    @else
        <livewire:ai.tools.catalog />
    @endif
</div>
