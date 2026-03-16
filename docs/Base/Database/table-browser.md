# Table Browser

**Module:** `app/Base/Database`
**Routes:** `admin/system/database-tables`
**Last Updated:** 2026-03-15

## Overview

The Table Browser is a PHPMyAdmin-inspired, read-only database table viewer built into BLB's admin panel. It displays the contents of any table registered in the `TableRegistry`, with search, sortable columns, pagination, foreign key navigation, and a collapsible table navigator sidebar grouped by module.

All data access goes through Laravel's Schema Builder and Query Builder — fully DB-agnostic.

## Pages

### Index (`admin/system/database-tables`)

Lists all registered tables with search filtering by table name or module. Displays:

- Table name
- Module name
- Row count
- Stability status (toggle, visible only when `APP_ENV=local`)

**Component:** `App\Base\Database\Livewire\DatabaseTables\Index`

### Show (`admin/system/database-tables/{tableName}`)

Displays the rows and column metadata of a single table.

**Component:** `App\Base\Database\Livewire\DatabaseTables\Show`

## Features

### Table Navigator Sidebar

A collapsible left sidebar lists all registered tables grouped by module. Features:

- **Module groups** — Collapsible sections with table counts, sorted alphabetically.
- **Recently viewed** — The last 8 visited tables appear in a pinned "Recent" section at the top (session-stored).
- **Client-side filter** — An Alpine.js-powered search input filters tables by name instantly without a server round-trip.
- **Resizable** — The sidebar width is draggable (160–320 px) and persisted to `localStorage`.
- **Toggle state** — Open/closed state is saved to the session so it persists across navigation.

### Search

A live search input (`wire:model.live.debounce.300ms`) filters rows using `LIKE` queries across all string/text columns (identified by `type_name` containing `char`, `text`, `varchar`, or `string`). Pagination resets automatically on search via the `ResetsPaginationOnSearch` concern.

The `search` query parameter is also read from the URL on mount, enabling FK drill-down links to pre-populate a search.

### Sortable Columns

Clicking a column header sorts by that column. Clicking again toggles `asc`/`desc`. The sort column is validated against the schema before being applied to the query.

### Foreign Key Navigation

Both outgoing and incoming foreign key relationships are displayed:

- **Outgoing** ("References:") — Pill-shaped links above the table showing tables this table references. Column headers with FK relationships show a link icon.
- **Incoming** ("Referenced by:") — Pill-shaped links showing tables that reference this table.
- **Cell links** — FK column values are clickable, navigating to the referenced table with the value pre-filled in search.

### UTC → Local Time Toggle

A toggle button in the table toolbar converts timestamp/datetime column values to the browser's local timezone using JavaScript's `Date.toLocaleString()`. The conversion is purely client-side via Alpine.js:

- Column types are identified server-side by checking `type_name` for `timestamp` or `datetime`.
- Only non-null cells in matching columns get the Alpine `x-effect` directive.
- The original value is preserved in a `data-raw` attribute for clean toggling.

The format follows the browser's locale (e.g., German users see `15.03.2026, 14:30:00`, Japanese users see `2026/3/15 14:30:00`).

### Cell Formatting

The `formatCell()` method handles display formatting. A **Raw/Formatted** toggle in the toolbar switches between human-friendly symbols and literal database values:

| Type | Formatted (default) | Raw |
|------|---------------------|-----|
| `null` | `—` (em dash) | `NULL` |
| `bool` / `boolean` | `✓` or `✗` | `true` or `false` |
| Long strings (>120 chars) | Truncated with `…`, full value in `title` tooltip | Same |
| Everything else | Raw string value | Same |

The toggle is a Livewire property (`rawValues`) — server-side re-render, no Alpine state needed.

## Key Components

### TableInspector Service

`App\Base\Database\Services\TableInspector` — the read-only data access layer. All database interaction goes through this service rather than the Livewire component directly.

| Method | Purpose |
|--------|---------|
| `columns(string $table)` | Column metadata via `Schema::getColumns()` |
| `rows(...)` | Paginated rows with optional search and sort |
| `rowCount(string $table)` | Total row count |
| `searchableColumns(string $table)` | Columns eligible for LIKE search |
| `foreignKeys(string $table)` | Outgoing and incoming FK relationships |
| `allTablesGroupedByModule()` | All registered tables grouped by module name |
| `isRegistered(string $table)` | Check if a table is in the `TableRegistry` |

### Table Registration

Only tables registered in `TableRegistry` (the `base_table_registry` table) are browsable. Tables are registered automatically during migrations — see `app/Base/Database/AGENTS.md` for details.

## Security

- **Registry guard:** Only tables present in `TableRegistry` can be viewed. Unregistered tables return 404.
- **Read-only:** No write operations. The browser cannot modify data.
- **Auth required:** All routes are wrapped in the `auth` middleware.

## File Structure

```
app/Base/Database/
├── Livewire/
│   └── DatabaseTables/
│       ├── Index.php              # Table listing with stability toggle
│       └── Show.php               # Table row viewer
├── Services/
│   └── TableInspector.php         # Read-only data access service
├── Models/
│   └── TableRegistry.php          # Registry model
├── Routes/
│   └── web.php                    # Route definitions
└── ServiceProvider.php

resources/core/views/livewire/admin/system/database-tables/
├── index.blade.php
└── show.blade.php
```

## Routes

| Route | Name | Component |
|-------|------|-----------|
| `GET admin/system/database-tables` | `admin.system.database-tables.index` | `DatabaseTables\Index` |
| `GET admin/system/database-tables/{tableName}` | `admin.system.database-tables.show` | `DatabaseTables\Show` |
