<?php

use App\Modules\Core\AI\Tools\WebFetchTool;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new WebFetchTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('web_fetch');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires web_fetch capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_web_fetch.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('url')
            ->and($schema['required'])->toBe(['url']);
    });
});

describe('input validation', function () {
    it('rejects empty URL', function () {
        $result = $this->tool->execute(['url' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects missing URL', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });
});

describe('SSRF protection', function () {
    it('blocks localhost', function () {
        $result = $this->tool->execute(['url' => 'http://localhost/test']);
        expect($result)->toContain('Blocked');
    });

    it('blocks 0.0.0.0', function () {
        $result = $this->tool->execute(['url' => 'http://0.0.0.0/test']);
        expect($result)->toContain('Blocked');
    });

    it('blocks .local domains', function () {
        $result = $this->tool->execute(['url' => 'http://myserver.local/test']);
        expect($result)->toContain('Blocked');
    });

    it('blocks non-http schemes', function () {
        $result = $this->tool->execute(['url' => 'ftp://example.com/file']);
        expect($result)->toContain('Only http and https');
    });

    it('blocks file scheme', function () {
        $result = $this->tool->execute(['url' => 'file:///etc/passwd']);
        expect($result)->toContain('Error');
    });

    it('allows ssrf_allow_private config to bypass', function () {
        config(['ai.tools.web_fetch.ssrf_allow_private' => true]);

        Http::fake(['*' => Http::response('<html><body><p>OK</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://192.168.1.1/test']);

        expect($result)->not->toContain('Blocked');
    });
});

describe('content fetching', function () {
    it('fetches and returns text content', function () {
        Http::fake(['*' => Http::response('<html><body><p>Hello World</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page']);

        expect($result)->toContain('Hello World');
    });

    it('strips script tags', function () {
        Http::fake(['*' => Http::response('<html><body><script>alert(1)</script><p>Content</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page']);

        expect($result)->toContain('Content')
            ->and($result)->not->toContain('alert');
    });

    it('strips style tags', function () {
        Http::fake(['*' => Http::response('<html><body><style>body{}</style><p>Content</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page']);

        expect($result)->toContain('Content')
            ->and($result)->not->toContain('body{}');
    });

    it('handles non-HTML content', function () {
        Http::fake(['*' => Http::response('{"key":"value"}', 200, ['Content-Type' => 'application/json'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/api']);

        expect($result)->toContain('{"key":"value"}');
    });

    it('truncates content to max_chars', function () {
        $longText = '<html><body><p>'.str_repeat('a', 1000).'</p></body></html>';
        Http::fake(['*' => Http::response($longText, 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page', 'max_chars' => 100]);

        expect($result)->toContain('truncated');
    });

    it('returns error for failed HTTP requests', function () {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $result = $this->tool->execute(['url' => 'http://example.com/missing']);

        expect($result)->toContain('HTTP 404');
    });

    it('includes source URL in output', function () {
        Http::fake(['*' => Http::response('<html><body><p>Test</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page']);

        expect($result)->toContain('http://example.com/page');
    });
});

describe('markdown extraction', function () {
    it('converts headings to markdown', function () {
        $html = '<html><body><h1>Title</h1><h2>Sub</h2></body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page', 'extract_mode' => 'markdown']);

        expect($result)->toContain('# Title')
            ->and($result)->toContain('## Sub');
    });

    it('converts links to markdown', function () {
        $html = '<html><body><a href="http://example.com">link text</a></body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page', 'extract_mode' => 'markdown']);

        expect($result)->toContain('[link text](http://example.com)');
    });

    it('converts bold to markdown', function () {
        $html = '<html><body><strong>bold text</strong></body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        $result = $this->tool->execute(['url' => 'http://example.com/page', 'extract_mode' => 'markdown']);

        expect($result)->toContain('**bold text**');
    });
});
