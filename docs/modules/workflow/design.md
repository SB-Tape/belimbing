# Workflow Engine: Design Document

**Document Type:** Architecture Design
**Status:** Draft — exploring the big picture
**Purpose:** Define BLB's status-centric workflow engine as the backbone for all business process management
**Last Updated:** 2026-03-17

---

## 1. The Problem in One Sentence

Every business runs on processes — leave applications, order fulfillment, school placements, customs clearance — and each process is fundamentally a sequence of **statuses** with **rules about who can move between them**.

---

## 2. Core Insight: Status *Is* the Workflow

BLB does not model workflows as separate flowchart objects that reference entities. Instead, **status is the workflow**. A process is defined entirely by:

1. The **statuses** it can be in
2. The **transitions** allowed between them
3. The **policies** governing each status (permissions, notifications, PIC)

This is deliberately simple. A leave application and an immigration clearance share the same engine — they differ only in their status graph and the policies attached to each node.

---

## 3. Design Principles

| Principle | Implication |
|-----------|-------------|
| **Status-centric** | The status graph *is* the process definition. No separate "workflow definition" abstraction. |
| **Entity-agnostic** | One engine drives any entity type. The `entity` discriminator scopes status sets. |
| **Config-driven** | Statuses, transitions, permissions, and notifications are database records, not code. Admins configure processes in the UI without deployments. |
| **Simple core, extensible edges** | The `StatusConfig` table handles 80% of cases. Complex transition logic (conditions, guards, actions) extends via a companion table and hooks — added when needed, not upfront. |
| **Parent class, child specializations** | `StatusConfig` is the base. Entity-specific needs (extra attributes, custom validation) are handled by child classes or metadata, not by forking the schema. |

---

## 4. Conceptual Model

### 4.1 Status Graph

A process is a **directed graph** where:
- **Nodes** = statuses (rows in `StatusConfig` for a given `entity`)
- **Edges** = allowed transitions (encoded in `next_statuses`)

```
Leave Application:

  ┌──────┐     ┌──────────────────┐     ┌──────────┐     ┌──────────┐     ┌──────────┐
  │ new  │────▶│ pending_approval │────▶│ approved │────▶│ on_leave │────▶│ complete │
  └──────┘     └──────────────────┘     └──────────┘     └──────────┘     └──────────┘
                        │
                        ├───────────────▶ rejected
                        │
                        └───────────────▶ closed
```

```
Order Fulfillment (multi-department):

  ┌─────────┐     ┌────────────┐     ┌─────────────────┐     ┌──────────┐     ┌───────────┐
  │ created │────▶│ processing │────▶│ customs_review  │────▶│ shipped  │────▶│ delivered │
  └─────────┘     └────────────┘     └─────────────────┘     └──────────┘     └───────────┘
                        │                     │
                        ▼                     ▼
                    cancelled            customs_hold ──▶ customs_cleared ──▶ shipped
```

### 4.2 What a Status Carries

Each status node is not just a label — it carries **policy**:

| Attribute | Purpose | Example |
|-----------|---------|---------|
| `code` | Machine identifier | `pending_approval` |
| `label` | Human display name | `Pending Approval` |
| `permissions` | Who can act on items in this status | `{"view": ["hr_staff"], "transition": ["hr_manager"]}` |
| `pic` | Person(s)-in-charge | `["hr_manager", "department_head"]` |
| `notifications` | Who gets notified on entry | `{"email": ["applicant", "hr_manager"], "in_app": ["applicant"]}` |
| `next_statuses` | Allowed outbound transitions | `["approved", "rejected", "closed"]` |
| `position` | Display order (lists, kanban columns) | `2` |
| `comment_tags` | Required/available comment categories | `["reason", "internal_note"]` |
| `prompt` | AI guidance for this status | `"Review the leave dates and check for conflicts"` |
| `kanban_code` | Groups statuses into kanban columns | `in_progress` |
| `is_active` | Soft enable/disable | `true` |

### 4.3 Entity Discrimination

The `entity` column scopes the status set. All statuses for `leave_application` form one graph; all statuses for `order_fulfillment` form another.

```
entity = "leave_application"  →  {new, pending_approval, approved, rejected, closed, on_leave, complete}
entity = "order_fulfillment"  →  {created, processing, customs_review, customs_hold, ...}
entity = "school_placement"   →  {application, document_review, interview, accepted, enrolled, ...}
```

---

## 5. Levels of Complexity

The design must handle a spectrum from trivial to complex. The table is the foundation; complexity is layered on, not built in.

### Level 1: Simple Linear Process
**Example:** Bug report — `open` → `in_progress` → `resolved` → `closed`

