# UI Restructure -- Tracking

> **Branch:** `main` (backup: `ui-with-volt`)
> **Architecture doc:** `docs/architecture/ui-layout.md`
> **Created:** 2026-03-09

## Summary

Major UI restructuring: core/licensee directory separation, Volt→standard Livewire migration, layout zone changes, and new components.

## Phase 1: Structural Move (Foundation) ✅

- [x] Move `resources/views/` to `resources/core/views/`
- [x] Move `resources/css/` to `resources/core/css/`
- [x] Move `resources/js/` to `resources/core/js/`
- [x] Update `vite.config.js` -- input paths, refresh globs, VITE_THEME_DIR support
- [x] Create `config/view.php` -- override Laravel default view paths to `resources/core/views`
- [x] Update `config/livewire.php` -- all `resource_path()` calls
- [x] Update `app/Providers/VoltServiceProvider.php` -- resource paths (or remove if Volt fully dropped)
- [x] Split `resources/css/app.css` into `resources/core/css/tokens.css` + `resources/core/css/components.css`
- [x] Create new `resources/app.css` entry point importing core then licensee
- [x] Update `@source` directives for new relative paths
- [x] Update `@vite` in `resources/core/views/partials/head.blade.php`
- [x] Update `stubs/livewire.layout.stub` @vite reference
- [x] Update AGENTS.md files (root, resources/views/, docs/, app/Modules/Core/AI/)
- [x] Update `.cursor/rules/ui-architect.mdc`
- [x] Create licensee `resources/custom/` scaffold (empty css/views/js dirs)
- [x] Verify build works (`npm run build`)
- [x] Verify `php artisan serve` works

## Phase 2: Layout Zone Changes ✅

- [x] Remove Impersonation Banner component (zone A eliminated)
- [x] Move impersonation warning to Status Bar (`text-status-danger`)
- [x] Update Top Bar: remove search placeholder, add Lara chat trigger
- [x] Update Status Bar: remove time placeholder, add impersonation warning
- [x] Set up `wire:navigate` for shell persistence (only Main Content swaps)
- [x] Implement drag-resizable sidebar with icon rail snap
  - [x] Drag handle (invisible until hover, `col-resize` cursor)
  - [x] Continuous width range (`w-14` to `w-72`)
  - [x] Auto-collapse to icon rail below threshold
  - [x] Persist width to `localStorage`
  - [x] Toggle button snaps between rail and last width
- [x] Implement sidebar pinned section
  - [x] Pinned items section at top of sidebar
  - [x] Drag-reorder within pinned section (HTML5 drag-and-drop, Alpine handlers)
  - [x] Pin/unpin action on menu items (hover icon button)
  - [x] Per-user storage (server-side, ordered list of menu item IDs)
  - [x] Migration for user pinned items (`user_pinned_menu_items` table)
  - [x] Model (`UserPinnedMenuItem`), controller (`PinnedMenuItemController`), routes
  - [x] Optimistic UI with server reconciliation (fetch-based toggle/reorder)
  - [x] CSRF meta tag added to head partial
- [x] Alphabetically order main menu items (remove manual position)

## Phase 3: New Components (~80%)

- [x] Build `<x-ui.tabs>` page-level tabs component
  - [x] Alpine.js client-side tab switching
  - [x] Active tab reflected in URL (hash)
  - [x] Accessible (ARIA roles, keyboard navigation)
- [x] Implement Lara Chat mobile full-screen takeover
  - [x] Full viewport below Top Bar on small screens
  - [x] Close button dismissal
- [ ] Create licensee directory scaffolding command (`blb:theme:init`)

## Phase 4: Documentation Updates ✅

- [x] `docs/architecture/file-structure.md` -- already up to date (no old paths found)
- [x] `docs/architecture/authorization.md` -- impersonation banner ref updated to status bar
- [x] `docs/architecture/broadcasting.md` -- echo.js path refs (discovered during sweep)
- [x] `docs/guides/theming.md` -- all 23 path references + override model
- [x] `docs/development/theme-customization.md` -- all 20 path references
- [x] `docs/development/palette-preference.md` -- CSS path refs
- [x] `docs/development/agent-context.md` -- view path refs
- [x] `docs/guides/development-setup.md` -- Vite config refs
- [x] `docs/tutorials/vite-roles.md` -- all path references
- [x] `docs/tutorials/volt-and-blade.md` -- view path refs (renamed to `livewire-and-blade.md`)
- [x] `docs/Base/Menu/remove-maryui-daisyui.md` -- all 13 path refs
- [x] `docs/Base/Menu/PRD.md` -- path ref
- [x] `docs/modules/menu-prd.md` -- path ref
- [x] `docs/todo/tool-workspace-ui.md` -- path ref
- [x] `docs/architecture/caddy-development-setup.md` -- Vite config refs
- [x] Regenerate IDE helper files (`.phpstorm.meta.php`, `_ide_helper.php`, `_ide_helper_models.php`)

