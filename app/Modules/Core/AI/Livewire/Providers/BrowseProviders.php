<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>
//
// Full-page component for browsing the provider catalog and connecting new
// providers. Replaces the orchestrator role of the old Providers component
// by embedding the catalog and connect-wizard child components directly.

namespace App\Modules\Core\AI\Livewire\Providers;

use App\Base\AI\Services\ModelCatalogService;
use Livewire\Attributes\On;
use Livewire\Component;

class BrowseProviders extends Component
{
    /** @var string|null null = catalog view, 'connect' = connect wizard step */
    public ?string $wizardStep = null;

    /** @var list<array> Connect form data passed to the connect-wizard child via mount prop */
    public array $connectForms = [];

    /**
     * Catalog requested "proceed to connect" — build connect forms and switch step.
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
        $this->wizardStep = null;
    }

    /**
     * All providers connected successfully — redirect to connections page.
     */
    #[On('wizard-completed')]
    public function onWizardCompleted(): void
    {
        $this->redirectRoute('admin.ai.providers.connections', navigate: true);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.ai.providers.browse-providers');
    }
}
