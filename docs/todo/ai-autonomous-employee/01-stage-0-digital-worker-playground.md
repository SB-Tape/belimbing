# Stage 0 - Digital Worker Playground (Implementation Checklist)

**Parent Plan:** `docs/todo/ai-autonomous-employee/00-staged-delivery-plan.md`
**Scope:** Web-only Digital Worker chat loop with persistent sessions/messages and visible runtime metadata
**Target Outcome:** A user can open Digital Worker Playground, chat, switch sessions, refresh, and keep full history.
**Prerequisite:** `docs/architecture/authorization.md` and `docs/todo/authorization/00-prd.md` Stage B + Stage D
**Status:** Core implemented; hardening and access guards still in progress
**Last Updated:** 2026-03-04

## 1. Stage 0 Contract

### In Scope
1. Authenticated web UI for chat and session switching
2. Persisted session/message history
3. Basic Digital Worker response runtime (no business write tools)
4. Debug metadata visible in UI (run id, model, latency)
5. Per-DW LLM configuration: company-level provider credentials, per-DW model selection via workspace `config.json`, config resolution cascade
6. Provider management UI for company-level LLM providers and models

### Out of Scope
1. Approval workflow
2. External channels (WhatsApp/Telegram/Slack)
3. Cross-user / Digital Worker-to-Digital Worker orchestration
4. High-risk tool execution
5. Guided DW onboarding wizard (identity → LLM config → authorization → review)

### Digital Worker Chained to Human
Per `docs/architecture/ai-digital-worker.md`: every Digital Worker is an employee with a supervision chain that resolves to a human. In Stage 0, playground sessions belong to a Digital Worker (an employee with `employee_type = 'digital_worker'`); access is scoped by "current user supervises this Digital Worker" (or is the human at the end of the chain).

### Current Snapshot (2026-03-04)

Implemented:
- Playground route and UI (Digital Worker tabs, session list, chat, debug panel)
- File-based sessions/messages in workspace (`.meta.json` + `.jsonl`)
- Per-DW `config.json` with ordered `llm.models[]` fallback chain
- Runtime fallback attempt trace (`fallback_attempts`) shown in debug panel
- LLM Providers management page with provider/model/default/priority controls

Still pending to fully close Stage 0:
- Service-level supervisor guard in `SessionManager` / `MessageManager` (UI scopes access; service-level enforcement still needs hardening)
- Stage 0 feature tests for playground/session isolation flows
- Persist selected session across browser refresh (history persists; active selection persistence still pending)

## 2. UI Deliverables

1. `Digital Worker Playground` page route
2. Left column: Digital Worker selector tabs + session list + "new session" action
3. Main column: chat transcript + composer
4. Right column: debug panel with latest run metadata and collapsible fallback attempt trace

## 3. Data Model Deliverables

### 3.1 Employees (existing table — already has required columns)

- `employee_type`: `'digital_worker'` already a valid value. Model exposes `isDigitalWorker(): bool` and scopes `digitalWorker()` / `human()`.
- `job_description` (TEXT, nullable) — already exists; optional short role label per architecture §4.5
- `supervisor_id` already exists — for Digital Worker supervision chain to human

### 3.2 File-Based Sessions and Messages (Workspace)

Following the OpenClaw pattern: sessions and messages are stored as files in a per-Digital Worker workspace directory, not in database tables.

**Workspace layout:**

```
storage/app/workspace/{employee_id}/
├── config.json                # Per-DW LLM configuration (provider, model, params)
├── sessions/
│   ├── {uuid}.jsonl           # Append-only transcript (one JSON line per message)
│   └── {uuid}.meta.json       # Session metadata (title, channel, timestamps)
├── MEMORY.md                  # (future) Long-term curated memory
├── memory/                    # (future) Daily logs
└── memory.db                  # (future) Vector index
```

**Session metadata (`{uuid}.meta.json`):**
```json
{
    "id": "uuid",
    "employee_id": 42,
    "channel_type": "web",
    "title": "Session title or null",
    "created_at": "2026-02-27T10:00:00Z",
    "last_activity_at": "2026-02-27T10:05:00Z"
}
```

