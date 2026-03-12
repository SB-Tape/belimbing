# Tool Workspace UI Plan

**Document Type:** Planning Document
**Scope:** Agent tool catalog, setup, testing, health, and governance UI
**Status:** Proposed
**Last Updated:** 2026-03-09
**Related:** `docs/architecture/agent-tools-blueprint.md`, `docs/architecture/ai-agent.md`, `docs/architecture/lara-system-agent.md`, `resources/core/views/livewire/ai/providers/manager.blade.php`

## 1. Problem Essence

BLB needs a dedicated Tool Workspace UI so authorized users can understand what each Agent tool does, determine whether it is ready to use, configure prerequisites safely, test it in a controlled way, and make informed authorization decisions.

---

## 2. Why This UI Exists

Today, BLB has tool infrastructure and a agent playground, but tools remain mostly implicit inside the runtime loop. That is powerful for Lara, but weak for human trust and operability.

The Tool Workspace UI closes that gap by giving supervisors and administrators a place to answer five questions quickly:

1. **What tools exist?**
2. **What does each tool do?**
3. **What setup is required before it can work?**
4. **Is it healthy and safe to enable?**
5. **Who is allowed to access the workspace, configure tools, and authorize tool use?**

This aligns with the BLB principle: unleash capability, govern with authz.

---

## 3. Public Interface First

The Tool Workspace should present four user-facing surfaces.

### 3.1 Tool Catalog

A searchable overview of all registered tools.

**Purpose**
- Help users discover available tools.
- Show readiness and risk at a glance.
- Route users into the correct per-tool workspace.

**Visible information per tool**
- Tool name
- Simple description in plain language
- Category (`Data`, `Web`, `System`, `Memory`, `Browser`, `Messaging`, etc.)
- Required capability key
- Readiness state
- Health state
- Risk class (`Read-only`, `External I/O`, `Browser`, `Messaging`, `High-impact`)
- Whether the current user can access the workspace entry for the tool

### 3.2 Per-Tool Workspace Panel

A dedicated detail surface for a single tool.

**Purpose**
- Explain the tool clearly.
- Show setup status and next actions.
- Offer a safe test/play area.
- Surface health and recent behavior.

**Sections**
1. **Overview** — simple description, why the tool exists, what it can and cannot do
2. **Setup** — required credentials, config, dependencies, and validation status
3. **Access & Policy** — who may access the workspace, who may configure the tool, who may execute it
4. **Try It** — a safe test console with sample inputs and previewed outputs
5. **Health** — last health check, last success/failure, latency, degraded warnings
6. **Observability** — recent executions, blocked attempts, common failure reasons, audit links

### 3.3 Setup Flows

Guided setup UI for tools with prerequisites.

**Purpose**
- Replace guesswork with checklists and validation.
- Distinguish between company configuration and per-user access.

**Examples**
- `WebSearchTool`: provider selection, API key presence, connection test
- `WebFetchTool`: SSRF guard status, outbound HTTP readiness, size/timeout limits
- `QueryDataTool`: read-only policy summary, timeout/row limit settings, test query availability
- `SystemInfoTool`: enabled sections, redaction rules, health source availability

### 3.4 Safe Test Console

A constrained playground for understanding a tool before delegating it through Lara.

**Purpose**
- Let users learn the tool.
- Verify configuration.
- Reduce fear before granting access.

**Rules**
- Clearly marked as a test surface
- Uses the same authz gates as real execution
- Enforces stricter defaults where needed (shorter limits, non-destructive presets, rate caps)
- Shows sanitized request/response previews, not raw secrets
- Provides sample prompts/inputs and expected result shapes

---

## 4. Top-Level Components

The UI should be designed as a small number of deep modules with simple interfaces.

### 4.1 Tool Catalog Module

**Responsibilities**
- List and filter tools
- Compute display metadata (category, readiness, health summary, risk badge)
- Route to the selected tool workspace

**Inputs**
- Registered tool definitions
- Tool metadata registry
- Current user authz context
- Tool health/readiness summaries

**Outputs**
- Catalog cards or rows
- Filterable search results
- Selected tool navigation

### 4.2 Tool Workspace Module

**Responsibilities**
- Render one tool's overview, setup, policy, test, and health sections
- Tailor actions based on authz and readiness
- Provide clear next-step guidance

**Inputs**
- Tool metadata
- Tool-specific setup state
- Tool-specific policy state
- Tool-specific health status
- Current user permissions

**Outputs**
- Tool detail layout
- Setup action prompts
- Test console state
- Audit and health summaries