Handled entirely by `StatusConfig` rows. `next_statuses` defines the path. Permissions are simple. No conditions, no external integrations.

### Level 2: Branching / Decision Points
**Example:** Leave application — `pending_approval` branches to `approved`, `rejected`, or `closed`

Still handled by `StatusConfig`. The `next_statuses` array contains multiple options. The UI presents the available transitions; the user picks one. `permissions` on the target status controls who can make the move.

### Level 3: Conditional Transitions
**Example:** Order fulfillment — transition to `shipped` only if payment is confirmed and inventory is reserved.

`StatusConfig.next_statuses` lists the *possible* transitions, but **transition guards** (conditions) determine if a specific transition is available *right now* for *this instance*. This is where the engine needs extension beyond the base table.

**Options (to be decided):**
- **a) Hook-based:** Register PHP closures/classes as transition guards via the Workflow hook system
- **b) Companion table:** `blb_status_transitions` with `from_status`, `to_status`, `guard_class`, `action_class` columns
- **c) Metadata in `next_statuses`:** Enrich the JSON: `[{"code": "shipped", "guard": "PaymentConfirmed"}]`

### Level 4: Multi-Department / Multi-Agency Orchestration
**Example:** School placement spanning admissions, finance, immigration, healthcare

At this level, a single status graph may not suffice. The process may need:
- **Sub-processes** (immigration clearance is its own status graph, embedded in the parent)
- **Parallel tracks** (health check and document verification happen simultaneously)
- **Cross-entity coordination** (the placement process triggers an immigration process)

**Design approach:** The `entity` discriminator can model sub-processes as separate status graphs (e.g., `placement.immigration`, `placement.health_check`). The parent entity tracks which sub-processes are active and their states. Orchestration logic lives in the `WorkflowEngine`, not in the table schema.

### Level 5: External System Integration
**Example:** Customs clearance requires API calls to government systems

Transition hooks (before/after) trigger external integrations. The status might enter a "waiting" state until an external callback advances it. This is event-driven and integrates with Laravel's job/event system.

---

## 6. The `StatusConfig` Table — Revisited

Based on the levels above, the current table design holds for Levels 1–2 and partially for Level 3. Here's the refined assessment:

### What Stays
- `entity` + `code` composite unique — correct
- `label`, `position`, `is_active` — correct
- `permissions`, `pic`, `notifications` as JSON — correct for the node-level policy
- `next_statuses` as JSON array — correct as the simple adjacency list
- `comment_tags`, `prompt`, `kanban_code` — correct for UI and AI integration

### What Needs Attention

| Issue | Resolution |
|-------|------------|
| Table name `blb_status_configs` uses `blb_` prefix | Align with Core naming: `workflow_status_configs` (or decide if Workflow is Base layer) |
| `is_active` is nullable with no default | Should be `NOT NULL DEFAULT TRUE` |
| Migration timestamp `2025_12_01` | Should be `0200_01_21_000000` per database conventions |
| `increments('id')` vs `id()` | Align with the ID strategy used by other Core tables |
| No `description` or `metadata` column | Consider a `metadata` JSON for entity-specific extensions (phases, swim lanes, SLAs) |
| `next_statuses` is flat array | Sufficient for Levels 1–2; Level 3+ may need enriched format or companion table |

### Future Companion: `workflow_status_transitions` (Not Now)

When Level 3+ demands it, a transitions table captures edge-level policy:

```
workflow_status_transitions
├── id
├── entity
├── from_code        →  source status code
├── to_code          →  target status code
├── guard_class      →  PHP class that evaluates if transition is allowed
├── action_class     →  PHP class that executes on transition
├── permissions      →  who can trigger this specific transition
├── metadata         →  conditions, SLA, priority
├── position         →  order when multiple transitions exist
├── is_active
└── timestamps
```

This table is **not needed yet**. It's documented here so the design accommodates it without requiring schema changes to `StatusConfig`.

---

## 7. The Engine Components

Per the file structure, the Workflow module has four planned components:

