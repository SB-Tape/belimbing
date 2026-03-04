<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\AI\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Stateless OpenAI-compatible chat completion client.
 *
 * Takes all configuration as explicit parameters — no knowledge of providers,
 * companies, or workspaces. Returns a normalized response array.
 */
class LlmClient
{
    /**
     * Execute a chat completion against any OpenAI-compatible endpoint.
     *
     * @param  string  $baseUrl  Provider base URL (e.g., 'https://api.openai.com/v1')
     * @param  string  $apiKey  Bearer token / API key
     * @param  string  $model  Model ID (e.g., 'gpt-5.2')
     * @param  list<array{role: string, content: string}>  $messages  Chat messages
     * @param  int  $maxTokens  Maximum tokens in response
     * @param  float  $temperature  Sampling temperature
     * @param  int  $timeout  HTTP timeout in seconds
     * @return array{content?: string, usage?: array<string, int|null>, latency_ms: int, error?: string, error_type?: string}
     */
    public function chat(
        string $baseUrl,
        string $apiKey,
        string $model,
        array $messages,
        int $maxTokens = 2048,
        float $temperature = 0.7,
        int $timeout = 60,
    ): array {
        $startTime = hrtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post(rtrim($baseUrl, '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);
        } catch (ConnectionException $e) {
            $latencyMs = $this->latencyMs($startTime);

            return [
                'error' => $e->getMessage(),
                'error_type' => 'connection_error',
                'latency_ms' => $latencyMs,
            ];
        }

        $latencyMs = $this->latencyMs($startTime);

        if ($response->failed()) {
            $body = $response->json();
            $errorDetail = $body['error']['message']
                ?? $body['error']['code']
                ?? $response->body();

            $errorType = match (true) {
                $response->status() === 429 => 'rate_limit',
                $response->status() >= 500 => 'server_error',
                default => 'client_error',
            };

            return [
                'error' => "HTTP {$response->status()}: {$errorDetail}",
                'error_type' => $errorType,
                'latency_ms' => $latencyMs,
            ];
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';
        $usage = $data['usage'] ?? [];

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
            ],
            'latency_ms' => $latencyMs,
        ];
    }

    private function latencyMs(int|float $startTime): int
    {
        return (int) ((hrtime(true) - $startTime) / 1_000_000);
    }
}