## Phase 5: Volt → Standard Livewire Migration ✅

Migrated all 60 Volt single-file components to standard Livewire class+Blade pairs.
PHP classes in module namespaces, Blade templates unchanged at `resources/core/views/livewire/`.

### Component Migration (60 components across 8 batches) ✅

- [x] Auth module (6): Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword
- [x] Settings module (4): Profile, Password, Appearance, DeleteUserForm
- [x] Users module (4): Index, Create, Show, Edit
- [x] Companies module (7): Index, Create, Show, LegalEntityTypes, DepartmentTypes, Relationships, Departments
- [x] Companies/Setup (1): Licensee
- [x] Employees module (3): Index, Create, Show
- [x] EmployeeTypes module (3): Index, Create, Edit
- [x] Address module (3): Index, Create, Show
- [x] Geonames module (3): Countries/Index, Admin1/Index, Postcodes/Index
- [x] AI module (10): LaraChatOverlay, Playground, Providers, Tools, Providers/ConnectWizard, Providers/Catalog, Providers/Manager, Tools/Catalog, Tools/Workspace, Setup/Lara
- [x] Authz module (5): Roles/Index, Roles/Create, Roles/Show, Capabilities/Index, PrincipalRoles/Index, PrincipalCapabilities/Index, DecisionLogs/Index
- [x] System modules (9): Schedule/ScheduledTasks/Index, Session/Sessions/Index, System/Info/Index, Cache/CacheManagement/Index, Queue/Jobs/Index, Queue/FailedJobs/Index, Queue/JobBatches/Index, Log/Logs/Index, Database/Migrations/Index, Database/Seeders/Index

### Route Migration ✅

- [x] All 14 route files updated from `Volt::route(...)` to `Route::get(..., ClassName::class)`

### Test Migration ✅

- [x] 6 test files migrated from `Volt::test('name')` to `Livewire::test(ClassName::class)`
- [x] Remaining tests use string-based `Livewire::test('name')` (resolved by discovery service)

### Volt Package Removal ✅

- [x] Delete `app/Providers/VoltServiceProvider.php`
- [x] Remove `VoltServiceProvider` from `bootstrap/providers.php`
- [x] `composer remove livewire/volt` + `composer require livewire/livewire` (re-add as direct dep)
- [x] Update `config/livewire.php` (`make_command.type` → `'class'`, `emoji` → `false`)
- [x] Update `composer.json` description/keywords
- [x] Regenerate IDE helper files

### Component Auto-Discovery Infrastructure ✅

- [x] Create `app/Base/Livewire/ComponentDiscoveryService.php` — scans `app/Base/*/Livewire/` and `app/Modules/*/*/Livewire/`, maps component names to FQCNs via `view('livewire.xxx')` parsing
- [x] Create `app/Base/Livewire/ServiceProvider.php` — registers singleton + calls `Livewire::component()` for each discovered component in `boot()`
- [x] Auto-discovered by `ProviderRegistry` (follows `app/Base/*/ServiceProvider.php` convention)
- [x] All 615 tests pass (0 failures)

### Documentation Updates

- [x] Update `docs/todo/ui-restructure.md` (this file)
- [x] Rewrite `docs/tutorials/livewire-and-blade.md` for standard Livewire patterns (renamed from `volt-and-blade.md`)

## Decisions Log

| Decision | Rationale |
|----------|-----------|
| No Volt | Collapses controller/view boundary; blocks independent licensee overrides; agent convenience irrelevant |
| `resources/core/` + `resources/extensions/{licensee}/` | Clear ownership boundary; safe upgrades; visible customization |
| `.env` VITE_THEME_DIR | Bridge between Vite (build-time) and PHP (runtime); default `custom` |
| Impersonation in Status Bar | Consolidates system warnings in one zone; eliminates dedicated banner zone |
| Lara in Top Bar | Replaces search placeholder; gives AI assistant prominent position |
| Livewire `wire:navigate` | Already in stack; morphs DOM preserving Alpine state in shell |
| Drag-resizable sidebar | Continuous width + icon rail snap; drag handle invisible until hover |
| Pinned menu section | User-curated quick access; drag-reorderable; main menu stays alphabetical |
| Page-level tabs only | Solves complex model UX; no application-level multi-screen tabs |
| Lara Chat mobile fullscreen | Floating overlay unusable on small screens |
| Tabs use URL hash | `history.replaceState` with `#tab-id`; survives refresh; responds to back/forward via `hashchange` |
| PHP classes in module namespaces | `app/Modules/Core/<Module>/Livewire/` or `app/Base/<Module>/Livewire/`; domain-aligned; licensees override Blade only |
| Component name from `view()` call | `view('livewire.xxx')` → component name `xxx`; avoids brittle convention-based mapping |
| Auto-discovery ServiceProvider | Follows `app/Base/*/ServiceProvider.php` pattern; no manual component registration needed |
| Singleton discovery (no cache) | ~60 files scanned once per boot; bounded cost; caching deferred until production profiling |
