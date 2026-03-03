<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderModel extends Model
{
    /**
     * @var string
     */
    protected $table = 'ai_provider_models';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_provider_id',
        'model_name',
        'display_name',
        'capability_tags',
        'context_window',
        'max_tokens',
        'is_active',
        'is_default',
        'cost_per_1m',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capability_tags' => 'array',
            'context_window' => 'integer',
            'max_tokens' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'cost_per_1m' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get cost per 1M tokens for a given dimension (input, output, cache_read, cache_write).
     *
     * @param  string  $key  One of: input, output, cache_read, cache_write
     */
    public function getCostPer1m(string $key): ?string
    {
        $cost = $this->cost_per_1m;

        if (! is_array($cost) || ! isset($cost[$key])) {
            return null;
        }

        return $cost[$key] !== null && $cost[$key] !== '' ? (string) $cost[$key] : null;
    }

    /**
     * Get the provider this model belongs to.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    /**
     * Scope to active models only.
     */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to the default model.
     */
    public function scopeDefault($query): void
    {
        $query->where('is_default', true);
    }

    /**
     * Set this model as the default for its provider, unsetting any previous default.
     */
    public function setAsDefault(): void
    {
        self::query()
            ->where('ai_provider_id', $this->ai_provider_id)
            ->where('is_default', true)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Unset this model as default.
     */
    public function unsetDefault(): void
    {
        $this->update(['is_default' => false]);
    }
}
