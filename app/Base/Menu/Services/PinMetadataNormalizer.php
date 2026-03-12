<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Base\Menu\Services;

class PinMetadataNormalizer
{
    /**
     * Normalize a pin label to a compact, user-facing leaf label.
     *
     * Accepts breadcrumb-style labels using "/" separators and returns
     * the final non-empty segment. Whitespace around segments is trimmed.
     */
    public function normalizeLabel(string $label): string
    {
        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => trim($segment), explode('/', $label)),
            static fn (string $segment): bool => $segment !== ''
        ));

        if ($segments === []) {
            return trim($label);
        }

        return $segments[array_key_last($segments)];
    }

    /**
     * Normalize a URL into a destination key suitable for duplicate detection.
     *
     * Compares app-internal destinations by:
     * - path
     * - sorted query string
     *
     * Ignores:
     * - scheme
     * - host
     * - port
     * - fragment
     *
     * This allows equivalent URLs such as absolute and relative forms of the
     * same internal route to collapse to a single canonical destination key.
     */
    public function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return trim($url);
        }

        $path = isset($parts['path']) && is_string($parts['path'])
            ? $this->normalizePath($parts['path'])
            : '/';

        $query = $parts['query'] ?? null;

        if (! is_string($query) || $query === '') {
            return $path;
        }

        return $path.'?'.$this->normalizeQueryString($query);
    }

    /**
     * Normalize the path portion of a URL.
     */
    private function normalizePath(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return '/';
        }

        if (! str_starts_with($normalized, '/')) {
            $normalized = '/'.$normalized;
        }

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }

    /**
     * Normalize a query string by sorting keys recursively and rebuilding it.
     */
    private function normalizeQueryString(string $query): string
    {
        $parameters = [];
        parse_str($query, $parameters);

        $this->sortRecursive($parameters);

        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Recursively sort an array by key to ensure deterministic query ordering.
     *
     * @param  array<mixed>  $value
     */
    private function sortRecursive(array &$value): void
    {
        ksort($value);

        foreach ($value as &$item) {
            if (is_array($item)) {
                $this->sortRecursive($item);
            }
        }
    }
}
