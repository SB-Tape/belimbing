<?php

use App\Modules\Core\AI\Services\LaraCapabilityMatcher;
use App\Modules\Core\AI\Services\LaraTaskDispatcher;
use App\Modules\Core\AI\Tools\DelegateTaskTool;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->dispatcher = Mockery::mock(LaraTaskDispatcher::class);
    $this->matcher = Mockery::mock(LaraCapabilityMatcher::class);
    $this->tool = new DelegateTaskTool($this->dispatcher, $this->matcher);
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('delegate_task');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires delegate capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_delegate.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['task', 'worker_id'])
            ->and($schema['required'])->toBe(['task']);
    });
});

describe('input validation', function () {
    it('rejects empty task', function () {
        $result = $this->tool->execute(['task' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects missing task', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });

    it('rejects task exceeding max length', function () {
        $result = $this->tool->execute(['task' => str_repeat('x', 5001)]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('maximum length');
    });

    it('accepts task at max length', function () {
        $this->dispatcher->shouldReceive('dispatchForCurrentUser')
            ->once()
            ->andReturn([
                'dispatch_id' => 'dw_dispatch_abc123',
                'status' => 'queued',
                'employee_id' => 1,
                'employee_name' => 'Worker',
                'task' => str_repeat('x', 5000),
                'acting_for_user_id' => 10,
                'created_at' => '2026-03-08T00:00:00+00:00',
            ]);

        $result = $this->tool->execute([
            'task' => str_repeat('x', 5000),
            'worker_id' => 1,
        ]);

        expect($result)->toContain('dispatched successfully');
    });
});

describe('dispatch with explicit worker_id', function () {
    it('dispatches to specified worker', function () {
        $this->dispatcher->shouldReceive('dispatchForCurrentUser')
            ->once()
            ->with(42, 'Analyze sales data')
            ->andReturn([
                'dispatch_id' => 'dw_dispatch_test123',
                'status' => 'queued',
                'employee_id' => 42,
                'employee_name' => 'Data Analyst',
                'task' => 'Analyze sales data',
                'acting_for_user_id' => 10,
                'created_at' => '2026-03-08T12:00:00+00:00',
            ]);

        $result = $this->tool->execute(['task' => 'Analyze sales data', 'worker_id' => 42]);

        expect($result)->toContain('dispatched successfully')
            ->and($result)->toContain('dw_dispatch_test123')
            ->and($result)->toContain('Data Analyst')
            ->and($result)->toContain('ID: 42')
            ->and($result)->toContain('Analyze sales data')
            ->and($result)->toContain('delegation_status');
    });

    it('returns error when dispatcher throws authorization exception', function () {
        $this->dispatcher->shouldReceive('dispatchForCurrentUser')
            ->once()
            ->andThrow(new AuthorizationException('Unauthorized Digital Worker dispatch target.'));

        $result = $this->tool->execute(['task' => 'Test task', 'worker_id' => 999]);

        expect($result)->toContain('Error')
            ->and($result)->toContain('Unauthorized');
    });
});

describe('dispatch with auto-matching', function () {
    it('auto-matches best worker when no worker_id given', function () {
        $this->matcher->shouldReceive('matchBestForTask')
            ->once()
            ->with('Generate monthly report')
            ->andReturn([
                'employee_id' => 7,
                'name' => 'Report Generator',
                'capability_summary' => 'Generates reports and summaries',
                'match_score' => 3,
            ]);

        $this->dispatcher->shouldReceive('dispatchForCurrentUser')
            ->once()
            ->with(7, 'Generate monthly report')
            ->andReturn([
                'dispatch_id' => 'dw_dispatch_auto456',
                'status' => 'queued',
                'employee_id' => 7,
                'employee_name' => 'Report Generator',
                'task' => 'Generate monthly report',
                'acting_for_user_id' => 10,
                'created_at' => '2026-03-08T12:00:00+00:00',
            ]);

        $result = $this->tool->execute(['task' => 'Generate monthly report']);

        expect($result)->toContain('dispatched successfully')
            ->and($result)->toContain('Report Generator');
    });

    it('returns error when no worker matches the task', function () {
        $this->matcher->shouldReceive('matchBestForTask')
            ->once()
            ->andReturn(null);

        $result = $this->tool->execute(['task' => 'Something obscure']);

        expect($result)->toContain('Error')
            ->and($result)->toContain('No suitable Digital Worker');
    });
});

describe('output format', function () {
    it('includes dispatch_id in result', function () {
        $this->dispatcher->shouldReceive('dispatchForCurrentUser')
            ->once()
            ->andReturn([
                'dispatch_id' => 'dw_dispatch_xyz789',
                'status' => 'queued',
                'employee_id' => 1,
                'employee_name' => 'Worker',
                'task' => 'Do something',
                'acting_for_user_id' => 10,
                'created_at' => '2026-03-08T12:00:00+00:00',
            ]);

        $result = $this->tool->execute(['task' => 'Do something', 'worker_id' => 1]);

        expect($result)->toContain('**Dispatch ID:**')
            ->and($result)->toContain('**Status:**')
            ->and($result)->toContain('**Assigned to:**')
            ->and($result)->toContain('**Task:**')
            ->and($result)->toContain('**Created:**');
    });
});
