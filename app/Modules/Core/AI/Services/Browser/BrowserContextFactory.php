<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services\Browser;

/**
 * Manages isolated browser context creation for headless browser automation.
 *
 * Handles Playwright CLI resolution and produces unique context identifiers
 * scoped by company and session. Actual Chromium process lifecycle is
 * delegated to BrowserPoolManager.
 */
class BrowserContextFactory
{
    /**
     * Resolve the path to the Playwright CLI binary.
     *
     * Checks config('ai.tools.browser.executable_path') first, then
     * falls back to auto-detection of npx playwright in the project.
     *
     * @return string|null Path to binary, or null if not found
     */
    public function resolvePlaywrightPath(): ?string
    {
        $configured = config('ai.tools.browser.executable_path');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $npxPath = base_path('node_modules/.bin/playwright');

        if (file_exists($npxPath)) {
            return $npxPath;
        }

        return null;
    }

    /**
     * Check whether the Playwright CLI is available.
     */
    public function isAvailable(): bool
    {
        return config('ai.tools.browser.enabled', false) && $this->resolvePlaywrightPath() !== null;
    }

    /**
     * Create a new isolated browser context identifier.
     *
     * Returns a unique context ID. The actual Chromium context lifecycle
     * is managed by BrowserPoolManager.
     *
     * @param  int  $companyId  Company scope for isolation
     * @param  string  $sessionId  agent session identifier
     * @return string Unique context identifier
     */
    public function createContextId(int $companyId, string $sessionId): string
    {
        return 'ctx_'.$companyId.'_'.$sessionId;
    }
}
