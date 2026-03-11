<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

use App\Base\AI\Contracts\Tool;
use App\Base\AI\Enums\ToolCategory;
use App\Base\AI\Enums\ToolRiskClass;
use App\Base\AI\Services\LlmClient;
use App\Modules\Core\AI\Services\ConfigResolver;
use Illuminate\Foundation\Testing\TestCase;
use Tests\Support\MakesRuntimeResponses;

uses(TestCase::class, MakesRuntimeResponses::class);

class TestTool implements Tool
{
    /**
     * @param  array<string, mixed>  $schema
     */
    public function __construct(
        private readonly string $toolName,
        private readonly string $toolDescription,
        private readonly array $schema,
        private readonly string $toolResult,
    ) {}

    public function name(): string
    {
        return $this->toolName;
    }

    public function description(): string
    {
        return $this->toolDescription;
    }

    public function parametersSchema(): array
    {
        return $this->schema;
    }

    public function requiredCapability(): ?string
    {
        return null;
    }

    public function category(): ToolCategory
    {
        return ToolCategory::SYSTEM;
    }

    public function riskClass(): ToolRiskClass
    {
        return ToolRiskClass::READ_ONLY;
    }

    public function execute(array $arguments): string
    {
        if (empty($arguments)) {
            return $this->toolResult;
        }

        return $this->toolResult.json_encode($arguments);
    }
}

function buildGenericTool(
    string $name,
    string $description,
    array $schema,
    string $result,
): Tool {
    return new TestTool($name, $description, $schema, $result);
}

function buildEchoTool(): Tool
{
    return buildGenericTool(
        'echo_tool',
        'Echoes input',
        ['type' => 'object', 'properties' => ['input' => ['type' => 'string']]],
        'executed:echo_tool:world',
    );
}

function buildNavigateActionTool(): Tool
{
    return buildGenericTool(
        'navigate_tool',
        'Returns Lara actions',
        ['type' => 'object'],
        '<lara-action>Livewire.navigate(\'/dashboard\')</lara-action>',
    );
}

describe('AgenticRuntime', function () {
    it('returns direct response when LLM produces no tool calls', function () {
        $configResolver = $this->mockResolvedConfigResolver([
            $this->makeConfig('test-provider', 'gpt-4', 'test-key'),
        ]);

        $llmClient = Mockery::mock(LlmClient::class);
        $llmClient->shouldReceive('chat')->once()->andReturn([
            'content' => 'Hello, I am Lara!',
            'latency_ms' => 150,
            'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 8],
        ]);

        $runtime = $this->makeAgenticRuntime($llmClient, $configResolver);
        $result = $runtime->run([$this->makeMessage('user', 'Hi')], 1, 'You are Lara.');

        expect($result['content'])->toBe('Hello, I am Lara!');
        expect($result['run_id'])->toStartWith('run_');
        expect($result['meta']['model'])->toBe('gpt-4');
        expect($result['meta']['provider_name'])->toBe('test-provider');
        expect($result['meta'])->not->toHaveKey('tool_actions');
    });

    it('executes tool calls and feeds results back to LLM', function () {
        $configResolver = $this->mockResolvedConfigResolver([
            $this->makeConfig('test-provider', 'gpt-4', 'test-key'),
        ]);

        $llmClient = Mockery::mock(LlmClient::class);

        // First call: LLM wants to call a tool
        $llmClient->shouldReceive('chat')->once()->andReturn(
            $this->makeToolCallResponse('call_001', 'echo_tool', '{"input": "world"}')
        );

        // Second call: LLM produces final response after receiving tool result
        $llmClient->shouldReceive('chat')->once()->andReturn(
            $this->makeFinalResponse('The echo result was: executed:echo_tool:world')
        );

        $runtime = $this->makeAgenticRuntime($llmClient, $configResolver, $this->makeToolRegistry(buildEchoTool()));
        $result = $runtime->run([$this->makeMessage('user', 'Echo world')], 1, 'You are Lara.');

        expect($result['content'])->toContain('executed:echo_tool:world');
        expect($result['meta']['tool_actions'])->toHaveCount(1);
        expect($result['meta']['tool_actions'][0]['tool'])->toBe('echo_tool');
        expect($result['meta']['tool_actions'][0]['arguments'])->toBe(['input' => 'world']);
    });

    it('prepends client actions collected from tool results to final content', function () {
        $configResolver = $this->mockResolvedConfigResolver([
            $this->makeConfig('test-provider', 'gpt-4', 'test-key'),
        ]);

        $llmClient = Mockery::mock(LlmClient::class);
        $llmClient->shouldReceive('chat')->once()->andReturn(
            $this->makeToolCallResponse('call_002', 'navigate_tool', '{}')
        );
        $llmClient->shouldReceive('chat')->once()->andReturn(
            $this->makeFinalResponse('Navigated successfully.')
        );

        $runtime = $this->makeAgenticRuntime($llmClient, $configResolver, $this->makeToolRegistry(buildNavigateActionTool()));
        $result = $runtime->run([$this->makeMessage('user', 'Go to dashboard')], 1, 'You are Lara.');

        expect($result['content'])->toStartWith('<lara-action>Livewire.navigate(\'/dashboard\')</lara-action>')
            ->and($result['content'])->toContain('Navigated successfully.');
    });

    it('returns error when no LLM configuration is available', function () {
        // Stub resolveConfig to return null by making resolve return empty
        // and having no employee in DB. We mock at the config resolver level.
        $configResolver = Mockery::mock(ConfigResolver::class);
        $configResolver->shouldReceive('resolve')->with(1)->andReturn([]);
        $configResolver->shouldReceive('resolvePrimaryWithDefaultFallback')->with(1)->andReturn(null);

        $llmClient = Mockery::mock(LlmClient::class);
        $runtime = $this->makeAgenticRuntime($llmClient, $configResolver);

        // Employee ID 1 doesn't exist in test DB, so company lookup fails gracefully
        $result = $runtime->run([$this->makeMessage('user', 'Hello')], 1, 'Prompt');

        expect($result['content'])->toContain('⚠');
        expect($result['meta'])->toHaveKey('error');
    });

    it('returns error when LLM call fails', function () {
        $configResolver = $this->mockResolvedConfigResolver([
            $this->makeConfig('test-provider', 'gpt-4', 'test-key'),
        ]);

        $llmClient = Mockery::mock(LlmClient::class);
        $llmClient->shouldReceive('chat')->once()->andReturn([
            'error' => 'Rate limit exceeded',
            'error_type' => 'rate_limit',
            'latency_ms' => 50,
        ]);

        $runtime = $this->makeAgenticRuntime($llmClient, $configResolver);
        $result = $runtime->run([$this->makeMessage('user', 'Hello')], 1, 'Prompt');

        expect($result['content'])->toContain('⚠');
        expect($result['meta']['error_type'])->toBe('rate_limit');
    });
});
