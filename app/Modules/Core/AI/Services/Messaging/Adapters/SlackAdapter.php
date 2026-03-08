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
 * Slack channel adapter stub.
 *
 * Provides the contract skeleton for Slack Web API integration.
 * All send methods return failure results until the channel integration
 * is fully configured with webhook routing and DB-backed accounts.
 */
class SlackAdapter implements ChannelAdapter
{
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('ai.tools.messaging.channels.slack.enabled', false);
    }

    public function channelId(): string
    {
        return 'slack';
    }

    public function label(): string
    {
        return 'Slack';
    }

    public function resolveAccount(int $companyId, ?string $accountId = null): ?ChannelAccount
    {
        return null;
    }

    public function sendText(ChannelAccount $account, string $target, string $text, array $options = []): SendResult
    {
        return SendResult::fail('Slack adapter is not yet configured. Channel integration pending.');
    }

    public function sendMedia(ChannelAccount $account, string $target, string $mediaPath, ?string $caption = null): SendResult
    {
        return SendResult::fail('Slack adapter is not yet configured. Channel integration pending.');
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
            supportsThreads: true,
            supportsMedia: true,
            supportsSearch: true,
            maxMessageLength: 40000,
            mediaTypes: ['image', 'document'],
        );
    }
}
