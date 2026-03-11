<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use App\Base\AI\Services\LlmClient;
use App\Modules\Core\AI\DTO\Message;
use Illuminate\Support\Str;

/**
 * Agentic runtime for Digital Workers with tool-calling loop.
 *
 * Extends the standard DW runtime pattern with an iterative tool-calling loop:
 * LLM call → tool execution → feed results back → LLM call → ... until the
 * LLM produces a final text response or the maximum iteration limit is reached.
 *
 * Uses the same config resolution and fallback strategy as DigitalWorkerRuntime
 * for the initial LLM call. Subsequent loop iterations reuse the resolved
 * provider/model (no mid-loop fallback).
 */
class AgenticRuntime
{
    private const MAX_ITERATIONS = 10;

    public function __construct(
        private readonly ConfigResolver $configResolver,
        private readonly LlmClient $llmClient,
        private readonly DigitalWorkerToolRegistry $toolRegistry,
        private readonly RuntimeCredentialResolver $credentialResolver,
        private readonly RuntimeMessageBuilder $messageBuilder,
        private readonly RuntimeResponseFactory $responseFactory,
    ) {}

    /**
     * Run an agentic conversation turn with tool calling.
     *
     * @param  list<Message>  $messages  Conversation history
     * @param  int  $employeeId  Lara's employee ID
     * @param  string|null  $systemPrompt  System prompt
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    public function run(array $messages, int $employeeId, ?string $systemPrompt = null): array
    {
        $runId = 'run_'.Str::random(12);
        $config = $this->configResolver->resolvePrimaryWithDefaultFallback($employeeId);

        if ($config === null) {
            return $this->responseFactory->error($runId, 'unknown', 'unknown', 0, __('No LLM configuration available.'));
        }

        $credentials = $this->credentialResolver->resolve($config);

        if (isset($credentials['error'])) {
            return $this->responseFactory->error(
                $runId,
                $config['model'],
                (string) ($config['provider_name'] ?? 'unknown'),
                0,
                $credentials['error'],
                $credentials['error_type'] ?? 'config_error',
            );
        }

        return $this->runToolCallingLoop($runId, $config, $credentials, $messages, $systemPrompt);
    }

    /**
     * Execute the iterative tool-calling loop after configuration has been resolved.
     *
     * @param  array{api_key: string, base_url: string, model: string, max_tokens: int, temperature: float, timeout: int, provider_name: string|null}  $config
     * @param  array{api_key: string, base_url: string}  $credentials
     * @param  list<Message>  $messages
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    private function runToolCallingLoop(
        string $runId,
        array $config,
        array $credentials,
        array $messages,
        ?string $systemPrompt,
    ): array {
        $apiMessages = $this->messageBuilder->build($messages, $systemPrompt);
        $tools = $this->toolRegistry->toolDefinitionsForCurrentUser();
        $toolActions = [];
        $clientActions = [];

        for ($iteration = 0; $iteration < self::MAX_ITERATIONS; $iteration++) {
            $result = $this->chatWithTools($credentials, $config, $apiMessages, $tools);

            $errorResult = $this->buildLlmErrorResult($runId, $config, $result);
            if ($errorResult !== null) {
                return $errorResult;
            }

            if ($this->hasNoToolCalls($result)) {
                return $this->successResult(
                    $runId,
                    $config,
                    $result,
                    $toolActions,
                    $clientActions,
                );
            }

            $this->appendAssistantToolCallMessage($apiMessages, $result);
            $this->executeToolCalls($result['tool_calls'], $apiMessages, $toolActions, $clientActions);
        }

        return $this->maxIterationsResult($runId, $config);
    }

    /**
     * Call the LLM with the current conversation and available tools.
     *
     * @param  array{api_key: string, base_url: string}  $credentials
     * @param  array{api_key: string, base_url: string, model: string, max_tokens: int, temperature: float, timeout: int, provider_name: string|null}  $config
     * @param  list<array<string, mixed>>  $apiMessages
     * @param  list<array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function chatWithTools(array $credentials, array $config, array $apiMessages, array $tools): array
    {
        return $this->llmClient->chat(
            baseUrl: $credentials['base_url'],
            apiKey: $credentials['api_key'],
            model: $config['model'],
            messages: $apiMessages,
            maxTokens: $config['max_tokens'],
            temperature: $config['temperature'],
            timeout: $config['timeout'],
            providerName: $config['provider_name'],
            tools: $tools !== [] ? $tools : null,
            toolChoice: $tools !== [] ? 'auto' : null,
        );
    }

    /**
     * Build an error result from an LLM failure payload.
     *
     * @param  array{api_key: string, base_url: string, model: string, max_tokens: int, temperature: float, timeout: int, provider_name: string|null}  $config
     * @param  array<string, mixed>  $result
     * @return array{content: string, run_id: string, meta: array<string, mixed>}|null
     */
    private function buildLlmErrorResult(string $runId, array $config, array $result): ?array
    {
        if (! isset($result['error'])) {
            return null;
        }

        return $this->responseFactory->error(
            $runId,
            $config['model'],
            (string) ($config['provider_name'] ?? 'unknown'),
            (int) ($result['latency_ms'] ?? 0),
            (string) $result['error'],
            (string) ($result['error_type'] ?? 'unknown'),
        );
    }

