<?php

use App\Modules\Core\AI\Tools\NotificationTool;
use App\Modules\Core\User\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->tool = new NotificationTool;
});

describe('tool metadata', function () {
    it('returns correct name', function () {
        expect($this->tool->name())->toBe('notification');
    });

    it('returns a description', function () {
        expect($this->tool->description())->not->toBeEmpty();
    });

    it('requires notification capability', function () {
        expect($this->tool->requiredCapability())->toBe('ai.tool_notification.execute');
    });

    it('has valid parameter schema', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['type'])->toBe('object')
            ->and($schema['properties'])->toHaveKeys(['user_id', 'channel', 'subject', 'body'])
            ->and($schema['required'])->toBe(['user_id', 'subject', 'body']);
    });
});

describe('input validation', function () {
    it('rejects missing user_id', function () {
        $result = $this->tool->execute(['subject' => 'Test', 'body' => 'Test body']);
        expect($result)->toContain('Error');
    });

    it('rejects invalid user_id type', function () {
        $result = $this->tool->execute(['user_id' => 'invalid', 'subject' => 'Test', 'body' => 'Test body']);
        expect($result)->toContain('Error');
    });

    it('rejects negative user_id', function () {
        $result = $this->tool->execute(['user_id' => -1, 'subject' => 'Test', 'body' => 'Test body']);
        expect($result)->toContain('Error');
    });

    it('rejects missing subject', function () {
        $result = $this->tool->execute(['user_id' => 1, 'body' => 'Test body']);
        expect($result)->toContain('Error');
    });

    it('rejects empty subject', function () {
        $result = $this->tool->execute(['user_id' => 1, 'subject' => '', 'body' => 'Test body']);
        expect($result)->toContain('Error');
    });

    it('rejects subject exceeding max length', function () {
        $result = $this->tool->execute([
            'user_id' => 1,
            'subject' => str_repeat('x', 256),
            'body' => 'Test body',
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('exceed');
    });

    it('rejects missing body', function () {
        $result = $this->tool->execute(['user_id' => 1, 'subject' => 'Test']);
        expect($result)->toContain('Error');
    });

    it('rejects empty body', function () {
        $result = $this->tool->execute(['user_id' => 1, 'subject' => 'Test', 'body' => ' ']);
        expect($result)->toContain('Error');
    });

    it('rejects body exceeding max length', function () {
        $result = $this->tool->execute([
            'user_id' => 1,
            'subject' => 'Test',
            'body' => str_repeat('x', 5001),
        ]);
        expect($result)->toContain('Error')
            ->and($result)->toContain('exceed');
    });

    it('rejects invalid channel', function () {
        $result = $this->tool->execute([
            'user_id' => 1,
            'subject' => 'Test',
            'body' => 'Test body',
            'channel' => 'sms',
        ]);
        expect($result)->toContain('Error');
    });

    it('defaults channel to database', function () {
        $schema = $this->tool->parametersSchema();

        expect($schema['properties']['channel']['enum'])->toContain('database');
    });
});

describe('recipient resolution', function () {
    it('returns error for non-existent user_id', function () {
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => 99999,
            'subject' => 'Test',
            'body' => 'Test body',
        ]);

        expect($result)->toContain('Error')
            ->and($result)->toContain('not found');
    });

    it('sends notification to specific user', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'subject' => 'Hello',
            'body' => 'World',
        ]);

        $data = json_decode($result, true);

        expect($data)->not->toBeNull()
            ->and($data['status'])->toBe('sent')
            ->and($data['recipients'])->toBe(1);
    });

    it('returns error for all when not authenticated', function () {
        $result = $this->tool->execute([
            'user_id' => 'all',
            'subject' => 'Broadcast',
            'body' => 'Message to everyone',
        ]);

        expect($result)->toContain('Error');
    });
});

describe('notification sending', function () {
    it('sends via database channel', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'channel' => 'database',
            'subject' => 'DB Notification',
            'body' => 'Stored in database',
        ]);

        $notifications = Notification::sentNotifications();
        $userKey = get_class($user);
        expect($notifications[$userKey][$user->getKey()] ?? [])->not->toBeEmpty();

        $sentTypes = $notifications[$userKey][$user->getKey()];
        $firstType = array_key_first($sentTypes);
        $notification = $sentTypes[$firstType][0]['notification'];
        expect($notification->via($user))->toBe(['database']);

        $data = json_decode($result, true);
        expect($data['channel'])->toBe('database');
    });

    it('sends via broadcast channel', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'channel' => 'broadcast',
            'subject' => 'Broadcast Notification',
            'body' => 'Sent via broadcast',
        ]);

        $notifications = Notification::sentNotifications();
        $userKey = get_class($user);
        $sentTypes = $notifications[$userKey][$user->getKey()];
        $firstType = array_key_first($sentTypes);
        $notification = $sentTypes[$firstType][0]['notification'];
        expect($notification->via($user))->toBe(['broadcast']);

        $data = json_decode($result, true);
        expect($data['channel'])->toBe('broadcast');
    });

    it('returns success with correct structure', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'subject' => 'Structure Test',
            'body' => 'Checking keys',
        ]);

        $data = json_decode($result, true);

        expect($data)->toHaveKeys(['status', 'recipients', 'channel', 'subject', 'sent_at']);
    });
});

describe('output format', function () {
    it('returns valid JSON on success', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'subject' => 'JSON Test',
            'body' => 'Valid JSON output',
        ]);

        expect(json_decode($result, true))->not->toBeNull();
    });

    it('includes sent_at timestamp', function () {
        $user = User::factory()->create();
        Notification::fake();

        $result = $this->tool->execute([
            'user_id' => $user->id,
            'subject' => 'Timestamp Test',
            'body' => 'Check sent_at field',
        ]);

        $data = json_decode($result, true);
        expect($data['sent_at'])->not->toBeEmpty();
    });
});
