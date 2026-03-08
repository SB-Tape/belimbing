<?php

use App\Modules\Core\AI\Tools\ScheduleTaskTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new ScheduleTaskTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('schedule_task');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires schedule capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_schedule.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['action', 'task_id', 'description', 'cron_expression', 'worker_id', 'enabled'])
            ->and($schema['required'])->toBe(['action']);
    });
});

describe('input validation', function () {
    it('rejects missing action', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });

    it('rejects invalid action', function () {
        $result = $this->tool->execute(['action' => 'bogus']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('Must be one of');
    });
});

describe('list action', function () {
    it('returns task list with total', function () {
        $result = $this->tool->execute(['action' => 'list']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data)->toHaveKeys(['tasks', 'total', 'message']);
    });

    it('returns empty tasks array', function () {
        $result = $this->tool->execute(['action' => 'list']);
        $data = json_decode($result, true);

        expect($data['tasks'])->toBe([])
            ->and($data['total'])->toBe(0);
    });
});

describe('add action', function () {
    it('rejects missing description', function () {
        $result = $this->tool->execute(['action' => 'add']);
        expect($result)->toContain('Error');
    });

    it('rejects missing cron_expression', function () {
        $result = $this->tool->execute(['action' => 'add', 'description' => 'test']);
        expect($result)->toContain('Error');
    });

    it('rejects invalid cron expression', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'test',
            'cron_expression' => 'not valid',
        ]);

        expect($result)->toContain('Error')
            ->and($result)->toContain('cron');
    });

    it('accepts valid 5-field cron expression', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => '0 9 * * 1',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('created')
            ->and($data['task_id'])->toStartWith('sched_');
    });

    it('includes worker_id when provided', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => '0 9 * * 1',
            'worker_id' => 42,
        ]);
        $data = json_decode($result, true);

        expect($data['worker_id'])->toBe(42);
    });

    it('defaults enabled to true', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => '0 9 * * 1',
        ]);
        $data = json_decode($result, true);

        expect($data['enabled'])->toBeTrue();
    });

    it('respects enabled false', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => '0 9 * * 1',
            'enabled' => false,
        ]);
        $data = json_decode($result, true);

        expect($data['enabled'])->toBeFalse();
    });
});

describe('update action', function () {
    it('rejects missing task_id', function () {
        $result = $this->tool->execute(['action' => 'update']);
        expect($result)->toContain('Error');
    });

    it('rejects invalid task_id format', function () {
        $result = $this->tool->execute(['action' => 'update', 'task_id' => 'bad_id']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('Invalid task_id');
    });

    it('accepts valid task_id', function () {
        $result = $this->tool->execute(['action' => 'update', 'task_id' => 'sched_abc123def456']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('updated');
    });

    it('rejects invalid cron expression in update', function () {
        $result = $this->tool->execute([
            'action' => 'update',
            'task_id' => 'sched_abc123def456',
            'cron_expression' => 'bad',
        ]);

        expect($result)->toContain('Error');
    });
});

describe('remove action', function () {
    it('rejects missing task_id', function () {
        $result = $this->tool->execute(['action' => 'remove']);
        expect($result)->toContain('Error');
    });

    it('accepts valid task_id', function () {
        $result = $this->tool->execute(['action' => 'remove', 'task_id' => 'sched_abc123def456']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('removed');
    });
});

describe('status action', function () {
    it('rejects missing task_id', function () {
        $result = $this->tool->execute(['action' => 'status']);
        expect($result)->toContain('Error');
    });

    it('returns status for valid task_id', function () {
        $result = $this->tool->execute(['action' => 'status', 'task_id' => 'sched_abc123def456']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('unknown')
            ->and($data)->toHaveKey('checked_at');
    });
});

describe('cron expression validation', function () {
    it('accepts standard cron patterns', function () {
        $patterns = [
            '* * * * *',
            '0 9 * * 1',
            '*/5 * * * *',
            '0 0 1,15 * *',
            '30 6 * * 1-5',
        ];

        foreach ($patterns as $pattern) {
            $result = $this->tool->execute([
                'action' => 'add',
                'description' => 'Test task',
                'cron_expression' => $pattern,
            ]);
            $data = json_decode($result, true);

            expect($data)->not->toBeNull("Failed for pattern: {$pattern}")
                ->and($data['status'])->toBe('created', "Failed for pattern: {$pattern}");
        }
    });

    it('rejects cron with wrong field count', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => '* * *',
        ]);

        expect($result)->toContain('Error');
    });

    it('rejects cron with letters', function () {
        $result = $this->tool->execute([
            'action' => 'add',
            'description' => 'Test task',
            'cron_expression' => 'a b c d e',
        ]);

        expect($result)->toContain('Error');
    });
});
