<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Tools;

use App\Modules\Core\AI\Contracts\DigitalWorkerTool;
use App\Modules\Core\AI\Services\Messaging\ChannelAdapterRegistry;

/**
 * Multi-channel messaging tool for Digital Workers.
 *
 * Provides enterprise-grade messaging across multiple platforms (WhatsApp,
 * Telegram, Slack, Email) via a single deep tool with action-based dispatch.
 * Each action routes through the ChannelAdapterRegistry to the appropriate
 * platform adapter.
 *
 * Supports sending, replying, reacting, editing, deleting messages, creating
 * polls, listing conversations, and searching message history. Channel
 * capabilities are validated before dispatch — unsupported actions return
 * informative errors.
 *
 * Note: Currently returns stub responses. Full channel integration will be
 * implemented once messaging accounts and webhook infrastructure are deployed.
 *
 * Gated by `ai.tool_message.execute` authz capability.
 * Per-channel send capabilities (e.g., `messaging.whatsapp.send`) are
 * enforced at the authz layer, not within this tool.
 */
class MessageTool implements DigitalWorkerTool
{
    /**
     * Valid actions for messaging.
     *
     * @var list<string>
     */
    private const ACTIONS = [
        'send',
        'reply',
        'react',
        'edit',
        'delete',
        'poll',
        'list_conversations',
        'search',
    ];

    /**
     * Maximum text message length.
     */
    private const MAX_TEXT_LENGTH = 50000;

    /**
     * Maximum number of poll options.
     */
    private const MAX_POLL_OPTIONS = 10;

    public function __construct(
        private readonly ChannelAdapterRegistry $adapterRegistry,
    ) {}

    public function name(): string
    {
        return 'message';
    }

