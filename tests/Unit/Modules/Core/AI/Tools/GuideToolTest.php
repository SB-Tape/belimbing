<?php

use App\Modules\Core\AI\Services\LaraKnowledgeNavigator;
use App\Modules\Core\AI\Tools\GuideTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new GuideTool(new LaraKnowledgeNavigator);
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('guide');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires guide capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_guide.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('topic')
            ->and($schema['required'])->toBe(['topic']);
    });
});

describe('input validation', function () {
    it('rejects empty topic', function () {
        $result = $this->tool->execute(['topic' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects missing topic', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });
});

describe('search results', function () {
    it('finds architecture documentation', function () {
        $result = $this->tool->execute(['topic' => 'architecture']);
        expect(mb_strtolower($result))->toContain('architecture');
    });

    it('finds authorization documentation', function () {
        $result = $this->tool->execute(['topic' => 'authorization']);
        $lower = mb_strtolower($result);

        expect($lower)->toMatch('/auth(orization)?/');
    });

    it('finds digital worker documentation', function () {
        $result = $this->tool->execute(['topic' => 'digital worker']);
        $lower = mb_strtolower($result);

        expect($lower)->toContain('digital worker');
    });

    it('returns no results message for unknown topic', function () {
        $result = $this->tool->execute(['topic' => 'xyznonexistent']);
        expect($result)->toContain('No documentation found');
    });

    it('lists available topics when no results', function () {
        $result = $this->tool->execute(['topic' => 'xyznonexistent']);
        expect($result)->toContain('Available topics');
    });

    it('respects max_sections parameter', function () {
        $result = $this->tool->execute(['topic' => 'architecture', 'max_sections' => 2]);

        expect($result)->not->toBeEmpty()
            ->and($result)->not->toContain('3.');
    });

    it('includes top match file content', function () {
        $result = $this->tool->execute(['topic' => 'lara']);
        expect($result)->toContain('Top match content');
    });
});