    /**
     * Determine whether the LLM has finished without requesting tools.
     *
     * @param  array<string, mixed>  $result
     */
    private function hasNoToolCalls(array $result): bool
    {
        return ! isset($result['tool_calls']) || $result['tool_calls'] === [];
    }

    /**
     * Append the assistant tool-call payload into the running conversation.
     *
     * @param  list<array<string, mixed>>  $apiMessages
     * @param  array<string, mixed>  $result
     */
    private function appendAssistantToolCallMessage(array &$apiMessages, array $result): void
    {
        $apiMessages[] = [
            'role' => 'assistant',
            'content' => $result['content'] ?? null,
            'tool_calls' => $result['tool_calls'],
        ];
    }

    /**
     * Execute requested tools and append tool responses back into the conversation.
     *
     * @param  list<array<string, mixed>>  $toolCalls
     * @param  list<array<string, mixed>>  $apiMessages
     * @param  list<array<string, mixed>>  $toolActions
     * @param  list<string>  $clientActions
     */
    private function executeToolCalls(
        array $toolCalls,
        array &$apiMessages,
        array &$toolActions,
        array &$clientActions,
    ): void {
        foreach ($toolCalls as $toolCall) {
            $toolExecution = $this->executeToolCall($toolCall);

            $toolActions[] = $toolExecution['action'];
            array_push($clientActions, ...$toolExecution['client_actions']);
            $apiMessages[] = $toolExecution['message'];
        }
    }

    /**
     * Execute a single tool call and format the follow-up metadata.
     *
     * @param  array<string, mixed>  $toolCall
     * @return array{
     *     action: array{tool: string, arguments: array<string, mixed>, result_preview: string},
     *     client_actions: list<string>,
     *     message: array{role: string, tool_call_id: string, content: string}
     * }
     */
    private function executeToolCall(array $toolCall): array
    {
        $functionName = (string) ($toolCall['function']['name'] ?? '');
        $arguments = $this->decodeToolArguments($toolCall);
        $toolCallId = (string) ($toolCall['id'] ?? '');
        $toolResult = $this->toolRegistry->execute($functionName, $arguments);

        return [
            'action' => [
                'tool' => $functionName,
                'arguments' => $arguments,
                'result_preview' => Str::limit($toolResult, 200),
            ],
            'client_actions' => $this->extractClientActions($toolResult),
            'message' => [
                'role' => 'tool',
                'tool_call_id' => $toolCallId,
                'content' => $toolResult,
            ],
        ];
    }

    /**
     * Decode JSON arguments from a tool call payload.
     *
     * @param  array<string, mixed>  $toolCall
     * @return array<string, mixed>
     */
    private function decodeToolArguments(array $toolCall): array
    {
        $arguments = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);

        return is_array($arguments) ? $arguments : [];
    }

    /**
     * Extract Lara client-action blocks from tool output.
     *
     * @return list<string>
     */
    private function extractClientActions(string $toolResult): array
    {
        if (preg_match_all('/<lara-action>.*?<\/lara-action>/s', $toolResult, $matches) < 1) {
            return [];
        }

        return $matches[0];
    }

    /**
     * Build the standard max-iteration failure response.
     *
     * @param  array{api_key: string, base_url: string, model: string, max_tokens: int, temperature: float, timeout: int, provider_name: string|null}  $config
     * @return array{content: string, run_id: string, meta: array<string, mixed>}
     */
    private function maxIterationsResult(string $runId, array $config): array
    {
        return $this->responseFactory->error(
            $runId,
            $config['model'],
            (string) ($config['provider_name'] ?? 'unknown'),
            0,
            __('Maximum tool-calling iterations reached. Please try a simpler request.'),
            'max_iterations',
        );
    }

    private function successResult(string $runId, array $config, array $llmResult, array $toolActions, array $clientActions = []): array
    {
        $content = $llmResult['content'] ?? '';

        if ($clientActions !== []) {
            $content = implode("\n", $clientActions)."\n".$content;
        }

        return $this->responseFactory->success(
            $runId,
            $config,
            $llmResult,
            $toolActions !== [] ? ['tool_actions' => $toolActions] : [],
            $content,
        );
    }
}