### 4.3 Tool Setup/Health Services

**Responsibilities**
- Normalize each tool's setup requirements into a common presentation model
- Run connectivity/health checks
- Report actionable failures without exposing secrets

**Inputs**
- Tool configuration
- Provider/dependency state
- Tool-defined health probes

**Outputs**
- Readiness state
- Health state
- Human-readable remediation guidance

### 4.4 Tool Test Harness

**Responsibilities**
- Execute user-initiated test runs safely
- Apply stricter guardrails than general runtime execution when needed
- Capture sanitized previews and metrics

**Inputs**
- Tool identifier
- Test input payload
- Authz context
- Environment/config state

**Outputs**
- Test result preview
- Validation errors
- Execution metrics
- Audit event

---

## 5. Authorization Model

Authorization must be explicit at three levels.

### 5.1 Workspace Access

A user may be allowed to open the Tool Workspace without being allowed to configure tools or execute them.

**Proposed capability family**
- `ai.tool_workspace.view`
- `ai.tool_workspace.manage`
- `ai.tool_workspace.test`

This is separate from the tool's own execution capability.

### 5.2 Tool Execution Access

Actual tool use remains governed by each tool's existing capability key, such as:
- `ai.tool_query_data.execute`
- `ai.tool_web_search.execute`
- `ai.tool_web_fetch.execute`
- `ai.tool_system_info.execute`

### 5.3 Administrative Setup Access

Users who can manage credentials or system-level configuration need stronger permissions than users who can merely view or test.

**Policy rule**
Configured does not mean granted. Ready does not mean allowed.

The UI must always make these distinctions visible.

---

## 6. Readiness and Health States

Each tool should expose a small, consistent status model.

### 6.1 Readiness States

- **Unavailable** — tool not registered in this environment
- **Unauthorized** — tool exists but current user cannot access this workspace action
- **Unconfigured** — required setup missing
- **Needs Attention** — partially configured but failing validation
- **Ready** — setup complete and tool can be tested/executed

### 6.2 Health States

- **Unknown** — no health check yet
- **Healthy** — last check passed
- **Degraded** — check passed with warnings or recent failures observed
- **Failing** — health check failed

### 6.3 Display Guidance

Readiness answers **can this be used?**
Health answers **is this behaving well right now?**

The UI should never collapse those into one ambiguous badge.

---

## 7. Tool Metadata Requirements

To support a high-quality UI, BLB should treat each tool as needing richer metadata than the runtime alone requires.

Each tool should eventually provide or be associated with:
- Machine name
- Display name
- Simple description
- Longer explanation
- Category
- Risk class
- Required capability
- Setup requirements
- Test examples
- Health check definition
- Documentation links
- Known limits and safety rules

If the runtime contract should stay minimal, this can live in a separate metadata registry keyed by tool name.

---

## 8. UX Shape

The Tool Workspace should visually follow the same design language as the provider manager UI.

### 8.1 Catalog Layout

**Preferred shape**
- Page header with help panel
- Search and filters
- Compact table or card list of tools
- Status badges for readiness, health, and risk
- Click row to open detail panel or dedicated detail route

**Recommended filters**
- Category
- Readiness
- Health
- Risk class
- Accessible to current user
- Requires setup

### 8.2 Per-Tool Layout

**Preferred shape**
- Header: title, simple description, badges, primary actions
- Main body: overview, setup, try-it, observability
- Secondary rail: health snapshot, authz snapshot, recent events

### 8.3 Setup Experience

**Preferred shape**
- Checklist first, form second
- Inline validation messages
- Explicit test buttons such as `Validate API Key` or `Run Health Check`
- Helpful decision copy, not just technical labels

### 8.4 Try-It Experience

**Preferred shape**
- Tool-specific sample input presets
- Preview panel for formatted output
- Guardrail banner describing test limits
- Empty state that teaches the user how to begin

---

## 9. Basic Tool Plans for Phase 1 Tools

### 9.1 QueryDataTool

**Overview copy**
Read data from BLB using safe, read-only SQL.

**Setup panel**
- No external API key required
- Show enforced query guardrails
- Show default row and timeout limits

**Try-it examples**
- Count employees
- Show latest records from a safe table
- Preview result formatting

**Health checks**
- Database reachable
- Read-only validator active
- Statement timeout configured

### 9.2 WebSearchTool

**Overview copy**
Search the public web using a configured provider and return summarized results.

**Setup panel**
- Provider selected?
- API key present?
- Default limits configured?
- Provider reachability test

