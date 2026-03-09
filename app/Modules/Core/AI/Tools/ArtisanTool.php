<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use Illuminate\Support\Facades\Process;

/**
 * Artisan command execution tool for Digital Workers.
 *
 * Allows a DW to run `php artisan` commands on behalf of the user.
 * Supports foreground execution with configurable timeout, and background
 * execution via Laravel queues (stub — pending queue job implementation).
 *
 * Gated by `ai.tool_artisan.execute` authz capability.
 *
 * Safety: Only `php artisan` commands are allowed. Laravel's Process
 * class uses proc_open without shell invocation, so metacharacters
 * have no shell-level effect. Timeout enforced per execution.
 */
class ArtisanTool implements DigitalWorkerTool
{
    /**
     * Default timeout in seconds for foreground execution.
     */
    private const DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * Minimum allowed timeout in seconds.
     */
    private const MIN_TIMEOUT_SECONDS = 1;

    /**
     * Maximum allowed timeout in seconds.
     */
    private const MAX_TIMEOUT_SECONDS = 300;

    public function name(): string
    {
        return 'artisan';
    }

    public function description(): string
    {
        return 'Execute a php artisan command and return its output. '
            .'Use this to query data (e.g., tinker), run BLB commands, check system status, etc. '
            .'Only artisan commands are allowed — no arbitrary shell commands. '
            .'Supports optional timeout override and background execution via Laravel queues.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => [
                    'type' => 'string',
                    'description' => 'The artisan command to run (without "php artisan" prefix). '
                        .'Examples: "tinker --execute=\'echo App\\\\Modules\\\\Core\\\\User\\\\Models\\\\User::count();\'", '
                        .'"blb:ai:catalog:sync --dry-run", "route:list --columns=name,uri".',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'description' => 'Timeout in seconds for foreground execution (default '.self::DEFAULT_TIMEOUT_SECONDS.', '
                        .'min '.self::MIN_TIMEOUT_SECONDS.', max '.self::MAX_TIMEOUT_SECONDS.'). '
                        .'Ignored when background is true.',
                ],
                'background' => [
                    'type' => 'boolean',
                    'description' => 'Run the command in the background via Laravel queues. '
                        .'Returns a dispatch_id immediately for polling with delegation_status tool.',
                ],
            ],
            'required' => ['command'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_artisan.execute';
    }

    public function execute(array $arguments): string
    {
        $command = $arguments['command'] ?? '';

        if (! is_string($command) || trim($command) === '') {
            return 'Error: No command provided.';
        }

        $command = trim($command);

        // Strip "php artisan" prefix if the LLM included it
        $command = preg_replace('/^(php\s+)?artisan\s+/', '', $command) ?? $command;

        if ($command === '') {
            return 'Error: Empty command after parsing.';
        }

        $background = (bool) ($arguments['background'] ?? false);

        if ($background) {
            return $this->executeBackground($command);
        }

        return $this->executeForeground($command, $arguments);
    }

    /**
     * Execute the command in the foreground with a configurable timeout.
     *
     * @param  array<string, mixed>  $arguments  Original arguments for timeout extraction
     */
    private function executeForeground(string $command, array $arguments): string
    {
        $timeout = $this->resolveTimeout($arguments);
        $fullCommand = 'php artisan '.$command;

        $result = Process::timeout($timeout)
            ->path(base_path())
            ->run($fullCommand);

        $output = trim($result->output());
        $errorOutput = trim($result->errorOutput());

        if (! $result->successful()) {
            $message = 'Command failed (exit code '.$result->exitCode().').';

            if ($errorOutput !== '') {
                $message .= "\n".$errorOutput;
            }

            if ($output !== '') {
                $message .= "\n".$output;
            }

            return $message;
        }

        if ($output === '' && $errorOutput === '') {
            return 'Command completed successfully (no output).';
        }

        return $output !== '' ? $output : $errorOutput;
    }

    /**
     * Dispatch the command for background execution via Laravel queues.
     *
     * Returns a dispatch_id immediately. The caller can poll for results
     * using the delegation_status tool.
     *
     * Note: Currently returns a stub response. Queue job implementation pending.
     */
    private function executeBackground(string $command): string
    {
        $dispatchId = 'artisan_'.bin2hex(random_bytes(12));

        return json_encode([
            'status' => 'dispatched',
            'dispatch_id' => $dispatchId,
            'command' => $command,
            'message' => 'Command dispatched for background execution (stub). '
                .'Queue job implementation pending. '
                .'Use the delegation_status tool to check progress.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Resolve the timeout value from arguments, clamped to allowed range.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function resolveTimeout(array $arguments): int
    {
        $timeout = $arguments['timeout'] ?? self::DEFAULT_TIMEOUT_SECONDS;

        if (! is_int($timeout)) {
            return self::DEFAULT_TIMEOUT_SECONDS;
        }

        return max(self::MIN_TIMEOUT_SECONDS, min(self::MAX_TIMEOUT_SECONDS, $timeout));
    }
}
