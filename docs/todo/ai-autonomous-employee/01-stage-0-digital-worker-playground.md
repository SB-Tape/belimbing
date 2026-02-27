# Stage 0 - Digital Worker Playground (Implementation Checklist)

**Parent Plan:** `docs/todo/ai-autonomous-employee/00-staged-delivery-plan.md`
**Scope:** Web-only Digital Worker chat loop with persistent sessions/messages and visible runtime metadata
**Target Outcome:** A user can open Digital Worker Playground, chat, switch sessions, refresh, and keep full history.
**Prerequisite:** `docs/architecture/authorization.md` and `docs/todo/authorization/00-prd.md` Stage B + Stage D
**Last Updated:** 2026-02-27

## 1. Stage 0 Contract

### In Scope
1. Authenticated web UI for chat and session switching
2. Persisted session/message history
3. Basic Digital Worker response runtime (no business write tools)
4. Debug metadata visible in UI (run id, model, latency)

### Out of Scope
1. Approval workflow
2. External channels (WhatsApp/Telegram/Slack)
3. Cross-user / Digital Worker-to-Digital Worker orchestration
4. High-risk tool execution

### Digital Worker Chained to Human
Per `docs/architecture/ai-digital-worker.md`: every Digital Worker is an employee with a supervision chain that resolves to a human. In Stage 0, playground sessions belong to a Digital Worker (an employee with `employee_type = 'digital_worker'`); access is scoped by "current user supervises this Digital Worker" (or is the human at the end of the chain).

## 2. UI Deliverables

1. `Digital Worker Playground` page route
2. Left column: Digital Worker selector (dropdown when user supervises multiple; badge when one) + session list + "new session" action
3. Main column: chat transcript + composer
4. Right column: debug panel with latest run metadata

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
{"role": "assistant", "content": "Hi there!", "timestamp": "2026-02-27T10:00:01Z", "run_id": "run_abc123", "meta": {"model": "gpt-4o-mini", "latency_ms": 850, "tokens": {"prompt": 42, "completion": 8}}}
```

### 3.3 File Conventions

1. Workspace base path: `storage/app/workspace/` (configurable via `config('ai.workspace_path')`)
   - Config file: `app/Base/AI/Config/ai.php` (module-level config, registered by `AIServiceProvider`)
   - Env override: `AI_WORKSPACE_PATH` (defaults to `storage_path('app/workspace')`)
2. Employee workspace: `{base}/{employee_id}/sessions/`
3. Session ID: UUID v4
4. JSONL: one JSON object per line, append-only, `FILE_APPEND | LOCK_EX` for atomic writes
5. Meta file: overwritten on each activity (title change, new message)
6. No DB migrations needed for sessions/messages

## 4. Backend Deliverables

Module: `app/Base/AI/`

1. `SessionManager`
   - create/list/get/delete sessions for a Digital Worker
   - `list` returns sessions ordered by `last_activity_at` descending (newest first)
   - access scoped by supervisor relationship (caller must supervise the Digital Worker)
2. `MessageManager`
   - append user message (JSONL append)
   - append assistant message (JSONL append)
   - read ordered timeline (parse JSONL)
3. `DigitalWorkerRuntime` (Stage 0 adapter)
   - takes latest conversation context
   - calls OpenAI-compatible API via Laravel HTTP client
   - returns plain assistant text + metadata (`run_id`, `model`, `latency_ms`)
4. Authorization policy
   - user can only access sessions for Digital Workers they supervise (Digital Worker chained to human)

## 5. Frontend Deliverables (Volt/Livewire)

1. Volt page component for playground shell
2. Session list (left panel)
3. Chat timeline (main panel)
4. Composer submit action
5. Debug panel (right panel, latest run metadata)

Behavior requirements:

1. Message submit is optimistic or clearly loading
2. New assistant message appears without full page reload
3. Session switching reloads timeline correctly
4. Refresh preserves selected session and history

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
