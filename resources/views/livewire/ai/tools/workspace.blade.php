<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>
//
// Per-tool Workspace — overview, setup checklist, health, and test examples.

use App\Modules\Core\AI\DTO\ToolMetadata;
use App\Modules\Core\AI\Enums\ToolHealthState;
use App\Modules\Core\AI\Enums\ToolReadiness;
use App\Modules\Core\AI\Services\ToolMetadataRegistry;
use App\Modules\Core\AI\Services\ToolReadinessService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $toolName;

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $metadataRegistry = app(ToolMetadataRegistry::class);
        $readinessService = app(ToolReadinessService::class);

        $metadata = $metadataRegistry->get($this->toolName);

        if (! $metadata) {
            return [
                'metadata' => null,
                'readiness' => ToolReadiness::UNAVAILABLE,
                'health' => ToolHealthState::UNKNOWN,
            ];
        }

        return [
            'metadata' => $metadata,
            'readiness' => $readinessService->readiness($this->toolName),
            'health' => $readinessService->health($this->toolName),
        ];
    }

    public function backToCatalog(): void
    {
        $this->dispatch('tool-back-to-catalog');
    }
}; ?>

<div>
    {{-- Breadcrumb navigation --}}
    <div class="mb-4">
        <button
            wire:click="backToCatalog"
            class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink transition-colors"
        >
            <x-icon name="heroicon-o-chevron-left" class="w-4 h-4" />
            {{ __('Back to Tools') }}
        </button>
    </div>

    @if($metadata)
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-ink">{{ $metadata->displayName }}</h2>
                    <p class="text-sm text-muted mt-1">{{ $metadata->summary }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-ui.badge :variant="$readiness->color()">{{ $readiness->label() }}</x-ui.badge>
                    <x-ui.badge :variant="$health->color()">{{ $health->label() }}</x-ui.badge>
                </div>
            </div>
        </div>

        {{-- Main content grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left column: Overview + Explanation --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- About this tool --}}
                <x-ui.card>
                    <h3 class="text-base font-semibold text-ink mb-2">{{ __('About this Tool') }}</h3>
                    <p class="text-sm text-muted leading-relaxed">{{ $metadata->explanation }}</p>
                </x-ui.card>

                {{-- Test Examples --}}
                @if(count($metadata->testExamples) > 0)
                    <x-ui.card>
                        <h3 class="text-base font-semibold text-ink mb-2">{{ __('Example Uses') }}</h3>
                        <p class="text-xs text-muted mb-3">{{ __('These examples show how a Digital Worker might invoke this tool. They help you understand what the tool does in practice.') }}</p>

                        <div class="space-y-3">
                            @foreach($metadata->testExamples as $example)
                                <div class="rounded-lg bg-surface-subtle p-3 border border-border-default">
                                    <div class="text-sm font-medium text-ink mb-1">{{ $example['label'] }}</div>
                                    <code class="text-xs text-muted block whitespace-pre-wrap break-all">{{ is_string($example['input']) ? $example['input'] : json_encode($example['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif

                {{-- Limits --}}
                @if(count($metadata->limits) > 0)
                    <x-ui.card>
                        <h3 class="text-base font-semibold text-ink mb-2">{{ __('Limits & Guardrails') }}</h3>
                        <ul class="space-y-1.5">
                            @foreach($metadata->limits as $limit)
                                <li class="flex items-start gap-2 text-sm">
                                    <x-icon name="heroicon-o-shield-check" class="w-4 h-4 text-muted shrink-0 mt-0.5" />
                                    <span class="text-muted">{{ $limit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            </div>

            {{-- Right column: Metadata + Setup --}}
            <div class="space-y-4">
                {{-- Quick facts --}}
                <x-ui.card>
                    <h3 class="text-base font-semibold text-ink mb-3">{{ __('Details') }}</h3>
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-muted uppercase tracking-wider">{{ __('Machine Name') }}</dt>
                            <dd class="text-sm text-ink font-mono mt-0.5">{{ $metadata->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted uppercase tracking-wider">{{ __('Category') }}</dt>
                            <dd class="text-sm text-ink mt-0.5">{{ $metadata->category->label() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted uppercase tracking-wider">{{ __('Risk Class') }}</dt>
                            <dd class="mt-0.5"><x-ui.badge :variant="$metadata->riskClass->color()">{{ $metadata->riskClass->label() }}</x-ui.badge></dd>
                        </div>
                        @if($metadata->capability)
                            <div>
                                <dt class="text-xs text-muted uppercase tracking-wider">{{ __('Capability') }}</dt>
                                <dd class="text-sm text-ink font-mono mt-0.5">{{ $metadata->capability }}</dd>
                            </div>
                        @endif
                    </dl>
                </x-ui.card>

                {{-- Setup requirements --}}
                @if(count($metadata->setupRequirements) > 0)
                    <x-ui.card>
                        <h3 class="text-base font-semibold text-ink mb-2">{{ __('Setup Checklist') }}</h3>
                        <p class="text-xs text-muted mb-3">{{ __('Requirements that must be met for this tool to work correctly.') }}</p>

                        <ul class="space-y-2">
                            @foreach($metadata->setupRequirements as $req)
                                <li class="flex items-start gap-2">
                                    <x-icon name="heroicon-o-information-circle" class="w-4 h-4 text-muted shrink-0 mt-0.5" />
                                    <span class="text-sm text-muted">{{ $req }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif

                {{-- Health checks --}}
                @if(count($metadata->healthChecks) > 0)
                    <x-ui.card>
                        <h3 class="text-base font-semibold text-ink mb-2">{{ __('Health Checks') }}</h3>
                        <ul class="space-y-1.5">
                            @foreach($metadata->healthChecks as $check)
                                <li class="flex items-start gap-2 text-sm">
                                    <x-icon name="heroicon-o-signal" class="w-4 h-4 text-muted shrink-0 mt-0.5" />
                                    <span class="text-muted">{{ $check }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.card>
                @endif
            </div>
        </div>
    @else
        {{-- Tool not found --}}
        <x-ui.card>
            <div class="text-center py-8">
                <p class="text-sm text-muted">{{ __('Tool ":name" not found in the metadata registry.', ['name' => $toolName]) }}</p>
            </div>
        </x-ui.card>
    @endif
</div>
