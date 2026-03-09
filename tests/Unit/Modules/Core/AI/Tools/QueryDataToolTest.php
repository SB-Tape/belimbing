<?php

use App\Modules\Core\AI\Tools\QueryDataTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new QueryDataTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('query_data');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires query_data capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_query_data.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('query')
            ->and($schema['properties'])->toHaveKey('limit')
            ->and($schema['required'])->toBe(['query']);
    });
});

describe('SQL validation', function () {
    it('rejects empty query', function () {
        $result = $this->tool->execute(['query' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects missing query', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });

    it('rejects INSERT statements', function () {
        $result = $this->tool->execute(['query' => "INSERT INTO users (name) VALUES ('test')"]);
        expect($result)->toContain('INSERT')
            ->and($result)->toContain('not allowed');
    });

    it('rejects UPDATE statements', function () {
        $result = $this->tool->execute(['query' => "UPDATE users SET name = 'test'"]);
        expect($result)->toContain('UPDATE')
            ->and($result)->toContain('not allowed');
    });

    it('rejects DELETE statements', function () {
        $result = $this->tool->execute(['query' => 'DELETE FROM users']);
        expect($result)->toContain('DELETE')
            ->and($result)->toContain('not allowed');
    });

    it('rejects DROP statements', function () {
        $result = $this->tool->execute(['query' => 'DROP TABLE users']);
        expect($result)->toContain('DROP')
            ->and($result)->toContain('not allowed');
    });

    it('rejects ALTER statements', function () {
        $result = $this->tool->execute(['query' => 'ALTER TABLE users ADD COLUMN foo text']);
        expect($result)->toContain('ALTER')
            ->and($result)->toContain('not allowed');
    });

    it('rejects TRUNCATE statements', function () {
        $result = $this->tool->execute(['query' => 'TRUNCATE TABLE users']);
        expect($result)->toContain('TRUNCATE')
            ->and($result)->toContain('not allowed');
    });

    it('rejects CREATE statements', function () {
        $result = $this->tool->execute(['query' => 'CREATE TABLE evil (id int)']);
        expect($result)->toContain('CREATE')
            ->and($result)->toContain('not allowed');
    });

    it('rejects multi-statement queries', function () {
        $result = $this->tool->execute(['query' => 'SELECT 1; DROP TABLE users']);
        expect($result)->toContain('not allowed');
    });

    it('rejects queries not starting with SELECT or WITH', function () {
        $result = $this->tool->execute(['query' => 'SHOW TABLES']);
        expect($result)->toContain('Only SELECT');
    });

    it('rejects SELECT with embedded write in subquery', function () {
        $result = $this->tool->execute(['query' => 'SELECT * FROM (DELETE FROM users RETURNING *) AS x']);
        expect($result)->toContain('DELETE')
            ->and($result)->toContain('not allowed');
    });
});

describe('query execution', function () {
    it('executes a simple SELECT and returns formatted results', function () {
        $result = $this->tool->execute(['query' => "SELECT 1 AS id, 'hello' AS greeting"]);

        expect($result)->toContain('id')
            ->and($result)->toContain('greeting')
            ->and($result)->toContain('hello')
            ->and($result)->toContain('1 row');
    });

    it('returns row count message for empty results', function () {
        $result = $this->tool->execute([
            'query' => 'SELECT 1 AS id WHERE 1 = 0',
        ]);

        expect($result)->toBe('Query returned 0 rows.');
    });

    it('respects limit parameter', function () {
        // Build a UNION ALL query that produces more rows than the limit
        $unions = implode(' UNION ALL ', array_fill(0, 10, 'SELECT 1 AS n'));
        $result = $this->tool->execute([
            'query' => $unions,
            'limit' => 3,
        ]);

        expect($result)->toContain('3 rows returned')
            ->and($result)->toContain('limit reached');
    });

    it('caps limit at maximum', function () {
        // Request a limit above MAX_ROWS; tool should cap it at 100
        $result = $this->tool->execute([
            'query' => 'SELECT 1 AS n',
            'limit' => 999,
        ]);

        // With only 1 row of data, we can't test the cap numerically,
        // but we verify the tool doesn't error on an oversized limit
        expect($result)->toContain('1 row');
    });

    it('handles NULL values', function () {
        $result = $this->tool->execute([
            'query' => 'SELECT NULL AS empty_col',
        ]);

        expect($result)->toContain('NULL');
    });

    it('strips trailing semicolons', function () {
        $result = $this->tool->execute(['query' => 'SELECT 1 AS test;']);
        expect($result)->not->toContain('Error');
    });

    it('reports query errors gracefully', function () {
        $result = $this->tool->execute(['query' => 'SELECT * FROM nonexistent_table_xyz']);
        expect($result)->toContain('error');
    });

    it('allows WITH (CTE) queries', function () {
        $result = $this->tool->execute(['query' => 'WITH cte AS (SELECT 1 AS val) SELECT * FROM cte']);
        expect($result)->toContain('val')
            ->and($result)->toContain('1 row');
    });
});
