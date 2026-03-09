<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;

/**
 * Client-side JavaScript execution tool for Digital Workers.
 *
 * Allows a DW to execute JavaScript in the user's browser via a
 * `<lara-action>` block that the client-side executor handles.
 * Scripts are validated for safety before execution.
 *
 * Gated by `ai.tool_write_js.execute` authz capability.
 */
class WriteJsTool implements DigitalWorkerTool
{
    private const int MAX_SCRIPT_LENGTH = 10000;

    private const int MAX_DESCRIPTION_LENGTH = 500;

    /**
     * Patterns blocked from script content for security.
     *
     * Checked case-insensitively via stripos to prevent code injection,
     * cookie theft, storage access, and dynamic import attacks.
     *
     * @var array<int, string>
     */
    private const array BLOCKED_PATTERNS = [
        'eval(',
        'Function(',
        'document.cookie',
        'localStorage',
        'sessionStorage',
        'importScripts',
        'import(',
    ];

    public function name(): string
    {
        return 'write_js';
    }

    public function description(): string
    {
        return 'Execute client-side JavaScript in the user\'s browser. '
            .'Returns a lara-action block for safe client-side execution. '
            .'Use for dynamic UI updates, clipboard operations, DOM manipulation, or browser API access. '
            .'Scripts must be CSP-compliant.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'script' => [
                    'type' => 'string',
                    'description' => 'JavaScript code to execute.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Human-readable description of what the script does.',
                ],
            ],
            'required' => ['script', 'description'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_write_js.execute';
    }

    public function execute(array $arguments): string
    {
        $script = $arguments['script'] ?? '';
        $description = $arguments['description'] ?? '';

        if (! is_string($script) || trim($script) === '') {
            return 'Error: script is required and must be a non-empty string.';
        }

        if (! is_string($description) || trim($description) === '') {
            return 'Error: description is required and must be a non-empty string.';
        }

        if (strlen($script) > self::MAX_SCRIPT_LENGTH) {
            return 'Error: script exceeds maximum length of '.self::MAX_SCRIPT_LENGTH.' characters.';
        }

        if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            return 'Error: description exceeds maximum length of '.self::MAX_DESCRIPTION_LENGTH.' characters.';
        }

        foreach (self::BLOCKED_PATTERNS as $pattern) {
            if (stripos($script, $pattern) !== false) {
                return 'Error: script contains blocked pattern "'.strtolower($pattern).'".';
            }
        }

        return '<lara-action>'.$script.'</lara-action>Script executed: '.$description.'.';
    }
}
