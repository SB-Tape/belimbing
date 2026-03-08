<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;

/**
 * Image analysis tool for Digital Workers.
 *
 * Analyzes images using vision models — describe content, extract text
 * (OCR), identify objects, and answer questions about images. Supports
 * common image formats (JPEG, PNG, GIF, WebP) via storage paths or URLs.
 *
 * Note: Currently returns stub responses. Vision model integration
 * will be implemented once the inference pipeline is deployed.
 *
 * Gated by `ai.tool_image_analysis.execute` authz capability.
 */
class ImageAnalysisTool implements DigitalWorkerTool
{
    /**
     * Supported image file extensions.
     *
     * @var list<string>
     */
    private const SUPPORTED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ];

    /**
     * Maximum allowed prompt length in characters.
     */
    private const MAX_PROMPT_LENGTH = 5000;

    public function name(): string
    {
        return 'image_analysis';
    }

    public function description(): string
    {
        return 'Analyze images using vision models — describe content, extract text (OCR), '
            .'identify objects, answer questions about images. '
            .'Supports common formats (JPEG, PNG, GIF, WebP).';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Storage path or URL to the image.',
                ],
                'prompt' => [
                    'type' => 'string',
                    'description' => 'What to analyze or question about the image.',
                ],
            ],
            'required' => ['path', 'prompt'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_image_analysis.execute';
    }

    public function execute(array $arguments): string
    {
        $path = $arguments['path'] ?? '';

        if (! is_string($path) || trim($path) === '') {
            return 'Error: "path" is required and must be a non-empty string.';
        }

        $prompt = $arguments['prompt'] ?? '';

        if (! is_string($prompt) || trim($prompt) === '') {
            return 'Error: "prompt" is required and must be a non-empty string.';
        }

        $path = trim($path);
        $prompt = trim($prompt);

        if (mb_strlen($prompt) > self::MAX_PROMPT_LENGTH) {
            return 'Error: "prompt" must not exceed '.self::MAX_PROMPT_LENGTH.' characters.';
        }

        $isUrl = str_starts_with($path, 'http://') || str_starts_with($path, 'https://');

        if (! $isUrl) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
                return 'Error: Unsupported image format. Supported extensions: '
                    .implode(', ', self::SUPPORTED_EXTENSIONS).'.';
            }
        }

        return json_encode([
            'action' => 'image_analysis',
            'path' => $path,
            'prompt' => $prompt,
            'status' => 'analyzed',
            'message' => 'Image analyzed (stub). Vision model integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
