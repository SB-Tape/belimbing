<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;

/**
 * Document analysis tool for Digital Workers.
 *
 * Analyzes PDFs and documents — extract text, summarize, and answer questions
 * about document content. For providers supporting native PDF (Anthropic,
 * Google), raw bytes are sent. For others, text is extracted via PDF parser.
 *
 * Note: Currently returns stub responses. PDF parser and LLM integration
 * will be implemented once the document processing infrastructure is deployed.
 *
 * Gated by `ai.tool_document_analysis.execute` authz capability.
 */
class DocumentAnalysisTool implements DigitalWorkerTool
{
    /**
     * Maximum length for the pages filter string.
     */
    private const MAX_PAGES_LENGTH = 100;

    /**
     * Maximum length for the prompt string.
     */
    private const MAX_PROMPT_LENGTH = 5000;

    /**
     * Regex pattern for validating page filter expressions.
     *
     * Supports single pages (1), ranges (1-5), and comma-separated
     * combinations (1,3,7 or 1-3,5,8-10).
     */
    private const PAGES_PATTERN = '/^\d+(-\d+)?(,\d+(-\d+)?)*$/';

    public function name(): string
    {
        return 'document_analysis';
    }

    public function description(): string
    {
        return 'Analyze PDFs and documents — extract text, summarize, answer questions about '
            .'document content. For providers supporting native PDF (Anthropic, Google), raw bytes '
            .'are sent. For others, text is extracted via PDF parser.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Storage path or URL to the document to analyze.',
                ],
                'prompt' => [
                    'type' => 'string',
                    'description' => 'What to analyze or extract from the document (max '.self::MAX_PROMPT_LENGTH.' characters).',
                ],
                'pages' => [
                    'type' => 'string',
                    'description' => 'Page filter expression, e.g. "1-5" or "1,3,7" or "1-3,5,8-10" '
                        .'(max '.self::MAX_PAGES_LENGTH.' characters). Optional; defaults to all pages.',
                ],
                'model' => [
                    'type' => 'string',
                    'description' => 'LLM model override for analysis. Optional; uses the default model if not specified.',
                ],
            ],
            'required' => ['path', 'prompt'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_document_analysis.execute';
    }

    public function execute(array $arguments): string
    {
        $validationError = $this->validateArguments($arguments);

        if ($validationError !== null) {
            return $validationError;
        }

        $path = trim($arguments['path']);
        $prompt = trim($arguments['prompt']);
        $pages = isset($arguments['pages']) && is_string($arguments['pages']) ? trim($arguments['pages']) : null;
        $model = isset($arguments['model']) && is_string($arguments['model']) ? trim($arguments['model']) : null;

        $data = [
            'action' => 'document_analysis',
            'path' => $path,
            'prompt' => $prompt,
        ];

        if ($pages !== null && $pages !== '') {
            $data['pages'] = $pages;
        }

        if ($model !== null && $model !== '') {
            $data['model'] = $model;
        }

        $data['status'] = 'analyzed';
        $data['message'] = 'Document analyzed (stub). PDF parser and LLM integration pending.';

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate all input arguments before execution.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     * @return string|null Error message if invalid, null if valid
     */
    private function validateArguments(array $arguments): ?string
    {
        $path = $arguments['path'] ?? '';

        if (! is_string($path) || trim($path) === '') {
            return 'Error: "path" is required and must be a non-empty string.';
        }

        $prompt = $arguments['prompt'] ?? '';

        if (! is_string($prompt) || trim($prompt) === '') {
            return 'Error: "prompt" is required and must be a non-empty string.';
        }

        if (mb_strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return 'Error: "prompt" must not exceed '.self::MAX_PROMPT_LENGTH.' characters.';
        }

        $pages = $arguments['pages'] ?? null;

        if ($pages !== null) {
            if (! is_string($pages)) {
                return 'Error: "pages" must be a string (e.g. "1-5", "1,3,7", "1-3,5,8-10").';
            }

            $pages = trim($pages);

            if ($pages !== '') {
                if (mb_strlen($pages) > self::MAX_PAGES_LENGTH) {
                    return 'Error: "pages" must not exceed '.self::MAX_PAGES_LENGTH.' characters.';
                }

                if (! preg_match(self::PAGES_PATTERN, $pages)) {
                    return 'Error: Invalid "pages" format. '
                        .'Expected patterns like "1-5", "1,3,7", or "1-3,5,8-10".';
                }
            }
        }

        return null;
    }
}
