<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\AI\Livewire\Concerns;

use App\Modules\Core\AI\Models\AiProviderModel;

/**
 * Model CRUD state and actions for the provider manager component.
 *
 * Handles add/edit/delete of per-provider model entries, including
 * cost overrides and default model selection.
 */
trait ManagesModels
{
    private const ZERO_COST = '0.000000';

    public bool $showModelForm = false;

    public bool $isEditingModel = false;

    public ?int $editingModelId = null;

    public ?int $modelProviderId = null;

    public string $modelModelName = '';

    public bool $modelIsActive = true;

    public string $modelCostInput = self::ZERO_COST;

    public string $modelCostOutput = self::ZERO_COST;

    public string $modelCostCacheRead = self::ZERO_COST;

    public string $modelCostCacheWrite = self::ZERO_COST;

    public bool $showDeleteModel = false;

    public ?int $deletingModelId = null;

    public string $deletingModelName = '';

    public function openCreateModel(int $providerId): void
    {
        $this->resetModelForm();
        $this->isEditingModel = false;
        $this->modelProviderId = $providerId;
        $this->showModelForm = true;
    }

    public function openEditModel(int $modelId): void
    {
        $model = AiProviderModel::query()->find($modelId);

        if (! $model) {
            return;
        }

        $this->resetModelForm();
        $this->isEditingModel = true;
        $this->editingModelId = $modelId;
        $this->modelProviderId = $model->ai_provider_id;
        $this->modelModelName = $model->model_id;
        $this->modelIsActive = $model->is_active;
        $cost = $model->cost_override ?? [];
        $this->modelCostInput = $cost['input'] ?? self::ZERO_COST;
        $this->modelCostOutput = $cost['output'] ?? self::ZERO_COST;
        $this->modelCostCacheRead = $cost['cache_read'] ?? self::ZERO_COST;
        $this->modelCostCacheWrite = $cost['cache_write'] ?? self::ZERO_COST;
        $this->showModelForm = true;
    }

    public function saveModel(): void
    {
        if ($this->modelProviderId === null) {
            return;
        }

        $this->validate([
            'modelModelName' => ['required', 'string', 'max:255'],
            'modelIsActive' => ['boolean'],
            'modelCostInput' => ['nullable', 'numeric', 'min:0'],
            'modelCostOutput' => ['nullable', 'numeric', 'min:0'],
            'modelCostCacheRead' => ['nullable', 'numeric', 'min:0'],
            'modelCostCacheWrite' => ['nullable', 'numeric', 'min:0'],
        ]);

        $costOverride = [
            'input' => $this->modelCostInput ?: null,
            'output' => $this->modelCostOutput ?: null,
            'cache_read' => $this->modelCostCacheRead ?: null,
            'cache_write' => $this->modelCostCacheWrite ?: null,
        ];
        $hasAnyCost = array_filter($costOverride, fn ($v) => $v !== null && $v !== '') !== [];

        $data = [
            'ai_provider_id' => $this->modelProviderId,
            'model_id' => $this->modelModelName,
            'is_active' => $this->modelIsActive,
            'cost_override' => $hasAnyCost ? $costOverride : null,
        ];

        if ($this->isEditingModel && $this->editingModelId) {
            $model = AiProviderModel::query()->find($this->editingModelId);

            if ($model) {
                unset($data['model_id']);
                $model->update($data);
            }
        } else {
            AiProviderModel::query()->create($data);
        }

        $this->showModelForm = false;
        $this->resetModelForm();
    }

    public function confirmDeleteModel(int $modelId): void
    {
        $model = AiProviderModel::query()->find($modelId);

        if (! $model) {
            return;
        }

        $this->deletingModelId = $modelId;
        $this->deletingModelName = $model->model_id;
        $this->showDeleteModel = true;
    }

    public function deleteModel(): void
    {
        if ($this->deletingModelId === null) {
            return;
        }

        AiProviderModel::query()->where('id', $this->deletingModelId)->delete();

        $this->showDeleteModel = false;
        $this->deletingModelId = null;
        $this->deletingModelName = '';
    }

    /**
     * Set a model as the default for its provider.
     */
    public function setDefaultModel(int $modelId): void
    {
        $model = AiProviderModel::query()->find($modelId);

        if (! $model) {
            return;
        }

        $model->setAsDefault();
    }

    private function resetModelForm(): void
    {
        $this->editingModelId = null;
        $this->modelProviderId = null;
        $this->modelModelName = '';
        $this->modelIsActive = true;
        $this->modelCostInput = self::ZERO_COST;
        $this->modelCostOutput = self::ZERO_COST;
        $this->modelCostCacheRead = self::ZERO_COST;
        $this->modelCostCacheWrite = self::ZERO_COST;
        $this->resetValidation();
    }
}
