<?php
// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

/**
 * Shared help content explaining the model table controls (★ default, ☑ available, costs).
 * Included in both the main Providers page and the ProviderSetup page-header help slot.
 */
?>
<div>
    <p class="font-medium text-ink">{{ __('Default model') }}</p>
    <ul class="list-disc list-inside space-y-1 text-muted mt-1">
        <li>{{ __('Each provider has a default model, marked with a') }} <span class="text-accent">★</span> {{ __('star icon.') }}</li>
        <li>{{ __('The default model is used as the fallback when a Agent does not specify a particular model.') }}</li>
        <li>{{ __('Click the ☆ next to a model to set it as the default. The current default is marked with') }} <span class="text-accent">★</span>.</li>
    </ul>
</div>

<div>
    <p class="font-medium text-ink">{{ __('Model availability') }}</p>
    <ul class="list-disc list-inside space-y-1 text-muted mt-1">
        <li>{{ __('Use the checkbox in the Available column to control which models Agents can use.') }}</li>
        <li>{{ __('Unchecked models remain registered but are not offered to Agents.') }}</li>
    </ul>
</div>

<div>
    <p class="font-medium text-ink">{{ __('Costs & billing') }}</p>
    <ul class="list-disc list-inside space-y-1 text-muted mt-1">
        <li>{{ __('API providers (OpenAI, Anthropic, etc.) bill per token used — costs are shown per 1M tokens.') }}</li>
        <li>{{ __('Subscription providers (GitHub Copilot) are included in your subscription at no extra per-token cost.') }}</li>
        <li>{{ __('Local providers (Ollama, vLLM) run on your own hardware and have no API fees.') }}</li>
        <li>{{ __('Click any cost cell to override the catalog default for that model.') }}</li>
    </ul>
</div>
