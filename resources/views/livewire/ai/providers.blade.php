<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>
//
// Provider catalog and onboarding flow inspired by OpenClaw
// (github.com/nicepkg/openclaw). Adapted for BLB's GUI context.

use App\Modules\Core\AI\Models\AiProvider;
use App\Modules\Core\AI\Models\AiProviderModel;
use App\Modules\Core\AI\Services\ProviderAuthFlowService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $search = '';

    public ?int $expandedProviderId = null;

    // --- Wizard state ---

    /** @var string|null null = manage view, 'catalog' = step 1, 'connect' = step 2 */
    public ?string $wizardStep = null;

    /** @var list<string> Selected template keys for onboarding */
    public array $selectedTemplates = [];

    /** @var string|null Which catalog provider row is expanded */
    public ?string $expandedCatalogProvider = null;

    /** @var list<array{key: string, display_name: string, base_url: string, api_key: string, api_key_url: string|null}>  Connect form data */
    public array $connectForms = [];

    /** @var array<int, array{status: string, user_code: string|null, verification_uri: string|null, error: string|null}>  Device flow state per connect form index */
    public array $deviceFlows = [];

    // --- Provider form (manual CRUD) ---

    public bool $showProviderForm = false;

    public bool $isEditingProvider = false;

    public ?int $editingProviderId = null;

    public string $providerName = '';

    public string $providerDisplayName = '';

    public string $providerBaseUrl = '';

    public string $providerApiKey = '';

    public bool $providerIsActive = true;

    public string $selectedTemplate = '';

    // Provider delete
    public bool $showDeleteProvider = false;

    public ?int $deletingProviderId = null;

    public string $deletingProviderName = '';

    // Model form
    public bool $showModelForm = false;

    public bool $isEditingModel = false;

    public ?int $editingModelId = null;

    public ?int $modelProviderId = null;

    public string $modelModelName = '';

    public string $modelDisplayName = '';

    public string $modelCapabilityTags = '';

    public bool $modelIsActive = true;

    public string $modelCostInput = '0.000000';

    public string $modelCostOutput = '0.000000';

    public string $modelCostCacheRead = '0.000000';

    public string $modelCostCacheWrite = '0.000000';

    // Model delete
    public bool $showDeleteModel = false;

    public ?int $deletingModelId = null;

    public string $deletingModelName = '';

    public function mount(): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return;
        }

        $hasProviders = AiProvider::query()->forCompany($companyId)->exists();

        if (! $hasProviders) {
            $this->wizardStep = 'catalog';
        }
    }

    public function toggleProvider(int $providerId): void
    {
        $this->expandedProviderId = $this->expandedProviderId === $providerId ? null : $providerId;
    }

    // --- Wizard methods ---

    /**
     * Open the provider catalog (step 1 of onboarding wizard).
     */
    public function openCatalog(): void
    {
        $this->selectedTemplates = [];
        $this->expandedCatalogProvider = null;
        $this->wizardStep = 'catalog';
    }

    /**
     * Toggle expansion of a provider row in the catalog view.
     */
    public function toggleCatalogProvider(string $key): void
    {
        $this->expandedCatalogProvider = $this->expandedCatalogProvider === $key ? null : $key;
    }

    /**
     * Toggle selection of a template provider in the catalog.
     */
    public function toggleSelectTemplate(string $key): void
    {
        if (in_array($key, $this->selectedTemplates, true)) {
            $this->selectedTemplates = array_values(array_diff($this->selectedTemplates, [$key]));
        } else {
            $this->selectedTemplates[] = $key;
        }
    }

    /**
     * Advance from catalog (step 1) to connect (step 2).
     *
     * Builds per-provider connect forms pre-filled from templates.
     */
    public function proceedToConnect(): void
    {
        if (count($this->selectedTemplates) === 0) {
            return;
        }

        $templates = config('ai.provider_templates', []);
        $this->connectForms = [];

        foreach ($this->selectedTemplates as $key) {
            $tpl = $templates[$key] ?? null;

            if ($tpl === null) {
                continue;
            }

            $formEntry = [
                'key' => $key,
                'display_name' => $tpl['display_name'] ?? $key,
                'base_url' => $tpl['base_url'] ?? '',
                'api_key' => '',
                'api_key_url' => $tpl['api_key_url'] ?? null,
                'auth_type' => $tpl['auth_type'] ?? 'api_key',
            ];

            // Cloudflare AI Gateway needs Account ID + Gateway ID to build the URL
            if ($key === 'cloudflare-ai-gateway') {
                $formEntry['cloudflare_account_id'] = '';
                $formEntry['cloudflare_gateway_id'] = '';
            }

            $this->connectForms[] = $formEntry;
        }

        $this->wizardStep = 'connect';
    }

    /**
     * Go back from connect (step 2) to catalog (step 1).
     */
    public function backToCatalog(): void
    {
        $this->cleanupAuthFlows();
        $this->deviceFlows = [];
        $this->wizardStep = 'catalog';
    }

    // --- Auth Flow methods (dispatched to ProviderAuthFlowService) ---

    /**
     * Start an interactive auth flow for a connect form entry.
     *
     * Delegates to ProviderAuthFlowService which handles provider-specific
     * logic (e.g., GitHub device flow). Sensitive data stays in server cache.
     *
     * @param  int  $index  Connect form index
     */
    public function startDeviceFlow(int $index): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return;
        }

        $service = app(ProviderAuthFlowService::class);
        $this->deviceFlows[$index] = $service->startFlow(
            $this->connectForms[$index]['key'],
            $companyId,
            $index,
        );
    }

    /**
     * Poll an active auth flow for completion (called via wire:poll).
     *
     * On success, updates connectForms with the obtained credentials.
     *
     * @param  int  $index  Connect form index
     */
    public function pollDeviceFlow(int $index): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return;
        }

        $service = app(ProviderAuthFlowService::class);
        $result = $service->pollFlow(
            $this->connectForms[$index]['key'],
            $companyId,
            $index,
        );

        if ($result['status'] === 'pending') {
            return;
        }

        if ($result['status'] === 'success') {
            $this->connectForms[$index]['api_key'] = $result['api_key'] ?? '';
            $this->connectForms[$index]['base_url'] = $result['base_url'] ?? $this->connectForms[$index]['base_url'];
        }

        $this->deviceFlows[$index]['status'] = $result['status'];
        $this->deviceFlows[$index]['error'] = $result['error'] ?? null;
    }

    /**
     * Create providers and import models for all connected forms.
     */
    public function connectAll(): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return;
        }

        $rules = [];

        foreach ($this->connectForms as $index => $form) {
            $authType = $form['auth_type'] ?? 'api_key';
            $key = $form['key'];

            // Cloudflare uses Account ID + Gateway ID instead of base_url input
            if ($key === 'cloudflare-ai-gateway') {
                $rules["connectForms.{$index}.cloudflare_account_id"] = ['required', 'string', 'max:255'];
                $rules["connectForms.{$index}.cloudflare_gateway_id"] = ['required', 'string', 'max:255'];
            } else {
                $rules["connectForms.{$index}.base_url"] = ['required', 'string', 'max:2048'];
            }

            if (in_array($authType, ['api_key', 'custom', 'device_flow'], true)) {
                $rules["connectForms.{$index}.api_key"] = ['required', 'string', 'max:2048'];
            } else {
                $rules["connectForms.{$index}.api_key"] = ['nullable', 'string', 'max:2048'];
            }
        }

        $this->validate($rules, [
            'connectForms.*.base_url.required' => __('Base URL is required.'),
            'connectForms.*.api_key.required' => __('API key is required.'),
            'connectForms.*.cloudflare_account_id.required' => __('Account ID is required.'),
            'connectForms.*.cloudflare_gateway_id.required' => __('Gateway ID is required.'),
        ]);

        $templates = config('ai.provider_templates', []);

        foreach ($this->connectForms as $form) {
            $key = $form['key'];
            $tpl = $templates[$key] ?? null;

            $existing = AiProvider::query()
                ->forCompany($companyId)
                ->where('name', $key)
                ->first();

            if ($existing) {
                continue;
            }

            // Cloudflare: build base_url from Account ID + Gateway ID
            $baseUrl = $form['base_url'];

            if ($key === 'cloudflare-ai-gateway') {
                $accountId = trim($form['cloudflare_account_id'] ?? '');
                $gatewayId = trim($form['cloudflare_gateway_id'] ?? '');
                $baseUrl = "https://gateway.ai.cloudflare.com/v1/{$accountId}/{$gatewayId}/openai";
            }

            $provider = AiProvider::query()->create([
                'company_id' => $companyId,
                'name' => $key,
                'display_name' => $form['display_name'],
                'base_url' => $baseUrl,
                'api_key' => $form['api_key'] !== '' ? $form['api_key'] : 'not-required',
                'is_active' => true,
                'created_by' => auth()->user()->employee?->id,
            ]);

            if ($tpl !== null && ! empty($tpl['models'])) {
                foreach ($tpl['models'] as $modelTemplate) {
                    AiProviderModel::query()->create([
                        'ai_provider_id' => $provider->id,
                        'model_name' => $modelTemplate['model_name'],
                        'display_name' => $modelTemplate['display_name'],
                        'capability_tags' => $modelTemplate['capability_tags'] ?? [],
                        'context_window' => $modelTemplate['context_window'] ?? null,
                        'max_tokens' => $modelTemplate['max_tokens'] ?? null,
                        'is_active' => true,
                        'cost_per_1m' => $modelTemplate['cost_per_1m'] ?? null,
                    ]);
                }
            }
        }

        $this->cleanupAuthFlows();
        $this->wizardStep = null;
        $this->selectedTemplates = [];
        $this->connectForms = [];
        $this->deviceFlows = [];
    }

    /**
     * Cancel the wizard and return to management view.
     */
    public function cancelWizard(): void
    {
        $this->cleanupAuthFlows();
        $this->wizardStep = null;
        $this->selectedTemplates = [];
        $this->connectForms = [];
        $this->deviceFlows = [];
        $this->expandedCatalogProvider = null;
    }

    // --- Provider CRUD ---

    public function openCreateProvider(): void
    {
        $this->resetProviderForm();
        $this->isEditingProvider = false;
        $this->showProviderForm = true;
    }

    /**
     * Apply a provider template, pre-filling form fields from config.
     */
    public function applyTemplate(string $templateKey): void
    {
        $this->selectedTemplate = $templateKey;

        if ($templateKey === '') {
            return;
        }

        $template = config('ai.provider_templates.'.$templateKey);

        if ($template === null) {
            return;
        }

        $this->providerName = $templateKey;
        $this->providerDisplayName = $template['display_name'] ?? '';
        $this->providerBaseUrl = $template['base_url'] ?? '';
    }

    /**
     * Import suggested models from a provider template into an existing provider.
     */
    public function importTemplateModels(int $providerId): void
    {
        $provider = AiProvider::query()->find($providerId);

        if (! $provider) {
            return;
        }

        $template = config('ai.provider_templates.'.$provider->name);

        if ($template === null || empty($template['models'])) {
            return;
        }

        $existingModels = AiProviderModel::query()
            ->where('ai_provider_id', $providerId)
            ->pluck('model_name')
            ->all();

        foreach ($template['models'] as $modelTemplate) {
            if (in_array($modelTemplate['model_name'], $existingModels, true)) {
                continue;
            }

            AiProviderModel::query()->create([
                'ai_provider_id' => $providerId,
                'model_name' => $modelTemplate['model_name'],
                'display_name' => $modelTemplate['display_name'],
                'capability_tags' => $modelTemplate['capability_tags'] ?? [],
                'context_window' => $modelTemplate['context_window'] ?? null,
                'max_tokens' => $modelTemplate['max_tokens'] ?? null,
                'is_active' => true,
                'cost_per_1m' => $modelTemplate['cost_per_1m'] ?? null,
            ]);
        }
    }

    public function openEditProvider(int $providerId): void
    {
        $provider = AiProvider::query()->find($providerId);

        if (! $provider) {
            return;
        }

        $this->resetProviderForm();
        $this->isEditingProvider = true;
        $this->editingProviderId = $providerId;
        $this->providerName = $provider->name;
        $this->providerDisplayName = $provider->display_name ?? '';
        $this->providerBaseUrl = $provider->base_url;
        $this->providerIsActive = $provider->is_active;
        $this->showProviderForm = true;
    }

    public function saveProvider(): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return;
        }

        $rules = [
            'providerName' => ['required', 'string', 'max:255'],
            'providerDisplayName' => ['nullable', 'string', 'max:255'],
            'providerBaseUrl' => ['required', 'string', 'max:2048'],
            'providerIsActive' => ['boolean'],
        ];

        if ($this->isEditingProvider) {
            $rules['providerApiKey'] = ['nullable', 'string', 'max:2048'];
        } else {
            $rules['providerApiKey'] = ['required', 'string', 'max:2048'];
        }

        $this->validate($rules);

        $data = [
            'company_id' => $companyId,
            'name' => $this->providerName,
            'display_name' => $this->providerDisplayName ?: $this->providerName,
            'base_url' => $this->providerBaseUrl,
            'is_active' => $this->providerIsActive,
        ];

        if ($this->isEditingProvider && $this->editingProviderId) {
            $provider = AiProvider::query()->find($this->editingProviderId);

            if ($provider) {
                if ($this->providerApiKey !== '') {
                    $data['api_key'] = $this->providerApiKey;
                }

                $provider->update($data);
            }
        } else {
            $data['api_key'] = $this->providerApiKey;
            $data['created_by'] = auth()->user()->employee?->id;
            AiProvider::query()->create($data);
        }

        $this->showProviderForm = false;
        $this->resetProviderForm();
    }

    public function confirmDeleteProvider(int $providerId): void
    {
        $provider = AiProvider::query()->find($providerId);

        if (! $provider) {
            return;
        }

        $this->deletingProviderId = $providerId;
        $this->deletingProviderName = $provider->display_name ?? $provider->name;
        $this->showDeleteProvider = true;
    }

    public function deleteProvider(): void
    {
        if ($this->deletingProviderId === null) {
            return;
        }

        $provider = AiProvider::query()->find($this->deletingProviderId);

        if ($provider) {
            $provider->models()->delete();
            $provider->delete();
        }

        if ($this->expandedProviderId === $this->deletingProviderId) {
            $this->expandedProviderId = null;
        }

        $this->showDeleteProvider = false;
        $this->deletingProviderId = null;
        $this->deletingProviderName = '';
    }

    // --- Model CRUD ---

    public function openCreateModel(int $providerId): void
    {
        $this->resetModelForm();
        $this->isEditingModel = false;
        $this->modelProviderId = $providerId;
        $this->showModelForm = true;
    }

    public function openEditModel(int $modelId): void
    {
        $model = AiProviderModel::query()->find($modelId);

        if (! $model) {
            return;
        }

        $this->resetModelForm();
        $this->isEditingModel = true;
        $this->editingModelId = $modelId;
        $this->modelProviderId = $model->ai_provider_id;
        $this->modelModelName = $model->model_name;
        $this->modelDisplayName = $model->display_name ?? '';
        $this->modelCapabilityTags = is_array($model->capability_tags) ? implode(', ', $model->capability_tags) : '';
        $this->modelIsActive = $model->is_active;
        $cost = $model->cost_per_1m ?? [];
        $this->modelCostInput = $cost['input'] ?? '0.000000';
        $this->modelCostOutput = $cost['output'] ?? '0.000000';
        $this->modelCostCacheRead = $cost['cache_read'] ?? '0.000000';
        $this->modelCostCacheWrite = $cost['cache_write'] ?? '0.000000';
        $this->showModelForm = true;
    }

    public function saveModel(): void
    {
        if ($this->modelProviderId === null) {
            return;
        }

        $this->validate([
            'modelModelName' => ['required', 'string', 'max:255'],
            'modelDisplayName' => ['nullable', 'string', 'max:255'],
            'modelCapabilityTags' => ['nullable', 'string'],
            'modelIsActive' => ['boolean'],
            'modelCostInput' => ['nullable', 'numeric', 'min:0'],
            'modelCostOutput' => ['nullable', 'numeric', 'min:0'],
            'modelCostCacheRead' => ['nullable', 'numeric', 'min:0'],
            'modelCostCacheWrite' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tags = $this->modelCapabilityTags !== ''
            ? array_map('trim', explode(',', $this->modelCapabilityTags))
            : [];

        $costPer1m = [
            'input' => $this->modelCostInput ?: null,
            'output' => $this->modelCostOutput ?: null,
            'cache_read' => $this->modelCostCacheRead ?: null,
            'cache_write' => $this->modelCostCacheWrite ?: null,
        ];
        $hasAnyCost = array_filter($costPer1m, fn ($v) => $v !== null && $v !== '') !== [];

        $data = [
            'ai_provider_id' => $this->modelProviderId,
            'model_name' => $this->modelModelName,
            'display_name' => $this->modelDisplayName ?: $this->modelModelName,
            'capability_tags' => array_values(array_filter($tags)),
            'is_active' => $this->modelIsActive,
            'cost_per_1m' => $hasAnyCost ? $costPer1m : null,
        ];

        if ($this->isEditingModel && $this->editingModelId) {
            $model = AiProviderModel::query()->find($this->editingModelId);

            if ($model) {
                $model->update($data);
            }
        } else {
            AiProviderModel::query()->create($data);
        }

        $this->showModelForm = false;
        $this->resetModelForm();
    }

    public function confirmDeleteModel(int $modelId): void
    {
        $model = AiProviderModel::query()->find($modelId);

        if (! $model) {
            return;
        }

        $this->deletingModelId = $modelId;
        $this->deletingModelName = $model->display_name ?? $model->model_name;
        $this->showDeleteModel = true;
    }

    public function deleteModel(): void
    {
        if ($this->deletingModelId === null) {
            return;
        }

        AiProviderModel::query()->where('id', $this->deletingModelId)->delete();

        $this->showDeleteModel = false;
        $this->deletingModelId = null;
        $this->deletingModelName = '';
    }

    public function with(): array
    {
        $companyId = $this->getCompanyId();
        $providers = collect();
        $expandedModels = collect();

        if ($companyId !== null) {
            $query = AiProvider::query()
                ->forCompany($companyId)
                ->withCount('models');

            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('display_name', 'like', '%'.$search.'%');
                });
            }

            $providers = $query->orderBy('display_name')->get();

            if ($this->expandedProviderId !== null) {
                $expandedModels = AiProviderModel::query()
                    ->where('ai_provider_id', $this->expandedProviderId)
                    ->orderBy('display_name')
                    ->get();
            }
        }

        $templates = collect(config('ai.provider_templates', []))
            ->map(fn ($t, $key) => ['value' => $key, 'label' => $t['display_name'] ?? $key])
            ->values()
            ->all();

        $hasTemplateModels = false;

        if ($this->expandedProviderId !== null) {
            $expandedProvider = $providers->firstWhere('id', $this->expandedProviderId);

            if ($expandedProvider) {
                $template = config('ai.provider_templates.'.$expandedProvider->name);
                $hasTemplateModels = ! empty($template['models']);
            }
        }

        $connectedNames = $providers->pluck('name')->all();

        $catalog = collect(config('ai.provider_templates', []))
            ->map(function ($tpl, $key) use ($connectedNames) {
                $models = $tpl['models'] ?? [];
                $allCosts = [];

                foreach ($models as $m) {
                    $costPer1m = $m['cost_per_1m'] ?? [];
                    foreach (['input', 'output', 'cache_read', 'cache_write'] as $dim) {
                        $c = $costPer1m[$dim] ?? null;
                        if ($c !== null && $c !== '') {
                            $allCosts[] = (float) $c;
                        }
                    }
                }

                $minCost = $allCosts !== [] ? min($allCosts) : null;
                $maxCost = $allCosts !== [] ? max($allCosts) : null;
                $costRange = null;
                if ($minCost !== null && $maxCost !== null) {
                    $costRange = $minCost === $maxCost ? $minCost : ['min' => $minCost, 'max' => $maxCost];
                }

                return [
                    'key' => $key,
                    'display_name' => $tpl['display_name'] ?? $key,
                    'description' => $tpl['description'] ?? '',
                    'base_url' => $tpl['base_url'] ?? '',
                    'api_key_url' => $tpl['api_key_url'] ?? null,
                    'auth_type' => $tpl['auth_type'] ?? 'api_key',
                    'model_count' => count($models),
                    'cost_range' => $costRange,
                    'models' => $models,
                    'connected' => in_array($key, $connectedNames, true),
                ];
            })
            ->values()
            ->all();

        return [
            'providers' => $providers,
            'expandedModels' => $expandedModels,
            'templateOptions' => $templates,
            'hasTemplateModels' => $hasTemplateModels,
            'catalog' => $catalog,
        ];
    }

    /**
     * Format a cost value for display (2 decimal places).
     */
    public function formatCost(?string $cost): string
    {
        if ($cost === null || $cost === '') {
            return '—';
        }

        return '$'.number_format((float) $cost, 2);
    }

    /**
     * Format a token count for display (e.g. 200000 → "200K", 1048576 → "1M").
     */
    public function formatTokenCount(?int $count): string
    {
        if ($count === null) {
            return '—';
        }

        if ($count >= 1000000) {
            $value = $count / 1000000;

            return ($value == (int) $value ? (int) $value : number_format($value, 1)).'M';
        }

        if ($count >= 1000) {
            $value = $count / 1000;

            return ($value == (int) $value ? (int) $value : number_format($value, 1)).'K';
        }

        return (string) $count;
    }

    private function getCompanyId(): ?int
    {
        $user = auth()->user();

        return $user?->employee?->company_id ? (int) $user->employee->company_id : null;
    }

    /**
     * Clean up all cached auth flow data for this company.
     */
    private function cleanupAuthFlows(): void
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null || count($this->deviceFlows) === 0) {
            return;
        }

        $service = app(ProviderAuthFlowService::class);
        $service->cleanupFlows($companyId, array_keys($this->deviceFlows));
    }

    private function resetProviderForm(): void
    {
        $this->editingProviderId = null;
        $this->providerName = '';
        $this->providerDisplayName = '';
        $this->providerBaseUrl = '';
        $this->providerApiKey = '';
        $this->providerIsActive = true;
        $this->selectedTemplate = '';
        $this->resetValidation();
    }

    private function resetModelForm(): void
    {
        $this->editingModelId = null;
        $this->modelProviderId = null;
        $this->modelModelName = '';
        $this->modelDisplayName = '';
        $this->modelCapabilityTags = '';
        $this->modelIsActive = true;
        $this->modelCostInput = '0.000000';
        $this->modelCostOutput = '0.000000';
        $this->modelCostCacheRead = '0.000000';
        $this->modelCostCacheWrite = '0.000000';
        $this->resetValidation();
    }
}; ?>

