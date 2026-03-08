<?php

use App\Modules\Core\AI\Services\Browser\BrowserSsrfGuard;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->guard = new BrowserSsrfGuard;
});

describe('url parsing', function () {
    it('rejects empty string', function () {
        expect($this->guard->validate(''))->toContain('Invalid URL');
    });

    it('rejects malformed URL', function () {
        expect($this->guard->validate('not-a-url'))->toContain('Invalid URL');
    });

    it('rejects ftp scheme', function () {
        expect($this->guard->validate('ftp://example.com/file'))->toContain('Only http and https');
    });

    it('rejects javascript scheme', function () {
        expect($this->guard->validate('javascript://example.com/alert(1)'))->toContain('Only http and https');
    });

    it('accepts http URL', function () {
        expect($this->guard->validate('http://example.com'))->toBe(true);
    });

    it('accepts https URL', function () {
        expect($this->guard->validate('https://example.com'))->toBe(true);
    });
});

describe('hostname blocklist', function () {
    it('blocks localhost', function () {
        expect($this->guard->validate('http://localhost'))->toContain('Blocked');
    });

    it('blocks 0.0.0.0', function () {
        expect($this->guard->validate('http://0.0.0.0'))->toContain('Blocked');
    });

    it('blocks ::1', function () {
        expect($this->guard->validate('http://[::1]'))->toContain('Blocked');
    });

    it('blocks .local domains', function () {
        expect($this->guard->validate('http://router.local'))->toContain('Blocked');
    });
});

describe('hostname allowlist', function () {
    it('allows hostname matching allowlist pattern', function () {
        config()->set('ai.tools.browser.ssrf_policy.hostname_allowlist', ['*.example.com']);

        expect($this->guard->validate('https://sub.example.com/page'))->toBe(true);
    });

    it('does not match non-matching hostnames', function () {
        config()->set('ai.tools.browser.ssrf_policy.hostname_allowlist', ['*.example.com']);
        config()->set('ai.tools.browser.ssrf_policy.allow_private_network', false);

        expect($this->guard->validate('http://192.168.1.1'))->toContain('private or reserved');
    });
});

describe('private network blocking', function () {
    it('blocks private IP 192.168.x.x', function () {
        expect($this->guard->validate('http://192.168.1.1'))->toContain('private or reserved');
    });

    it('blocks private IP 10.x.x.x', function () {
        expect($this->guard->validate('http://10.0.0.1'))->toContain('private or reserved');
    });

    it('blocks reserved 127.x', function () {
        expect($this->guard->validate('http://127.0.0.1'))->toContain('Blocked');
    });
});

describe('allow_private_network config', function () {
    it('allows private IPs when allow_private_network is true', function () {
        config()->set('ai.tools.browser.ssrf_policy.allow_private_network', true);

        expect($this->guard->validate('http://192.168.1.1'))->toBe(true);
    });
});