    public function description(): string
    {
        return 'Send and manage messages across channels (WhatsApp, Telegram, Slack, Email). '
            .'Supports sending text/media, replying, reacting, editing, deleting messages, '
            .'creating polls, listing conversations, and searching message history. '
            .'Each action requires a channel parameter to route to the correct platform.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => self::ACTIONS,
                    'description' => 'The messaging action to perform.',
                ],
                'channel' => [
                    'type' => 'string',
                    'description' => 'Channel to use: whatsapp, telegram, slack, email.',
                ],
                'target' => [
                    'type' => 'string',
                    'description' => 'Recipient identifier (phone number, chat ID, email address, channel name).',
                ],
                'text' => [
                    'type' => 'string',
                    'description' => 'Message text content (max '.self::MAX_TEXT_LENGTH.' characters).',
                ],
                'media_path' => [
                    'type' => 'string',
                    'description' => 'Path to media file to attach (for "send" action).',
                ],
                'message_id' => [
                    'type' => 'string',
                    'description' => 'Platform-specific message ID (for reply, react, edit, delete actions).',
                ],
                'emoji' => [
                    'type' => 'string',
                    'description' => 'Emoji to react with (for "react" action).',
                ],
                'question' => [
                    'type' => 'string',
                    'description' => 'Poll question (for "poll" action).',
                ],
                'options' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Poll options (for "poll" action, max '.self::MAX_POLL_OPTIONS.').',
                ],
                'query' => [
                    'type' => 'string',
                    'description' => 'Search query (for "search" action).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximum results to return (for list_conversations and search, default 10).',
                ],
            ],
            'required' => ['action', 'channel'],
        ];
    }

    public function requiredCapability(): ?string
    {
        return 'ai.tool_message.execute';
    }

    public function execute(array $arguments): string
    {
        $action = $arguments['action'] ?? '';

        if (! is_string($action) || ! in_array($action, self::ACTIONS, true)) {
            return 'Error: Invalid action. Must be one of: '.implode(', ', self::ACTIONS).'.';
        }

        $channel = $arguments['channel'] ?? '';

        if (! is_string($channel) || trim($channel) === '') {
            return 'Error: "channel" is required. Available channels: '
                .implode(', ', $this->adapterRegistry->channels()).'.';
        }

        $channel = trim($channel);

        if (! $this->adapterRegistry->isAvailable($channel)) {
            $available = $this->adapterRegistry->channels();

            return 'Error: Channel "'.$channel.'" is not available. '
                .($available !== [] ? 'Available channels: '.implode(', ', $available).'.' : 'No channels are configured.');
        }

        return match ($action) {
            'send' => $this->handleSend($channel, $arguments),
            'reply' => $this->handleReply($channel, $arguments),
            'react' => $this->handleReact($channel, $arguments),
            'edit' => $this->handleEdit($channel, $arguments),
            'delete' => $this->handleDelete($channel, $arguments),
            'poll' => $this->handlePoll($channel, $arguments),
            'list_conversations' => $this->handleListConversations($channel, $arguments),
            'search' => $this->handleSearch($channel, $arguments),
        };
    }

    /**
     * Handle the "send" action.
     *
     * Sends a text or media message to a target recipient.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleSend(string $channel, array $arguments): string
    {
        $target = $arguments['target'] ?? '';

        if (! is_string($target) || trim($target) === '') {
            return 'Error: "target" is required for the send action.';
        }

        $text = $arguments['text'] ?? '';

        if (! is_string($text) || trim($text) === '') {
            return 'Error: "text" is required for the send action.';
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return 'Error: "text" must not exceed '.self::MAX_TEXT_LENGTH.' characters.';
        }

        $adapter = $this->adapterRegistry->resolve($channel);
        $capabilities = $adapter->capabilities();

        if (mb_strlen($text) > $capabilities->maxMessageLength) {
            return 'Error: Message exceeds '.$channel.' limit of '
                .$capabilities->maxMessageLength.' characters.';
        }

        $mediaPath = $arguments['media_path'] ?? null;

        return json_encode([
            'action' => 'send',
            'channel' => $channel,
            'target' => trim($target),
            'text' => trim($text),
            'media_path' => is_string($mediaPath) ? trim($mediaPath) : null,
            'status' => 'sent',
            'message_id' => null,
            'message' => 'Message sent (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "reply" action.
     *
     * Replies to a specific message by its platform message ID.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleReply(string $channel, array $arguments): string
    {
        $messageId = $arguments['message_id'] ?? '';

        if (! is_string($messageId) || trim($messageId) === '') {
            return 'Error: "message_id" is required for the reply action.';
        }

        $text = $arguments['text'] ?? '';

        if (! is_string($text) || trim($text) === '') {
            return 'Error: "text" is required for the reply action.';
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return 'Error: "text" must not exceed '.self::MAX_TEXT_LENGTH.' characters.';
        }

        return json_encode([
            'action' => 'reply',
            'channel' => $channel,
            'message_id' => trim($messageId),
            'text' => trim($text),
            'status' => 'replied',
            'message' => 'Reply sent (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "react" action.
     *
     * Reacts to a message with an emoji.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleReact(string $channel, array $arguments): string
    {
        $adapter = $this->adapterRegistry->resolve($channel);

        if (! $adapter->capabilities()->supportsReactions) {
            return 'Error: '.$channel.' does not support reactions.';
        }

        $messageId = $arguments['message_id'] ?? '';

        if (! is_string($messageId) || trim($messageId) === '') {
            return 'Error: "message_id" is required for the react action.';
        }

        $emoji = $arguments['emoji'] ?? '';

        if (! is_string($emoji) || trim($emoji) === '') {
            return 'Error: "emoji" is required for the react action.';
        }

        return json_encode([
            'action' => 'react',
            'channel' => $channel,
            'message_id' => trim($messageId),
            'emoji' => trim($emoji),
            'status' => 'reacted',
            'message' => 'Reaction added (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "edit" action.
     *
     * Edits a previously sent message.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleEdit(string $channel, array $arguments): string
    {
        $adapter = $this->adapterRegistry->resolve($channel);

        if (! $adapter->capabilities()->supportsEditing) {
            return 'Error: '.$channel.' does not support message editing.';
        }

        $messageId = $arguments['message_id'] ?? '';

        if (! is_string($messageId) || trim($messageId) === '') {
            return 'Error: "message_id" is required for the edit action.';
        }

        $text = $arguments['text'] ?? '';

        if (! is_string($text) || trim($text) === '') {
            return 'Error: "text" is required for the edit action.';
        }

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            return 'Error: "text" must not exceed '.self::MAX_TEXT_LENGTH.' characters.';
        }

        return json_encode([
            'action' => 'edit',
            'channel' => $channel,
            'message_id' => trim($messageId),
            'text' => trim($text),
            'status' => 'edited',
            'message' => 'Message edited (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "delete" action.
     *
     * Deletes a previously sent message.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleDelete(string $channel, array $arguments): string
    {
        $adapter = $this->adapterRegistry->resolve($channel);

        if (! $adapter->capabilities()->supportsDeletion) {
            return 'Error: '.$channel.' does not support message deletion.';
        }

        $messageId = $arguments['message_id'] ?? '';

        if (! is_string($messageId) || trim($messageId) === '') {
            return 'Error: "message_id" is required for the delete action.';
        }

        return json_encode([
            'action' => 'delete',
            'channel' => $channel,
            'message_id' => trim($messageId),
            'status' => 'deleted',
            'message' => 'Message deleted (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "poll" action.
     *
     * Creates a poll in the target conversation.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handlePoll(string $channel, array $arguments): string
    {
        $adapter = $this->adapterRegistry->resolve($channel);

        if (! $adapter->capabilities()->supportsPolls) {
            return 'Error: '.$channel.' does not support polls.';
        }

        $target = $arguments['target'] ?? '';

        if (! is_string($target) || trim($target) === '') {
            return 'Error: "target" is required for the poll action.';
        }

        $question = $arguments['question'] ?? '';

        if (! is_string($question) || trim($question) === '') {
            return 'Error: "question" is required for the poll action.';
        }

        $options = $arguments['options'] ?? [];

        if (! is_array($options) || count($options) < 2) {
            return 'Error: "options" must be an array with at least 2 items.';
        }

        if (count($options) > self::MAX_POLL_OPTIONS) {
            return 'Error: "options" must not exceed '.self::MAX_POLL_OPTIONS.' items.';
        }

        foreach ($options as $option) {
            if (! is_string($option) || trim($option) === '') {
                return 'Error: Each poll option must be a non-empty string.';
            }
        }

        return json_encode([
            'action' => 'poll',
            'channel' => $channel,
            'target' => trim($target),
            'question' => trim($question),
            'options' => array_map('trim', $options),
            'status' => 'created',
            'message' => 'Poll created (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "list_conversations" action.
     *
     * Lists recent conversations on the specified channel.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleListConversations(string $channel, array $arguments): string
    {
        $limit = 10;
        if (isset($arguments['limit']) && is_int($arguments['limit'])) {
            $limit = max(1, min(50, $arguments['limit']));
        }

        return json_encode([
            'action' => 'list_conversations',
            'channel' => $channel,
            'limit' => $limit,
            'conversations' => [],
            'status' => 'listed',
            'message' => 'Conversations listed (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Handle the "search" action.
     *
     * Searches message history across the specified channel.
     *
     * @param  string  $channel  Channel identifier
     * @param  array<string, mixed>  $arguments  Parsed arguments from LLM
     */
    private function handleSearch(string $channel, array $arguments): string
    {
        $adapter = $this->adapterRegistry->resolve($channel);

        if (! $adapter->capabilities()->supportsSearch) {
            return 'Error: '.$channel.' does not support message search.';
        }

        $query = $arguments['query'] ?? '';

        if (! is_string($query) || trim($query) === '') {
            return 'Error: "query" is required for the search action.';
        }

        $limit = 10;
        if (isset($arguments['limit']) && is_int($arguments['limit'])) {
            $limit = max(1, min(50, $arguments['limit']));
        }

        return json_encode([
            'action' => 'search',
            'channel' => $channel,
            'query' => trim($query),
            'limit' => $limit,
            'results' => [],
            'status' => 'searched',
            'message' => 'Search completed (stub). Channel adapter integration pending.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
