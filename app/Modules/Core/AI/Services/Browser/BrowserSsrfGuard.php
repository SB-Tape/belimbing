<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services\Browser;

/**
 * Reusable SSRF protection guard for browser-related URL validation.
 *
 * Shared by both BrowserTool and WebFetchTool to enforce consistent
 * URL safety checks. Blocks requests to private/internal networks,
 * loopback addresses, link-local ranges, and reserved IP ranges.
 *
 * Policy is controlled via config('ai.tools.browser.ssrf_policy'):
 * - allow_private_network: bypass IP range checks (development only)
 * - hostname_allowlist: fnmatch patterns that bypass IP checks
 */
class BrowserSsrfGuard
{
    /**
     * Validate whether the URL is safe from SSRF.
     *
     * Checks are applied in order: URL structure, scheme, hostname
     * blocklist, allowlist bypass, and finally resolved-IP range
     * validation (unless allow_private_network is enabled).
     *
     * @param  string  $url  The URL to validate
     * @return string|true True if safe, error string if blocked
     */
    public function validate(string $url): string|true
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

        if ($host === 'localhost' || $host === '0.0.0.0' || $host === '::1') {
            return "Blocked: requests to {$host} are not allowed.";
        }

        if (str_ends_with($host, '.local')) {
            return 'Blocked: requests to .local domains are not allowed.';
        }

        $allowlist = (array) config('ai.tools.browser.ssrf_policy.hostname_allowlist', []);

        if ($this->matchesAllowlist($host, $allowlist)) {
            return true;
        }

        if (config('ai.tools.browser.ssrf_policy.allow_private_network', false)) {
            return true;
        }

        $ip = gethostbyname($host);

        if ($ip === $host && ! filter_var($host, FILTER_VALIDATE_IP)) {
            return "Blocked: unable to resolve hostname {$host}.";
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return "Blocked: {$host} resolves to a private or reserved IP address ({$ip}).";
        }

        return true;
    }

    /**
     * Check whether the hostname matches any pattern in the allowlist.
     *
     * Supports fnmatch-style wildcards (e.g., '*.example.com').
     *
     * @param  string  $host  Lowercase hostname to check
     * @param  array<int, string>  $allowlist  Patterns to match against
     */
    private function matchesAllowlist(string $host, array $allowlist): bool
    {
        foreach ($allowlist as $pattern) {
            if (fnmatch(strtolower($pattern), $host)) {
                return true;
            }
        }

        return false;
    }
}
