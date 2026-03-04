<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\AI\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stateless model discovery via the OpenAI-compatible GET /models endpoint.
 *
 * Takes base URL and API key as explicit parameters — no knowledge of
 * provider records or database. Returns a raw list of discovered model IDs.
 */
class ProviderDiscoveryService
{
    /**
     * Discover available models from an OpenAI-compatible /models endpoint.
     *
     * @param  string  $baseUrl  Provider base URL (e.g., 'https://api.openai.com/v1')
     * @param  string  $apiKey  Bearer token / API key (empty string if not required)
     * @return list<array{model_id: string, display_name: string}>
     *
     * @throws RuntimeException
     */
    public function discoverModels(string $baseUrl, string $apiKey = ''): array
    {
        $request = Http::acceptJson()->timeout(15);

        if ($apiKey !== '' && $apiKey !== 'not-required') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get(rtrim($baseUrl, '/').'/models');

        if (! $response->successful()) {
            throw new RuntimeException('Model discovery failed: HTTP '.$response->status());
        }

        $data = $response->json('data', []);

        if (! is_array($data)) {
            return [];
        }

        $models = [];

        foreach ($data as $entry) {
            $id = $entry['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $models[] = [
                'model_id' => $id,
                'display_name' => $this->humanizeModelId($id),
            ];
        }

        usort($models, fn (array $a, array $b): int => strcasecmp($a['display_name'], $b['display_name']));

        return $models;
    }

    /**
     * Convert a model ID like "gpt-5-mini" into "GPT-5 Mini".
     */
    private function humanizeModelId(string $id): string
    {
        $name = str_replace(['-', '_'], ' ', $id);

        $name = (string) preg_replace_callback(
            '/\b(gpt|o\d+|claude|gemini|grok)\b/i',
            fn (array $m): string => ucfirst($m[1]),
            $name,
        );

        return ucwords($name);
    }
}
