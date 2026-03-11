<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Services;

use App\Modules\Core\AI\DTO\Message;

/**
 * Builds OpenAI-compatible message payloads from BLB runtime messages.
 */
class RuntimeMessageBuilder
{
    /**
     * Build the messages array for the OpenAI API.
     *
     * @param  list<Message>  $messages
     * @return list<array{role: string, content: string}>
     */
    public function build(array $messages, ?string $systemPrompt): array
    {
        $apiMessages = [];

        if ($systemPrompt !== null) {
            $apiMessages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $message) {
            if ($message->role === 'user' || $message->role === 'assistant') {
                $apiMessages[] = [
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            }
        }

        return $apiMessages;
    }
}