<div>
    <x-slot name="title">{{ __('LLM Providers') }}</x-slot>

    @if($wizardStep === 'catalog')
        {{-- ========================================== --}}
        {{-- STEP 1: Provider Catalog                   --}}
        {{-- ========================================== --}}
        <div class="space-y-section-gap">
            <x-ui.page-header :title="__('Choose Providers')" :subtitle="__('Browse available LLM providers and select the ones you want to connect.')">
                <x-slot name="help">
                    <div class="space-y-2">
                        <p>{{ __('Select one or more providers from the catalog below. Expand a row to compare models, context windows, and pricing. After selecting, you\'ll enter your API key for each provider.') }}</p>
                        <p>{{ __('Each provider requires an API key from their developer dashboard. Providers like Ollama are free and run locally. GitHub Copilot is included with a GitHub Copilot subscription.') }}</p>
                    </div>
                </x-slot>
                <x-slot name="actions">
                    <x-ui.button variant="ghost" wire:click="cancelWizard">
                        {{ __('Cancel') }}
                    </x-ui.button>
                    <x-ui.button
                        variant="primary"
                        wire:click="proceedToConnect"
                        :disabled="count($selectedTemplates) === 0"
                    >
                        <x-icon name="heroicon-o-sparkles" class="w-4 h-4" />
                        {{ count($selectedTemplates) === 0 ? __('Connect Providers') : __('Connect Providers (:count)', ['count' => count($selectedTemplates)]) }}
                    </x-ui.button>
                </x-slot>
            </x-ui.page-header>

            <x-ui.card>
                <div class="overflow-x-auto -mx-card-inner px-card-inner">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-subtle/80">
                            <tr>
                                <th class="px-table-cell-x py-table-header-y w-8"></th>
                                <th class="px-table-cell-x py-table-header-y w-8"></th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Provider') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Description') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Models') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Cost $/1M') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface-card divide-y divide-border-default">
                            @foreach($catalog as $entry)
                                <tr
                                    wire:key="catalog-{{ $entry['key'] }}"
                                    wire:click="toggleCatalogProvider('{{ $entry['key'] }}')"
                                    class="hover:bg-surface-subtle/50 transition-colors cursor-pointer"
                                >
                                    <td class="px-table-cell-x py-table-cell-y" wire:click.stop>
                                        @if($entry['connected'])
                                            <span class="w-4 h-4 block"></span>
                                        @else
                                            <input
                                                type="checkbox"
                                                class="w-4 h-4 rounded border border-border-input bg-surface-card accent-accent focus:ring-2 focus:ring-accent focus:ring-offset-2"
                                                @checked(in_array($entry['key'], $selectedTemplates, true))
                                                wire:click="toggleSelectTemplate('{{ $entry['key'] }}')"
                                            />
                                        @endif
                                    </td>
                                    <td class="px-table-cell-x py-table-cell-y">
                                        <x-icon
                                            :name="$expandedCatalogProvider === $entry['key'] ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right'"
                                            class="w-4 h-4 text-muted"
                                        />
                                    </td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm font-medium text-ink">{{ $entry['display_name'] }}</td>
                                    <td class="px-table-cell-x py-table-cell-y text-sm text-muted">{{ $entry['description'] }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $entry['model_count'] ?: '—' }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">
                                        @if(is_array($entry['cost_range'] ?? null))
                                            {{ $this->formatCost((string) $entry['cost_range']['min']) }}–{{ $this->formatCost((string) $entry['cost_range']['max']) }}
                                        @elseif(($entry['cost_range'] ?? null) !== null)
                                            {{ $this->formatCost((string) $entry['cost_range']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap">
                                        @if($entry['connected'])
                                            <x-ui.badge variant="success">{{ __('Connected') }}</x-ui.badge>
                                        @endif
                                    </td>
                                </tr>

                                {{-- Expanded model catalog --}}
                                @if($expandedCatalogProvider === $entry['key'] && count($entry['models']) > 0)
                                    <tr wire:key="catalog-{{ $entry['key'] }}-models">
                                        <td colspan="7" class="p-0">
                                            <div class="bg-surface-subtle/30 border-t border-border-default px-8 py-3">
                                                <span class="text-[11px] uppercase tracking-wider font-semibold text-muted mb-2 block">{{ __('Model Catalog') }}</span>
                                                <table class="min-w-full divide-y divide-border-default text-sm">
                                                    <thead class="bg-surface-subtle/80">
                                                        <tr>
                                                            <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Model') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Tags') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Context') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Max Output') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Input $/1M') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Output $/1M') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Cache Read $/1M') }}</th>
                                                            <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Cache Write $/1M') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-surface-card divide-y divide-border-default">
                                                        @foreach($entry['models'] as $catModel)
                                                            <tr class="hover:bg-surface-subtle/50 transition-colors">
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm font-medium text-ink">{{ $catModel['display_name'] }}</td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap">
                                                                    @if(!empty($catModel['capability_tags']))
                                                                        <div class="flex gap-1 flex-wrap">
                                                                            @foreach($catModel['capability_tags'] as $tag)
                                                                                <x-ui.badge>{{ $tag }}</x-ui.badge>
                                                                            @endforeach
                                                                        </div>
                                                                    @else
                                                                        <span class="text-muted">—</span>
                                                                    @endif
                                                                </td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatTokenCount($catModel['context_window'] ?? null) }}</td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatTokenCount($catModel['max_tokens'] ?? null) }}</td>
                                                                @php $cost = $catModel['cost_per_1m'] ?? []; @endphp
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['input'] ?? null) }}</td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['output'] ?? null) }}</td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['cache_read'] ?? null) }}</td>
                                                                <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['cache_write'] ?? null) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                @elseif($expandedCatalogProvider === $entry['key'] && count($entry['models']) === 0)
                                    <tr wire:key="catalog-{{ $entry['key'] }}-empty">
                                        <td colspan="7" class="p-0">
                                            <div class="bg-surface-subtle/30 border-t border-border-default px-8 py-3">
                                                <p class="text-sm text-muted py-2 text-center">{{ __('Models are discovered dynamically after connecting. Add models manually from the management view.') }}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

    @elseif($wizardStep === 'connect')
        {{-- ========================================== --}}
        {{-- STEP 2: Connect Providers                  --}}
        {{-- ========================================== --}}
        <div class="space-y-section-gap">
            @php
                $hasIncompleteDeviceFlow = false;
                foreach ($connectForms as $i => $f) {
                    if (($f['auth_type'] ?? 'api_key') === 'device_flow') {
                        $flowStatus = $deviceFlows[$i]['status'] ?? 'idle';
                        if ($flowStatus !== 'success') {
                            $hasIncompleteDeviceFlow = true;
                        }
                    }
                }
            @endphp
            <x-ui.page-header :title="__('Connect Providers')" :subtitle="__('Enter your API key for each selected provider.')">
                <x-slot name="actions">
                    <x-ui.button variant="ghost" wire:click="backToCatalog">
                        <x-icon name="heroicon-o-arrow-left" class="w-4 h-4" />
                        {{ __('Back') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" wire:click="connectAll" :disabled="$hasIncompleteDeviceFlow">
                        <x-icon name="heroicon-o-bolt" class="w-4 h-4" />
                        {{ __('Connect All & Import Models') }}
                    </x-ui.button>
                </x-slot>
            </x-ui.page-header>

            <div class="space-y-4">
                @foreach($connectForms as $index => $form)
                    @php $authType = $form['auth_type'] ?? 'api_key'; @endphp
                    <x-ui.card wire:key="connect-{{ $form['key'] }}">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-base font-medium tracking-tight text-ink">{{ $form['display_name'] }}</h3>
                                @if($authType === 'local')
                                    <p class="text-xs text-muted mt-0.5">{{ __('Local server — API key is optional') }}</p>
                                @elseif($authType === 'oauth')
                                    <p class="text-xs text-muted mt-0.5">{{ __('OAuth provider — paste API key if available, or configure after connecting') }}</p>
                                @elseif($authType === 'subscription')
                                    <p class="text-xs text-muted mt-0.5">{{ __('Subscription service — paste access token or API key') }}</p>
                                @elseif($authType === 'custom')
                                    <p class="text-xs text-muted mt-0.5">{{ __('Requires additional configuration after connecting') }}</p>
                                @elseif($authType === 'device_flow')
                                    <p class="text-xs text-muted mt-0.5">{{ __('Requires GitHub device login — an active GitHub Copilot subscription is needed') }}</p>
                                @endif
                            </div>
                            @if($authType !== 'device_flow' && $form['api_key_url'])
                                <a
                                    href="{{ $form['api_key_url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-sm text-accent hover:underline inline-flex items-center gap-1"
                                >
                                    {{ __('Get API Key') }}
                                    <x-icon name="heroicon-o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                </a>
                            @endif
                        </div>

                        @if($authType === 'device_flow')
                            {{-- ── Device Flow UI (GitHub Copilot) ── --}}
                            @php $flow = $deviceFlows[$index] ?? ['status' => 'idle', 'user_code' => null, 'verification_uri' => null, 'error' => null]; @endphp

                            @if($flow['status'] === 'idle')
                                <div class="space-y-3">
                                    <x-ui.input
                                        wire:model="connectForms.{{ $index }}.base_url"
                                        label="{{ __('Base URL') }}"
                                        required
                                        :error="$errors->first('connectForms.'.$index.'.base_url')"
                                    />
                                    <div>
                                        <x-ui.button variant="primary" wire:click="startDeviceFlow({{ $index }})">
                                            <x-icon name="github" class="w-4 h-4" />
                                            {{ __('Start GitHub Login') }}
                                        </x-ui.button>
                                        <p class="text-xs text-muted mt-1.5">{{ __('Opens the GitHub device authorization flow. You\'ll receive a code to enter on GitHub.') }}</p>
                                    </div>
                                </div>
                            @elseif($flow['status'] === 'pending')
                                <div wire:poll.5s="pollDeviceFlow({{ $index }})">
                                    <div class="bg-surface-subtle rounded-lg p-4 space-y-3">
                                        <div class="flex items-center gap-2">
                                            <div class="animate-spin h-4 w-4 border-2 border-accent border-t-transparent rounded-full"></div>
                                            <span class="text-sm font-medium text-ink">{{ __('Waiting for GitHub authorization...') }}</span>
                                        </div>
                                        <div class="space-y-2">
                                            <div>
                                                <span class="text-[11px] uppercase tracking-wider font-semibold text-muted block mb-1">{{ __('Your Code') }}</span>
                                                <p class="text-2xl font-mono font-bold text-ink tracking-[0.3em]">{{ $flow['user_code'] }}</p>
                                            </div>
                                            <p class="text-xs text-muted">{{ __('Visit the link below and enter this code to authorize BLB.') }}</p>
                                            <a
                                                href="{{ $flow['verification_uri'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="text-sm text-accent hover:underline inline-flex items-center gap-1"
                                            >
                                                {{ $flow['verification_uri'] }}
                                                <x-icon name="heroicon-o-arrow-top-right-on-square" class="w-3.5 h-3.5" />
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @elseif($flow['status'] === 'success')
                                <div class="space-y-2">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="heroicon-o-check-circle" class="w-5 h-5 text-status-success" />
                                        <span class="text-sm font-medium text-ink">{{ __('GitHub Copilot authorized successfully') }}</span>
                                    </div>
                                    <p class="text-xs text-muted">{{ __('Click "Connect All & Import Models" above to finish setup.') }}</p>
                                </div>
                            @else
                                {{-- error / expired / denied --}}
                                <div class="space-y-3">
                                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
                                        <p class="text-sm text-red-700 dark:text-red-400">{{ $flow['error'] ?? __('Authorization failed') }}</p>
                                    </div>
                                    <x-ui.button variant="ghost" wire:click="startDeviceFlow({{ $index }})">
                                        <x-icon name="heroicon-o-arrow-path" class="w-4 h-4" />
                                        {{ __('Try Again') }}
                                    </x-ui.button>
                                </div>
                            @endif
                        @elseif($form['key'] === 'cloudflare-ai-gateway')
                            {{-- ── Cloudflare AI Gateway (Account ID + Gateway ID + API Key) ── --}}
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <x-ui.input
                                        wire:model="connectForms.{{ $index }}.cloudflare_account_id"
                                        label="{{ __('Account ID') }}"
                                        required
                                        placeholder="{{ __('Cloudflare Account ID') }}"
                                        :error="$errors->first('connectForms.'.$index.'.cloudflare_account_id')"
                                    />
                                    <x-ui.input
                                        wire:model="connectForms.{{ $index }}.cloudflare_gateway_id"
                                        label="{{ __('Gateway ID') }}"
                                        required
                                        placeholder="{{ __('AI Gateway name') }}"
                                        :error="$errors->first('connectForms.'.$index.'.cloudflare_gateway_id')"
                                    />
                                </div>
                                <x-ui.input
                                    wire:model="connectForms.{{ $index }}.api_key"
                                    type="password"
                                    label="{{ __('API Key') }}"
                                    required
                                    placeholder="{{ __('Cloudflare API token') }}"
                                    :error="$errors->first('connectForms.'.$index.'.api_key')"
                                />
                                <p class="text-xs text-muted">{{ __('The base URL will be computed as: gateway.ai.cloudflare.com/v1/{account_id}/{gateway_id}/openai') }}</p>
                            </div>
                        @else
                            {{-- ── Standard API Key / URL form ── --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <x-ui.input
                                    wire:model="connectForms.{{ $index }}.base_url"
                                    label="{{ __('Base URL') }}"
                                    required
                                    :error="$errors->first('connectForms.'.$index.'.base_url')"
                                />

                                <x-ui.input
                                    wire:model="connectForms.{{ $index }}.api_key"
                                    type="password"
                                    :label="in_array($authType, ['local', 'oauth', 'subscription']) ? __('API Key (optional)') : __('API Key')"
                                    :required="in_array($authType, ['api_key', 'custom'])"
                                    :placeholder="match($authType) {
                                        'local' => __('Leave empty for local servers'),
                                        'oauth' => __('Paste API key if available'),
                                        'subscription' => __('Paste access token'),
                                        default => __('Paste your API key'),
                                    }"
                                    :error="$errors->first('connectForms.'.$index.'.api_key')"
                                />
                            </div>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        </div>

    @else
        {{-- ========================================== --}}
        {{-- MANAGEMENT VIEW (existing providers)       --}}
        {{-- ========================================== --}}
        <div class="space-y-section-gap">
            <x-ui.page-header :title="__('LLM Providers')" :subtitle="__('Manage AI providers and their models')">
                <x-slot name="help">
                    <div class="space-y-2">
                        <p>{{ __('Digital Workers require at least one LLM to function. This page is where you register the AI providers your organization uses and the models available under each.') }}</p>
                        <p>{{ __('Each provider needs an API key from the provider\'s developer dashboard (e.g. platform.openai.com, console.anthropic.com). When creating a provider from a template, expand it and click "Import Suggested" to add common models with approximate pricing.') }}</p>
                        <p>{!! __('Once providers and models are registered here, assign them to individual Digital Workers from the :link.', ['link' => '<a href="' . route('admin.ai.playground') . '" class="text-accent hover:underline">' . e(__('AI Playground')) . '</a>']) !!}</p>
                        <p>{{ __('API usage is billed directly by the provider based on token consumption — review their pricing before enabling models for production use.') }}</p>
                    </div>
                </x-slot>
                <x-slot name="actions">
                    <x-ui.button variant="ghost" wire:click="openCreateProvider">
                        <x-icon name="heroicon-o-plus" class="w-4 h-4" />
                        {{ __('Manual Add') }}
                    </x-ui.button>
                    <x-ui.button variant="primary" wire:click="openCatalog">
                        <x-icon name="heroicon-o-rectangle-stack" class="w-4 h-4" />
                        {{ __('Browse Providers') }}
                    </x-ui.button>
                </x-slot>
            </x-ui.page-header>

            <x-ui.card>
                <div class="mb-2">
                    <x-ui.search-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Search by name...') }}"
                    />
                </div>

                <div class="overflow-x-auto -mx-card-inner px-card-inner">
                    <table class="min-w-full divide-y divide-border-default text-sm">
                        <thead class="bg-surface-subtle/80">
                            <tr>
                                <th class="px-table-cell-x py-table-header-y w-8"></th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Name') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Display Name') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Base URL') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Models') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface-card divide-y divide-border-default">
                            @forelse($providers as $provider)
                                <tr
                                    wire:key="provider-{{ $provider->id }}"
                                    wire:click="toggleProvider({{ $provider->id }})"
                                    class="hover:bg-surface-subtle/50 transition-colors cursor-pointer"
                                >
                                    <td class="px-table-cell-x py-table-cell-y">
                                        <x-icon
                                            :name="$expandedProviderId === $provider->id ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-right'"
                                            class="w-4 h-4 text-muted"
                                        />
                                    </td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm font-medium text-ink">{{ $provider->name }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted">{{ $provider->display_name }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted font-mono text-xs truncate max-w-[200px]">{{ $provider->base_url }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums">{{ $provider->models_count }}</td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap">
                                        @if($provider->is_active)
                                            <x-ui.badge variant="success">{{ __('Active') }}</x-ui.badge>
                                        @else
                                            <x-ui.badge variant="default">{{ __('Inactive') }}</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button
                                                wire:click.stop="openEditProvider({{ $provider->id }})"
                                                class="text-accent hover:bg-surface-subtle p-1 rounded"
                                                title="{{ __('Edit') }}"
                                            >
                                                <x-icon name="heroicon-o-pencil" class="w-4 h-4" />
                                            </button>
                                            <button
                                                wire:click.stop="confirmDeleteProvider({{ $provider->id }})"
                                                class="text-accent hover:bg-surface-subtle p-1 rounded"
                                                title="{{ __('Delete') }}"
                                            >
                                                <x-icon name="heroicon-o-trash" class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                {{-- Expanded models sub-table --}}
                                @if($expandedProviderId === $provider->id)
                                    <tr wire:key="provider-{{ $provider->id }}-models">
                                        <td colspan="7" class="p-0">
                                            <div class="bg-surface-subtle/30 border-t border-border-default px-8 py-3">
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-[11px] uppercase tracking-wider font-semibold text-muted">{{ __('Models') }}</span>
                                                    <div class="flex items-center gap-1">
                                                        @if($hasTemplateModels)
                                                            <x-ui.button variant="ghost" size="sm" wire:click.stop="importTemplateModels({{ $provider->id }})">
                                                                <x-icon name="heroicon-o-arrow-down-tray" class="w-3.5 h-3.5" />
                                                                {{ __('Import Suggested') }}
                                                            </x-ui.button>
                                                        @endif
                                                        <x-ui.button variant="ghost" size="sm" wire:click.stop="openCreateModel({{ $provider->id }})">
                                                            <x-icon name="heroicon-o-plus" class="w-3.5 h-3.5" />
                                                            {{ __('Add Model') }}
                                                        </x-ui.button>
                                                    </div>
                                                </div>

                                                @if($expandedModels->count() > 0)
                                                    <table class="min-w-full divide-y divide-border-default text-sm">
                                                        <thead class="bg-surface-subtle/80">
                                                            <tr>
                                                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Model Name') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Display Name') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Tags') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Context') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Max Output') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Input $/1M') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Output $/1M') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Cache Read $/1M') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Cache Write $/1M') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-left text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Status') }}</th>
                                                                <th class="px-table-cell-x py-table-header-y text-right text-[11px] font-semibold text-muted uppercase tracking-wider">{{ __('Actions') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-surface-card divide-y divide-border-default">
                                                            @foreach($expandedModels as $model)
                                                                <tr wire:key="model-{{ $model->id }}" class="hover:bg-surface-subtle/50 transition-colors">
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm font-medium text-ink font-mono">{{ $model->model_name }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted">{{ $model->display_name }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap">
                                                                        @if(is_array($model->capability_tags) && count($model->capability_tags) > 0)
                                                                            <div class="flex gap-1 flex-wrap">
                                                                                @foreach($model->capability_tags as $tag)
                                                                                    <x-ui.badge>{{ $tag }}</x-ui.badge>
                                                                                @endforeach
                                                                            </div>
                                                                        @else
                                                                            <span class="text-muted">—</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatTokenCount($model->context_window) }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatTokenCount($model->max_tokens) }}</td>
                                                                    @php $cost = $model->cost_per_1m ?? []; @endphp
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['input'] ?? null) }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['output'] ?? null) }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['cache_read'] ?? null) }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-sm text-muted tabular-nums text-right">{{ $this->formatCost($cost['cache_write'] ?? null) }}</td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap">
                                                                        @if($model->is_active)
                                                                            <x-ui.badge variant="success">{{ __('Active') }}</x-ui.badge>
                                                                        @else
                                                                            <x-ui.badge variant="default">{{ __('Inactive') }}</x-ui.badge>
                                                                        @endif
                                                                    </td>
                                                                    <td class="px-table-cell-x py-table-cell-y whitespace-nowrap text-right">
                                                                        <div class="flex items-center justify-end gap-1">
                                                                            <button wire:click="openEditModel({{ $model->id }})" class="text-accent hover:bg-surface-subtle p-1 rounded" title="{{ __('Edit') }}">
                                                                                <x-icon name="heroicon-o-pencil" class="w-4 h-4" />
                                                                            </button>
                                                                            <button wire:click="confirmDeleteModel({{ $model->id }})" class="text-accent hover:bg-surface-subtle p-1 rounded" title="{{ __('Delete') }}">
                                                                                <x-icon name="heroicon-o-trash" class="w-4 h-4" />
                                                                            </button>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                @else
                                                    <p class="text-sm text-muted py-4 text-center">{{ __('No models registered for this provider.') }}</p>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="7" class="px-table-cell-x py-8 text-center">
                                        <div class="space-y-2">
                                            <p class="text-sm text-muted">{{ __('No providers connected yet.') }}</p>
                                            <x-ui.button variant="primary" wire:click="openCatalog">
                                                <x-icon name="heroicon-o-rectangle-stack" class="w-4 h-4" />
                                                {{ __('Browse Provider Catalog') }}
                                            </x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>
    @endif

    {{-- Provider Create/Edit Modal (manual add) --}}
    <x-ui.modal wire:model="showProviderForm" class="max-w-lg">
        <div class="p-card-inner">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium tracking-tight text-ink">
                    {{ $isEditingProvider ? __('Edit Provider') : __('Add Provider') }}
                </h3>
                <button wire:click="$set('showProviderForm', false)" class="text-muted hover:text-ink">
                    <x-icon name="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </div>

            <form wire:submit="saveProvider" class="space-y-4">
                @unless($isEditingProvider)
                    <x-ui.select wire:change="applyTemplate($event.target.value)" label="{{ __('Template') }}">
                        <option value="">{{ __('Other provider') }}</option>
                        @foreach($templateOptions as $tpl)
                            <option value="{{ $tpl['value'] }}" @selected($selectedTemplate === $tpl['value'])>{{ $tpl['label'] }}</option>
                        @endforeach
                    </x-ui.select>
                @endunless

                <x-ui.input
                    wire:model="providerName"
                    label="{{ __('Name') }}"
                    required
                    placeholder="{{ __('e.g. openai') }}"
                    :error="$errors->first('providerName')"
                />

                <x-ui.input
                    wire:model="providerDisplayName"
                    label="{{ __('Display Name') }}"
                    placeholder="{{ __('e.g. OpenAI') }}"
                    :error="$errors->first('providerDisplayName')"
                />

                <x-ui.input
                    wire:model="providerBaseUrl"
                    label="{{ __('Base URL') }}"
                    required
                    placeholder="{{ __('e.g. https://api.openai.com/v1') }}"
                    :error="$errors->first('providerBaseUrl')"
                />

                <x-ui.input
                    wire:model="providerApiKey"
                    type="password"
                    label="{{ __('API Key') }}"
                    :required="!$isEditingProvider"
                    :placeholder="$isEditingProvider ? __('Leave blank to keep current key') : ''"
                    :error="$errors->first('providerApiKey')"
                />

                <x-ui.checkbox wire:model="providerIsActive" label="{{ __('Active') }}" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" wire:click="$set('showProviderForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ $isEditingProvider ? __('Update') : __('Create') }}</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    {{-- Provider Delete Confirmation --}}
    <x-ui.modal wire:model="showDeleteProvider" class="max-w-sm">
        <div class="p-card-inner">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium tracking-tight text-ink">{{ __('Delete Provider') }}</h3>
                <button wire:click="$set('showDeleteProvider', false)" class="text-muted hover:text-ink">
                    <x-icon name="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </div>

            <p class="text-sm text-muted mb-4">
                {{ __('Are you sure you want to delete :name? All associated models will also be removed.', ['name' => $deletingProviderName]) }}
            </p>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="ghost" wire:click="$set('showDeleteProvider', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="deleteProvider">{{ __('Delete') }}</x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    {{-- Model Create/Edit Modal --}}
    <x-ui.modal wire:model="showModelForm" class="max-w-lg">
        <div class="p-card-inner">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium tracking-tight text-ink">
                    {{ $isEditingModel ? __('Edit Model') : __('Add Model') }}
                </h3>
                <button wire:click="$set('showModelForm', false)" class="text-muted hover:text-ink">
                    <x-icon name="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </div>

            <form wire:submit="saveModel" class="space-y-4">
                <x-ui.input
                    wire:model="modelModelName"
                    label="{{ __('Model Name') }}"
                    required
                    placeholder="{{ __('e.g. gpt-4o') }}"
                    :error="$errors->first('modelModelName')"
                />

                <x-ui.input
                    wire:model="modelDisplayName"
                    label="{{ __('Display Name') }}"
                    placeholder="{{ __('e.g. GPT-4o') }}"
                    :error="$errors->first('modelDisplayName')"
                />

                <div>
                    <x-ui.input
                        wire:model="modelCapabilityTags"
                        label="{{ __('Capability Tags') }}"
                        placeholder="{{ __('e.g. chat, code, vision') }}"
                        :error="$errors->first('modelCapabilityTags')"
                    />
                    <p class="text-xs text-muted mt-1">{{ __('Comma-separated tags.') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui.input
                        wire:model="modelCostInput"
                        type="number"
                        step="0.000001"
                        min="0"
                        label="{{ __('Input Cost / 1M tokens') }}"
                        :error="$errors->first('modelCostInput')"
                    />
                    <x-ui.input
                        wire:model="modelCostOutput"
                        type="number"
                        step="0.000001"
                        min="0"
                        label="{{ __('Output Cost / 1M tokens') }}"
                        :error="$errors->first('modelCostOutput')"
                    />
                    <x-ui.input
                        wire:model="modelCostCacheRead"
                        type="number"
                        step="0.000001"
                        min="0"
                        label="{{ __('Cache Read Cost / 1M tokens') }}"
                        :error="$errors->first('modelCostCacheRead')"
                    />
                    <x-ui.input
                        wire:model="modelCostCacheWrite"
                        type="number"
                        step="0.000001"
                        min="0"
                        label="{{ __('Cache Write Cost / 1M tokens') }}"
                        :error="$errors->first('modelCostCacheWrite')"
                    />
                </div>

                <x-ui.checkbox wire:model="modelIsActive" label="{{ __('Active') }}" />

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" wire:click="$set('showModelForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button type="submit" variant="primary">{{ $isEditingModel ? __('Update') : __('Create') }}</x-ui.button>
                </div>
            </form>
        </div>
    </x-ui.modal>

    {{-- Model Delete Confirmation --}}
    <x-ui.modal wire:model="showDeleteModel" class="max-w-sm">
        <div class="p-card-inner">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium tracking-tight text-ink">{{ __('Delete Model') }}</h3>
                <button wire:click="$set('showDeleteModel', false)" class="text-muted hover:text-ink">
                    <x-icon name="heroicon-o-x-mark" class="w-5 h-5" />
                </button>
            </div>

            <p class="text-sm text-muted mb-4">
                {{ __('Are you sure you want to delete :name?', ['name' => $deletingModelName]) }}
            </p>

            <div class="flex justify-end gap-2">
                <x-ui.button variant="ghost" wire:click="$set('showDeleteModel', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="deleteModel">{{ __('Delete') }}</x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
