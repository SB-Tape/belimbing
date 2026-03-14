<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Livewire\Providers;

class CloudflareGatewaySetup extends ProviderSetup
{
    public string $cloudflareAccountId = '';

    public string $cloudflareGatewayId = '';

    /**
     * Auto-connect when the Cloudflare Gateway ID is updated (blur).
     *
     * Overrides parent behavior because Cloudflare uses Account ID + Gateway ID
     * + API key, not the generic base URL + API key shape.
     */
    public function updatedCloudflareGatewayId(): void
    {
        if ($this->connectedProviderId !== null) {
            return;
        }

        $this->tryAutoConnect();
    }

    /**
     * Attempt auto-connect for Cloudflare's custom credential shape.
     *
     * Overrides parent to require Account ID + Gateway ID + API key before
     * creating the provider record and running model discovery.
     */
    protected function tryAutoConnect(): void
    {
        if ($this->cloudflareAccountId === '' || $this->cloudflareGatewayId === '' || $this->apiKey === '') {
            return;
        }

        $this->connect();
    }

    /**
     * Build validation rules for Cloudflare gateway setup fields.
     *
     * Overrides parent because base URL is derived from account + gateway IDs.
     *
     * @return array<string, list<string>>
     */
    protected function buildValidationRules(): array
    {
        return [
            'cloudflareAccountId' => ['required', 'string', 'max:255'],
            'cloudflareGatewayId' => ['required', 'string', 'max:255'],
            'apiKey' => ['required', 'string', 'max:2048'],
        ];
    }

    /**
     * Build validation messages for Cloudflare gateway setup fields.
     *
     * @return array<string, string>
     */
    protected function buildValidationMessages(): array
    {
        return array_merge(parent::buildValidationMessages(), [
            'cloudflareAccountId.required' => __('Account ID is required.'),
            'cloudflareGatewayId.required' => __('Gateway ID is required.'),
        ]);
    }

    /**
     * Build Cloudflare AI Gateway base URL from Account ID and Gateway ID.
     *
     * Overrides parent to convert Cloudflare-specific fields into the
     * OpenAI-compatible endpoint consumed by provider discovery.
     */
    protected function resolveBaseUrl(): string
    {
        $accountId = trim($this->cloudflareAccountId);
        $gatewayId = trim($this->cloudflareGatewayId);

        return "https://gateway.ai.cloudflare.com/v1/{$accountId}/{$gatewayId}/openai";
    }
}
