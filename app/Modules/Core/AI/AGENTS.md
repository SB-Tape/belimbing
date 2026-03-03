# AI Module — Agent Guidelines

## Module Context

The AI module manages LLM provider configuration, Digital Worker runtime, and the provider catalog for BLB.

**Key files:**
- `Config/ai.php` — Provider templates (static catalog), LLM runtime defaults
- `Models/AiProvider.php` — Company-scoped provider credentials (encrypted API key)
- `Models/AiProviderModel.php` — Model registry per provider (costs, context window, max tokens, capability tags)
- `Services/ConfigResolver.php` — Resolves LLM config cascade: DW workspace → company provider → runtime defaults
- `Services/DigitalWorkerRuntime.php` — Executes LLM calls with ordered fallback
- `Services/SessionManager.php` / `Services/MessageManager.php` — Workspace-based session and message storage

## Provider Catalog Maintenance

The provider catalog in `Config/ai.php` under `provider_templates` is a **static snapshot** of known LLM providers and their models, inspired by [OpenClaw](https://github.com/nicepkg/openclaw)'s model catalog. It serves as a quick-start for admins.

### When to update the catalog

- **New model released** — Add to the appropriate provider's `models` array
- **Model deprecated** — Remove from the catalog (existing DB records are unaffected)
- **Pricing changed** — Update `cost_per_1m` (keys: `input`, `output`, `cache_read`, `cache_write`)
- **New provider** — Add a new entry under `provider_templates` with `display_name`, `base_url`, `description`, `api_key_url`, and `models`
- **Context window or max tokens changed** — Update `context_window` and `max_tokens`

### Per-model fields

| Field | Type | Description |
|-------|------|-------------|
| `model_name` | string | API model identifier (e.g. `gpt-5.2`) |
| `display_name` | string | Human-readable name (e.g. `GPT-5.2`) |
| `capability_tags` | string[] | `chat`, `code`, `vision`, `reasoning` |
| `context_window` | int | Input context window in tokens |
| `max_tokens` | int | Maximum output tokens |
| `cost_per_1m` | array\|null | Cost per 1M tokens in USD: `{ input, output, cache_read, cache_write }` |

### Per-provider fields

| Field | Type | Description |
|-------|------|-------------|
| `display_name` | string | Human-readable provider name |
| `base_url` | string | OpenAI-compatible API base URL |
| `description` | string | One-liner for catalog UI |
| `api_key_url` | string\|null | URL where admins obtain API keys (null for local/OAuth providers) |
| `auth_type` | string | Connect wizard behavior: `api_key`, `local`, `oauth`, `subscription`, `custom` |
| `models` | array | Array of model definitions (empty for discovery-based providers) |

### auth_type values

| Value | Behavior | API Key | Examples |
|-------|----------|---------|----------|
| `api_key` | Standard API key auth | Required | OpenAI, Anthropic, Google, most providers |
| `local` | Local/self-hosted server | Optional | Ollama, vLLM, LiteLLM, Copilot Proxy |
| `oauth` | OAuth flow required | Optional | Qwen Portal, Chutes |
| `subscription` | Included with subscription | Optional | (reserved) |
| `device_flow` | GitHub OAuth device flow | Auto (token) | GitHub Copilot |
| `custom` | Needs additional config | Required | Cloudflare AI Gateway (Account ID + Gateway ID) |

### Relationship to OpenClaw

BLB's provider catalog was initially seeded from OpenClaw's `models-config.providers.ts` model definitions (context windows, max tokens, capability flags). When updating, cross-reference OpenClaw's catalog for new providers and model specs, but adapt pricing and parameters to BLB's per-1M-token schema.

### Important conventions

- Costs are **per 1M tokens** — matching OpenClaw's convention. Stored in `cost_per_1m` JSON: `input`, `output`, `cache_read`, `cache_write`.
- `capability_tags` use lowercase strings: `chat`, `code`, `vision`, `reasoning`
- Providers with dynamic model discovery (Ollama, OpenRouter) have empty `models` arrays
- `api_key_url` is `null` for local/free providers (Ollama)
- GitHub Copilot model costs are `null` (included in subscription)

## Database Schema

- `ai_providers` — Company-scoped, encrypted `api_key`, unique on `(company_id, name)`
- `ai_provider_models` — Per-provider models with `context_window`, `max_tokens`, `cost_per_1m` JSON, `capability_tags` JSON
- Migration prefix: `0200_02_01_*` (AI module block)

## UI Pages

- `resources/views/livewire/ai/providers.blade.php` — Provider catalog wizard + CRUD management
- `resources/views/livewire/ai/playground.blade.php` — DW chat playground + LLM config assignment
