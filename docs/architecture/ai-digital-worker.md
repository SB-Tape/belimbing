# AI Digital Worker Architecture

**Document Type:** Architecture Specification
**Status:** Planning
**Last Updated:** 2026-02-26
**Related:** `docs/architecture/user-employee-company.md`, `docs/architecture/authorization.md`, `docs/architecture/database.md`

---

## 1. Problem Essence

BLB needs Digital Workers to be managed as first-class employees under the same organizational model and authorization system as humans, with clear delegation boundaries and accountable supervision.

---

## 2. Decision Summary

1. Digital Worker is an employee under the same management UI and org structure as human employees.
2. Human and Digital Worker share one employee model/table.
3. Existing employee attributes are reused; non-applicable fields are nullable.
4. Add only minimal new employee fields now: `employee_type` (with value `'digital_worker'`) and `job_description` (see §4.5).
5. Cost/token accounting is deferred to a future HR module.
6. Digital Worker permissions are constrained by delegation and cannot exceed supervisor effective permissions.
7. **Digital Worker context for execution:** OpenClaw-style workspaces (IDENTITY, SOUL, AGENTS, etc.) define “who” and “how”; BLB keeps a single `job_description` field as a short role label for now; full workspace-based context is the target when integrating an OpenClaw-like runtime.

---

## 3. Public Interface

### 3.1 Workforce Subject Model

One subject model for authorization and org operations:

- `Employee` with `employee_type = 'digital_worker'` for Digital Worker; any other value (full_time, part_time, contractor, intern) denotes a human
- Both can be assigned roles and permissions
- Both can supervise subordinate employees
- In AuthZ, Digital Worker is represented as principal type `digital_worker` (`PrincipalType::DIGITAL_WORKER`); same capability vocabulary as human actors.

### 3.2 Required Operations

1. `createEmployee(...)`
2. `updateEmployee(...)`
3. `assignSupervisor(employeeId, supervisorId)`
4. `assignRole(employeeId, roleId)`
5. `grantPermission(employeeId, capability)`
6. `revokePermission(employeeId, capability)`
7. `setJobDescription(employeeId, text)`
8. `disableEmployee(employeeId)`

### 3.3 Digital Worker-Specific Management Operations

1. `createDigitalWorker(supervisorId, profile)`
2. `setDigitalWorkerScope(employeeId, scope)`
3. `validateDelegation(supervisorId, subordinateId)`

The UI remains the same employee UI; Digital Worker uses type-aware behavior, not a separate product surface.

### 3.4 Canonical Terms and Naming Alignment

This document follows the AuthZ canonical naming contract in `docs/architecture/authorization.md` §1.1:
1. Use `Digital Worker` (not `PA`).
2. AuthZ actor type is `PrincipalType::DIGITAL_WORKER` with persisted value `'digital_worker'`.
3. Framework AI capabilities use `ai.digital_worker.*`.
4. Delegation context links Digital Worker actions to a human accountability chain (`actingForUserId` in current DTO, with future support for richer supervision metadata).

---

## 4. Employee Data Model (Current Scope)

### 4.1 Single Table Strategy

Use one `employees` table for both human and Digital Worker records.

### 4.2 Minimal Additions

1. **Digital Worker in employee_type:** Add `'digital_worker'` as a valid value for the existing `employee_type` column. When `employee_type === 'digital_worker'`, the row is a Digital Worker; otherwise (full_time, part_time, contractor, intern) it is a human. No additional column. The model exposes `isDigitalWorker(): bool` (e.g. `return $this->employee_type === 'digital_worker'`) and scopes `scopeDigitalWorker()` / `scopeHuman()` for convenience.
2. **job_description** (`TEXT`, nullable at DB): Short role label or summary for the Digital Worker (e.g. “Customer support Digital Worker”, “Leave approver”). Used for HR/UI and optional display in execution context. Full agent identity and behaviour are defined by an OpenClaw-style workspace when that runtime is adopted (see §4.5 and §13).

### 4.3 Attribute Applicability

- Existing attributes remain available for both types.
- Non-applicable attributes are `NULL`.
- `phone` remains nullable and valid for Digital Worker (messaging channels now or later).
- `date_of_birth` is interpreted as employee identity birth date.
  - Human: biological date of birth.
  - Digital Worker: identity creation date.

No additional Digital Worker operational or financial fields are added at this stage.

### 4.4 Indicating Digital Worker in the Employee Module

