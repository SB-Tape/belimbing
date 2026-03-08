<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services\Messaging\Adapters;

use App\Modules\Core\AI\Contracts\Messaging\ChannelAdapter;
use App\Modules\Core\AI\DTO\Messaging\ChannelAccount;
use App\Modules\Core\AI\DTO\Messaging\ChannelCapabilities;
use App\Modules\Core\AI\DTO\Messaging\InboundMessage;
use App\Modules\Core\AI\DTO\Messaging\SendResult;
use Illuminate\Http\Request;

/**
 * Telegram channel adapter stub.
 *
 * Provides the contract skeleton for Telegram Bot API integration.
 * All send methods return failure results until the channel integration
 * is fully configured with webhook routing and DB-backed accounts.
 */
class TelegramAdapter implements ChannelAdapter
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('ai.tools.messaging.channels.telegram.enabled', false);
    }

    public function channelId(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function resolveAccount(int $companyId, ?string $accountId = null): ?ChannelAccount
    {
        return null;
    }

    public function sendText(ChannelAccount $account, string $target, string $text, array $options = []): SendResult
    {
        return SendResult::fail('Telegram adapter is not yet configured. Channel integration pending.');
    }

    public function sendMedia(ChannelAccount $account, string $target, string $mediaPath, ?string $caption = null): SendResult
    {
        return SendResult::fail('Telegram adapter is not yet configured. Channel integration pending.');
    }

    public function parseInbound(Request $request): ?InboundMessage
    {
        return null;
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(
            supportsReactions: true,
            supportsEditing: true,
            supportsDeletion: true,
            supportsPolls: true,
            supportsMedia: true,
            maxMessageLength: 4096,
            mediaTypes: ['image', 'document', 'audio', 'video'],
        );
    }
}
