<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use App\Base\AI\Services\GithubCopilotAuthService;

/**
 * Resolves API credentials for runtime calls, including provider-specific exchanges.
 */
class RuntimeCredentialResolver
{
    public function __construct(
        private readonly GithubCopilotAuthService $githubCopilotAuth,
    ) {}

    /**
     * Resolve API credentials for a runtime request.
     *
     * @param  array{api_key: string, base_url: string, provider_name: string|null}  $config
     * @return array{api_key: string, base_url: string}|array{error: string, error_type: string}
     */
    public function resolve(array $config): array
    {
        $configurationError = $this->configurationError($config);

        if ($configurationError !== null) {
            return $configurationError;
        }

        $apiKey = $config['api_key'];
        $baseUrl = $config['base_url'];

        if ($config['provider_name'] === 'github-copilot') {
            try {
                $copilot = $this->githubCopilotAuth->exchangeForCopilotToken($apiKey);
                $apiKey = $copilot['token'];
                $baseUrl = $copilot['base_url'];
            } catch (\RuntimeException $e) {
                return [
                    'error' => __('Copilot token exchange failed: :error', ['error' => $e->getMessage()]),
                    'error_type' => 'auth_error',
                ];
            }
        }

        return ['api_key' => $apiKey, 'base_url' => $baseUrl];
    }

    /**
     * @param  array{api_key: string, base_url: string, provider_name: string|null}  $config
     * @return array{error: string, error_type: string}|null
     */
    private function configurationError(array $config): ?array
    {
        if (empty($config['api_key'])) {
            return [
                'error' => __('API key is not configured for provider :provider.', [
                    'provider' => $config['provider_name'] ?? 'default',
                ]),
                'error_type' => 'config_error',
            ];
        }

        if (empty($config['base_url'])) {
            return [
                'error' => __('Base URL is not configured for provider :provider.', [
                    'provider' => $config['provider_name'] ?? 'default',
                ]),
                'error_type' => 'config_error',
            ];
        }

        return null;
    }
}
