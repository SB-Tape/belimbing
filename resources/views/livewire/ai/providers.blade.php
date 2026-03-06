<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>
//
// Provider catalog and onboarding flow inspired by OpenClaw
// (github.com/nicepkg/openclaw). Adapted for BLB's GUI context.
//
// Orchestrator: routes between catalog → connect → manager steps.
// Each step is a standalone Volt child component in providers/.

use App\Base\AI\Services\ModelCatalogService;
use App\Modules\Core\AI\Models\AiProvider;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /** @var string|null null = manage view, 'catalog' = step 1, 'connect' = step 2 */
    public ?string $wizardStep = null;

    /** @var list<array> Connect form data passed to the connect-wizard child via mount prop */
    public array $connectForms = [];

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

    /**
     * Catalog requested "proceed to connect" — build connect forms and switch step.
     *
     * Forms are stored as a property and passed to the connect-wizard child
     * component via its mount prop, avoiding event timing issues.
     *
     * @param  array  $templates  Selected template keys from catalog
     */
    #[On('wizard-proceed-to-connect')]
    public function onProceedToConnect(array $templates): void
    {
        if ($templates === []) {
            return;
        }

        $allProviders = app(ModelCatalogService::class)->getProviders();
        $forms = [];

        foreach ($templates as $key) {
            $tpl = $allProviders[$key] ?? null;

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

            $forms[] = $formEntry;
        }

        $this->connectForms = $forms;
        $this->wizardStep = 'connect';
    }

    /**
     * Connect wizard requested return to catalog.
     */
    #[On('wizard-back-to-catalog')]
    public function onBackToCatalog(): void
    {
        $this->wizardStep = 'catalog';
    }

    /**
     * Wizard cancelled — return to management view.
     */
    #[On('wizard-cancel')]
    public function onCancelWizard(): void
    {
        $this->wizardStep = null;
    }

    /**
     * All providers connected successfully — exit wizard.
     */
    #[On('wizard-completed')]
    public function onWizardCompleted(): void
    {
        $this->wizardStep = null;
    }

    /**
     * Manager requested to open the catalog.
     */
    #[On('wizard-open-catalog')]
    public function onOpenCatalog(): void
    {
        $this->wizardStep = 'catalog';
    }

    private function getCompanyId(): ?int
    {
        $user = auth()->user();

        return $user?->employee?->company_id ? (int) $user->employee->company_id : null;
    }
}; ?>

<div>
    <x-slot name="title">{{ __('LLM Providers') }}</x-slot>

    @if($wizardStep === 'catalog')
        <livewire:ai.providers.catalog />
    @elseif($wizardStep === 'connect')
        <livewire:ai.providers.connect-wizard :initial-forms="$connectForms" />
    @else
        <livewire:ai.providers.manager />
    @endif
</div>
