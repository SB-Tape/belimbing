<?php

use App\Modules\Core\AI\Contracts\Messaging\ChannelAdapter;
use App\Modules\Core\AI\DTO\Messaging\ChannelCapabilities;
use App\Modules\Core\AI\Services\Messaging\ChannelAdapterRegistry;
use App\Modules\Core\AI\Tools\MessageTool;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->registry = new ChannelAdapterRegistry;

    // Register a full-capability adapter (Telegram-like)
    $fullAdapter = Mockery::mock(ChannelAdapter::class);
    $fullAdapter->shouldReceive('channelId')->andReturn('telegram');
    $fullAdapter->shouldReceive('label')->andReturn('Telegram');
    $fullAdapter->shouldReceive('capabilities')->andReturn(new ChannelCapabilities(
        supportsReactions: true,
        supportsEditing: true,
        supportsDeletion: true,
        supportsPolls: true,
        supportsThreads: true,
        supportsMedia: true,
        supportsSearch: true,
        maxMessageLength: 4096,
    ));
    $this->registry->register($fullAdapter);

    // Register a limited-capability adapter (Email-like)
    $limitedAdapter = Mockery::mock(ChannelAdapter::class);
    $limitedAdapter->shouldReceive('channelId')->andReturn('email');
    $limitedAdapter->shouldReceive('label')->andReturn('Email');
    $limitedAdapter->shouldReceive('capabilities')->andReturn(new ChannelCapabilities(
        supportsReactions: false,
        supportsEditing: false,
        supportsDeletion: false,
        supportsPolls: false,
        supportsMedia: true,
        supportsSearch: true,
        maxMessageLength: 100000,
    ));
    $this->registry->register($limitedAdapter);

    $this->tool = new MessageTool($this->registry);
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('message');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires message capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_message.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKey('action')
            ->and($schema['properties'])->toHaveKey('channel')
            ->and($schema['required'])->toBe(['action', 'channel']);
    });

    it('schema declares all actions', function () {
        $schema = $this->tool->parametersSchema();
        $actions = $schema['properties']['action']['enum'];

        expect($actions)->toContain('send')
            ->and($actions)->toContain('reply')
            ->and($actions)->toContain('react')
            ->and($actions)->toContain('edit')
            ->and($actions)->toContain('delete')
            ->and($actions)->toContain('poll')
            ->and($actions)->toContain('list_conversations')
            ->and($actions)->toContain('search');
    });
});

describe('input validation', function () {
    it('rejects missing action', function () {
        $result = $this->tool->execute(['channel' => 'telegram']);
        expect($result)->toContain('Error');
    });

    it('rejects invalid action', function () {
        $result = $this->tool->execute(['action' => 'bogus', 'channel' => 'telegram']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('Must be one of');
    });

    it('rejects missing channel', function () {
        $result = $this->tool->execute(['action' => 'send']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('channel');
    });

    it('rejects empty channel', function () {
        $result = $this->tool->execute(['action' => 'send', 'channel' => '']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('channel');
    });

    it('rejects unavailable channel', function () {
        $result = $this->tool->execute(['action' => 'send', 'channel' => 'discord']);
        expect($result)->toContain('Error')
            ->and($result)->toContain('not available');
    });

    it('lists available channels when channel unavailable', function () {
        $result = $this->tool->execute(['action' => 'send', 'channel' => 'discord']);
        expect($result)->toContain('telegram')
            ->and($result)->toContain('email');
    });

    it('handles no registered channels gracefully', function () {
        $emptyRegistry = new ChannelAdapterRegistry;
        $tool = new MessageTool($emptyRegistry);

        $result = $tool->execute(['action' => 'send', 'channel' => 'whatsapp']);
        expect($result)->toContain('No channels are configured');
    });
});

describe('send action', function () {
    it('requires target', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'text' => 'Hello',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('target');
    });

    it('requires text', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('text');
    });

    it('rejects text exceeding max length', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
            'text' => str_repeat('x', 50001),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('50000');
    });

    it('rejects text exceeding channel limit', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
            'text' => str_repeat('x', 4097),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('4096');
    });

    it('sends successfully', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
            'text' => 'Hello there!',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('send')
            ->and($data['channel'])->toBe('telegram')
            ->and($data['target'])->toBe('+1234567890')
            ->and($data['text'])->toBe('Hello there!')
            ->and($data['status'])->toBe('sent');
    });

    it('includes media_path when provided', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
            'text' => 'See attachment',
            'media_path' => '/storage/uploads/image.png',
        ]);

        $data = json_decode($result, true);
        expect($data['media_path'])->toBe('/storage/uploads/image.png');
    });

    it('sets media_path to null when not provided', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'telegram',
            'target' => '+1234567890',
            'text' => 'No attachment',
        ]);

        $data = json_decode($result, true);
        expect($data['media_path'])->toBeNull();
    });

    it('allows longer text on high-limit channels', function () {
        $longText = str_repeat('x', 5000);
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'email',
            'target' => 'user@example.com',
            'text' => $longText,
        ]);

        $data = json_decode($result, true);
        expect($data['status'])->toBe('sent');
    });
});