- **Schema:** No new column. The existing `employee_type` column accepts `'digital_worker'` as a value; when `employee_type === 'digital_worker'`, the row is a Digital Worker. Other values (full_time, part_time, contractor, intern) denote human employees.
- **Model:** Expose `isDigitalWorker(): bool` (e.g. `return $this->employee_type === 'digital_worker'`) and query scopes `scopeDigitalWorker($query)`, `scopeHuman($query)` so callers can do `Employee::query()->digitalWorker()->get()` or `$employee->isDigitalWorker()`.
- **UI:** In employee lists, show a badge (e.g. `<x-ui.badge variant="info">Digital Worker</x-ui.badge>`) when `$employee->isDigitalWorker()`; in create/edit, add a control (e.g. checkbox or radio “Human / Digital Worker”) that sets `employee_type` to 'digital_worker' for a Digital Worker or to an employment kind for a human. Filter the list by “Human only” / “Digital Worker only” / “All” using `employee_type`.


### 4.5 job_description vs OpenClaw-Style Workspace

**OpenClaw workspace pattern** (from `~/.openclaw/workspace/` and similar): Agent identity and behaviour are defined by a **set of markdown files**, not a single text field:

| File | Purpose |
|------|---------|
| `IDENTITY.md` | Name, creature, vibe, emoji — "Who am I?" |
| `SOUL.md` | Core truths, boundaries, tone — "How I behave." |
| `USER.md` | Who the human is (name, timezone, context) — "Who I'm helping." |
| `AGENTS.md` | Session load order, memory strategy, act-vs-ask, safety, heartbeats — "How I operate." |
| `TOOLS.md` | Env-specific notes (SSH, TTS, devices) — "My cheat sheet." |
| `BOOTSTRAP.md` | One-time onboarding; deleted after first run. |
| `HEARTBEAT.md` | Periodic check prompts. |
| `MEMORY.md` | Long-term curated memory (main session). |
| `memory/YYYY-MM-DD.md` | Daily raw logs. |

**Decision for BLB:**

- **Keep `job_description`** as a single `TEXT` field on the employee row: short, human-readable role label or summary (e.g. "Customer support Digital Worker", "Leave approver"). Use it for HR/UI and, if needed, as a fallback or one-line hint in execution context. Nullable; not mandatory at create for Digital Worker (can be set before activation or left as summary only).
- **Do not** replicate the full OpenClaw file set in the DB as separate columns. When integrating an OpenClaw-like runtime, **Digital Worker context for execution should be workspace-based**: each Digital Worker has an associated workspace (directory or virtual file set) containing IDENTITY, SOUL, AGENTS, USER (or company/supervisor context), TOOLS, etc. The runtime loads these files to build the system prompt and behaviour; `job_description` may be displayed in the UI or injected as a short summary, but the authoritative "job" is the workspace content.
- **Pivot path:** Stage 0 can rely on `job_description` only (or omit it). When adding execution that follows OpenClaw's model, introduce an **Digital Worker workspace** (path or storage key) and treat the workspace as the source of truth for identity and behaviour; keep `job_description` as an optional label for lists and reports.

---

## 5. Authorization and Delegation Rules

### 5.1 Core Invariants

1. Digital Worker effective permissions must be a strict subset of supervisor effective permissions.
2. Delegation cannot create new privileges.
3. Explicit deny always wins.
4. Every Digital Worker must have a supervision chain that resolves to a human accountable owner.
5. Supervision graph must be acyclic.

### 5.2 Supervisor Model

- Supervisor can be human or Digital Worker.
- Human employees with required capability can create/manage subordinate Digital Workers.
- One supervisor can manage multiple subordinate Digital Workers, subject to policy limits.

### 5.3 Capability Gate (Illustrative)

Capabilities for Digital Worker administration should be explicit in AuthZ, for example:

1. `employee.digital_worker.create`
2. `employee.digital_worker.update`
3. `employee.digital_worker.assign_role`
4. `employee.digital_worker.assign_permission`
5. `employee.digital_worker.disable`

The final capability vocabulary is owned by the AuthZ module.

---

## 6. UI and UX Rules

1. Employee listing includes both human and Digital Worker rows.
2. Display type badges: `Human` / `Digital Worker`.
3. Employee create/edit flow includes type selector.
4. Forms render the same base fields; optional guidance can hide non-relevant physical fields for Digital Worker.
5. Supervisor and role assignment flows are shared.
6. Delegation policy violations return explicit deny reasons.