| Component | Responsibility |
|-----------|---------------|
| **WorkflowEngine** | Orchestrates status changes. Given an entity instance, validates and executes transitions. Entry point for all status operations. |
| **StatusManager** | CRUD and querying of `StatusConfig` records. Loads the status graph for an entity. Caches aggressively. |
| **TransitionValidator** | Evaluates whether a transition is allowed: checks `next_statuses`, `permissions`, and any registered guards. |
| **Hooks/** | Before/after transition hooks. Notifications, audit logging, external integrations, AI prompts. |

### Call Flow

```
User clicks "Approve" on a leave application
    │
    ▼
WorkflowEngine::transition($leaveApp, 'approved')
    │
    ├── StatusManager::getStatusGraph('leave_application')     // load & cache
    │
    ├── TransitionValidator::validate($currentStatus, 'approved', $actor)
    │       ├── Is 'approved' in current status's next_statuses?
    │       ├── Does $actor have permission to trigger this transition?
    │       └── Do all registered guards pass? (Level 3+)
    │
    ├── Hooks::fireBefore('leave_application', 'pending_approval', 'approved')
    │
    ├── $leaveApp->status = 'approved'
    │   $leaveApp->save()
    │
    ├── Hooks::fireAfter('leave_application', 'pending_approval', 'approved')
    │       ├── Send notifications (per target status config)
    │       ├── Assign PIC (per target status config)
    │       └── Log audit trail
    │
    └── return TransitionResult
```

---

## 8. How Entities Participate

An entity that participates in the workflow engine needs:

1. A `status` column (string, storing the current status code)
2. A known `entity` identifier (e.g., `'leave_application'`)

That's it. The engine is not invasive. A trait (e.g., `HasWorkflowStatus`) could provide:

```php
// Conceptual — not code yet
trait HasWorkflowStatus
{
    public function workflowEntity(): string;           // returns 'leave_application'
    public function currentStatus(): StatusConfig;       // resolves current status config
    public function availableTransitions(): Collection;  // what can happen next
    public function transitionTo(string $code): void;    // delegates to WorkflowEngine
}
```

Existing entities like `Company` (which already has `status` with `active`, `suspended`, etc.) could adopt the engine retroactively by:
1. Creating `StatusConfig` rows for `entity = 'company'`
2. Adding the trait
3. Gradually replacing hardcoded status methods with engine calls

---

## 9. Kanban as a View, Not a Model

The `kanban_code` field maps statuses to visual columns. Multiple statuses can share a kanban column:

```
Kanban Column: "In Progress"     →  statuses: processing, customs_review, customs_hold
Kanban Column: "Done"            →  statuses: delivered, complete
Kanban Column: "Blocked"         →  statuses: customs_hold, rejected
```

This is purely a view-layer concern. The workflow engine doesn't know about kanban — it only knows statuses and transitions. The UI reads `kanban_code` to group cards.

---

## 10. AI Integration Points

The `prompt` field on each status enables AI assistance:

- **Status-aware suggestions:** "This leave application has been in `pending_approval` for 3 days. The prompt says: *Review the leave dates and check for conflicts.*"
- **Transition guidance:** AI can suggest the next action based on the current status and available transitions
- **Auto-classification:** AI can recommend which status an incoming item should start in
- **SLA monitoring:** Combined with timestamps, AI can flag items that are stuck

---

## 11. Open Questions

| # | Question | Notes |
|---|----------|-------|
| 1 | **Is Workflow a Core module (`0200`) or Base infrastructure (`0100`)?** | If every business process depends on it, it might be Base. But it has no meaning without business entities, which suggests Core. |
| 2 | **Level 3 mechanism: hooks, companion table, or enriched JSON?** | Hooks are most Laravel-native. Companion table is most explicit. Enriched JSON is simplest but least flexible. |
| 3 | **How do sub-processes (Level 4) relate to parent processes?** | Entity naming convention (`placement.immigration`) vs. explicit parent-child relationship in schema. |
| 4 | **Should `StatusConfig` support versioning?** | When an admin changes a process definition, should in-flight items keep their original definition? |
| 5 | **Should the engine enforce that `status` column values match `StatusConfig` codes?** | Strict enforcement vs. advisory. Strict is safer but less flexible during migration. |
| 6 | **How does this integrate with AuthZ?** | `permissions` in `StatusConfig` needs to map to BLB's capability system. The `workflow` capability category already exists in authz config. |
| 7 | **Status history / audit trail?** | A `workflow_status_history` table logging every transition (who, when, from, to, comments) is likely needed. Where does it live? |

---

## 12. What's Next

This document captures the big picture. Before writing code:

1. **Resolve the open questions** — especially #1 (layer placement) and #2 (Level 3 mechanism)
2. **Define the public interface** — `WorkflowEngine`, `StatusManager`, `TransitionValidator` method signatures
3. **Pick one real use case** (e.g., leave application) and walk through it end-to-end against this design
4. **Fix the migration** — timestamp, table name, `is_active` default
5. **Then build** — model first, engine second, UI last

---

*"A process is just a graph of statuses. Keep the graph simple; let the policies be rich."*
