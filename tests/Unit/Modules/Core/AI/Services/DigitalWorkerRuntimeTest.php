<?php

use App\Base\AI\Services\GithubCopilotAuthService;
use App\Base\AI\Services\LlmClient;
use App\Modules\Core\AI\DTO\Message;
use App\Modules\Core\AI\Services\ConfigResolver;
use App\Modules\Core\AI\Services\DigitalWorkerRuntime;
use Tests\TestCase;

uses(TestCase::class);

function makeRuntime(
    ConfigResolver $configResolver,
    LlmClient $llmClient,
    ?GithubCopilotAuthService $copilotAuth = null,
): DigitalWorkerRuntime {
    return new DigitalWorkerRuntime(
        $configResolver,
        $llmClient,
        $copilotAuth ?? Mockery::mock(GithubCopilotAuthService::class),
    );
}

function makeConfig(string $provider, string $model, string $apiKey = 'sk-test', string $baseUrl = 'https://api.example.com/v1'): array
{
    return [
        'api_key' => $apiKey,
        'base_url' => $baseUrl,
        'model' => $model,
        'max_tokens' => 2048,
        'temperature' => 0.7,
        'timeout' => 60,
        'provider_name' => $provider,
    ];
}

function makeMessage(string $role, string $content): Message
{
    return new Message(
        role: $role,
        content: $content,
        timestamp: new DateTimeImmutable(),
    );
}

it('returns empty fallback_attempts on first model success', function (): void {
    $configResolver = Mockery::mock(ConfigResolver::class);
    $configResolver->shouldReceive('resolve')->with(1)->andReturn([
        makeConfig('openai', 'gpt-4o'),
    ]);

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('chat')->once()->andReturn([
        'content' => 'Hello!',
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        'latency_ms' => 200,
    ]);

    $runtime = makeRuntime($configResolver, $llmClient);
    $result = $runtime->run([makeMessage('user', 'Hi')], 1);

    expect($result['content'])->toBe('Hello!')
        ->and($result['meta']['fallback_attempts'])->toBeArray()->toBeEmpty()
        ->and($result['meta']['model'])->toBe('gpt-4o');
});

it('collects fallback attempt entries on transient failures before success', function (): void {
    $configResolver = Mockery::mock(ConfigResolver::class);
    $configResolver->shouldReceive('resolve')->with(1)->andReturn([
        makeConfig('provider-a', 'model-a'),
        makeConfig('provider-b', 'model-b'),
        makeConfig('provider-c', 'model-c'),
    ]);

    $llmClient = Mockery::mock(LlmClient::class);
    // First call: server error (fallback-worthy)
    $llmClient->shouldReceive('chat')->once()->ordered()->andReturn([
        'error' => 'HTTP 500: Internal Server Error',
        'error_type' => 'server_error',
        'latency_ms' => 150,
    ]);
    // Second call: rate limit (fallback-worthy)
    $llmClient->shouldReceive('chat')->once()->ordered()->andReturn([
        'error' => 'HTTP 429: Too Many Requests',
        'error_type' => 'rate_limit',
        'latency_ms' => 50,
    ]);
    // Third call: success
    $llmClient->shouldReceive('chat')->once()->ordered()->andReturn([
        'content' => 'Finally worked!',
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
        'latency_ms' => 300,
    ]);

    $runtime = makeRuntime($configResolver, $llmClient);
    $result = $runtime->run([makeMessage('user', 'Hi')], 1);

    expect($result['content'])->toBe('Finally worked!')
        ->and($result['meta']['model'])->toBe('model-c')
        ->and($result['meta']['fallback_attempts'])->toHaveCount(2);

    $attempt1 = $result['meta']['fallback_attempts'][0];
    expect($attempt1['provider'])->toBe('provider-a')
        ->and($attempt1['model'])->toBe('model-a')
        ->and($attempt1['error'])->toContain('500')
        ->and($attempt1['error_type'])->toBe('server_error')
        ->and($attempt1['latency_ms'])->toBe(150);

    $attempt2 = $result['meta']['fallback_attempts'][1];
    expect($attempt2['provider'])->toBe('provider-b')
        ->and($attempt2['model'])->toBe('model-b')
        ->and($attempt2['error'])->toContain('429')
        ->and($attempt2['error_type'])->toBe('rate_limit')
        ->and($attempt2['latency_ms'])->toBe(50);
});

it('includes fallback attempts when all models fail', function (): void {
    $configResolver = Mockery::mock(ConfigResolver::class);
    $configResolver->shouldReceive('resolve')->with(1)->andReturn([
        makeConfig('prov-a', 'model-a'),
        makeConfig('prov-b', 'model-b'),
    ]);

    $llmClient = Mockery::mock(LlmClient::class);
    $llmClient->shouldReceive('chat')->once()->ordered()->andReturn([
        'error' => 'HTTP 500: Server Error',
        'error_type' => 'server_error',
        'latency_ms' => 100,
    ]);
    $llmClient->shouldReceive('chat')->once()->ordered()->andReturn([
        'error' => 'Connection refused',
        'error_type' => 'connection_error',
        'latency_ms' => 50,
    ]);

    $runtime = makeRuntime($configResolver, $llmClient);
    $result = $runtime->run([makeMessage('user', 'Hi')], 1);

    // Last failure is returned as the result
    expect($result['meta']['error'])->toContain('Connection refused')
        ->and($result['meta']['fallback_attempts'])->toHaveCount(2);

    // Both attempts recorded
    expect($result['meta']['fallback_attempts'][0]['provider'])->toBe('prov-a')
        ->and($result['meta']['fallback_attempts'][0]['error_type'])->toBe('server_error')
        ->and($result['meta']['fallback_attempts'][1]['provider'])->toBe('prov-b')
        ->and($result['meta']['fallback_attempts'][1]['error_type'])->toBe('connection_error');
});

it('does not fall back on client errors and still records empty attempts', function (): void {
    $configResolver = Mockery::mock(ConfigResolver::class);
    $configResolver->shouldReceive('resolve')->with(1)->andReturn([
        makeConfig('openai', 'gpt-4o'),
        makeConfig('anthropic', 'claude-3'),
    ]);

    $llmClient = Mockery::mock(LlmClient::class);
    // Client error (401) — should NOT trigger fallback
    $llmClient->shouldReceive('chat')->once()->andReturn([
        'error' => 'HTTP 401: Unauthorized',
        'error_type' => 'client_error',
        'latency_ms' => 30,
    ]);

    $runtime = makeRuntime($configResolver, $llmClient);
    $result = $runtime->run([makeMessage('user', 'Hi')], 1);

    // Should stop at first model, no fallback
    expect($result['meta']['error'])->toContain('401')
        ->and($result['meta']['fallback_attempts'])->toBeEmpty();
});

it('records config_error in result without fallback since not transient', function (): void {
    $configResolver = Mockery::mock(ConfigResolver::class);
    $configResolver->shouldReceive('resolve')->with(1)->andReturn([
        makeConfig('broken', 'model-a', '', 'https://api.example.com/v1'),
        makeConfig('working', 'model-b'),
    ]);

    $llmClient = Mockery::mock(LlmClient::class);

    $runtime = makeRuntime($configResolver, $llmClient);
    $result = $runtime->run([makeMessage('user', 'Hi')], 1);

    // config_error is NOT in the shouldFallback transient list, so no fallback
    expect($result['meta']['error'])->toContain('API key is not configured')
        ->and($result['meta']['fallback_attempts'])->toBeEmpty();
});
