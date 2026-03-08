<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use App\Modules\Core\AI\Services\Browser\BrowserPoolManager;
use App\Modules\Core\AI\Services\Browser\BrowserSsrfGuard;

/**
 * Headless browser automation tool for Digital Workers.
 *
 * Provides enterprise-grade browser automation via server-side headless
 * Chromium managed through a pool of isolated browser contexts. Supports
 * navigation, page snapshots, screenshots, interaction, tab management,
 * JS evaluation (opt-in), PDF export, cookie management, and wait conditions.
 *
 * Each action is dispatched through a single deep tool interface. Browser
 * contexts are company-scoped and session-isolated via BrowserPoolManager.
 * All navigation is SSRF-guarded.
 *
 * Note: Currently returns stub responses. Full Playwright CLI subprocess
 * integration will be implemented once the browser infrastructure is deployed.
 *
 * Gated by `ai.tool_browser.execute` authz capability.
 * The `evaluate` action additionally requires `ai.tool_browser_evaluate.execute`.
 */
class BrowserTool implements DigitalWorkerTool
{
    /**
     * Valid actions for browser automation.
     *
     * @var list<string>
     */
    private const ACTIONS = [
        'navigate',
        'snapshot',
        'screenshot',
        'act',
        'tabs',
        'open',
        'close',
        'evaluate',
        'pdf',
        'cookies',
        'wait',
    ];

    /**
     * Valid interaction kinds for the "act" action.
     *
     * @var list<string>
     */
    private const ACT_KINDS = [
        'click',
        'type',
        'select',
        'press',
        'drag',
        'hover',
        'scroll',
        'fill',
    ];

    /**
     * Valid cookie sub-actions.
     *
     * @var list<string>
     */
    private const COOKIE_ACTIONS = [
        'get',
        'set',
        'clear',
    ];

    public function __construct(
        private readonly BrowserPoolManager $poolManager,
        private readonly BrowserSsrfGuard $ssrfGuard,
    ) {}

    public function name(): string
    {
        return 'browser';
    }

    public function description(): string
    {
        return 'Automate a headless browser for web scraping, form filling, and page inspection. '
            .'Supports navigation, page snapshots (structured text), screenshots, interaction '
            .'(click, type, select, fill), tab management, PDF export, cookie management, '
            .'and waiting for page state. Each DW session gets an isolated browser context.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => self::ACTIONS,
                    'description' => 'The browser action to perform.',
                ],
                'url' => [
                    'type' => 'string',
                    'description' => 'URL to navigate to (for "navigate" and "open" actions).',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['ai', 'aria'],
                    'description' => 'Snapshot format: "ai" for LLM-optimized (default), "aria" for accessibility tree.',
                ],
                'interactive' => [
                    'type' => 'boolean',
                    'description' => 'Whether to include interactive element refs in snapshot (default true).',
                ],
                'compact' => [
                    'type' => 'boolean',
                    'description' => 'Whether to return a compact snapshot (default false).',
                ],
                'full_page' => [
                    'type' => 'boolean',
                    'description' => 'Capture full page screenshot instead of viewport only.',
                ],
                'ref' => [
                    'type' => 'string',
                    'description' => 'Element reference from a snapshot, used by "act" and "screenshot" actions.',
                ],
                'selector' => [
                    'type' => 'string',
                    'description' => 'CSS selector for targeting elements (screenshot, wait).',
                ],
                'kind' => [
                    'type' => 'string',
                    'enum' => self::ACT_KINDS,
                    'description' => 'Interaction kind for "act" action: click, type, select, press, drag, hover, scroll, fill.',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Text input for type/fill/press actions, or text to wait for.',
                ],
                'submit' => [
                    'type' => 'boolean',
                    'description' => 'Whether to submit the form after typing/filling (default false).',
                ],
                'tab_id' => [
                    'type' => 'string',
                    'description' => 'Tab identifier for "close" action.',
                ],
                'script' => [
                    'type' => 'string',
                    'description' => 'JavaScript code to evaluate in page context (requires evaluate to be enabled).',
                ],
                'cookie_action' => [
                    'type' => 'string',
                    'enum' => self::COOKIE_ACTIONS,
                    'description' => 'Cookie sub-action: "get", "set", or "clear".',
                ],
                'cookie_name' => [
                    'type' => 'string',
                    'description' => 'Cookie name (for get/set/clear).',
                ],
                'cookie_value' => [
                    'type' => 'string',
                    'description' => 'Cookie value (for set).',
                ],
                'cookie_url' => [
                    'type' => 'string',
                    'description' => 'URL scope for cookie operations.',
                ],
                'timeout_ms' => [
                    'type' => 'integer',
                    'description' => 'Timeout in milliseconds for "wait" action (default 5000).',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_browser.execute';
    }

