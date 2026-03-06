<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\DTO;

use DateTimeImmutable;

final readonly class Message
{
    /**
     * @param  'user'|'assistant'|'system'  $role
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public string $role,
        public string $content,
        public DateTimeImmutable $timestamp,
        public ?string $runId = null,
        public array $meta = [],
    ) {}

    /**
     * Create from a decoded JSONL line.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromJsonLine(array $data): self
    {
        return new self(
            role: $data['role'],
            content: $data['content'],
            timestamp: new DateTimeImmutable($data['timestamp']),
            runId: $data['run_id'] ?? null,
            meta: $data['meta'] ?? [],
        );
    }

    /**
     * Serialize to a JSON string for JSONL append.
     */
    public function toJsonLine(): string
    {
        $payload = [
            'role' => $this->role,
            'content' => $this->content,
            'timestamp' => $this->timestamp->format('c'),
        ];

        if (is_string($this->runId)) {
            $payload['run_id'] = $this->runId;
        }

        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
