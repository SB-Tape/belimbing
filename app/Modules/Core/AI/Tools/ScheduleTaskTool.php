<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use Illuminate\Support\Str;

/**
 * Scheduled task management tool for Digital Workers.
 *
 * Provides CRUD operations for Laravel-native scheduled tasks stored in the
 * database. Each task defines a cron expression, target DW, task description,
 * and enabled state. Scheduled tasks execute via Laravel's scheduler,
 * dispatching to DW runtimes.
 *
 * Note: Currently returns stub responses. Full persistence will be available
 * once the scheduled_tasks DB table and scheduler integration are implemented.
 *
 * Gated by `ai.tool_schedule.execute` authz capability.
 */
class ScheduleTaskTool implements DigitalWorkerTool
{
    /**
     * Valid actions for schedule management.
     *
     * @var list<string>
     */
    private const ACTIONS = [
        'list',
        'add',
        'update',
        'remove',
        'status',
    ];

    /**
     * Expected prefix for scheduled task IDs.
     */
    private const TASK_ID_PREFIX = 'sched_';

    /**
     * Regex pattern for validating 5-field cron expressions.
     *
     * Fields: minute hour day-of-month month day-of-week.
     * Supports digits, wildcards (*), ranges (-), steps (/), and lists (,).
     */
    private const CRON_PATTERN = '/^(\*(?:\/[0-9]+)?|[0-9\/,\-]+)\s+(\*(?:\/[0-9]+)?|[0-9\/,\-]+)\s+(\*(?:\/[0-9]+)?|[0-9\/,\-]+)\s+(\*(?:\/[0-9]+)?|[0-9\/,\-]+)\s+(\*(?:\/[0-9]+)?|[0-9\/,\-]+)$/';

    public function name(): string
    {
        return 'schedule_task';
    }

    public function description(): string
    {
        return 'Manage scheduled tasks for Digital Workers. '
            .'Supports listing, adding, updating, removing, and checking status of '
            .'scheduled tasks. Each task defines a cron expression, target worker, '
            .'description, and enabled state. Tasks execute via Laravel\'s scheduler.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => self::ACTIONS,
                    'description' => 'The operation to perform: '
                        .'"list" all tasks, "add" a new task, "update" an existing task, '
                        .'"remove" a task, or check "status" of a task.',
                ],
                'task_id' => [
                    'type' => 'string',
                    'description' => 'The scheduled task ID. Required for update, remove, and status actions. '
                        .'Format: "sched_<alphanumeric>".',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Description of what the scheduled task should do. '
                        .'Required for add, optional for update.',
                ],
                'cron_expression' => [
                    'type' => 'string',
                    'description' => 'Standard 5-field cron expression (minute hour day month weekday). '
                        .'Required for add, optional for update. Example: "0 9 * * 1" for every Monday at 9am.',
                ],
                'worker_id' => [
                    'type' => 'integer',
                    'description' => 'Employee ID of the target Digital Worker to execute the task. '
                        .'Optional; use worker_list to discover available workers.',
                ],
                'enabled' => [
                    'type' => 'boolean',
                    'description' => 'Whether the scheduled task is enabled. Defaults to true for add. '
                        .'Optional for update.',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_schedule.execute';
    }

    public function execute(array $arguments): string
    {
        $action = $arguments['action'] ?? '';

        if (! is_string($action) || ! in_array($action, self::ACTIONS, true)) {
            return 'Error: Invalid action. Must be one of: '.implode(', ', self::ACTIONS).'.';
        }

        return match ($action) {
            'list' => $this->handleList(),
            'add' => $this->handleAdd($arguments),
            'update' => $this->handleUpdate($arguments),
            'remove' => $this->handleRemove($arguments),
            'status' => $this->handleStatus($arguments),
        };
    }

