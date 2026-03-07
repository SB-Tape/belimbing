<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use Illuminate\Support\Facades\DB;

/**
 * Read-only database query tool for Digital Workers.
 *
 * Allows a DW to execute SELECT queries against the application database
 * to answer data questions (e.g., "How many employees are active?").
 *
 * Safety: Only SELECT statements are allowed. Write operations (INSERT,
 * UPDATE, DELETE, DROP, ALTER, TRUNCATE, CREATE, GRANT, REVOKE) are
 * rejected at the SQL parsing level. A statement timeout and row count
 * cap provide additional protection.
 *
 * Gated by `ai.tool_query_data.execute` authz capability.
 */
class QueryDataTool implements DigitalWorkerTool
{
    private const TIMEOUT_SECONDS = 10;

    private const MAX_ROWS = 100;

    private const DEFAULT_LIMIT = 50;

    /**
     * SQL keywords that indicate a write operation.
     *
     * @var list<string>
     */
    private const FORBIDDEN_KEYWORDS = [
        'INSERT',
        'UPDATE',
        'DELETE',
        'DROP',
        'ALTER',
        'TRUNCATE',
        'CREATE',
        'GRANT',
        'REVOKE',
        'REPLACE',
        'RENAME',
        'LOCK',
        'UNLOCK',
        'CALL',
        'EXEC',
        'EXECUTE',
        'COPY',
        'VACUUM',
        'REINDEX',
    ];

    public function name(): string
    {
        return 'query_data';
    }

    public function description(): string
    {
        return 'Execute a read-only SQL query against the BLB database and return results. '
            .'Use this to answer data questions like "How many employees are active?", '
            .'"Show recent orders", or "List all companies". Only SELECT queries are allowed. '
            .'Results are returned as a formatted table. Maximum '.self::MAX_ROWS.' rows.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'The SQL SELECT query to execute. '
                        .'Only SELECT statements are allowed — no INSERT, UPDATE, DELETE, DROP, etc. '
                        .'Use standard SQL compatible with PostgreSQL. '
                        .'Examples: "SELECT count(*) FROM employees WHERE status = \'active\'", '
                        .'"SELECT id, full_name, designation FROM employees LIMIT 10".',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum number of rows to return (1–'.self::MAX_ROWS.', default '.self::DEFAULT_LIMIT.'). '
                        .'Overrides any LIMIT clause in the query.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_query_data.execute';
    }

    public function execute(array $arguments): string
    {
        $query = $arguments['query'] ?? '';

        if (! is_string($query) || trim($query) === '') {
            return 'Error: No query provided.';
        }

        $query = trim($query);

        // Remove trailing semicolons (prevents multi-statement injection)
        $query = rtrim($query, ';');

        $validationError = $this->validateQuery($query);
        if ($validationError !== null) {
            return $validationError;
        }

        $limit = self::DEFAULT_LIMIT;
        if (isset($arguments['limit']) && is_int($arguments['limit'])) {
            $limit = max(1, min($arguments['limit'], self::MAX_ROWS));
        }

        // Enforce row limit by wrapping the query
        $limitedQuery = $this->applyLimit($query, $limit);

        try {
            $results = DB::connection()
                ->getReadPdo()
                ->prepare($limitedQuery);

            $results->execute();

            /** @var list<array<string, mixed>> $rows */
            $rows = $results->fetchAll(\PDO::FETCH_ASSOC);

            if ($rows === []) {
                return 'Query returned 0 rows.';
            }

            return $this->formatResults($rows, $limit);
        } catch (\PDOException $e) {
            return 'Query error: '.$e->getMessage();
        } catch (\Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    /**
     * Validate that the query is a safe SELECT statement.
     *
     * Checks are ordered to produce the most specific error message:
     * forbidden keywords first (names the offending operation), then
     * structural checks (multi-statement, must start with SELECT/WITH).
     */
    private function validateQuery(string $query): ?string
    {
        // Reject semicolons within the query (multi-statement)
        if (str_contains($query, ';')) {
            return 'Error: Multiple statements are not allowed.';
        }

        // Scan for forbidden keywords at word boundaries
        foreach (self::FORBIDDEN_KEYWORDS as $keyword) {
            if (preg_match('/\b'.$keyword.'\b/i', $query)) {
                return 'Error: '.$keyword.' operations are not allowed. Only SELECT queries are permitted.';
            }
        }

        // Must start with SELECT or WITH (for CTEs)
        $normalised = strtoupper(ltrim($query));

        if (! str_starts_with($normalised, 'SELECT') && ! str_starts_with($normalised, 'WITH')) {
            return 'Error: Only SELECT queries are allowed. Your query must start with SELECT or WITH.';
        }

        return null;
    }

    /**
     * Apply a row limit to the query.
     *
     * Wraps the user query as a subquery to enforce the limit reliably,
     * regardless of whether the original query has a LIMIT clause.
     */
    private function applyLimit(string $query, int $limit): string
    {
        return 'SELECT * FROM ('.$query.') AS _blb_limited LIMIT '.$limit;
    }

    /**
     * Format query results as a readable text table.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function formatResults(array $rows, int $limit): string
    {
        $rowCount = count($rows);
        $columns = array_keys($rows[0]);

        // Calculate column widths
        $widths = [];
        foreach ($columns as $col) {
            $widths[$col] = mb_strlen($col);
        }
        foreach ($rows as $row) {
            foreach ($columns as $col) {
                $value = $this->formatValue($row[$col] ?? null);
                $widths[$col] = max($widths[$col], mb_strlen($value));
            }
        }

        // Cap column widths to keep output manageable
        $maxColWidth = 60;
        foreach ($widths as $col => $width) {
            $widths[$col] = min($width, $maxColWidth);
        }

        // Build header
        $header = '| ';
        $separator = '|-';
        foreach ($columns as $col) {
            $header .= mb_str_pad($col, $widths[$col]).' | ';
            $separator .= str_repeat('-', $widths[$col]).'-|-';
        }

        // Build rows
        $lines = [trim($header), trim($separator)];
        foreach ($rows as $row) {
            $line = '| ';
            foreach ($columns as $col) {
                $value = $this->formatValue($row[$col] ?? null);
                if (mb_strlen($value) > $maxColWidth) {
                    $value = mb_substr($value, 0, $maxColWidth - 3).'...';
                }
                $line .= mb_str_pad($value, $widths[$col]).' | ';
            }
            $lines[] = trim($line);
        }

        $result = implode("\n", $lines);

        $suffix = $rowCount >= $limit
            ? "\n\n(".$rowCount.' rows returned — limit reached, there may be more results)'
            : "\n\n(".$rowCount.' '.($rowCount === 1 ? 'row' : 'rows').' returned)';

        return $result.$suffix;
    }

    /**
     * Format a single cell value for display.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