---

## 7. Error Policy

1. Policy violations are denied deterministically by AuthZ.
2. Validation errors return field-specific messages.
3. Privilege escalation attempts are logged as security events.
4. Management actions must be auditable with actor, target employee, and decision reason.

---

## 8. Implementation Dependencies

Stage 0 (Digital Worker Playground) requires authorization PRD Stage B (Policy Engine + RBAC) and Stage D (Digital Worker Delegation) from `docs/todo/authorization/00-prd.md`. Stage D is partially complete: `PrincipalType::DIGITAL_WORKER` actor and same RBAC as human are operational. Assignment-time validation and cascade revocation (Stage D remaining items) are not blockers for Stage 0, which is a read-only playground with no sensitive write tools.

---

## 9. Workspace Configuration

The per-Digital Worker workspace base path is configured in `app/Base/AI/Config/ai.php` (module-level config registered by `AIServiceProvider`):

- Config key: `config('ai.workspace_path')`
- Env override: `AI_WORKSPACE_PATH`
- Default: `storage_path('app/workspace')` → `storage/app/workspace/`

Each Digital Worker gets a subdirectory: `{workspace_path}/{employee_id}/` containing `sessions/`, and future `MEMORY.md`, `memory/`, `memory.db` (see §14).

---

## 10. Implementation Boundaries (Now vs Later)

### 10.1 In Scope Now

1. Digital Worker as `employee_type = 'digital_worker'` in a unified employee model.
2. `job_description` as optional short role label (nullable); full Digital Worker context is workspace-based when OpenClaw-like runtime is adopted (§4.5).
3. Delegation constraints integrated with shared AuthZ.
4. Unified management UI behavior.

### 10.2 Out of Scope Now

1. HR-specific compensation, token spend, and cost accounting.
2. Rich Digital Worker runtime telemetry fields in employee core table.
3. Channel-level integration details (Telegram/WhatsApp) as schema drivers.

---

## 11. Alignment with BLB Principles

1. Deep module boundary: complexity (delegation, policy checks, audit rules) is hidden in AuthZ + employee domain services.
2. Simple public interface: managers operate through familiar employee workflows.
3. Strategic programming: avoid premature Digital Worker-specific schema sprawl while preserving forward compatibility.

---

## 12. Open Questions

1. Resolved: `job_description` is optional short label; workspace is source of truth for execution (§4.5).
2. Should policy set a hard maximum depth for Digital Worker supervision chains?
3. Should Digital Worker creation require dual approval for high-privilege departments?

---

## 13. OpenClaw Architecture (Research Findings)

*Relevant when designing Digital Worker execution, tooling, or channel integration. Source: OpenClaw agent system research. See also §4.5 for how BLB's `job_description` relates to OpenClaw-style workspace files.*

### 13.1 High-Level Architecture

**Pattern:** Skills (teach) + Tools (execute) + Policies (constrain) + Channels (interface)

```
User Message (WhatsApp/Telegram/Slack)
  ↓
Gateway (routing & access control)
  ↓
Queue (session-based serialization)
  ↓
Agent (AI runtime with skills & tools)
  ↓
Tool Execution (sandboxed if configured)
  ↓
Response (streamed back to channel)
  ↓
Session Persistence (JSONL history)
```

### 13.2 Core Components

#### Agent
- Embedded AI runtime based on pi-mono
- Processes messages through serialized execution loop
- Each agent has dedicated workspace directory
- Session manager for conversation history
- **Bootstrap files for context:** IDENTITY.md, SOUL.md, USER.md, AGENTS.md, TOOLS.md, HEARTBEAT.md, MEMORY.md, memory/YYYY-MM-DD.md (see §4.5 for BLB alignment)

**Execution Model:**
- Runs serialized per session (prevents race conditions)
- Each run has unique `runId` for tracking
- Sessions isolated by `sessionKey` (e.g., per user, per group)
- Supports Docker sandboxing for security

#### Skills
AgentSkills-compatible instruction packs (Markdown files)

**Structure:**
```
skill-name/
├── SKILL.md          # YAML frontmatter + instructions
└── (optional files)
```

**Loading Precedence:**
1. Workspace skills (highest priority)
2. Managed skills (user-installed)
3. Bundled skills (shipped with system)

**Conditional Loading (Gating):**
- OS platform requirements
- Required binaries (e.g., csvkit)
- Environment variables
- Config values

#### Tools
Executable functions exposed to the AI