    /**
     * Handle the "list" action.
     *
     * Returns all scheduled tasks. Currently returns an empty stub since
     * the scheduled_tasks table is not yet implemented.
     */
    private function handleList(): string
    {
        return json_encode([
            'tasks' => [],
            'total' => 0,
            'message' => 'Scheduled task persistence is pending. '
                .'No tasks are stored yet. The scheduled_tasks DB table '
                .'and scheduler integration are not yet implemented.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "add" action.
     *
     * Validates required fields (description, cron_expression) and returns
     * a stub response with a generated task ID.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleAdd(array $arguments): string
    {
        $description = $arguments['description'] ?? '';
        $cronExpression = $arguments['cron_expression'] ?? '';

        if (! is_string($description) || trim($description) === '') {
            return 'Error: "description" is required for the add action.';
        }

        if (! is_string($cronExpression) || trim($cronExpression) === '') {
            return 'Error: "cron_expression" is required for the add action.';
        }

        $cronExpression = trim($cronExpression);

        if (! $this->isValidCronExpression($cronExpression)) {
            return 'Error: Invalid cron_expression format. '
                .'Expected 5-field cron format: "minute hour day month weekday". '
                .'Example: "0 9 * * 1" for every Monday at 9am.';
        }

        $taskId = self::TASK_ID_PREFIX.Str::random(12);
        $enabled = $arguments['enabled'] ?? true;
        $workerId = $arguments['worker_id'] ?? null;

        return json_encode([
            'task_id' => $taskId,
            'description' => trim($description),
            'cron_expression' => $cronExpression,
            'worker_id' => is_int($workerId) ? $workerId : null,
            'enabled' => (bool) $enabled,
            'status' => 'created',
            'message' => 'Task created (stub). Persistence is pending — this task '
                .'will not survive restarts until the scheduled_tasks DB table '
                .'and scheduler integration are implemented.',
            'created_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "update" action.
     *
     * Validates task_id format and any provided update fields, then returns
     * a stub response.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleUpdate(array $arguments): string
    {
        $validationError = $this->validateTaskId($arguments);

        if ($validationError !== null) {
            return $validationError;
        }

        $taskId = trim($arguments['task_id']);
        $cronExpression = $arguments['cron_expression'] ?? null;

        if (is_string($cronExpression) && trim($cronExpression) !== '' && ! $this->isValidCronExpression(trim($cronExpression))) {
            return 'Error: Invalid cron_expression format. '
                .'Expected 5-field cron format: "minute hour day month weekday". '
                .'Example: "0 9 * * 1" for every Monday at 9am.';
        }

        return json_encode([
            'task_id' => $taskId,
            'status' => 'updated',
            'message' => 'Task updated (stub). Persistence is pending — changes '
                .'will not take effect until the scheduled_tasks DB table '
                .'and scheduler integration are implemented.',
            'updated_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "remove" action.
     *
     * Validates task_id format and returns a stub removal response.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleRemove(array $arguments): string
    {
        $validationError = $this->validateTaskId($arguments);

        if ($validationError !== null) {
            return $validationError;
        }

        $taskId = trim($arguments['task_id']);

        return json_encode([
            'task_id' => $taskId,
            'status' => 'removed',
            'message' => 'Task removed (stub). Persistence is pending — this '
                .'is a no-op until the scheduled_tasks DB table and scheduler '
                .'integration are implemented.',
            'removed_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "status" action.
     *
     * Validates task_id format and returns a stub status response.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleStatus(array $arguments): string
    {
        $validationError = $this->validateTaskId($arguments);

        if ($validationError !== null) {
            return $validationError;
        }

        $taskId = trim($arguments['task_id']);

        return json_encode([
            'task_id' => $taskId,
            'status' => 'unknown',
            'enabled' => null,
            'last_run_at' => null,
            'next_run_at' => null,
            'message' => 'Task status lookup is pending. The scheduled_tasks DB table '
                .'and scheduler integration are not yet implemented.',
            'checked_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate that the task_id argument is present and has the expected format.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     * @return string|null Error message if invalid, null if valid
     */
    private function validateTaskId(array $arguments): ?string
    {
        $taskId = $arguments['task_id'] ?? '';

        if (! is_string($taskId) || trim($taskId) === '') {
            return 'Error: "task_id" is required for this action.';
        }

        $taskId = trim($taskId);

        if (! str_starts_with($taskId, self::TASK_ID_PREFIX)
            || strlen($taskId) <= strlen(self::TASK_ID_PREFIX)
            || ! ctype_alnum(substr($taskId, strlen(self::TASK_ID_PREFIX)))
        ) {
            return 'Error: Invalid task_id format. '
                .'Expected format: "sched_<alphanumeric>" as returned by the add action.';
        }

        return null;
    }

    /**
     * Validate that a cron expression is in standard 5-field format.
     *
     * @param  string  $expression  The cron expression to validate
     */
    private function isValidCronExpression(string $expression): bool
    {
        return (bool) preg_match(self::CRON_PATTERN, $expression);
    }
}
