<?php

use App\Modules\Core\AI\Tools\WriteJsTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new WriteJsTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('write_js');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires write_js capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_write_js.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['script', 'description'])
            ->and($schema['required'])->toBe(['script', 'description']);
    });
});

describe('input validation', function () {
    it('rejects missing script', function () {
        $result = $this->tool->execute(['description' => 'Test']);
        expect($result)->toContain('Error');
    });

    it('rejects empty script', function () {
        $result = $this->tool->execute(['script' => '', 'description' => 'Test']);
        expect($result)->toContain('Error');
    });

    it('rejects non-string script', function () {
        $result = $this->tool->execute(['script' => 42, 'description' => 'Test']);
        expect($result)->toContain('Error');
    });

    it('rejects missing description', function () {
        $result = $this->tool->execute(['script' => 'console.log("hi")']);
        expect($result)->toContain('Error');
    });

    it('rejects empty description', function () {
        $result = $this->tool->execute(['script' => 'console.log("hi")', 'description' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects script exceeding max length', function () {
        $result = $this->tool->execute([
            'script' => str_repeat('x', 10001),
            'description' => 'Test',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('maximum length');
    });

    it('rejects description exceeding max length', function () {
        $result = $this->tool->execute([
            'script' => 'console.log("hi")',
            'description' => str_repeat('x', 501),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('maximum length');
    });
});

describe('security validation', function () {
    it('blocks eval()', function () {
        $result = $this->tool->execute([
            'script' => 'eval("alert(1)")',
            'description' => 'Test eval',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks eval() case-insensitively', function () {
        $result = $this->tool->execute([
            'script' => 'EVAL("alert(1)")',
            'description' => 'Test eval',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks Function()', function () {
        $result = $this->tool->execute([
            'script' => 'new Function("return 1")()',
            'description' => 'Test Function',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks document.cookie', function () {
        $result = $this->tool->execute([
            'script' => 'let c = document.cookie;',
            'description' => 'Test cookie theft',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks localStorage', function () {
        $result = $this->tool->execute([
            'script' => 'localStorage.getItem("key")',
            'description' => 'Test localStorage',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks sessionStorage', function () {
        $result = $this->tool->execute([
            'script' => 'sessionStorage.setItem("key", "val")',
            'description' => 'Test sessionStorage',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks importScripts', function () {
        $result = $this->tool->execute([
            'script' => 'importScripts("evil.js")',
            'description' => 'Test importScripts',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });

    it('blocks dynamic import()', function () {
        $result = $this->tool->execute([
            'script' => 'import("./module.js").then(m => m.run())',
            'description' => 'Test dynamic import',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('blocked');
    });
});

describe('lara-action execution', function () {
    it('returns lara-action block with script', function () {
        $result = $this->tool->execute([
            'script' => 'console.log("hello")',
            'description' => 'Log a greeting',
        ]);

        expect($result)->toContain('<lara-action>')
            ->and($result)->toContain('</lara-action>')
            ->and($result)->toContain('console.log("hello")');
    });

    it('includes description in response text', function () {
        $result = $this->tool->execute([
            'script' => 'alert("hi")',
            'description' => 'Show an alert',
        ]);

        expect($result)->toContain('Script executed: Show an alert.');
    });

    it('follows NavigateTool lara-action pattern', function () {
        $result = $this->tool->execute([
            'script' => 'document.title = "New Title"',
            'description' => 'Change page title',
        ]);

        expect($result)->toBe(
            '<lara-action>document.title = "New Title"</lara-action>Script executed: Change page title.'
        );
    });

    it('allows safe DOM manipulation', function () {
        $script = 'document.getElementById("app").classList.add("dark")';
        $result = $this->tool->execute([
            'script' => $script,
            'description' => 'Enable dark mode',
        ]);
        $data = null;

        expect($result)->toContain('<lara-action>')
            ->and($result)->toContain($script);
    });

    it('allows clipboard API', function () {
        $script = 'navigator.clipboard.writeText("copied text")';
        $result = $this->tool->execute([
            'script' => $script,
            'description' => 'Copy text to clipboard',
        ]);

        expect($result)->toContain('<lara-action>')
            ->and($result)->toContain($script);
    });

    it('allows fetch API', function () {
        $script = 'fetch("/api/status").then(r => r.json())';
        $result = $this->tool->execute([
            'script' => $script,
            'description' => 'Check API status',
        ]);

        expect($result)->toContain('<lara-action>')
            ->and($result)->toContain($script);
    });
});