    public function execute(array $arguments): string
    {
        $action = $arguments['action'] ?? '';

        if (! is_string($action) || ! in_array($action, self::ACTIONS, true)) {
            return 'Error: Invalid action. Must be one of: '.implode(', ', self::ACTIONS).'.';
        }

        if (! $this->poolManager->isAvailable()) {
            return 'Error: Browser automation is not available. '
                .'The browser tool is either disabled or Playwright is not installed. '
                .'Contact an administrator to enable it.';
        }

        return match ($action) {
            'navigate' => $this->handleNavigate($arguments),
            'snapshot' => $this->handleSnapshot($arguments),
            'screenshot' => $this->handleScreenshot($arguments),
            'act' => $this->handleAct($arguments),
            'tabs' => $this->handleTabs(),
            'open' => $this->handleOpen($arguments),
            'close' => $this->handleClose($arguments),
            'evaluate' => $this->handleEvaluate($arguments),
            'pdf' => $this->handlePdf(),
            'cookies' => $this->handleCookies($arguments),
            'wait' => $this->handleWait($arguments),
        };
    }

    /**
     * Handle the "navigate" action.
     *
     * Navigates the browser to the given URL after SSRF validation.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleNavigate(array $arguments): string
    {
        $url = $arguments['url'] ?? '';

        if (! is_string($url) || trim($url) === '') {
            return 'Error: "url" is required for the navigate action.';
        }

        $url = trim($url);
        $ssrfCheck = $this->ssrfGuard->validate($url);

        if ($ssrfCheck !== true) {
            return 'Error: '.$ssrfCheck;
        }

        return json_encode([
            'action' => 'navigate',
            'url' => $url,
            'status' => 'navigated',
            'message' => 'Navigation completed (stub). Playwright integration pending.',
            'title' => '',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "snapshot" action.
     *
     * Returns a structured text representation of the page for LLM consumption.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleSnapshot(array $arguments): string
    {
        $format = 'ai';
        if (isset($arguments['format']) && in_array($arguments['format'], ['ai', 'aria'], true)) {
            $format = $arguments['format'];
        }

        $interactive = $arguments['interactive'] ?? true;
        $compact = $arguments['compact'] ?? false;

        return json_encode([
            'action' => 'snapshot',
            'format' => $format,
            'interactive' => (bool) $interactive,
            'compact' => (bool) $compact,
            'content' => '',
            'status' => 'captured',
            'message' => 'Snapshot captured (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "screenshot" action.
     *
     * Captures a screenshot of the viewport or a specific element.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleScreenshot(array $arguments): string
    {
        return json_encode([
            'action' => 'screenshot',
            'full_page' => (bool) ($arguments['full_page'] ?? false),
            'ref' => $arguments['ref'] ?? null,
            'selector' => $arguments['selector'] ?? null,
            'image_base64' => '',
            'status' => 'captured',
            'message' => 'Screenshot captured (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "act" action.
     *
     * Performs an interaction on a page element using snapshot refs.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleAct(array $arguments): string
    {
        $kind = $arguments['kind'] ?? '';

        if (! is_string($kind) || ! in_array($kind, self::ACT_KINDS, true)) {
            return 'Error: "kind" is required for the act action. '
                .'Must be one of: '.implode(', ', self::ACT_KINDS).'.';
        }

        $ref = $arguments['ref'] ?? null;

        if (! is_string($ref) || trim($ref) === '') {
            return 'Error: "ref" is required for the act action. '
                .'Use a snapshot to get element references.';
        }

        return json_encode([
            'action' => 'act',
            'kind' => $kind,
            'ref' => trim($ref),
            'text' => $arguments['text'] ?? null,
            'submit' => (bool) ($arguments['submit'] ?? false),
            'status' => 'performed',
            'message' => 'Action performed (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "tabs" action.
     *
     * Lists all open browser tabs.
     */
    private function handleTabs(): string
    {
        return json_encode([
            'action' => 'tabs',
            'tabs' => [],
            'status' => 'listed',
            'message' => 'Tabs listed (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "open" action.
     *
     * Opens a new tab with the given URL after SSRF validation.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleOpen(array $arguments): string
    {
        $url = $arguments['url'] ?? '';

        if (! is_string($url) || trim($url) === '') {
            return 'Error: "url" is required for the open action.';
        }

        $url = trim($url);
        $ssrfCheck = $this->ssrfGuard->validate($url);

        if ($ssrfCheck !== true) {
            return 'Error: '.$ssrfCheck;
        }

        return json_encode([
            'action' => 'open',
            'url' => $url,
            'tab_id' => '',
            'status' => 'opened',
            'message' => 'New tab opened (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "close" action.
     *
     * Closes a browser tab by tab ID.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleClose(array $arguments): string
    {
        $tabId = $arguments['tab_id'] ?? '';

        if (! is_string($tabId) || trim($tabId) === '') {
            return 'Error: "tab_id" is required for the close action.';
        }

        return json_encode([
            'action' => 'close',
            'tab_id' => trim($tabId),
            'status' => 'closed',
            'message' => 'Tab closed (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "evaluate" action.
     *
     * Executes JavaScript in the page context. Disabled by default;
     * requires config('ai.tools.browser.evaluate_enabled') to be true.
     * This is a high-trust action with a separate authz capability.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleEvaluate(array $arguments): string
    {
        if (! config('ai.tools.browser.evaluate_enabled', false)) {
            return 'Error: JavaScript evaluation is disabled. '
                .'An administrator must enable it via config("ai.tools.browser.evaluate_enabled").';
        }

        $script = $arguments['script'] ?? '';

        if (! is_string($script) || trim($script) === '') {
            return 'Error: "script" is required for the evaluate action.';
        }

        return json_encode([
            'action' => 'evaluate',
            'script' => trim($script),
            'result' => null,
            'status' => 'evaluated',
            'message' => 'Script evaluated (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "pdf" action.
     *
     * Exports the current page as a PDF document.
     */
    private function handlePdf(): string
    {
        return json_encode([
            'action' => 'pdf',
            'path' => '',
            'status' => 'exported',
            'message' => 'PDF exported (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "cookies" action.
     *
     * Manages cookies for the current browser context.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleCookies(array $arguments): string
    {
        $cookieAction = $arguments['cookie_action'] ?? '';

        if (! is_string($cookieAction) || ! in_array($cookieAction, self::COOKIE_ACTIONS, true)) {
            return 'Error: "cookie_action" is required for the cookies action. '
                .'Must be one of: '.implode(', ', self::COOKIE_ACTIONS).'.';
        }

        return match ($cookieAction) {
            'get' => $this->handleCookieGet($arguments),
            'set' => $this->handleCookieSet($arguments),
            'clear' => $this->handleCookieClear($arguments),
        };
    }

    /**
     * Handle cookie "get" sub-action.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleCookieGet(array $arguments): string
    {
        return json_encode([
            'action' => 'cookies',
            'cookie_action' => 'get',
            'cookie_name' => $arguments['cookie_name'] ?? null,
            'cookies' => [],
            'status' => 'retrieved',
            'message' => 'Cookies retrieved (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle cookie "set" sub-action.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleCookieSet(array $arguments): string
    {
        $name = $arguments['cookie_name'] ?? '';
        $value = $arguments['cookie_value'] ?? '';

        if (! is_string($name) || trim($name) === '') {
            return 'Error: "cookie_name" is required to set a cookie.';
        }

        if (! is_string($value)) {
            return 'Error: "cookie_value" is required to set a cookie.';
        }

        return json_encode([
            'action' => 'cookies',
            'cookie_action' => 'set',
            'cookie_name' => trim($name),
            'cookie_value' => $value,
            'cookie_url' => $arguments['cookie_url'] ?? null,
            'status' => 'set',
            'message' => 'Cookie set (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle cookie "clear" sub-action.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleCookieClear(array $arguments): string
    {
        return json_encode([
            'action' => 'cookies',
            'cookie_action' => 'clear',
            'cookie_name' => $arguments['cookie_name'] ?? null,
            'status' => 'cleared',
            'message' => 'Cookies cleared (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "wait" action.
     *
     * Waits for a specific page state: text content, CSS selector, or URL match.
     *
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleWait(array $arguments): string
    {
        $text = $arguments['text'] ?? null;
        $selector = $arguments['selector'] ?? null;
        $url = $arguments['url'] ?? null;

        if ($text === null && $selector === null && $url === null) {
            return 'Error: At least one of "text", "selector", or "url" is required for the wait action.';
        }

        $timeoutMs = 5000;
        if (isset($arguments['timeout_ms']) && is_int($arguments['timeout_ms'])) {
            $timeoutMs = max(100, $arguments['timeout_ms']);
        }

        return json_encode([
            'action' => 'wait',
            'text' => $text,
            'selector' => $selector,
            'url' => $url,
            'timeout_ms' => $timeoutMs,
            'status' => 'waited',
            'message' => 'Wait completed (stub). Playwright integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