**Try-it examples**
- Search by keyword
- Search with freshness filter
- Preview excerpt formatting

**Health checks**
- Provider auth valid
- Provider endpoint reachable
- Rate limit or recent failure warnings

### 9.3 WebFetchTool

**Overview copy**
Fetch and extract content from a public URL with SSRF protection and response limits.

**Setup panel**
- Outbound HTTP available
- SSRF guard enabled
- Timeout and max size visible

**Try-it examples**
- Fetch a known public documentation page
- Compare markdown/text extraction modes

**Health checks**
- HTTP client connectivity
- SSRF guard operational
- Content extraction pipeline healthy

### 9.4 SystemInfoTool

**Overview copy**
Inspect non-sensitive BLB system state for diagnostics and situational awareness.

**Setup panel**
- Section availability
- Redaction policy summary

**Try-it examples**
- Fetch `health` section
- Fetch `modules` section

**Health checks**
- Required data providers available
- Sensitive fields masked correctly

---

## 10. Observability and Audit

Every tool workspace should help users understand recent behavior.

**Minimum observability surface**
- Last health check time and result
- Last successful execution time
- Recent failed executions count
- Recent blocked/unauthorized attempts count
- Typical latency range
- Link to deeper audit/event logs when available

**Why this matters**
Trust is created by visibility. If BLB wants humans to grant powerful capabilities, the product must show evidence, not just configuration toggles.

---

## 11. Error Handling Policy

The UI should avoid success-shaped ambiguity.

**Rules**
- Missing setup should produce explicit remediation guidance
- Unauthorized actions should say what is blocked, not fail silently
- Health checks should distinguish validation errors, authz failures, dependency failures, and provider outages
- Test console errors should show sanitized technical detail plus a plain-language explanation

---

## 12. Expected User Flows

### 12.1 Administrator Evaluates a Tool
1. Open Tool Catalog
2. Filter to `Requires setup`
3. Open tool workspace
4. Read simple description and risk badges
5. Complete setup checklist
6. Run validation test
7. Review health and decide whether to grant execution access

### 12.2 Supervisor Learns a Tool Before Enabling It
1. Open tool workspace
2. Read what the tool does and does not do
3. Run a safe example in `Try It`
4. Review sample output and guardrails
5. Decide whether to request/grant access for a agent

### 12.3 Auditor Reviews Tool Behavior
1. Open tool workspace in read-only mode
2. Review readiness, health, and recent execution history
3. Inspect blocked attempts and recent failures

---

## 13. Complexity Hotspots

1. **Per-tool variance** — some tools need credentials, others do not; the UI must feel consistent without forcing fake sameness.
2. **Authz layering** — workspace access, test access, setup access, and execution access must not be conflated.
3. **Health semantics** — avoid generic health checks that say little; each tool needs meaningful probes.
4. **Safe testing** — the test harness must help users without becoming an accidental bypass around production guardrails.
5. **Metadata ownership** — decide whether rich tool metadata lives on the tool class or in a separate registry.

---

## 14. Recommended Delivery Order

### Slice 1 — Catalog Foundation
- Add a Tool Catalog page and metadata registry
- Show simple descriptions, categories, readiness, and risk badges
- Gate page access with workspace authz

### Slice 2 — Per-Tool Workspace
- Add overview, setup, and health sections
- Start with Phase 1 tools only
- Reuse provider-manager visual patterns

### Slice 3 — Test Harness
- Add safe test/play surface for Phase 1 tools
- Store sanitized audit records for test runs

### Slice 4 — Policy and Observability
- Add explicit workspace/test/manage capabilities
- Add recent executions, blocked attempts, and health history

### Slice 5 — Broader Tool Families
- Extend the workspace model to memory, delegation, browser, and messaging tools

---

## 15. Open Design Questions

These should be resolved before implementation starts.

1. Should the per-tool workspace be a modal/drawer from the catalog, or a dedicated route per tool?
2. Should rich tool metadata live in the tool class contract, or in a separate UI metadata registry?
3. How much of the test harness should be reusable infrastructure versus tool-specific custom UI?
4. Which existing audit/event store should back recent executions and blocked attempts?
5. Which roles should receive `ai.tool_workspace.view`, `manage`, and `test` by default?

---

## 16. Proposed Outcome

When this plan is implemented, BLB will have a Tool Workspace that makes tool capability transparent instead of implicit: users will be able to discover tools, understand them through simple descriptions, configure them with confidence, test them safely, review health and history, and make better authorization decisions.