**Message format (one JSON line per message in `{uuid}.jsonl`):**
```json
{"role": "user", "content": "Hello", "timestamp": "2026-02-27T10:00:00Z", "run_id": null, "meta": {}}
{"role": "assistant", "content": "Hi there!", "timestamp": "2026-02-27T10:00:01Z", "run_id": "run_abc123", "meta": {"model": "gpt-4o-mini", "latency_ms": 850, "tokens": {"prompt": 42, "completion": 8}, "fallback_attempts": []}}
```

When fallback occurs, `fallback_attempts` contains structured entries:
```json
{"role": "assistant", "content": "Hi!", "timestamp": "...", "run_id": "run_def456", "meta": {"model": "gpt-4o-mini", "latency_ms": 300, "tokens": {"prompt": 42, "completion": 8}, "fallback_attempts": [{"provider": "anthropic", "model": "claude-sonnet-4-20250514", "error": "HTTP 500: Internal Server Error", "error_type": "server_error", "latency_ms": 150}]}}
```

### 3.3 Provider Credentials (Database)

Company-level LLM provider credentials stored in `ai_providers` table. See `docs/architecture/ai-digital-worker.md` §15.1 for full schema.

**Migration:** `app/Modules/Core/AI/Database/Migrations/` (module-aware)

Key columns: `company_id`, `name` (unique per company), `display_name`, `base_url`, `api_key` (encrypted), `is_active`, `created_by`.

**Model:** `App\Modules\Core\AI\Models\AiProvider` with `encrypted` cast on `api_key`.

### 3.4 Per-DW Workspace Config (`config.json`)

Each Digital Worker's workspace contains a `config.json` for per-DW LLM overrides:

```json
{
    "llm": {
        "models": [
            {
                "provider": "anthropic",
                "model": "claude-sonnet-4-20250514",
                "max_tokens": 4096,
                "temperature": 0.5
            },
            {
                "provider": "openai",
                "model": "gpt-4o-mini"
            }
        ]
    }
}
```

- `models`: ordered list; first entry is primary, rest are fallbacks on transient failures
- `provider` references `ai_providers.name` within the DW's company
- Falls back to global `config('ai.llm.*')` when absent
- See `docs/architecture/ai-digital-worker.md` §15.2–§15.3 for resolution rules

### 3.5 File Conventions

1. Workspace base path: `storage/app/workspace/` (configurable via `config('ai.workspace_path')`)
   - Config file: `app/Base/AI/Config/ai.php` (module-level config, registered by `AIServiceProvider`)
   - Env override: `AI_WORKSPACE_PATH` (defaults to `storage_path('app/workspace')`)
2. Employee workspace: `{base}/{employee_id}/sessions/`
3. Session ID: UUID v4
4. JSONL: one JSON object per line, append-only, `FILE_APPEND | LOCK_EX` for atomic writes
5. Meta file: overwritten on each activity (title change, new message)
6. No DB migrations needed for sessions/messages

## 4. Backend Deliverables

Module: `app/Modules/Core/AI/`

1. `SessionManager`
    - create/list/get/delete sessions for a Digital Worker
    - `list` returns sessions ordered by `last_activity_at` descending (newest first)
    - service-level supervisor guard is pending; current UI flow scopes by supervised Digital Workers
2. `MessageManager`
   - append user message (JSONL append)
   - append assistant message (JSONL append)
   - read ordered timeline (parse JSONL)
3. `ConfigResolver`
   - reads per-DW `config.json` from workspace
   - resolves provider credentials from `ai_providers` by `(company_id, provider_name)`
   - merges with global defaults (`config('ai.llm.*')`) via cascade (see architecture §15.3)
   - validates config before runtime call (missing key → clear error, not cURL timeout)
4. `DigitalWorkerRuntime` (Stage 0 adapter)
   - takes latest conversation context + resolved LLM config
   - calls OpenAI-compatible API via Laravel HTTP client
   - returns plain assistant text + metadata (`run_id`, `model`, `latency_ms`)
   - **fallback attempt trace**: collects structured `fallback_attempts` entries (provider, model, error, error_type, latency_ms) for each failed model before success or exhaustion (OpenClaw-inspired, see architecture §15.5)
