<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use App\Base\AI\Services\LlmClient;
use App\Modules\Core\AI\DTO\Message;
use Illuminate\Support\Str;

/**
 * Stage 0 Digital Worker runtime adapter.
 *
 * Delegates LLM execution to the stateless Base LlmClient. Handles per-DW
 * configuration resolution, message building, and ordered fallback on
 * transient failures (connection error, HTTP 429, 5xx).
 */
class DigitalWorkerRuntime
{
    public function __construct(
        private readonly ConfigResolver $configResolver,
        private readonly LlmClient $llmClient,
    ) {}

    /**
     * Run a conversation turn and return the assistant response with metadata.
     *
     * Resolves LLM config for the given Digital Worker and tries models in
     * priority order, falling back on transient failures.
     *
     * @param  list<Message>  $messages  Conversation history
     * @param  int  $employeeId  Digital Worker employee ID
     * @param  string|null  $systemPrompt  Optional system prompt for the Digital Worker
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    public function run(array $messages, int $employeeId, ?string $systemPrompt = null): array
    {
        $runId = 'run_'.Str::random(12);
        $configs = $this->configResolver->resolve($employeeId);

        $lastResult = null;

        foreach ($configs as $config) {
            $result = $this->tryModel($messages, $systemPrompt, $config, $runId);

            if (! $this->shouldFallback($result)) {
                return $result;
            }

            $lastResult = $result;
        }

        return $lastResult ?? $this->errorResult($runId, 'unknown', 0, __('No LLM configuration available.'));
    }

    /**
     * Try a single model configuration and return the result.
     *
     * Delegates HTTP execution to the stateless Base LlmClient and wraps
     * the response in the DW result format.
     *
     * @param  list<Message>  $messages  Conversation history
     * @param  string|null  $systemPrompt  Optional system prompt
     * @param  array{api_key: string, base_url: string, model: string, max_tokens: int, temperature: float, timeout: int, provider_name: string|null}  $config
     * @param  string  $runId  Run identifier
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    private function tryModel(array $messages, ?string $systemPrompt, array $config, string $runId): array
    {
        $model = $config['model'];

        if (empty($config['api_key'])) {
            return $this->errorResult($runId, $model, 0, __('API key is not configured for provider :provider.', [
                'provider' => $config['provider_name'] ?? 'default',
            ]), 'config_error');
        }

        if (empty($config['base_url'])) {
            return $this->errorResult($runId, $model, 0, __('Base URL is not configured for provider :provider.', [
                'provider' => $config['provider_name'] ?? 'default',
            ]), 'config_error');
        }

        $apiMessages = $this->buildApiMessages($messages, $systemPrompt);

        $result = $this->llmClient->chat(
            baseUrl: $config['base_url'],
            apiKey: $config['api_key'],
            model: $model,
            messages: $apiMessages,
            maxTokens: $config['max_tokens'],
            temperature: $config['temperature'],
            timeout: $config['timeout'],
        );

        if (isset($result['error'])) {
            return $this->errorResult(
                $runId, $model, $result['latency_ms'],
                $result['error'], $result['error_type'] ?? 'unknown',
            );
        }

        return [
            'content' => $result['content'] ?? '',
            'run_id' => $runId,
            'meta' => [
                'model' => $model,
                'provider_name' => $config['provider_name'],
                'latency_ms' => $result['latency_ms'],
                'tokens' => [
                    'prompt' => $result['usage']['prompt_tokens'] ?? null,
                    'completion' => $result['usage']['completion_tokens'] ?? null,
                ],
            ],
        ];
    }

    /**
     * Determine whether the runtime should fall back to the next model.
     *
     * Falls back on transient failures (connection, rate limit, server error).
     * Does NOT fall back on client errors (400, 401, 403) or success.
     *
     * @param  array{content: string, run_id: string, meta: array<string, mixed>}  $result
     */
    private function shouldFallback(array $result): bool
    {
        $errorType = $result['meta']['error_type'] ?? null;

        return in_array($errorType, ['connection_error', 'rate_limit', 'server_error'], true);
    }

    /**
     * Build an error response with the detail surfaced in both chat and debug panel.
     *
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    private function errorResult(string $runId, string $model, int $latencyMs, string $detail, string $errorType = 'unknown'): array
    {
        return [
            'content' => __('⚠ :detail', ['detail' => $detail]),
            'run_id' => $runId,
            'meta' => [
                'model' => $model,
                'latency_ms' => $latencyMs,
                'error' => $detail,
                'error_type' => $errorType,
            ],
        ];
    }

    /**
     * Build the messages array for the OpenAI API.
     *
     * @param  list<Message>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function buildApiMessages(array $messages, ?string $systemPrompt): array
    {
        $apiMessages = [];

        if ($systemPrompt !== null) {
            $apiMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $message) {
            if ($message->role === 'user' || $message->role === 'assistant') {
                $apiMessages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            }
        }

        return $apiMessages;
    }
}
