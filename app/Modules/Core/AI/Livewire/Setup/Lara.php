<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Livewire\Setup;

use App\Modules\Core\AI\Models\AiProvider;
use App\Modules\Core\AI\Models\AiProviderModel;
use App\Modules\Core\AI\Services\ConfigResolver;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Employee\Models\Employee;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Lara extends Component
{
    public ?int $selectedProviderId = null;

    public ?string $selectedModelId = null;

    public function mount(): void
    {
        if (! Company::query()->whereKey(Company::LICENSEE_ID)->exists()) {
            return;
        }

        $resolver = app(ConfigResolver::class);

        if (Employee::laraActivationState() === true) {
            $this->hydrateFromCurrentConfig($resolver);

            return;
        }

        $this->selectedProviderId = AiProvider::query()
            ->forCompany(Company::LICENSEE_ID)
            ->active()
            ->orderBy('priority')
            ->orderBy('display_name')
            ->value('id');

        $this->hydrateSelectedModel();
    }

    /**
     * Provision the Lara employee record.
     *
     * Delegates to Employee::provisionLara() — the single source of truth.
     */
    public function provisionLara(): void
    {
        if (Employee::provisionLara()) {
            Session::flash('success', __('Lara has been provisioned.'));
        }
    }

    /**
     * Keep model selection in sync when provider selection changes.
     */
    public function updatedSelectedProviderId(): void
    {
        $this->hydrateSelectedModel();
    }

    /**
     * Auto-save when model selection changes and Lara is already activated.
     */
    public function updatedSelectedModelId(): void
    {
        if (Employee::laraActivationState() !== true) {
            return;
        }

        if ($this->selectedModelId === null) {
            return;
        }

        $this->validateProviderAndModel();
        $this->writeConfig();
    }

    /**
     * Activate Lara by writing workspace config with selected provider and model.
     */
    public function activateLara(): void
    {
        $this->validateProviderAndModel();
        $this->writeConfig();

        Session::flash('success', __('Lara has been activated.'));
        $this->redirect(route('admin.setup.lara'), navigate: true);
    }

    /**
     * Provide data to the Blade template.
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        $activationState = Employee::laraActivationState();
        $licenseeExists = Company::query()->whereKey(Company::LICENSEE_ID)->exists();
        $laraActivated = $activationState === true;

        $providers = collect();
        $models = collect();
        $isUsingDefault = false;
        $activeProviderName = null;
        $activeModelId = null;

        if ($licenseeExists) {
            $providers = AiProvider::query()
                ->forCompany(Company::LICENSEE_ID)
                ->active()
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'name']);
        }

        if ($this->selectedProviderId) {
            $models = AiProviderModel::query()
                ->where('ai_provider_id', $this->selectedProviderId)
                ->active()
                ->orderByDesc('is_default')
                ->orderBy('model_id')
                ->get(['id', 'model_id', 'is_default']);
        }

        if ($laraActivated) {
            $resolver = app(ConfigResolver::class);
            $workspaceConfig = $resolver->readWorkspaceConfig(Employee::LARA_ID);
            $hasExplicitConfig = $workspaceConfig !== null
                && ($workspaceConfig['llm']['models'] ?? []) !== [];

            if ($hasExplicitConfig) {
                $entry = $workspaceConfig['llm']['models'][0];
                $activeProviderName = $entry['provider'] ?? null;
                $activeModelId = $entry['model'] ?? null;
            } else {
                $isUsingDefault = true;
                $default = $resolver->resolveDefault(Company::LICENSEE_ID);
                $activeProviderName = $default['provider_name'] ?? null;
                $activeModelId = $default['model'] ?? null;
            }
        }

        return view('livewire.admin.setup.lara', [
            'laraExists' => $activationState !== null,
            'licenseeExists' => $licenseeExists,
            'laraActivated' => $laraActivated,
            'providers' => $providers,
            'models' => $models,
            'isUsingDefault' => $isUsingDefault,
            'activeProviderName' => $activeProviderName,
            'activeModelId' => $activeModelId,
        ]);
    }

    /**
     * Populate provider and model selections from Lara's current config.
     *
     * Tries explicit workspace config first, then falls back to company default.
     */
    private function hydrateFromCurrentConfig(ConfigResolver $resolver): void
    {
        $workspaceConfig = $resolver->readWorkspaceConfig(Employee::LARA_ID);
        $modelEntry = $workspaceConfig['llm']['models'][0] ?? null;

        if ($modelEntry !== null) {
            $providerName = $modelEntry['provider'] ?? null;

            if ($providerName !== null) {
                $this->selectedProviderId = AiProvider::query()
                    ->forCompany(Company::LICENSEE_ID)
                    ->where('name', $providerName)
                    ->value('id');
            }

            $this->selectedModelId = $modelEntry['model'] ?? null;

            return;
        }

        // No workspace config — hydrate from company default.
        $default = $resolver->resolveDefault(Company::LICENSEE_ID);

        if ($default === null) {
            return;
        }

        if ($default['provider_name'] !== null) {
            $this->selectedProviderId = AiProvider::query()
                ->forCompany(Company::LICENSEE_ID)
                ->where('name', $default['provider_name'])
                ->value('id');
        }

        $this->selectedModelId = $default['model'] ?? null;
    }

    private function hydrateSelectedModel(): void
    {
        if ($this->selectedProviderId === null) {
            $this->selectedModelId = null;

            return;
        }

        $providerExists = AiProvider::query()
            ->whereKey($this->selectedProviderId)
            ->forCompany(Company::LICENSEE_ID)
            ->active()
            ->exists();

        if (! $providerExists) {
            $this->selectedProviderId = null;
            $this->selectedModelId = null;

            return;
        }

        $this->selectedModelId = AiProviderModel::query()
            ->where('ai_provider_id', $this->selectedProviderId)
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('model_id')
            ->value('model_id');
    }

    /**
     * Validate provider and model selections.
     */
    private function validateProviderAndModel(): void
    {
        $this->validate([
            'selectedProviderId' => [
                'required',
                'integer',
                Rule::exists('ai_providers', 'id')
                    ->where('company_id', Company::LICENSEE_ID)
                    ->where('is_active', true),
            ],
            'selectedModelId' => [
                'required',
                'string',
                Rule::exists('ai_provider_models', 'model_id')
                    ->where('ai_provider_id', $this->selectedProviderId)
                    ->where('is_active', true),
            ],
        ]);
    }

    /**
     * Write workspace config with the currently selected provider and model.
     */
    private function writeConfig(): void
    {
        $provider = AiProvider::query()
            ->whereKey($this->selectedProviderId)
            ->forCompany(Company::LICENSEE_ID)
            ->active()
            ->firstOrFail();

        $config = [
            'llm' => [
                'models' => [
                    [
                        'provider' => $provider->name,
                        'model' => $this->selectedModelId,
                    ],
                ],
            ],
        ];

        $resolver = app(ConfigResolver::class);
        $resolver->writeWorkspaceConfig(Employee::LARA_ID, $config);
    }
}
