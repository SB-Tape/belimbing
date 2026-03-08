<?php

use App\Modules\Core\AI\Services\Browser\BrowserPoolManager;
use App\Modules\Core\AI\Services\Browser\BrowserSsrfGuard;
use App\Modules\Core\AI\Tools\BrowserTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->poolManager = Mockery::mock(BrowserPoolManager::class);
    $this->ssrfGuard = Mockery::mock(BrowserSsrfGuard::class);
    $this->tool = new BrowserTool($this->poolManager, $this->ssrfGuard);

    $this->poolManager->shouldReceive('isAvailable')->andReturn(true)->byDefault();
    $this->ssrfGuard->shouldReceive('validate')->andReturn(true)->byDefault();
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('browser');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires browser capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_browser.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('action')
            ->and($schema['required'])->toBe(['action']);
    });
});

describe('input validation', function () {
    it('rejects missing action', function () {
        $result = $this->tool->execute([]);
        expect($result)->toContain('Error');
    });

    it('rejects invalid action', function () {
        $result = $this->tool->execute(['action' => 'bogus']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('Must be one of');
    });

    it('returns error when pool unavailable', function () {
        $this->poolManager->shouldReceive('isAvailable')->andReturn(false);

        $result = $this->tool->execute(['action' => 'navigate', 'url' => 'https://example.com']);

        expect($result)->toContain('not available');
    });
});

describe('navigate action', function () {
    it('rejects missing url', function () {
        $result = $this->tool->execute(['action' => 'navigate']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('url');
    });

    it('rejects SSRF blocked url', function () {
        $this->ssrfGuard->shouldReceive('validate')
            ->with('http://evil.internal')
            ->andReturn('Blocked: private');

        $result = $this->tool->execute(['action' => 'navigate', 'url' => 'http://evil.internal']);

        expect($result)->toContain('Error')
            ->and($result)->toContain('Blocked');
    });

    it('navigates successfully', function () {
        $result = $this->tool->execute(['action' => 'navigate', 'url' => 'https://example.com']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('navigated');
    });
});

describe('snapshot action', function () {
    it('returns snapshot with default format', function () {
        $result = $this->tool->execute(['action' => 'snapshot']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['format'])->toBe('ai')
            ->and($data['status'])->toBe('captured');
    });

    it('accepts aria format', function () {
        $result = $this->tool->execute(['action' => 'snapshot', 'format' => 'aria']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['format'])->toBe('aria');
    });
});

describe('screenshot action', function () {
    it('returns screenshot stub', function () {
        $result = $this->tool->execute(['action' => 'screenshot']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('captured');
    });

    it('accepts full_page flag', function () {
        $result = $this->tool->execute(['action' => 'screenshot', 'full_page' => true]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['full_page'])->toBeTrue();
    });
});

describe('act action', function () {
    it('rejects missing kind', function () {
        $result = $this->tool->execute(['action' => 'act']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('kind');
    });

    it('rejects invalid kind', function () {
        $result = $this->tool->execute(['action' => 'act', 'kind' => 'bogus']);
        expect($result)->toContain('Error');
    });

    it('rejects missing ref', function () {
        $result = $this->tool->execute(['action' => 'act', 'kind' => 'click']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('ref');
    });

    it('performs action successfully', function () {
        $result = $this->tool->execute(['action' => 'act', 'kind' => 'click', 'ref' => 'e1']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('performed');
    });
});

describe('tabs action', function () {
    it('lists tabs', function () {
        $result = $this->tool->execute(['action' => 'tabs']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('listed');
    });
});

describe('open action', function () {
    it('rejects missing url', function () {
        $result = $this->tool->execute(['action' => 'open']);
        expect($result)->toContain('Error');
    });

    it('opens tab successfully', function () {
        $result = $this->tool->execute(['action' => 'open', 'url' => 'https://example.com']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('opened');
    });
});

describe('close action', function () {
    it('rejects missing tab_id', function () {
        $result = $this->tool->execute(['action' => 'close']);
        expect($result)->toContain('Error');
    });

    it('closes tab', function () {
        $result = $this->tool->execute(['action' => 'close', 'tab_id' => 'tab1']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('closed');
    });
});

describe('evaluate action', function () {
    it('rejects when evaluate disabled', function () {
        config()->set('ai.tools.browser.evaluate_enabled', false);

        $result = $this->tool->execute(['action' => 'evaluate', 'script' => 'alert(1)']);

        expect($result)->toContain('Error')
            ->and($result)->toContain('disabled');
    });

    it('rejects missing script when enabled', function () {
        config()->set('ai.tools.browser.evaluate_enabled', true);

        $result = $this->tool->execute(['action' => 'evaluate']);

        expect($result)->toContain('Error')
            ->and($result)->toContain('script');
    });

    it('evaluates when enabled', function () {
        config()->set('ai.tools.browser.evaluate_enabled', true);

        $result = $this->tool->execute(['action' => 'evaluate', 'script' => 'document.title']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('evaluated');
    });
});

describe('pdf action', function () {
    it('exports pdf', function () {
        $result = $this->tool->execute(['action' => 'pdf']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('exported');
    });
});

describe('cookies action', function () {
    it('rejects missing cookie_action', function () {
        $result = $this->tool->execute(['action' => 'cookies']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('cookie_action');
    });

    it('rejects invalid cookie_action', function () {
        $result = $this->tool->execute(['action' => 'cookies', 'cookie_action' => 'bogus']);
        expect($result)->toContain('Error');
    });

    it('gets cookies', function () {
        $result = $this->tool->execute(['action' => 'cookies', 'cookie_action' => 'get']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('retrieved');
    });

    it('rejects set without name', function () {
        $result = $this->tool->execute(['action' => 'cookies', 'cookie_action' => 'set']);
        expect($result)->toContain('Error');
    });

    it('sets cookie', function () {
        $result = $this->tool->execute([
            'action' => 'cookies',
            'cookie_action' => 'set',
            'cookie_name' => 'test',
            'cookie_value' => 'val',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('set');
    });

    it('clears cookies', function () {
        $result = $this->tool->execute(['action' => 'cookies', 'cookie_action' => 'clear']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('cleared');
    });
});

describe('wait action', function () {
    it('rejects when no condition specified', function () {
        $result = $this->tool->execute(['action' => 'wait']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('At least one');
    });

    it('accepts text condition', function () {
        $result = $this->tool->execute(['action' => 'wait', 'text' => 'Hello']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('waited');
    });

    it('accepts selector condition', function () {
        $result = $this->tool->execute(['action' => 'wait', 'selector' => '#main']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('waited');
    });

    it('accepts url condition', function () {
        $result = $this->tool->execute(['action' => 'wait', 'url' => 'https://example.com/done']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('waited');
    });

    it('uses default timeout', function () {
        $result = $this->tool->execute(['action' => 'wait', 'text' => 'Hello']);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['timeout_ms'])->toBe(5000);
    });

    it('accepts custom timeout', function () {
        $result = $this->tool->execute(['action' => 'wait', 'text' => 'Hi', 'timeout_ms' => 10000]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['timeout_ms'])->toBe(10000);
    });
});
