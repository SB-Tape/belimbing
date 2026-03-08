<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use App\Modules\Core\User\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Notification sending tool for Digital Workers.
 *
 * Sends notifications to BLB users via Laravel's notification system
 * (database or broadcast channels). Supports sending to a specific user
 * by ID or broadcasting to all users in the authenticated user's company.
 *
 * Gated by `ai.tool_notification.execute` authz capability.
 */
class NotificationTool implements DigitalWorkerTool
{
    /**
     * Maximum length for the notification subject.
     */
    private const MAX_SUBJECT_LENGTH = 255;

    /**
     * Maximum length for the notification body.
     */
    private const MAX_BODY_LENGTH = 5000;

    /**
     * Allowed notification channels.
     *
     * @var list<string>
     */
    private const CHANNELS = ['database', 'broadcast'];

    public function name(): string
    {
        return 'notification';
    }

    public function description(): string
    {
        return 'Send a notification to a BLB user or all users in the current company. '
            .'Supports database and broadcast channels. '
            .'Use this when the user asks to notify someone, send an alert, '
            .'or broadcast a message to the team.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => [
                    'oneOf' => [
                        ['type' => 'integer', 'description' => 'Target user ID.'],
                        ['type' => 'string', 'enum' => ['all'], 'description' => 'Send to all users in the company.'],
                    ],
                    'description' => 'User ID to notify, or "all" to broadcast to all company users.',
                ],
                'channel' => [
                    'type' => 'string',
                    'enum' => self::CHANNELS,
                    'description' => 'Notification channel: "database" (default) or "broadcast".',
                ],
                'subject' => [
                    'type' => 'string',
                    'description' => 'Notification title/subject (max '.self::MAX_SUBJECT_LENGTH.' characters).',
                ],
                'body' => [
                    'type' => 'string',
                    'description' => 'Notification body/content (max '.self::MAX_BODY_LENGTH.' characters).',
                ],
            ],
            'required' => ['user_id', 'subject', 'body'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_notification.execute';
    }

    public function execute(array $arguments): string
    {
        $validationError = $this->validateArguments($arguments);
        if ($validationError !== null) {
            return $validationError;
        }

        $userId = $arguments['user_id'];
        $channel = $arguments['channel'] ?? 'database';
        $subject = trim($arguments['subject']);
        $body = trim($arguments['body']);

        try {
            $users = $this->resolveRecipients($userId);
        } catch (\RuntimeException $e) {
            return 'Error: '.$e->getMessage();
        }

        $notification = $this->buildNotification($subject, $body, $channel);

        try {
            NotificationFacade::send($users, $notification);
        } catch (\Throwable $e) {
            if ($this->isTableMissing($e)) {
                return 'Error: The notifications table does not exist. '
                    .'Run "php artisan notifications:table" and "php artisan migrate" to create it.';
            }

            return 'Error: Failed to send notification — '.$e->getMessage();
        }

        $data = [
            'status' => 'sent',
            'recipients' => count($users),
            'channel' => $channel,
            'subject' => $subject,
            'sent_at' => now()->toIso8601String(),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Validate all input arguments before execution.
     */
    private function validateArguments(array $arguments): ?string
    {
        $userId = $arguments['user_id'] ?? null;

        if ($userId === null) {
            return 'Error: user_id is required.';
        }

        if ($userId !== 'all' && (! is_int($userId) || $userId < 1)) {
            return 'Error: user_id must be a positive integer or the string "all".';
        }

        $channel = $arguments['channel'] ?? 'database';
        if (! is_string($channel) || ! in_array($channel, self::CHANNELS, true)) {
            return 'Error: channel must be one of: '.implode(', ', self::CHANNELS).'.';
        }

        $subject = $arguments['subject'] ?? null;
        if (! is_string($subject) || trim($subject) === '') {
            return 'Error: subject is required and must be a non-empty string.';
        }
        if (mb_strlen($subject) > self::MAX_SUBJECT_LENGTH) {
            return 'Error: subject must not exceed '.self::MAX_SUBJECT_LENGTH.' characters.';
        }

        $body = $arguments['body'] ?? null;
        if (! is_string($body) || trim($body) === '') {
            return 'Error: body is required and must be a non-empty string.';
        }
        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            return 'Error: body must not exceed '.self::MAX_BODY_LENGTH.' characters.';
        }

        return null;
    }

    /**
     * Resolve target users from the user_id argument.
     *
     * @param  int|string  $userId  User ID or "all"
     * @return \Illuminate\Database\Eloquent\Collection<int, User>|array<int, User>
     *
     * @throws \RuntimeException If the user cannot be resolved
     */
    private function resolveRecipients(int|string $userId): \Illuminate\Database\Eloquent\Collection|array
    {
        if ($userId === 'all') {
            return $this->resolveAllCompanyUsers();
        }

        $user = User::query()->find($userId);

        if ($user === null) {
            throw new \RuntimeException('User with ID '.$userId.' not found.');
        }

        return [$user];
    }

    /**
     * Resolve all users in the authenticated user's company.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     *
     * @throws \RuntimeException If no auth user or no company
     */
    private function resolveAllCompanyUsers(): \Illuminate\Database\Eloquent\Collection
    {
        $authUser = Auth::user();

        if (! $authUser instanceof User) {
            throw new \RuntimeException('No authenticated user. Cannot resolve company users.');
        }

        $companyId = $authUser->getCompanyId();

        if ($companyId === null) {
            throw new \RuntimeException('Authenticated user has no company. Cannot broadcast to all.');
        }

        $users = User::query()->where('company_id', $companyId)->get();

        if ($users->isEmpty()) {
            throw new \RuntimeException('No users found in company ID '.$companyId.'.');
        }

        return $users;
    }

    /**
     * Build an anonymous Notification instance for the given content and channel.
     */
    private function buildNotification(string $subject, string $body, string $channel): Notification
    {
        return new class($subject, $body, $channel) extends Notification
        {
            public function __construct(
                private readonly string $subject,
                private readonly string $body,
                private readonly string $channel,
            ) {}

            /**
             * @return list<string>
             */
            public function via(object $notifiable): array
            {
                return [$this->channel];
            }

            /**
             * @return array<string, string>
             */
            public function toArray(object $notifiable): array
            {
                return [
                    'subject' => $this->subject,
                    'body' => $this->body,
                ];
            }

            public function toBroadcast(object $notifiable): BroadcastMessage
            {
                return new BroadcastMessage([
                    'subject' => $this->subject,
                    'body' => $this->body,
                ]);
            }
        };
    }

    /**
     * Check whether a throwable indicates a missing database table.
     */
    private function isTableMissing(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'notifications') && (
            str_contains($message, 'does not exist')
            || str_contains($message, 'no such table')
            || str_contains($message, 'doesn\'t exist')
        );
    }
}
