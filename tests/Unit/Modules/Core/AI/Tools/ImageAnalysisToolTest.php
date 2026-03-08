<?php

use App\Modules\Core\AI\Tools\ImageAnalysisTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->tool = new ImageAnalysisTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('image_analysis');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires image analysis capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_image_analysis.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['path', 'prompt'])
            ->and($schema['required'])->toBe(['path', 'prompt']);
    });
});

describe('input validation', function () {
    it('rejects missing path', function () {
        $result = $this->tool->execute(['prompt' => 'Describe this image']);
        expect($result)->toContain('Error');
    });

    it('rejects empty path', function () {
        $result = $this->tool->execute(['path' => '', 'prompt' => 'Describe this image']);
        expect($result)->toContain('Error');
    });

    it('rejects non-string path', function () {
        $result = $this->tool->execute(['path' => 42, 'prompt' => 'Describe this image']);
        expect($result)->toContain('Error');
    });

    it('rejects missing prompt', function () {
        $result = $this->tool->execute(['path' => '/images/photo.jpg']);
        expect($result)->toContain('Error');
    });

    it('rejects empty prompt', function () {
        $result = $this->tool->execute(['path' => '/images/photo.jpg', 'prompt' => '']);
        expect($result)->toContain('Error');
    });

    it('rejects prompt exceeding max length', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo.jpg',
            'prompt' => str_repeat('x', 5001),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('exceed');
    });

    it('rejects unsupported image extension', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo.bmp',
            'prompt' => 'Describe this',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('Unsupported');
    });

    it('rejects unsupported extension with uppercase', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo.BMP',
            'prompt' => 'Describe this',
        ]);
        expect($result)->toContain('Error');
    });

    it('rejects file with no extension', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo',
            'prompt' => 'Describe this',
        ]);
        expect($result)->toContain('Error');
    });
});

describe('supported formats', function () {
    it('accepts jpg files', function () {
        $result = $this->tool->execute(['path' => '/images/photo.jpg', 'prompt' => 'Describe']);
        $data = json_decode($result, true);
        expect($data['status'])->toBe('analyzed');
    });

    it('accepts jpeg files', function () {
        $result = $this->tool->execute(['path' => '/images/photo.jpeg', 'prompt' => 'Describe']);
        $data = json_decode($result, true);
        expect($data['status'])->toBe('analyzed');
    });

    it('accepts png files', function () {
        $result = $this->tool->execute(['path' => '/images/photo.png', 'prompt' => 'Describe']);
        $data = json_decode($result, true);
        expect($data['status'])->toBe('analyzed');
    });

    it('accepts gif files', function () {
        $result = $this->tool->execute(['path' => '/images/photo.gif', 'prompt' => 'Describe']);
        $data = json_decode($result, true);
        expect($data['status'])->toBe('analyzed');
    });

    it('accepts webp files', function () {
        $result = $this->tool->execute(['path' => '/images/photo.webp', 'prompt' => 'Describe']);
        $data = json_decode($result, true);
        expect($data['status'])->toBe('analyzed');
    });
});

describe('URL paths', function () {
    it('accepts http URL without extension check', function () {
        $result = $this->tool->execute([
            'path' => 'http://example.com/image',
            'prompt' => 'Describe this',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('analyzed');
    });

    it('accepts https URL without extension check', function () {
        $result = $this->tool->execute([
            'path' => 'https://example.com/images/photo.png',
            'prompt' => 'Describe this',
        ]);
        $data = json_decode($result, true);

        expect($data['status'])->toBe('analyzed');
    });

    it('accepts https URL with query params', function () {
        $result = $this->tool->execute([
            'path' => 'https://example.com/image?w=500&h=300',
            'prompt' => 'Describe this',
        ]);
        $data = json_decode($result, true);

        expect($data['status'])->toBe('analyzed');
    });
});

describe('stub execution', function () {
    it('returns valid JSON with required fields', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo.jpg',
            'prompt' => 'What is in this image?',
        ]);
        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data)->toHaveKeys(['action', 'path', 'prompt', 'status', 'message'])
            ->and($data['action'])->toBe('image_analysis')
            ->and($data['status'])->toBe('analyzed');
    });

    it('includes path and prompt in response', function () {
        $result = $this->tool->execute([
            'path' => '/storage/images/chart.png',
            'prompt' => 'Extract data from chart',
        ]);
        $data = json_decode($result, true);

        expect($data['path'])->toBe('/storage/images/chart.png')
            ->and($data['prompt'])->toBe('Extract data from chart');
    });

    it('returns stub message', function () {
        $result = $this->tool->execute([
            'path' => '/images/photo.jpg',
            'prompt' => 'Describe',
        ]);
        $data = json_decode($result, true);

        expect($data['message'])->toContain('stub');
    });
});
