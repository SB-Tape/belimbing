<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use App\Modules\Core\AI\Services\LaraKnowledgeNavigator;

/**
 * BLB framework documentation guide tool for Digital Workers.
 *
 * Allows a DW to search curated BLB framework documentation by topic,
 * returning matched references with summaries and the content of the
 * top-matching file for substantive grounding.
 *
 * Gated by `ai.tool_guide.execute` authz capability.
 */
class GuideTool implements DigitalWorkerTool
{
    private const MAX_LINES = 200;

    private const MAX_SECTIONS_LIMIT = 10;

    public function __construct(
        private readonly LaraKnowledgeNavigator $navigator,
    ) {}

    public function name(): string
    {
        return 'guide';
    }

    public function description(): string
    {
        return 'Search BLB framework documentation for a topic and return relevant references with summaries. '
            .'Use this to look up architecture decisions, module docs, conventions, or design patterns '
            .'before answering questions about the BLB framework.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => [
                    'type' => 'string',
                    'description' => 'Topic to search BLB framework documentation for.',
                ],
                'max_sections' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of relevant sections to return (default 5, max 10).',
                ],
            ],
            'required' => ['topic'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_guide.execute';
    }

    public function execute(array $arguments): string
    {
        $topic = $arguments['topic'] ?? '';

        if (! is_string($topic) || trim($topic) === '') {
            return 'Error: No topic provided.';
        }

        $topic = trim($topic);
        $maxSections = $this->resolveMaxSections($arguments);

        $results = $this->navigator->search($topic, $maxSections);

        if ($results === []) {
            return $this->formatNoResults($topic);
        }

        return $this->formatResults($results, $topic);
    }

    private function resolveMaxSections(array $arguments): int
    {
        $maxSections = $arguments['max_sections'] ?? 5;

        if (! is_int($maxSections) || $maxSections < 1) {
            return 5;
        }

        return min($maxSections, self::MAX_SECTIONS_LIMIT);
    }

    /**
     * Format search results as a numbered reference list with top match content.
     *
     * @param  list<array{title: string, path: string, summary: string}>  $results
     */
    private function formatResults(array $results, string $topic): string
    {
        $count = count($results);
        $output = 'Found '.$count.' relevant reference'.($count !== 1 ? 's' : '').' for "'.$topic.'":'."\n";

        foreach ($results as $index => $ref) {
            $number = $index + 1;
            $output .= "\n".$number.'. **'.$ref['title'].'**'
                ."\n".'   Path: '.$ref['path']
                ."\n".'   '.$ref['summary']."\n";
        }

        $topMatchContent = $this->readFileContent($results[0]['path']);

        if ($topMatchContent !== null) {
            $output .= "\n".'---'
                ."\n".'Top match content ('.$results[0]['path'].'):'."\n\n"
                .$topMatchContent;
        }

        return $output;
    }

    private function formatNoResults(string $topic): string
    {
        $catalog = $this->navigator->catalog();

        $output = 'No documentation found for "'.$topic.'".'."\n\n".'Available topics:';

        foreach ($catalog as $entry) {
            $output .= "\n".'- '.$entry['title'].' ('.$entry['path'].')';
        }

        return $output;
    }

    /**
     * Read up to MAX_LINES lines from a documentation file.
     *
     * @param  string  $path  Relative path to the documentation file
     */
    private function readFileContent(string $path): ?string
    {
        $absolutePath = base_path($path);

        if (! file_exists($absolutePath)) {
            return null;
        }

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return null;
        }

        $lines = array_slice($lines, 0, self::MAX_LINES);

        return implode("\n", $lines);
    }
}
