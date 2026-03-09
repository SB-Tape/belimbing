<?php

use App\Modules\Core\AI\Tools\DocumentAnalysisTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new DocumentAnalysisTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('document_analysis');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires document analysis capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_document_analysis.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['path', 'prompt', 'pages', 'model'])
            ->and($schema['required'])->toBe(['path', 'prompt']);
    });
});

describe('input validation', function () {
    it('rejects missing path', function () {
        $result = $this->tool->execute(['prompt' => 'Summarize this']);
        expect($result)->toContain('Error');
    });

    it('rejects empty path', function () {
        $result = $this->tool->execute(['path' => '', 'prompt' => 'Summarize this']);
        expect($result)->toContain('Error');
    });

    it('rejects non-string path', function () {
        $result = $this->tool->execute(['path' => 123, 'prompt' => 'Summarize this']);
        expect($result)->toContain('Error');
    });

    it('rejects missing prompt', function () {
        $result = $this->tool->execute(['path' => '/docs/report.pdf']);
        expect($result)->toContain('Error');
    });

    it('rejects empty prompt', function () {
        $result = $this->tool->execute(['path' => '/docs/report.pdf', 'prompt' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects prompt exceeding max length', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => str_repeat('x', 5001),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('exceed');
    });

    it('rejects invalid pages format with letters', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => 'abc',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('pages');
    });

    it('rejects invalid pages format with spaces', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => '1 - 5',
        ]);
        expect($result)->toContain('Error');
    });

    it('rejects pages exceeding max length', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => str_repeat('1,', 60).'1',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('exceed');
    });

    it('rejects non-string pages', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => 5,
        ]);
        expect($result)->toContain('Error');
    });
});

describe('pages format validation', function () {
    it('accepts single page number', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => '1',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['pages'])->toBe('1');
    });

    it('accepts page range', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => '1-5',
        ]);
        $data = json_decode($result, true);

        expect($data['pages'])->toBe('1-5');
    });

    it('accepts comma-separated pages', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => '1,3,7',
        ]);
        $data = json_decode($result, true);

        expect($data['pages'])->toBe('1,3,7');
    });

    it('accepts mixed ranges and pages', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this',
            'pages' => '1-3,5,8-10',
        ]);
        $data = json_decode($result, true);

        expect($data['pages'])->toBe('1-3,5,8-10');
    });
});

describe('stub execution', function () {
    it('returns valid JSON with required fields', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize this document',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data)->toHaveKeys(['action', 'path', 'prompt', 'status', 'message'])
            ->and($data['action'])->toBe('document_analysis')
            ->and($data['status'])->toBe('analyzed');
    });

    it('includes path and prompt in response', function () {
        $result = $this->tool->execute([
            'path' => '/storage/docs/contract.pdf',
            'prompt' => 'Extract key dates',
        ]);
        $data = json_decode($result, true);

        expect($data['path'])->toBe('/storage/docs/contract.pdf')
            ->and($data['prompt'])->toBe('Extract key dates');
    });

    it('includes pages when provided', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
            'pages' => '1-3',
        ]);
        $data = json_decode($result, true);

        expect($data)->toHaveKey('pages')
            ->and($data['pages'])->toBe('1-3');
    });

    it('excludes pages when not provided', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toHaveKey('pages');
    });

    it('includes model when provided', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
            'model' => 'claude-3-opus',
        ]);
        $data = json_decode($result, true);

        expect($data)->toHaveKey('model')
            ->and($data['model'])->toBe('claude-3-opus');
    });

    it('excludes model when not provided', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toHaveKey('model');
    });

    it('returns stub message', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
        ]);
        $data = json_decode($result, true);

        expect($data['message'])->toContain('stub');
    });

    it('trims whitespace from inputs', function () {
        $result = $this->tool->execute([
            'path' => '  /docs/report.pdf  ',
            'prompt' => '  Summarize this  ',
        ]);
        $data = json_decode($result, true);

        expect($data['path'])->toBe('/docs/report.pdf')
            ->and($data['prompt'])->toBe('Summarize this');
    });

    it('handles empty pages string as no pages', function () {
        $result = $this->tool->execute([
            'path' => '/docs/report.pdf',
            'prompt' => 'Summarize',
            'pages' => '  ',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toHaveKey('pages');
    });
});
