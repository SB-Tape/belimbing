<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use App\Modules\Core\AI\Models\AiProvider;
use App\Modules\Core\AI\Models\AiProviderModel;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Discovers and syncs models from OpenAI-compatible provider APIs.
 *
 * Hits the standard GET /models endpoint that most OpenAI-compatible providers
 * support (OpenAI, Anthropic proxy, Google AI, Ollama, OpenRouter, vLLM,
 * LiteLLM, Together AI, etc.). For GitHub Copilot, exchanges the stored
 * GitHub token for a short-lived Copilot API token first.
 *
 * Discovered models are enriched with metadata (capability_tags, context_window,
 * max_tokens, cost_per_1m) from the static provider template in Config/ai.php
 * when a matching model_name is found.
 */
class ModelDiscoveryService
{
    public function __construct(
        private readonly GithubCopilotAuthService $githubCopilotAuth,
    ) {}

    /**
     * Discover available models from a provider's API.
     *
     * @param  AiProvider  $provider  Provider with base_url and api_key
     * @return list<array{model_name: string, display_name: string}>
     *
     * @throws RuntimeException
     */
    public function discoverModels(AiProvider $provider): array
    {
        $baseUrl = rtrim($provider->base_url, '/');
        $apiKey = $provider->api_key;

        // GitHub Copilot: exchange GitHub token for Copilot API token
        if ($provider->name === 'github-copilot') {
            $copilot = $this->githubCopilotAuth->exchangeForCopilotToken($apiKey);
            $baseUrl = rtrim($copilot['base_url'], '/');
            $apiKey = $copilot['token'];
        }

        $request = Http::acceptJson()->timeout(15);

        if ($apiKey !== '' && $apiKey !== 'not-required') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get($baseUrl.'/models');

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
                'model_name' => $id,
                'display_name' => $this->humanizeModelId($id),
            ];
        }

        usort($models, fn (array $a, array $b): int => strcasecmp($a['display_name'], $b['display_name']));

        return $models;
    }

    /**
     * Sync discovered models into the database for a provider.
     *
     * Upserts models: adds new ones, updates metadata for existing ones from
     * the static template. Preserves user customizations (is_active, cost_per_1m).
     *
     * If API discovery fails, falls back to importing from the static template.
     *
     * @param  AiProvider  $provider  Provider to sync models for
     * @return array{added: int, updated: int, total: int}
     */
    public function syncModels(AiProvider $provider): array
    {
        try {
            $discovered = $this->discoverModels($provider);
        } catch (RuntimeException) {
            return $this->importFromTemplate($provider);
        }

        if (count($discovered) === 0) {
            return $this->importFromTemplate($provider);
        }

        $templateModels = $this->getTemplateModels($provider->name);

        $existingModels = AiProviderModel::query()
            ->where('ai_provider_id', $provider->id)
            ->get()
            ->keyBy('model_name');

        $added = 0;
        $updated = 0;

        foreach ($discovered as $model) {
            $meta = $templateModels[$model['model_name']] ?? [];
            $existing = $existingModels->get($model['model_name']);

            if ($existing) {
                $updates = array_filter([
                    'display_name' => $meta['display_name'] ?? null,
                    'capability_tags' => $meta['capability_tags'] ?? null,
                    'context_window' => $meta['context_window'] ?? null,
                    'max_tokens' => $meta['max_tokens'] ?? null,
                ], fn ($v) => $v !== null);

                if (count($updates) > 0) {
                    $existing->update($updates);
                }

                $updated++;
            } else {
                AiProviderModel::query()->create([
                    'ai_provider_id' => $provider->id,
                    'model_name' => $model['model_name'],
                    'display_name' => $meta['display_name'] ?? $model['display_name'],
                    'capability_tags' => $meta['capability_tags'] ?? [],
                    'context_window' => $meta['context_window'] ?? null,
                    'max_tokens' => $meta['max_tokens'] ?? null,
                    'is_active' => true,
                    'cost_per_1m' => $meta['cost_per_1m'] ?? null,
                ]);

                $added++;
            }
        }

        // Auto-set default model if none exists for this provider
        $this->ensureDefaultModel($provider);

        return [
            'added' => $added,
            'updated' => $updated,
            'total' => count($discovered),
        ];
    }

    /**
     * Import models from the static template config.
     *
     * Used as fallback when API discovery fails or returns no models.
     *
     * @return array{added: int, updated: int, total: int}
     */
    public function importFromTemplate(AiProvider $provider): array
    {
        $templateModels = $this->getTemplateModels($provider->name);

        if (count($templateModels) === 0) {
            return ['added' => 0, 'updated' => 0, 'total' => 0];
        }

        $existingNames = AiProviderModel::query()
            ->where('ai_provider_id', $provider->id)
            ->pluck('model_name')
            ->all();

        $added = 0;

        foreach ($templateModels as $meta) {
            if (in_array($meta['model_name'], $existingNames, true)) {
                continue;
            }

            AiProviderModel::query()->create([
                'ai_provider_id' => $provider->id,
                'model_name' => $meta['model_name'],
                'display_name' => $meta['display_name'],
                'capability_tags' => $meta['capability_tags'] ?? [],
                'context_window' => $meta['context_window'] ?? null,
                'max_tokens' => $meta['max_tokens'] ?? null,
                'is_active' => true,
                'cost_per_1m' => $meta['cost_per_1m'] ?? null,
            ]);

            $added++;
        }

        // Auto-set default model if none exists for this provider
        $this->ensureDefaultModel($provider);

        return [
            'added' => $added,
            'updated' => 0,
            'total' => count($templateModels),
        ];
    }

    /**
     * Ensure a provider has a default model set.
     *
     * Prefers models with both 'chat' and 'code' capability tags. Falls back
     * to the first active model if no capable model is found.
     */
    public function ensureDefaultModel(AiProvider $provider): void
    {
        $hasDefault = AiProviderModel::query()
            ->where('ai_provider_id', $provider->id)
            ->where('is_default', true)
            ->exists();

        if ($hasDefault) {
            return;
        }

        // Prefer a model with chat + code capabilities
        $candidate = AiProviderModel::query()
            ->where('ai_provider_id', $provider->id)
            ->where('is_active', true)
            ->get()
            ->first(function (AiProviderModel $m): bool {
                $tags = $m->capability_tags ?? [];

                return in_array('chat', $tags, true) && in_array('code', $tags, true);
            });

        // Fall back to first active model
        if ($candidate === null) {
            $candidate = AiProviderModel::query()
                ->where('ai_provider_id', $provider->id)
                ->where('is_active', true)
                ->orderBy('display_name')
                ->first();
        }

        if ($candidate instanceof AiProviderModel) {
            $candidate->setAsDefault();
        }
    }

    /**
     * Get template models for a provider, keyed by model_name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getTemplateModels(string $providerName): array
    {
        $template = config('ai.provider_templates.'.$providerName);

        if ($template === null || empty($template['models'])) {
            return [];
        }

        $models = [];

        foreach ($template['models'] as $m) {
            $models[$m['model_name']] = $m;
        }

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