**Categories:**
- **Coding Tools:** File operations (read, write, edit, exec)
- **Web Tools:** External data (web_fetch, web_search)
- **Messaging Tools:** Send messages across channels
- **Session Tools:** Multi-agent coordination (sessions_send, sessions_spawn)
- **Platform Tools:** System integration (browser, canvas)

**Tool Schema:**
```typescript
interface Tool {
  name: string;
  description: string;
  schema: JSONSchema;  // TypeBox/Zod schema for parameters
  execute: (toolCallId: string, params: unknown) => Promise<ToolResult>;
}
```

#### Policies
Multi-level security constraints

**Policy Resolution Layers:**
1. Tool profile policy (e.g., "safe", "full")
2. Per-model overrides
3. Global allow/deny
4. Per-agent overrides
5. Per-group/channel policies
6. Sandbox restrictions
7. Subagent restrictions

**Example Policy:**
```json
{
  "tools": {
    "allow": ["customer_lookup", "invoice_create"],
    "deny": ["database_raw_query"],
    "exec": {
      "security": "allowlist",
      "ask": "on-miss",
      "safeBins": ["git", "npm"]
    }
  }
}
```

### 13.3 Agent Execution Loop (Summary)

1. **Message Entry** — User sends via channel; gateway validates; enqueued in session lane
2. **Session Resolution** — Load history from JSONL; restore context; token budgeting
3. **Context Assembly** — Load bootstrap files and eligible skills; build system prompt
4. **LLM Inference** — Send prompt + tools; stream response; process tool calls
5. **Tool Execution Loop** — Validate against policies; execute (sandbox if configured); log; return result
6. **Response Delivery** — Stream back to channel; persist transcript
7. **Cleanup & Logging** — Save session state; audit log

**Timeout Handling:** Agent runtime ~600s default; wait timeout ~30s client-side; AbortSignal for cancellation.

### 13.4 Security Mechanisms

- **Access Control:** Channel-specific policies (DM vs group), pairing codes, allowlists, mention requirements
- **Tool Policy:** Multi-level allow/deny; per-user, per-company, per-tool; approval workflows for sensitive ops
- **Sandboxing:** Docker containers; workspace access modes (none/ro/rw); resource limits; network isolation
- **Exec Approvals:** Human-in-the-loop for shell commands; allowlist of safe commands
- **Session Isolation:** Separate sessions per user; company-scoped data; no cross-user/cross-company leakage
- **Audit & Compliance:** All tool executions logged; session transcripts retained; security audit commands

### 13.5 Research References

- Agent runtime, session management: `openclaw/src/agents/`
- Skills: `openclaw/skills/` (AgentSkills-compatible)
- Tools: `openclaw/src/agents/tools/`
- Channels: `openclaw/src/{telegram,discord,slack,signal,imessage,web}/`
- Security: `openclaw/docs/gateway/security/`
- Execution flow: `openclaw/docs/concepts/agent-loop.md`

---

## 14. Memory and Recall Architecture

*Relevant when implementing Digital Worker semantic memory (long-term recall beyond the chat transcript). See also §4.5 (workspace files) and §13 (OpenClaw).*

### 14.1 Transcript vs Memory

| Concern | Transcript | Memory (Recall) |
|---------|------------|-----------------|
| **Purpose** | Chat turn-by-turn history (user/assistant messages in order) | Long-term searchable knowledge (facts, decisions, observations) |
| **Source** | JSONL files per session (`workspace/{employee_id}/sessions/{uuid}.jsonl`) | Markdown files (MEMORY.md, memory/YYYY-MM-DD.md) |
| **Usage** | Provide last N turns as LLM context | Semantic search: "recall relevant past knowledge" before responding |
| **Stage** | Stage 0 (Playground) | Post-Stage 0 |

Both are needed for a capable Digital Worker: transcript for immediate context, memory for history.

### 14.2 MemSearch Pattern

