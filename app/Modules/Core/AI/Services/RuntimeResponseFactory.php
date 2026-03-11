<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

/**
 * Builds consistent runtime response payloads for chat success and error states.
 */
class RuntimeResponseFactory
{
    /**
     * Build a success result with standard LLM metadata.
     *
     * @param  array{model: string, provider_name: string|null}  $config
     * @param  array{latency_ms: int, usage?: array{prompt_tokens?: int, completion_tokens?: int}, content?: string|null}  $llmResult
     * @param  array<string, mixed>  $extraMeta
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    public function success(
        string $runId,
        array $config,
        array $llmResult,
        array $extraMeta = [],
        ?string $content = null,
    ): array {
        $meta = array_merge([
            'model' => $config['model'],
            'provider_name' => $config['provider_name'],
            'llm' => [
                'provider' => (string) ($config['provider_name'] ?? 'unknown'),
                'model' => $config['model'],
            ],
            'latency_ms' => $llmResult['latency_ms'],
            'tokens' => [
                'prompt' => $llmResult['usage']['prompt_tokens'] ?? null,
                'completion' => $llmResult['usage']['completion_tokens'] ?? null,
            ],
            'fallback_attempts' => [],
        ], $extraMeta);

        return [
            'content' => $content ?? (string) ($llmResult['content'] ?? ''),
            'run_id' => $runId,
            'meta' => $meta,
        ];
    }

    /**
     * Build an error result with standard LLM metadata.
     *
     * @param  array<string, mixed>  $extraMeta
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    public function error(
        string $runId,
        string $model,
        string $providerName,
        int $latencyMs,
        string $detail,
        string $errorType = 'unknown',
        array $extraMeta = [],
    ): array {
        $meta = array_merge([
            'model' => $model,
            'provider_name' => $providerName,
            'llm' => [
                'provider' => $providerName,
                'model' => $model,
            ],
            'latency_ms' => $latencyMs,
            'error' => $detail,
            'error_type' => $errorType,
            'fallback_attempts' => [],
        ], $extraMeta);

        return [
            'content' => __('⚠ :detail', ['detail' => $detail]),
            'run_id' => $runId,
            'meta' => $meta,
        ];
    }
}
