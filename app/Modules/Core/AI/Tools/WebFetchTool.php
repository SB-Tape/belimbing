<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use Illuminate\Support\Facades\Http;

/**
 * Web page fetching and content extraction tool for Digital Workers.
 *
 * Allows a DW to fetch external web pages and extract readable content
 * for research, data gathering, and contextual understanding.
 *
 * Safety: SSRF protection blocks requests to private/internal networks
 * by default. Response size is capped to prevent memory exhaustion.
 * Redirect count is limited to prevent redirect loops.
 *
 * Gated by `ai.tool_web_fetch.execute` authz capability.
 */
class WebFetchTool implements DigitalWorkerTool
{
    private const DEFAULT_TIMEOUT_SECONDS = 30;

    private const DEFAULT_MAX_RESPONSE_BYTES = 5242880; // 5MB

    private const DEFAULT_MAX_CHARS = 50000;

    private const MAX_REDIRECTS = 5;

    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): string
    {
        return 'Fetch a web page and extract its readable content. '
            .'Use this to read documentation, articles, product pages, or any public URL. '
            .'Returns extracted text content from the page.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'The URL to fetch (must be http or https).',
                ],
                'max_chars' => [
                    'type' => 'integer',
                    'description' => 'Maximum characters of content to return (default 50000). '
                        .'Reduce for concise summaries, increase for full-page content.',
                ],
                'extract_mode' => [
                    'type' => 'string',
                    'enum' => ['text', 'markdown'],
                    'description' => 'Content extraction mode: "text" for plain text (default), '
                        .'"markdown" to preserve headings, links, and formatting.',
                ],
            ],
            'required' => ['url'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_web_fetch.execute';
    }

    public function execute(array $arguments): string
    {
        $url = $arguments['url'] ?? '';

        if (! is_string($url) || trim($url) === '') {
            return 'Error: No URL provided.';
        }

        $url = trim($url);

        $maxChars = self::DEFAULT_MAX_CHARS;
        if (isset($arguments['max_chars']) && is_int($arguments['max_chars'])) {
            $maxChars = max(1, $arguments['max_chars']);
        }

        $extractMode = 'text';
        if (isset($arguments['extract_mode']) && in_array($arguments['extract_mode'], ['text', 'markdown'], true)) {
            $extractMode = $arguments['extract_mode'];
        }

        // SSRF protection
        $safeCheck = $this->isUrlSafe($url);
        if ($safeCheck !== true) {
            return 'Error: '.$safeCheck;
        }

        $timeout = (int) config('ai.tools.web_fetch.timeout_seconds', self::DEFAULT_TIMEOUT_SECONDS);
        $maxBytes = (int) config('ai.tools.web_fetch.max_response_bytes', self::DEFAULT_MAX_RESPONSE_BYTES);

        try {
            $response = Http::timeout($timeout)
                ->maxRedirects(self::MAX_REDIRECTS)
                ->withHeaders(['User-Agent' => 'BLB/1.0 (Digital Worker)'])
                ->get($url);
        } catch (\Throwable $e) {
            return 'Error: Failed to fetch URL: '.$e->getMessage();
        }

        if (! $response->successful()) {
            return 'Failed to fetch URL: HTTP '.$response->status();
        }

        $body = $response->body();

        // Enforce response size cap
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        $contentType = $response->header('Content-Type') ?? '';
        $isHtml = str_contains(strtolower($contentType), 'text/html');

        if ($isHtml) {
            $content = $this->extractHtml($body, $extractMode);
        } else {
            $content = $body;
        }

        // Truncate to max_chars
        $truncated = false;
        if (mb_strlen($content) > $maxChars) {
            $content = mb_substr($content, 0, $maxChars);
            $truncated = true;
        }

        if ($truncated) {
            $content .= "\n\n[Content truncated at {$maxChars} characters]";
        }

        $charCount = mb_strlen($content);

        return "# Content from {$url}\n\n{$content}\n\n---\nFetched {$charCount} characters from {$url}";
    }

    /**
     * Check whether the given URL is safe to fetch (SSRF protection).
     *
     * Blocks requests to private/internal networks, loopback addresses,
     * link-local ranges, and reserved IP ranges. Can be bypassed via
     * config for development environments.
     *
     * @param  string  $url  The URL to validate
     * @return string|true True if safe, or an error string describing why it was blocked
     */
    private function isUrlSafe(string $url): string|true
    {
        $parsed = parse_url($url);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            return 'Invalid URL: unable to parse.';
        }

        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return 'Only http and https URLs are allowed.';
        }

        $host = strtolower($parsed['host']);

        if ($host === '') {
            return 'Invalid URL: empty hostname.';
        }

        // Check for explicitly blocked hostnames
        if ($host === 'localhost' || $host === '0.0.0.0' || $host === '::1') {
            return "Blocked: requests to {$host} are not allowed.";
        }

        if (str_ends_with($host, '.local')) {
            return 'Blocked: requests to .local domains are not allowed.';
        }

        // Allow private networks if configured (development only)
        if (config('ai.tools.web_fetch.ssrf_allow_private', false)) {
            return true;
        }

        // Resolve hostname to IP
        $ip = gethostbyname($host);

        // gethostbyname returns the hostname unchanged if resolution fails
        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return "Blocked: unable to resolve hostname {$host}.";
        }

        // Check resolved IP against private and reserved ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return "Blocked: {$host} resolves to a private or reserved IP address ({$ip}).";
        }

        return true;
    }

    /**
     * Extract readable content from an HTML string.
     *
     * @param  string  $html  Raw HTML content
     * @param  string  $mode  Extraction mode: 'text' or 'markdown'
     */
    private function extractHtml(string $html, string $mode): string
    {
        if ($mode === 'markdown') {
            return $this->extractAsMarkdown($html);
        }

        return $this->extractAsText($html);
    }

    /**
     * Extract plain text from HTML by stripping tags and cleaning whitespace.
     */
    private function extractAsText(string $html): string
    {
        // Remove script, style, nav, header, footer, aside blocks
        $html = $this->stripNoiseTags($html);

        // Strip all remaining HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean whitespace: collapse runs of spaces/tabs on each line
        $text = (string) preg_replace('/[^\S\n]+/', ' ', $text);

        // Collapse 3+ consecutive newlines to double newline
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Extract content as markdown, preserving structural elements.
     *
     * Converts headings, links, lists, emphasis, and paragraphs to
     * markdown syntax before stripping remaining HTML tags.
     */
    private function extractAsMarkdown(string $html): string
    {
        // Remove script, style, nav, header, footer, aside blocks
        $html = $this->stripNoiseTags($html);

        // Convert headings to markdown
        for ($i = 6; $i >= 1; $i--) {
            $prefix = str_repeat('#', $i);
            $html = (string) preg_replace(
                '#<h'.$i.'[^>]*>(.*?)</h'.$i.'>#si',
                "\n\n{$prefix} $1\n\n",
                $html
            );
        }

        // Convert links to markdown
        $html = (string) preg_replace(
            '#<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>#si',
            '[$2]($1)',
            $html
        );

        // Convert list items
        $html = (string) preg_replace('#<li[^>]*>(.*?)</li>#si', "\n- $1", $html);

        // Convert paragraphs
        $html = (string) preg_replace('#<p[^>]*>(.*?)</p>#si', "\n\n$1\n\n", $html);

        // Convert line breaks
        $html = (string) preg_replace('#<br\s*/?\s*>#si', "\n", $html);

        // Convert bold/strong
        $html = (string) preg_replace('#<(?:strong|b)[^>]*>(.*?)</(?:strong|b)>#si', '**$1**', $html);

        // Convert italic/emphasis
        $html = (string) preg_replace('#<(?:em|i)[^>]*>(.*?)</(?:em|i)>#si', '*$1*', $html);

        // Strip all remaining HTML tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean whitespace
        $text = (string) preg_replace('/[^\S\n]+/', ' ', $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Strip noise tags (script, style, nav, header, footer, aside) and their content.
     */
    private function stripNoiseTags(string $html): string
    {
        $noiseTags = ['script', 'style', 'nav', 'header', 'footer', 'aside'];

        foreach ($noiseTags as $tag) {
            $html = (string) preg_replace('#<'.$tag.'[^>]*>.*?</'.$tag.'>#si', '', $html);
        }

        return $html;
    }
}
