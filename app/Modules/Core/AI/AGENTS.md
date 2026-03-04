# Core AI Module — Agent Guidelines

## Module Context

Core AI is the **governance layer** for AI in BLB. It manages company-scoped provider configuration, Digital Worker runtime, and sessions. It depends on Base AI (`app/Base/AI/`) for stateless infrastructure (model catalog, LLM client, provider discovery).

**Key files:**
- `Models/AiProvider.php` — Company-scoped provider credentials (encrypted API key, `company_id`/`created_by` FKs)
- `Models/AiProviderModel.php` — Model registry per provider (`model_id`, `is_active`, `is_default`, `cost_override`)
- `Services/ConfigResolver.php` — Resolves LLM config cascade: DW workspace → company provider → runtime defaults
- `Services/DigitalWorkerRuntime.php` — Executes LLM calls with fallback, delegates HTTP to Base `LlmClient`
- `Services/ModelDiscoveryService.php` — Syncs models from live API + catalog enrichment via Base services
- `Services/ProviderAuthFlowService.php` — Company-scoped auth lifecycle (delegates to Base `GithubCopilotAuthService`)
- `Services/SessionManager.php` / `Services/MessageManager.php` — Workspace-based session and message storage

## Dependency on Base AI

Core AI reads catalog data from `app/Base/AI/Services/ModelCatalogService`. It does **not** own the `ai` config key — Base AI does. Core reads `config('ai.*')` from Base.

| Concern | Base AI (stateless) | Core AI (governance) |
|---------|---------------------|---------------------|
| Model catalog | `ModelCatalogService` — fetch, cache, serve | Reads from Base catalog |
| LLM execution | `LlmClient::chat(...)` | `DigitalWorkerRuntime` — config + fallback via Base client |
| Provider discovery | `ProviderDiscoveryService::discoverModels(...)` | `ModelDiscoveryService` — syncs to DB |
| Auth helpers | `GithubCopilotAuthService` | `ProviderAuthFlowService` — company-scoped lifecycle |

## Database Schema

- `ai_providers` — Company-scoped, encrypted `api_key`, unique on `(company_id, name)`
- `ai_provider_models` — Per-provider models: `model_id` (string), `is_active`, `is_default`, `cost_override` (JSON)
- Migration prefix: `0200_02_01_*` (AI module block)
- Catalog fields (name, costs, limits, capabilities) are served from Base `ModelCatalogService`, NOT stored in DB
- `cost_override` allows admin overrides that survive catalog refreshes

### auth_type values (from Base overlay config)

| Value | Behavior | API Key | Examples |
|-------|----------|---------|----------|
| `api_key` | Standard API key auth | Required | OpenAI, Anthropic, Google, most providers |
| `local` | Local/self-hosted server | Optional | Ollama, vLLM, LiteLLM, Copilot Proxy |
| `oauth` | OAuth flow required | Optional | Qwen Portal, Chutes |
| `device_flow` | GitHub OAuth device flow | Auto (token) | GitHub Copilot |
| `custom` | Needs additional config | Required | Cloudflare AI Gateway (Account ID + Gateway ID) |

## UI Pages

- `resources/views/livewire/ai/providers.blade.php` — Provider catalog wizard + CRUD management
- `resources/views/livewire/ai/playground.blade.php` — DW chat playground + LLM config assignment
