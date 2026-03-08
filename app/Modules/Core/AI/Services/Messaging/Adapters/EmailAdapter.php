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
 * Email channel adapter stub.
 *
 * Provides the contract skeleton for email-based messaging integration.
 * All send methods return failure results until the channel integration
 * is fully configured with SMTP/IMAP and DB-backed accounts.
 */
class EmailAdapter implements ChannelAdapter
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('ai.tools.messaging.channels.email.enabled', false);
    }

    public function channelId(): string
    {
        return 'email';
    }

    public function label(): string
    {
        return 'Email';
    }

    public function resolveAccount(int $companyId, ?string $accountId = null): ?ChannelAccount
    {
        return null;
    }

    public function sendText(ChannelAccount $account, string $target, string $text, array $options = []): SendResult
    {
        return SendResult::fail('Email adapter is not yet configured. Channel integration pending.');
    }

    public function sendMedia(ChannelAccount $account, string $target, string $mediaPath, ?string $caption = null): SendResult
    {
        return SendResult::fail('Email adapter is not yet configured. Channel integration pending.');
    }

    public function parseInbound(Request $request): ?InboundMessage
    {
        return null;
    }

    public function capabilities(): ChannelCapabilities
    {
        return new ChannelCapabilities(
            supportsMedia: true,
            supportsSearch: true,
            maxMessageLength: 100000,
            mediaTypes: ['image', 'document', 'audio', 'video'],
        );
    }
}