describe('reply action', function () {
    it('requires message_id', function () {
        $result = $this->tool->execute([
            'action' => 'reply',
            'channel' => 'telegram',
            'text' => 'Reply text',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('message_id');
    });

    it('requires text', function () {
        $result = $this->tool->execute([
            'action' => 'reply',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('text');
    });

    it('replies successfully', function () {
        $result = $this->tool->execute([
            'action' => 'reply',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
            'text' => 'Got it!',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('reply')
            ->and($data['channel'])->toBe('telegram')
            ->and($data['message_id'])->toBe('msg-123')
            ->and($data['text'])->toBe('Got it!')
            ->and($data['status'])->toBe('replied');
    });
});

describe('react action', function () {
    it('requires message_id', function () {
        $result = $this->tool->execute([
            'action' => 'react',
            'channel' => 'telegram',
            'emoji' => '👍',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('message_id');
    });

    it('requires emoji', function () {
        $result = $this->tool->execute([
            'action' => 'react',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('emoji');
    });

    it('reacts successfully on supported channel', function () {
        $result = $this->tool->execute([
            'action' => 'react',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
            'emoji' => '👍',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('react')
            ->and($data['emoji'])->toBe('👍')
            ->and($data['status'])->toBe('reacted');
    });

    it('rejects reaction on unsupported channel', function () {
        $result = $this->tool->execute([
            'action' => 'react',
            'channel' => 'email',
            'message_id' => 'msg-123',
            'emoji' => '👍',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('does not support reactions');
    });
});

describe('edit action', function () {
    it('requires message_id', function () {
        $result = $this->tool->execute([
            'action' => 'edit',
            'channel' => 'telegram',
            'text' => 'Updated text',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('message_id');
    });

    it('requires text', function () {
        $result = $this->tool->execute([
            'action' => 'edit',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('text');
    });

    it('edits successfully on supported channel', function () {
        $result = $this->tool->execute([
            'action' => 'edit',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
            'text' => 'Updated text',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('edit')
            ->and($data['message_id'])->toBe('msg-123')
            ->and($data['text'])->toBe('Updated text')
            ->and($data['status'])->toBe('edited');
    });

    it('rejects editing on unsupported channel', function () {
        $result = $this->tool->execute([
            'action' => 'edit',
            'channel' => 'email',
            'message_id' => 'msg-123',
            'text' => 'Updated text',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('does not support message editing');
    });
});

describe('delete action', function () {
    it('requires message_id', function () {
        $result = $this->tool->execute([
            'action' => 'delete',
            'channel' => 'telegram',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('message_id');
    });

    it('deletes successfully on supported channel', function () {
        $result = $this->tool->execute([
            'action' => 'delete',
            'channel' => 'telegram',
            'message_id' => 'msg-123',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('delete')
            ->and($data['message_id'])->toBe('msg-123')
            ->and($data['status'])->toBe('deleted');
    });

    it('rejects deletion on unsupported channel', function () {
        $result = $this->tool->execute([
            'action' => 'delete',
            'channel' => 'email',
            'message_id' => 'msg-123',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('does not support message deletion');
    });
});

describe('poll action', function () {
    it('requires target', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'question' => 'Lunch?',
            'options' => ['Pizza', 'Sushi'],
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('target');
    });

    it('requires question', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'target' => 'chat-123',
            'options' => ['Pizza', 'Sushi'],
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('question');
    });

    it('requires at least 2 options', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'target' => 'chat-123',
            'question' => 'Lunch?',
            'options' => ['Pizza'],
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('at least 2');
    });

    it('rejects more than 10 options', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'target' => 'chat-123',
            'question' => 'Pick one?',
            'options' => array_fill(0, 11, 'Option'),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('10');
    });

    it('rejects empty option strings', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'target' => 'chat-123',
            'question' => 'Lunch?',
            'options' => ['Pizza', ''],
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('non-empty string');
    });

    it('creates poll successfully on supported channel', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'telegram',
            'target' => 'chat-123',
            'question' => 'Lunch?',
            'options' => ['Pizza', 'Sushi', 'Tacos'],
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('poll')
            ->and($data['channel'])->toBe('telegram')
            ->and($data['question'])->toBe('Lunch?')
            ->and($data['options'])->toBe(['Pizza', 'Sushi', 'Tacos'])
            ->and($data['status'])->toBe('created');
    });

    it('rejects polls on unsupported channel', function () {
        $result = $this->tool->execute([
            'action' => 'poll',
            'channel' => 'email',
            'target' => 'user@example.com',
            'question' => 'Lunch?',
            'options' => ['Pizza', 'Sushi'],
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('does not support polls');
    });
});

describe('list_conversations action', function () {
    it('lists conversations with default limit', function () {
        $result = $this->tool->execute([
            'action' => 'list_conversations',
            'channel' => 'telegram',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('list_conversations')
            ->and($data['channel'])->toBe('telegram')
            ->and($data['limit'])->toBe(10)
            ->and($data['conversations'])->toBe([])
            ->and($data['status'])->toBe('listed');
    });

    it('respects custom limit', function () {
        $result = $this->tool->execute([
            'action' => 'list_conversations',
            'channel' => 'telegram',
            'limit' => 25,
        ]);

        $data = json_decode($result, true);
        expect($data['limit'])->toBe(25);
    });

    it('caps limit at 50', function () {
        $result = $this->tool->execute([
            'action' => 'list_conversations',
            'channel' => 'telegram',
            'limit' => 100,
        ]);

        $data = json_decode($result, true);
        expect($data['limit'])->toBe(50);
    });

    it('enforces minimum limit of 1', function () {
        $result = $this->tool->execute([
            'action' => 'list_conversations',
            'channel' => 'telegram',
            'limit' => 0,
        ]);

        $data = json_decode($result, true);
        expect($data['limit'])->toBe(1);
    });
});

describe('search action', function () {
    it('requires query', function () {
        $result = $this->tool->execute([
            'action' => 'search',
            'channel' => 'telegram',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('query');
    });

    it('searches successfully on supported channel', function () {
        $result = $this->tool->execute([
            'action' => 'search',
            'channel' => 'telegram',
            'query' => 'project status',
        ]);

        $data = json_decode($result, true);

        expect($data['action'])->toBe('search')
            ->and($data['channel'])->toBe('telegram')
            ->and($data['query'])->toBe('project status')
            ->and($data['limit'])->toBe(10)
            ->and($data['results'])->toBe([])
            ->and($data['status'])->toBe('searched');
    });

    it('respects custom limit', function () {
        $result = $this->tool->execute([
            'action' => 'search',
            'channel' => 'telegram',
            'query' => 'meeting',
            'limit' => 5,
        ]);

        $data = json_decode($result, true);
        expect($data['limit'])->toBe(5);
    });

    it('rejects search on unsupported channel', function () {
        // Need a channel without search support
        $noSearchAdapter = Mockery::mock(ChannelAdapter::class);
        $noSearchAdapter->shouldReceive('channelId')->andReturn('nosearch');
        $noSearchAdapter->shouldReceive('capabilities')->andReturn(new ChannelCapabilities(
            supportsSearch: false,
        ));
        $this->registry->register($noSearchAdapter);

        $result = $this->tool->execute([
            'action' => 'search',
            'channel' => 'nosearch',
            'query' => 'test',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('does not support message search');
    });
});

describe('channel adapter registry integration', function () {
    it('lists available channels in error messages', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'unknown',
        ]);
        expect($result)->toContain('telegram')
            ->and($result)->toContain('email');
    });

    it('routes to correct channel', function () {
        $result = $this->tool->execute([
            'action' => 'send',
            'channel' => 'email',
            'target' => 'user@example.com',
            'text' => 'Hello via email',
        ]);

        $data = json_decode($result, true);
        expect($data['channel'])->toBe('email');
    });
});
