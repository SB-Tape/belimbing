<?php

use App\Modules\Core\AI\Tools\SystemInfoTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new SystemInfoTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('system_info');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires system_info capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_system_info.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('section')
            ->and($schema['properties']['section'])->toHaveKey('enum');
    });
});

describe('section selection', function () {
    it('returns all sections by default', function () {
        $result = $this->tool->execute([]);
        $data = json_decode($result, true);

        expect($data)->toHaveKeys(['framework', 'modules', 'providers', 'health']);
    });

    it('returns only requested section', function () {
        $result = $this->tool->execute(['section' => 'framework']);
        $data = json_decode($result, true);

        expect($data)->toHaveKey('framework')
            ->and($data)->not->toHaveKey('modules')
            ->and($data)->not->toHaveKey('providers')
            ->and($data)->not->toHaveKey('health');
    });

    it('falls back to all for invalid section', function () {
        $result = $this->tool->execute(['section' => 'bogus']);
        $data = json_decode($result, true);

        expect($data)->toHaveKeys(['framework', 'modules', 'providers', 'health']);
    });

    it('returns framework section with expected keys', function () {
        $result = $this->tool->execute(['section' => 'framework']);
        $data = json_decode($result, true);

        expect($data['framework'])->toHaveKeys([
            'laravel_version',
            'php_version',
            'php_sapi',
            'environment',
            'debug_mode',
            'timezone',
            'locale',
        ]);
    });

    it('returns health section with expected keys', function () {
        $result = $this->tool->execute(['section' => 'health']);
        $data = json_decode($result, true);

        expect($data['health'])->toHaveKeys([
            'queue_connection',
            'cache_driver',
            'session_driver',
            'database',
            'storage_writable',
        ]);
    });

    it('reports database as connected', function () {
        $result = $this->tool->execute(['section' => 'health']);
        $data = json_decode($result, true);

        expect($data['health']['database'])->toBe('connected');
    });

    it('returns modules as array', function () {
        $result = $this->tool->execute(['section' => 'modules']);
        $data = json_decode($result, true);

        expect($data['modules'])->toBeArray();
    });

    it('returns providers as array', function () {
        $result = $this->tool->execute(['section' => 'providers']);
        $data = json_decode($result, true);

        expect($data['providers'])->toBeArray();
    });
});

describe('output format', function () {
    it('returns valid JSON', function () {
        $result = $this->tool->execute([]);

        expect(json_decode($result, true))->not->toBeNull();
    });

    it('returns pretty-printed JSON', function () {
        $result = $this->tool->execute([]);

        expect($result)->toContain("\n");
    });
});