5. `AiProvider` model + migration
   - Eloquent model with `encrypted` cast on `api_key`
   - company-scoped, supervisor-viewable (names only), admin-editable
6. Authorization policy
    - routes currently require `auth` middleware
    - playground UI only lists Digital Workers supervised by the current user
    - capability-specific middleware (`ai.provider.manage`, `ai.digital_worker.configure_llm`) is planned

## 5. Frontend Deliverables (Volt/Livewire)

1. Volt page component for playground shell
2. DW tab bar (switch between supervised Digital Workers)
3. Session list (left panel)
4. Chat timeline (main panel)
5. Composer submit action
6. Debug panel (right panel, latest run metadata — shows which model was used)
7. LLM config modal (primary + fallback model ordering per Digital Worker)
8. Provider management page (catalog/manual add, model sync, default model, provider priority)

Behavior requirements:

1. Message submit is optimistic or clearly loading
2. New assistant message appears without full page reload
3. Session switching reloads timeline correctly
4. Refresh preserves message history (selected session persistence is a hardening follow-up)

## 6. Testing Deliverables (Pest)

### Feature Tests
1. Auth user can open playground
2. Session create/list only shows sessions for Digital Workers the user supervises
3. Message post persists both user and assistant lines in JSONL
4. User cannot access another user's Digital Worker sessions (403/404)
5. Refresh fetches existing timeline in order

### Unit Tests
1. Runtime adapter returns required metadata keys
2. MessageManager maintains role ordering and timestamp ordering
3. SessionManager creates valid meta.json and empty JSONL
4. **Runtime fallback trace** — `DigitalWorkerRuntimeTest` (implemented):
   - Returns empty `fallback_attempts` on first model success
   - Collects structured attempt entries on transient failures before success
   - Includes fallback attempts when all models fail
   - Does not fall back on client errors (401/403); attempts array is empty
   - Records config_error without fallback since not transient

## 7. Manual UAT Script

1. Login as Employee A
2. Open Playground and create `Session A1`
3. Send 3 prompts and verify assistant responses appear
4. Create `Session A2`, send 1 prompt
5. Switch back to `Session A1` and verify original 3 prompts/history intact
6. Refresh browser and verify active session + messages still present
7. Login as Employee B and verify Employee A sessions are not visible

## 8. Exit Criteria

1. All Stage 0 feature tests pass
2. UAT script passes without data leakage
3. No sensitive tools exposed in Stage 0 runtime

## 9. Implementation Order (Recommended)

1. Module skeleton (`app/Base/AI/ServiceProvider.php`, `Config/ai.php`, DTOs)
2. File-based services (`SessionManager`, `MessageManager`)
3. Authorization policy boundaries (Digital Worker chained to human, scope by supervisor)
4. Runtime adapter (OpenAI-compatible via HTTP client)
5. Routes + Volt UI shell + components
6. Pest tests + UAT run

## 10. Future: Memory and Recall

Stage 0 persists only the **chat transcript** (JSONL files). Long-term semantic memory (MemSearch-style: markdown source of truth, vector index for recall) is out of scope. When implementing memory:

- See `docs/architecture/ai-digital-worker.md` §14 for the design: transcript vs memory, MemSearch pattern, PHP-native implementation, hybrid search, compaction.
- Workspace layout already reserves `MEMORY.md`, `memory/*.md`, and `memory.db` alongside `sessions/`.

## 11. Risks and Mitigations

1. **Risk:** Chat state desync between frontend and files
   - **Mitigation:** Source-of-truth reload from JSONL after each send completion
2. **Risk:** Session leakage across users
   - **Mitigation:** Enforce supervisor-scoped access in SessionManager; workspace path derived from employee_id
3. **Risk:** Runtime latency variance
   - **Mitigation:** Capture latency in metadata and expose in debug panel (no target for now)
4. **Risk:** JSONL corruption from concurrent writes
   - **Mitigation:** `LOCK_EX` flag on appends; Stage 0 is single-user-per-session (serialized by design)
