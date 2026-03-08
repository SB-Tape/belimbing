<?php

use App\Modules\Core\AI\Tools\MemoryGetTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new MemoryGetTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('memory_get');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires memory_get capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_memory_get.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('path')
            ->and($schema['required'])->toBe(['path']);
    });
});

describe('input validation', function () {
    it('rejects empty path', function () {
        $result = $this->tool->execute(['path' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects missing path', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });

    it('rejects absolute paths', function () {
        $result = $this->tool->execute(['path' => '/etc/passwd']);
        expect($result)->toContain('absolute');
    });

    it('rejects directory traversal', function () {
        $result = $this->tool->execute(['path' => '../../../etc/passwd']);
        expect($result)->toContain('traversal');
    });

    it('rejects null bytes', function () {
        $result = $this->tool->execute(['path' => "file\0.md"]);
        expect($result)->toContain('null bytes');
    });
});

describe('file reading', function () {
    it('reads a file from docs scope', function () {
        $result = $this->tool->execute(['path' => 'brief.md']);

        expect($result)->not->toContain('Error')
            ->and($result)->toContain('brief.md');
    });

    it('returns error for nonexistent file', function () {
        $result = $this->tool->execute(['path' => 'nonexistent-file-xyz.md']);
        expect($result)->toContain('not found');
    });

    it('respects from parameter', function () {
        $result = $this->tool->execute(['path' => 'brief.md', 'from' => 3]);

        expect($result)->not->toContain('# Project Brief: Belimbing')
            ->and($result)->toContain('lines 3-');
    });

    it('respects lines parameter', function () {
        $result = $this->tool->execute(['path' => 'brief.md', 'lines' => 5]);
        expect($result)->toContain('5 lines');
    });

    it('includes footer with scope info', function () {
        $result = $this->tool->execute(['path' => 'brief.md']);
        expect($result)->toContain('docs:brief.md');
    });
});

describe('scope selection', function () {
    it('defaults to docs scope', function () {
        $result = $this->tool->execute(['path' => 'brief.md']);
        expect($result)->toContain('docs:brief.md');
    });

    it('returns error for nonexistent workspace file', function () {
        $result = $this->tool->execute(['path' => 'MEMORY.md', 'scope' => 'workspace']);
        expect($result)->toContain('Error');
    });
});