[MemSearch](https://zilliztech.github.io/memsearch/) (Zilliz/Milvus) extracts OpenClaw's memory system into a standalone library. Core principles:

- **Markdown as source of truth** — Plain `.md` files are the canonical store; the vector index is derived and rebuildable.
- **Vector store as index** — Embeddings enable semantic search; the index can be dropped and rebuilt from markdown anytime.
- **Git-native** — Version knowledge bases with standard git workflows.
- **No vendor lock-in** — Switch embedding or vector backends without data loss.

Reference: [Milvus blog: We extracted OpenClaw's memory system and open-sourced it (MemSearch)](https://milvus.io/blog/we-extracted-openclaws-memory-system-and-opensourced-it-memsearch.md)

### 14.3 BLB Implementation Direction

**PHP-native implementation** — Implement the MemSearch pattern in PHP to avoid Python subprocesses and keep the stack homogeneous. Components:

- Markdown parsing: `league/commonmark` or similar
- Chunking: by heading and paragraph structure
- Embeddings: HTTP calls to OpenAI, Voyage, or Ollama
- Vector storage: see §14.4

**Vector backend: SQLite per Digital Worker** — Use a dedicated SQLite database per Digital Worker for vector storage:

- Each Digital Worker gets `workspace/{employee_id}/memory.db`
- Aligns with per-agent workspace isolation (OpenClaw pattern)
- Strong tenant isolation by design; backup/export = copy one file
- Requires a vector extension: [sqlite-vec](https://github.com/asg017/sqlite-vec) or [sqlite-vss](https://github.com/asg017/sqlite-vss)

**Workspace layout (per Digital Worker):**

```
workspace/{employee_id}/
├── MEMORY.md              # Persistent facts & decisions
├── memory/
│   ├── 2026-02-07.md      # Daily log
│   └── 2026-02-09.md
└── memory.db              # Vector index for this DW's markdown (derived, rebuildable)
```

**Alternative:** pgvector in the main PostgreSQL database with `employee_id` for tenancy. Simpler ops (one DB, standard migrations) but less natural per-DW isolation. Choose based on scale and deployment constraints.

### 14.4 Search Strategy: Hybrid Vector + BM25

MemSearch demonstrates that hybrid retrieval outperforms pure vector search for agent memory. Default weighting:

- **Vector search (70%):** Semantic matching — a query for "Redis cache config" finds chunks about "Redis L1 cache with 5min TTL" even with different wording.
- **BM25 keyword search (30%):** Exact matching — a query for "PostgreSQL 16" does not return results about "PostgreSQL 15". Critical for error codes, function names, version-specific facts.

The 70/30 split is MemSearch's empirically tuned default. For workflows heavy on exact matches (code references, IDs), raise BM25 weight to 50%. BLB's PHP-native implementation should support configurable weights per Digital Worker or globally.

### 14.5 Compaction: Daily Logs → Long-Term Memory

MemSearch includes a **compact** workflow that distills older daily logs (`memory/YYYY-MM-DD.md`) into curated long-term entries in `MEMORY.md`. This prevents unbounded growth of daily files while preserving key facts and decisions.

**Pattern:**
1. Periodically (e.g., weekly or on threshold), feed older daily logs to the LLM with a distillation prompt.
2. Extract durable facts, decisions, and preferences into `MEMORY.md` (append or merge under headings).
3. Archive or delete processed daily logs (or keep as raw history if storage allows).
4. Re-index after compaction.

**BLB consideration:** Compaction can run as a scheduled Laravel command per Digital Worker. The human supervisor should be able to review and edit `MEMORY.md` directly (transparency principle). Compaction is post–Stage 0 but should be designed alongside the initial memory implementation to avoid rework.

### 14.6 Implementation Scope (Future)

1. Scan markdown in `workspace/{employee_id}/`
2. Chunk by heading/paragraph; embed via HTTP
3. Store vectors in SQLite (sqlite-vec) or pgvector
4. Search: hybrid vector (70%) + BM25 (30%), return top-K chunks with source attribution
5. Deduplication: content hash to skip re-embedding unchanged chunks
6. Sync: file watcher with debounce (~1500ms) or Laravel scheduler for incremental indexing
7. Compaction: scheduled distillation of daily logs into `MEMORY.md`

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1 | 2026-02-25 | AI + Kiat | Pivoted from PA document to Digital Worker architecture; unified employee model and delegation invariants |
| 0.2 | 2026-02-26 | AI + Kiat | Added §14 Memory and Recall: transcript vs memory, MemSearch pattern, PHP-native direction, SQLite per DW |
| 0.3 | 2026-02-27 | AI + Kiat | Renamed §3.3 operations to Digital Worker; added §14.4 hybrid search strategy (vector 70% + BM25 30%); added §14.5 compaction workflow |
| 0.4 | 2026-02-27 | AI + Kiat | Added §8 Implementation Dependencies, §9 Workspace Configuration; renumbered §8–12 → §10–14 |
