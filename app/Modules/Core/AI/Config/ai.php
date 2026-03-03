<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

return [
    /*
    |--------------------------------------------------------------------------
    | Digital Worker Workspace Base Path
    |--------------------------------------------------------------------------
    |
    | Root directory for per-Digital Worker workspaces. Each Digital Worker
    | gets a subdirectory named by employee_id containing sessions, memory
    | files, and vector indexes.
    |
    */
    'workspace_path' => env('AI_WORKSPACE_PATH', storage_path('app/workspace')),

    /*
    |--------------------------------------------------------------------------
    | LLM Runtime Defaults
    |--------------------------------------------------------------------------
    |
    | Default runtime parameters when not specified per-model in the DW
    | workspace config. Credentials come from company-scoped AiProvider
    | records, not from env vars.
    |
    */
    'llm' => [
        'max_tokens' => 2048,
        'temperature' => 0.7,
        'timeout' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Templates (Static Catalog)
    |--------------------------------------------------------------------------
    |
    | Provider catalog inspired by OpenClaw (github.com/nicepkg/openclaw).
    | Adapted for BLB's GUI context.
    |
    | Known LLM providers with base URLs and common models. Used by the admin
    | UI for quick-add: selecting a template pre-fills provider fields and
    | offers to import suggested models with approximate pricing.
    |
    | Costs are per 1M tokens (matching the ai_provider_models schema).
    | Prices reflect public API rates and may drift over time — admins can
    | adjust after import.
    |
    | auth_type controls the connect wizard UI behavior:
    |   'api_key'      — Standard API key (base_url + api_key required)
    |   'local'        — Local/self-hosted server (api_key optional)
    |   'oauth'        — OAuth flow required (api_key optional)
    |   'subscription' — Included with subscription (api_key optional)
    |   'device_flow'  — GitHub OAuth device flow (token obtained interactively)
    |   'custom'       — Requires additional configuration
    |
    */
    'provider_templates' => [

        // ─── Major Cloud Providers ──────────────────────────────────────

        'openai' => [
            'display_name' => 'OpenAI',
            'description' => 'GPT-5 series and o4 reasoning models',
            'base_url' => 'https://api.openai.com/v1',
            'api_key_url' => 'https://platform.openai.com/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'gpt-5.2',
                    'display_name' => 'GPT-5.2',
                    'capability_tags' => ['chat', 'vision', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '1.750000', 'output' => '14.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5.2-pro',
                    'display_name' => 'GPT-5.2 Pro',
                    'capability_tags' => ['chat', 'vision', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '21.000000', 'output' => '168.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5',
                    'display_name' => 'GPT-5',
                    'capability_tags' => ['chat', 'vision', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '1.250000', 'output' => '10.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5-mini',
                    'display_name' => 'GPT-5 Mini',
                    'capability_tags' => ['chat', 'vision', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => '0.250000', 'output' => '2.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5-nano',
                    'display_name' => 'GPT-5 Nano',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 128000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => '0.100000', 'output' => '0.400000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-4.1',
                    'display_name' => 'GPT-4.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 1047576,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '2.000000', 'output' => '8.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-4.1-mini',
                    'display_name' => 'GPT-4.1 Mini',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 1047576,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '0.400000', 'output' => '1.600000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-4.1-nano',
                    'display_name' => 'GPT-4.1 Nano',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 1047576,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '0.100000', 'output' => '0.400000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'o4-mini',
                    'display_name' => 'o4 Mini',
                    'capability_tags' => ['reasoning', 'code'],
                    'context_window' => 200000,
                    'max_tokens' => 100000,
                    'cost_per_1m' => ['input' => '1.100000', 'output' => '4.400000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'anthropic' => [
            'display_name' => 'Anthropic',
            'description' => 'Claude Sonnet, Opus, and Haiku models',
            'base_url' => 'https://api.anthropic.com/v1',
            'api_key_url' => 'https://console.anthropic.com/settings/keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'claude-sonnet-4-6-20250227',
                    'display_name' => 'Claude Sonnet 4.6',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => '3.000000', 'output' => '15.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-sonnet-4-5-20250214',
                    'display_name' => 'Claude Sonnet 4.5',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => '3.000000', 'output' => '15.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-haiku-4-5-20250214',
                    'display_name' => 'Claude Haiku 4.5',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '1.000000', 'output' => '5.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-opus-4-6-20250227',
                    'display_name' => 'Claude Opus 4.6',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '5.000000', 'output' => '25.000000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'google' => [
            'display_name' => 'Google AI',
            'description' => 'Gemini 2.5–3.1 series with long context',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
            'api_key_url' => 'https://aistudio.google.com/apikey',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'gemini-3.1-pro-preview',
                    'display_name' => 'Gemini 3.1 Pro',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1000000,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '2.000000', 'output' => '12.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-3-flash-preview',
                    'display_name' => 'Gemini 3 Flash',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1000000,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '0.500000', 'output' => '3.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-2.5-pro',
                    'display_name' => 'Gemini 2.5 Pro',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '1.250000', 'output' => '10.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-2.5-flash',
                    'display_name' => 'Gemini 2.5 Flash',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '0.150000', 'output' => '0.600000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-2.5-flash-lite',
                    'display_name' => 'Gemini 2.5 Flash-Lite',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '0.100000', 'output' => '0.400000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        // ─── AI Platforms ───────────────────────────────────────────────

        'xai' => [
            'display_name' => 'xAI',
            'description' => 'Grok models for reasoning and code',
            'base_url' => 'https://api.x.ai/v1',
            'api_key_url' => 'https://console.x.ai/team/default/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'grok-4',
                    'display_name' => 'Grok 4',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 2000000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => '0.20', 'output' => '0.50', 'cache_read' => '0.05', 'cache_write' => null],
                ],
            ],
        ],

        'mistral' => [
            'display_name' => 'Mistral AI',
            'description' => 'Mistral Large and open-weight models',
            'base_url' => 'https://api.mistral.ai/v1',
            'api_key_url' => 'https://console.mistral.ai/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'mistral-large-latest',
                    'display_name' => 'Mistral Large',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 262144,
                    'max_tokens' => 262144,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'nvidia' => [
            'display_name' => 'NVIDIA NIM',
            'description' => 'NVIDIA-hosted inference — Llama, Nemotron, Mistral',
            'base_url' => 'https://integrate.api.nvidia.com/v1',
            'api_key_url' => 'https://build.nvidia.com/explore/discover',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'nvidia/llama-3.1-nemotron-70b-instruct',
                    'display_name' => 'Llama 3.1 Nemotron 70B Instruct',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'meta/llama-3.3-70b-instruct',
                    'display_name' => 'Llama 3.3 70B Instruct',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'nvidia/mistral-nemo-minitron-8b-8k-instruct',
                    'display_name' => 'Mistral NeMo Minitron 8B Instruct',
                    'capability_tags' => ['chat'],
                    'context_window' => 8192,
                    'max_tokens' => 2048,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        // ─── Chinese Providers ──────────────────────────────────────────

        'minimax' => [
            'display_name' => 'MiniMax',
            'description' => 'MiniMax M2.x series — reasoning and vision',
            'base_url' => 'https://api.minimax.io/anthropic',
            'api_key_url' => 'https://platform.minimaxi.com/user-center/basic-information/interface-key',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'MiniMax-M2.1',
                    'display_name' => 'MiniMax M2.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.300000', 'output' => '1.200000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'MiniMax-M2.1-lightning',
                    'display_name' => 'MiniMax M2.1 Lightning',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.300000', 'output' => '1.200000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'MiniMax-VL-01',
                    'display_name' => 'MiniMax VL 01',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.300000', 'output' => '1.200000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'MiniMax-M2.5',
                    'display_name' => 'MiniMax M2.5',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.300000', 'output' => '1.200000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'MiniMax-M2.5-Lightning',
                    'display_name' => 'MiniMax M2.5 Lightning',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.300000', 'output' => '1.200000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'moonshot' => [
            'display_name' => 'Moonshot AI',
            'description' => 'Kimi K2.5 — large context, vision, free tier',
            'base_url' => 'https://api.moonshot.ai/v1',
            'api_key_url' => 'https://platform.moonshot.cn/console/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'kimi-k2.5',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'kimi-coding' => [
            'display_name' => 'Kimi Coding',
            'description' => 'Kimi for Coding — reasoning-first coding agent',
            'base_url' => 'https://api.kimi.com/coding/',
            'api_key_url' => 'https://platform.moonshot.cn/console/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'k2p5',
                    'display_name' => 'Kimi for Coding',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'xiaomi' => [
            'display_name' => 'Xiaomi',
            'description' => 'MiMo V2 Flash — free local reasoning model',
            'base_url' => 'https://api.xiaomimimo.com/anthropic',
            'api_key_url' => 'https://dev.mi.com/xiaomimimo',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'mimo-v2-flash',
                    'display_name' => 'MiMo V2 Flash',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'qwen-portal' => [
            'display_name' => 'Qwen (Portal)',
            'description' => 'Alibaba Qwen models — requires OAuth login',
            'base_url' => 'https://portal.qwen.ai/v1',
            'api_key_url' => null,
            'auth_type' => 'oauth',
            'models' => [
                [
                    'model_name' => 'coder-model',
                    'display_name' => 'Qwen Coder',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'vision-model',
                    'display_name' => 'Qwen Vision',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'qianfan' => [
            'display_name' => 'Baidu Qianfan',
            'description' => 'DeepSeek and ERNIE models via Baidu Cloud',
            'base_url' => 'https://qianfan.baidubce.com/v2',
            'api_key_url' => 'https://console.bce.baidu.com/qianfan/ais/console/applicationConsole/application',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'deepseek-v3.2',
                    'display_name' => 'DeepSeek V3.2',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 98304,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'ernie-5.0-thinking-preview',
                    'display_name' => 'ERNIE 5.0 Thinking',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 119000,
                    'max_tokens' => 64000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'zai' => [
            'display_name' => 'Z.AI (GLM)',
            'description' => 'GLM-5 and GLM-4.7 series — free coding plan available',
            'base_url' => 'https://api.z.ai/api/paas/v4',
            'api_key_url' => 'https://open.bigmodel.cn/usercenter/apikeys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'glm-5',
                    'display_name' => 'GLM-5',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4.7',
                    'display_name' => 'GLM-4.7',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4.7-flash',
                    'display_name' => 'GLM-4.7 Flash',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4.7-flashx',
                    'display_name' => 'GLM-4.7 FlashX',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'volcengine' => [
            'display_name' => 'Volcano Engine',
            'description' => 'ByteDance Doubao and hosted models (China)',
            'base_url' => 'https://ark.cn-beijing.volces.com/api/v3',
            'api_key_url' => 'https://console.volcengine.com/ark/region:ark+cn-beijing/apiKey',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'doubao-seed-code-preview-251028',
                    'display_name' => 'Doubao Seed Code Preview',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'doubao-seed-1-8-251228',
                    'display_name' => 'Doubao Seed 1.8',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'kimi-k2-5-260127',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4-7-251222',
                    'display_name' => 'GLM 4.7',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'deepseek-v3-2-251201',
                    'display_name' => 'DeepSeek V3.2',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 128000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'byteplus' => [
            'display_name' => 'BytePlus ARK',
            'description' => 'BytePlus-hosted models (International)',
            'base_url' => 'https://ark.ap-southeast.bytepluses.com/api/v3',
            'api_key_url' => 'https://console.byteplus.com/ark/region:ark+ap-southeast-1/apiKey',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'seed-1-8-251228',
                    'display_name' => 'Seed 1.8',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'kimi-k2-5-260127',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 256000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4-7-251222',
                    'display_name' => 'GLM 4.7',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 4096,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        // ─── Aggregators & Gateways ─────────────────────────────────────

        'openrouter' => [
            'display_name' => 'OpenRouter',
            'description' => 'Multi-provider gateway — access 200+ models',
            'base_url' => 'https://openrouter.ai/api/v1',
            'api_key_url' => 'https://openrouter.ai/keys',
            'auth_type' => 'api_key',
            'models' => [],
        ],

        'together' => [
            'display_name' => 'Together AI',
            'description' => 'Open-source model hosting — Llama, DeepSeek, Qwen',
            'base_url' => 'https://api.together.xyz/v1',
            'api_key_url' => 'https://api.together.xyz/settings/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'zai-org/GLM-4.7',
                    'display_name' => 'GLM 4.7 Fp8',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.450000', 'output' => '2.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'moonshotai/Kimi-K2.5',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '0.500000', 'output' => '2.800000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
                    'display_name' => 'Llama 3.3 70B Instruct Turbo',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.880000', 'output' => '0.880000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'meta-llama/Llama-4-Scout-17B-16E-Instruct',
                    'display_name' => 'Llama 4 Scout 17B 16E Instruct',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 10000000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '0.180000', 'output' => '0.590000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'meta-llama/Llama-4-Maverick-17B-128E-Instruct-FP8',
                    'display_name' => 'Llama 4 Maverick 17B 128E Instruct FP8',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 20000000,
                    'max_tokens' => 32768,
                    'cost_per_1m' => ['input' => '0.270000', 'output' => '0.850000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'deepseek-ai/DeepSeek-V3.1',
                    'display_name' => 'DeepSeek V3.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.600000', 'output' => '1.250000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'deepseek-ai/DeepSeek-R1',
                    'display_name' => 'DeepSeek R1',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '3.000000', 'output' => '7.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'moonshotai/Kimi-K2-Instruct-0905',
                    'display_name' => 'Kimi K2 Instruct 0905',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '1.000000', 'output' => '3.000000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'huggingface' => [
            'display_name' => 'Hugging Face',
            'description' => 'Inference Providers — OpenAI-compatible chat',
            'base_url' => 'https://router.huggingface.co/v1',
            'api_key_url' => 'https://huggingface.co/settings/tokens',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'deepseek-ai/DeepSeek-R1',
                    'display_name' => 'DeepSeek R1',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '3.000000', 'output' => '7.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'deepseek-ai/DeepSeek-V3.1',
                    'display_name' => 'DeepSeek V3.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.600000', 'output' => '1.250000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
                    'display_name' => 'Llama 3.3 70B Instruct Turbo',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => '0.880000', 'output' => '0.880000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'openai/gpt-oss-120b',
                    'display_name' => 'GPT-OSS 120B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'kilocode' => [
            'display_name' => 'Kilo Gateway',
            'description' => 'Multi-model gateway — Claude, GPT, Gemini, Grok',
            'base_url' => 'https://api.kilo.ai/api/gateway/',
            'api_key_url' => 'https://kilo.ai/settings/api-keys',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'anthropic/claude-opus-4.6',
                    'display_name' => 'Claude Opus 4.6',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1000000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'z-ai/glm-5:free',
                    'display_name' => 'GLM-5 (Free)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 202800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'minimax/minimax-m2.5:free',
                    'display_name' => 'MiniMax M2.5 (Free)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'anthropic/claude-sonnet-4.5',
                    'display_name' => 'Claude Sonnet 4.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1000000,
                    'max_tokens' => 64000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'openai/gpt-5.2',
                    'display_name' => 'GPT-5.2',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'google/gemini-3-pro-preview',
                    'display_name' => 'Gemini 3 Pro Preview',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'google/gemini-3-flash-preview',
                    'display_name' => 'Gemini 3 Flash Preview',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65535,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'x-ai/grok-code-fast-1',
                    'display_name' => 'Grok Code Fast 1',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 256000,
                    'max_tokens' => 10000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'moonshotai/kimi-k2.5',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 65535,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'opencode-zen' => [
            'display_name' => 'OpenCode Zen',
            'description' => 'Pay-as-you-go multi-model proxy — Claude, GPT, Gemini',
            'base_url' => 'https://opencode.ai/zen/v1',
            'api_key_url' => 'https://opencode.ai/auth',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'gpt-5.1-codex',
                    'display_name' => 'GPT-5.1 Codex',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '1.070000', 'output' => '8.500000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-opus-4-6',
                    'display_name' => 'Claude Opus 4.6',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1000000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '5.000000', 'output' => '25.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-opus-4-5',
                    'display_name' => 'Claude Opus 4.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 64000,
                    'cost_per_1m' => ['input' => '5.000000', 'output' => '25.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-3-pro',
                    'display_name' => 'Gemini 3 Pro',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '2.000000', 'output' => '12.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5.1-codex-mini',
                    'display_name' => 'GPT-5.1 Codex Mini',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '0.250000', 'output' => '2.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5.1',
                    'display_name' => 'GPT-5.1',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '1.070000', 'output' => '8.500000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'glm-4.7',
                    'display_name' => 'GLM-4.7',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 204800,
                    'max_tokens' => 131072,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-3-flash',
                    'display_name' => 'Gemini 3 Flash',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => '0.500000', 'output' => '3.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5.1-codex-max',
                    'display_name' => 'GPT-5.1 Codex Max',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '1.250000', 'output' => '10.000000', 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gpt-5.2',
                    'display_name' => 'GPT-5.2',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 400000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => '1.750000', 'output' => '14.000000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'synthetic' => [
            'display_name' => 'Synthetic',
            'description' => 'Anthropic-compatible multi-model inference — free',
            'base_url' => 'https://api.synthetic.new/anthropic',
            'api_key_url' => 'https://synthetic.new/dashboard',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'hf:MiniMaxAI/MiniMax-M2.1',
                    'display_name' => 'MiniMax M2.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 192000,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:moonshotai/Kimi-K2-Thinking',
                    'display_name' => 'Kimi K2 Thinking',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:zai-org/GLM-4.7',
                    'display_name' => 'GLM-4.7',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 198000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-R1-0528',
                    'display_name' => 'DeepSeek R1 0528',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-V3-0324',
                    'display_name' => 'DeepSeek V3 0324',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-V3.1',
                    'display_name' => 'DeepSeek V3.1',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-V3.1-Terminus',
                    'display_name' => 'DeepSeek V3.1 Terminus',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-V3.2',
                    'display_name' => 'DeepSeek V3.2',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 159000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:meta-llama/Llama-3.3-70B-Instruct',
                    'display_name' => 'Llama 3.3 70B Instruct',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:meta-llama/Llama-4-Maverick-17B-128E-Instruct-FP8',
                    'display_name' => 'Llama 4 Maverick 17B 128E Instruct FP8',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 524000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:moonshotai/Kimi-K2-Instruct-0905',
                    'display_name' => 'Kimi K2 Instruct 0905',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:moonshotai/Kimi-K2.5',
                    'display_name' => 'Kimi K2.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:openai/gpt-oss-120b',
                    'display_name' => 'GPT OSS 120B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:Qwen/Qwen3-235B-A22B-Instruct-2507',
                    'display_name' => 'Qwen3 235B A22B Instruct 2507',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:Qwen/Qwen3-Coder-480B-A35B-Instruct',
                    'display_name' => 'Qwen3 Coder 480B A35B Instruct',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:Qwen/Qwen3-VL-235B-A22B-Instruct',
                    'display_name' => 'Qwen3 VL 235B A22B Instruct',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 250000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:zai-org/GLM-4.5',
                    'display_name' => 'GLM-4.5',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:zai-org/GLM-4.6',
                    'display_name' => 'GLM-4.6',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 198000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:zai-org/GLM-5',
                    'display_name' => 'GLM-5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 256000,
                    'max_tokens' => 128000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:deepseek-ai/DeepSeek-V3',
                    'display_name' => 'DeepSeek V3',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hf:Qwen/Qwen3-235B-A22B-Thinking-2507',
                    'display_name' => 'Qwen3 235B A22B Thinking 2507',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 256000,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'venice' => [
            'display_name' => 'Venice AI',
            'description' => 'Privacy-focused inference — uncensored + proxied models',
            'base_url' => 'https://api.venice.ai/api/v1',
            'api_key_url' => 'https://venice.ai/settings/api',
            'auth_type' => 'api_key',
            'models' => [
                [
                    'model_name' => 'llama-3.3-70b',
                    'display_name' => 'Llama 3.3 70B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'llama-3.2-3b',
                    'display_name' => 'Llama 3.2 3B',
                    'capability_tags' => ['chat'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'hermes-3-llama-3.1-405b',
                    'display_name' => 'Hermes 3 Llama 3.1 405B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-235b-a22b-thinking-2507',
                    'display_name' => 'Qwen3 235B Thinking',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-235b-a22b-instruct-2507',
                    'display_name' => 'Qwen3 235B Instruct',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-coder-480b-a35b-instruct',
                    'display_name' => 'Qwen3 Coder 480B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-next-80b',
                    'display_name' => 'Qwen3 Next 80B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-vl-235b-a22b',
                    'display_name' => 'Qwen3 VL 235B (Vision)',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'qwen3-4b',
                    'display_name' => 'Venice Small (Qwen3 4B)',
                    'capability_tags' => ['chat', 'reasoning'],
                    'context_window' => 32768,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'deepseek-v3.2',
                    'display_name' => 'DeepSeek V3.2',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 163840,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'venice-uncensored',
                    'display_name' => 'Venice Uncensored (Dolphin-Mistral)',
                    'capability_tags' => ['chat'],
                    'context_window' => 32768,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'mistral-31-24b',
                    'display_name' => 'Venice Medium (Mistral)',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'google-gemma-3-27b-it',
                    'display_name' => 'Google Gemma 3 27B Instruct',
                    'capability_tags' => ['chat', 'vision'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'openai-gpt-oss-120b',
                    'display_name' => 'GPT OSS 120B',
                    'capability_tags' => ['chat', 'code'],
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'zai-org-glm-4.7',
                    'display_name' => 'GLM 4.7',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-opus-45',
                    'display_name' => 'Claude Opus 4.5 (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-sonnet-45',
                    'display_name' => 'Claude Sonnet 4.5 (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'openai-gpt-52',
                    'display_name' => 'GPT-5.2 (via Venice)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'openai-gpt-52-codex',
                    'display_name' => 'GPT-5.2 Codex (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-3-pro-preview',
                    'display_name' => 'Gemini 3 Pro (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-3-flash-preview',
                    'display_name' => 'Gemini 3 Flash (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'grok-41-fast',
                    'display_name' => 'Grok 4.1 Fast (via Venice)',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'grok-code-fast-1',
                    'display_name' => 'Grok Code Fast 1 (via Venice)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'kimi-k2-thinking',
                    'display_name' => 'Kimi K2 Thinking (via Venice)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 262144,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'minimax-m21',
                    'display_name' => 'MiniMax M2.1 (via Venice)',
                    'capability_tags' => ['chat', 'code', 'reasoning'],
                    'context_window' => 202752,
                    'max_tokens' => 8192,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'cloudflare-ai-gateway' => [
            'display_name' => 'Cloudflare AI Gateway',
            'description' => 'Anthropic proxy via Cloudflare — requires Account ID + Gateway ID',
            'base_url' => 'https://gateway.ai.cloudflare.com/v1',
            'api_key_url' => 'https://dash.cloudflare.com/',
            'auth_type' => 'custom',
            'models' => [
                [
                    'model_name' => 'claude-sonnet-4-5',
                    'display_name' => 'Claude Sonnet 4.5',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 64000,
                    'cost_per_1m' => ['input' => '3.000000', 'output' => '15.000000', 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        // ─── Subscription & Special ─────────────────────────────────────

        'github-copilot' => [
            'display_name' => 'GitHub Copilot',
            'description' => 'GitHub device login — requires Copilot subscription',
            'base_url' => 'https://api.individual.githubcopilot.com',
            'api_key_url' => 'https://github.com/settings/copilot',
            'auth_type' => 'device_flow',
            'models' => [
                [
                    'model_name' => 'gpt-5-mini',
                    'display_name' => 'GPT-5 Mini',
                    'capability_tags' => ['chat', 'vision', 'code', 'reasoning'],
                    'context_window' => 200000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'claude-sonnet-4',
                    'display_name' => 'Claude Sonnet 4',
                    'capability_tags' => ['chat', 'code', 'vision'],
                    'context_window' => 200000,
                    'max_tokens' => 16384,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'o4-mini',
                    'display_name' => 'o4 Mini',
                    'capability_tags' => ['reasoning', 'code'],
                    'context_window' => 200000,
                    'max_tokens' => 100000,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
                [
                    'model_name' => 'gemini-2.5-pro',
                    'display_name' => 'Gemini 2.5 Pro',
                    'capability_tags' => ['chat', 'code', 'vision', 'reasoning'],
                    'context_window' => 1048576,
                    'max_tokens' => 65536,
                    'cost_per_1m' => ['input' => null, 'output' => null, 'cache_read' => null, 'cache_write' => null],
                ],
            ],
        ],

        'copilot-proxy' => [
            'display_name' => 'Copilot Proxy',
            'description' => 'Local proxy for VS Code Copilot models — requires Copilot Proxy extension',
            'base_url' => 'http://localhost:1337/v1',
            'api_key_url' => null,
            'auth_type' => 'local',
            'models' => [],
        ],

        'chutes' => [
            'display_name' => 'Chutes',
            'description' => 'GPU marketplace — requires OAuth authentication',
            'base_url' => 'https://api.chutes.ai/v1',
            'api_key_url' => null,
            'auth_type' => 'oauth',
            'models' => [],
        ],

        // ─── Local / Self-hosted ────────────────────────────────────────

        'ollama' => [
            'display_name' => 'Ollama (Local)',
            'description' => 'Self-hosted local inference — free and private',
            'base_url' => 'http://localhost:11434/v1',
            'api_key_url' => null,
            'auth_type' => 'local',
            'models' => [],
        ],

        'vllm' => [
            'display_name' => 'vLLM (Local)',
            'description' => 'Self-hosted OpenAI-compatible server — models discovered at runtime',
            'base_url' => 'http://127.0.0.1:8000/v1',
            'api_key_url' => null,
            'auth_type' => 'local',
            'models' => [],
        ],

        'litellm' => [
            'display_name' => 'LiteLLM (Local)',
            'description' => 'Unified LLM gateway — proxy for 100+ providers',
            'base_url' => 'http://localhost:4000',
            'api_key_url' => null,
            'auth_type' => 'local',
            'models' => [],
        ],
    ],
];
