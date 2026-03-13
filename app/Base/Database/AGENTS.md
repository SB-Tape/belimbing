# Database Module (app/Base/Database)

This module provides **module-aware migration infrastructure** extending Laravel's migration commands. It enables selective migration/rollback by module and automatic seeder discovery.

**Full architecture:** [docs/architecture/database.md](../../../docs/architecture/database.md) — naming conventions, migration registry, table naming, dependency graph.

## Migration file names (brief)

- **Format:** `YYYY_MM_DD_HHMMSS_description.php`
- **Layer prefixes (year):** `0001` Laravel core · `0100` Base · `0200` Core · `0300+` Business · `2026+` Extensions
- **Module id:** Within a layer, `MM_DD` identifies the module (e.g. `0200_01_03_*` = Geonames). See the **Migration Registry** in `docs/architecture/database.md` for assigned prefixes and dependencies.
- **Hard rule:** For `app/Base/*` and `app/Modules/*/*`, do **not** use real calendar year prefixes. Use layered prefixes (`0100`, `0200`, `0300+`) only. Real years (`2026+`) are for extensions.

### Naming examples

- **Base module:** `0100_01_11_000000_create_base_authz_roles_table.php`
- **Core module:** `0200_01_20_000000_create_users_table.php`
- **Extension module:** `2026_01_15_000000_create_vendor_feature_table.php`

### Prefix reservation (required)

Before creating a new module migration series, reserve/confirm the module `MM_DD` prefix in the **Migration Registry** at `docs/architecture/database.md` to avoid collisions and to document dependencies.

## Key Components

### Commands (Override Laravel)

All commands support `--module=<name>` for selective operation:

| Command | Description |
|---------|-------------|
| `MigrateCommand` | Runs migrations with module filtering and seeder registry support |
| `RollbackCommand` | Rolls back migrations, optionally filtered by module |
| `ResetCommand` | Resets all migrations, optionally filtered by module |
| `RefreshCommand` | Refreshes migrations with module-aware seeding |
| `StatusCommand` | Shows migration status, optionally filtered by module |

```bash
# Default: all modules (module-first architecture)
php artisan migrate
# Or the following, which is equivalent:
php artisan migrate --module=*

# Module-specific operations (case-sensitive)
php artisan migrate --module=Geonames
php artisan migrate --module=Geonames,Company
php artisan migrate:rollback --module=Geonames
```

### SeederRegistry Model

Tracks seeder execution state. Seeders are registered by migrations using `RegistersSeeders` trait and executed automatically during `migrate --seed`.

**Statuses:** `pending` → `running` → `completed` | `failed` | `skipped`

### RegistersSeeders Trait

Used by migrations to register seeders for automatic execution:

```php
use App\Base\Database\RegistersSeeders;

return new class extends Migration
{
    use RegistersSeeders;

    public function up(): void
    {
        Schema::create('geonames_countries', ...);

        // Register seeder for automatic execution
        $this->registerSeeder(CountriesSeeder::class);
    }
};
```

**App-level seeders** (non-module): Same pattern as modules — the migration that creates the tables registers the seeder in `up()` and unregisters in `down()` (use `RegistersSeeders`). Migration in `database/migrations/`, seeder class in `database/seeders/`. They get `module_name`/`module_path` = null and run with `migrate --seed` in migration order. Do not add seeders to `DatabaseSeeder::run()`.

### Concerns

- **InteractsWithModuleOption**: Parses `--module` option (comma-delimited, case-sensitive)
- **InteractsWithModuleMigrations**: Loads migrations from module directories

## Module Auto-Discovery Paths

Migrations are discovered from:
- `app/Base/*/Database/Migrations/`
- `app/Modules/*/*/Database/Migrations/`

## Implementation Notes

### ServiceProvider Pattern

Commands are registered via Laravel's `extend()` method in a deferred service provider. This overrides Laravel's migration commands while preserving the container's deferred loading.

```php
// ServiceProvider.php
$this->app->extend('command.migrate', fn ($command, $app) =>
    new MigrateCommand($app['migrator'], $app['events'])
);
```

### Seeding Behavior

Seeder registration is done in migrations via `registerSeeder()`. Seeders under `app/Base/*/Database/Seeders/` and `app/Modules/*/*/Database/Seeders/` are also discovered when you pass `--seed`: any not yet in the registry are added then, so they run even if the migration did not call `registerSeeder()`. Plain `migrate` (no `--seed`) never runs seeders. If a migration does not register its seeder, pass `--seed` (e.g. below).

```bash
# Run all pending seeders (after migrations)
php artisan migrate --seed

# Seed only one module (case-sensitive)
php artisan migrate --seed --module=Company

# Run a single seeder (short form: Module/SeederClass or Module/Sub/SeederClass)
php artisan migrate --seed --seeder=Company/RelationshipTypeSeeder
# Or FQCN with single quotes so backslashes are preserved
php artisan migrate --seed --seeder='App\Modules\Core\Company\Database\Seeders\RelationshipTypeSeeder'
```

### Development vs. Production Seeders

| Category | Location | Naming | Registered in migration? |
|----------|----------|--------|--------------------------|
| **Production** | `Database/Seeders/` | `{Entity}Seeder` | Yes (`registerSeeder()`) |
| **Development** | `Database/Seeders/Dev/` | `Dev{Description}Seeder` | No (run explicitly) |

- **Production seeders** populate reference/config data needed in all environments.
- **Development seeders** create fake data for UI work and manual testing. They extend `App\Base\Database\Seeders\DevSeeder`, implement `seed()` (not `run()`), and live in `Dev/` subdirectory with a `Dev` class prefix. DevSeeder may only run when `APP_ENV=local` (throws otherwise).

```bash
# Run a dev seeder explicitly (note the Dev/ subdirectory in the path)
php artisan migrate --seed --seeder=Company/Dev/DevCompanyAddressSeeder
```

## Table Stability Registry

**All tables default to stable.** When a table is registered (via migration or auto-discovery), it starts with `is_stable = true`. This means a fresh BLB install has all tables stable out of the box — `migrate:fresh` preserves them by default.

**Stability is a development-only concept.** It controls whether `migrate:fresh` preserves a table's data. The stability column is hidden in the admin UI outside `APP_ENV=local` since `migrate:fresh` should never run in production/staging.

### How migrate:fresh interacts with stability

| Command | Behavior |
|---------|----------|
| `migrate:fresh --seed --dev` | Preserves stable tables, drops only unstable ones |
| `migrate:fresh --seed --dev --force-wipe` | Ignores stability — drops ALL tables (true nuclear reset) |

### When to mark a table unstable

Mark a table unstable before editing a migration that **alters its schema** (adding/removing/renaming columns, changing indexes, modifying column types):

```bash
# Via tinker (quick)
php artisan tinker --execute="App\Base\Database\Models\TableRegistry::query()->where('table_name', 'ai_providers')->update(['is_stable' => false, 'stabilized_at' => null, 'stabilized_by' => null]);"
```

Or toggle it off in the admin UI at `admin/system/tables` (local env only).

**Rule:** Never modify the schema of a stable table without first marking it unstable. `migrate:fresh` skips stable tables — if the schema is outdated, the migration will fail or produce silent data corruption.

### Custom/extension modules

New tables added by custom or extension modules also default to stable on registration. If a module is under active schema development, mark its tables unstable until the schema is finalized.

## Database Portability

**All database operations must be DB-agnostic.** Never use raw SQL that targets a specific database engine (MySQL, PostgreSQL, SQLite). Use Laravel's Schema Builder and Query Builder abstractions instead.

| Instead of | Use |
|------------|-----|
| `DB::statement('SET FOREIGN_KEY_CHECKS=0')` | `Schema::disableForeignKeyConstraints()` |
| `DB::statement('SET FOREIGN_KEY_CHECKS=1')` | `Schema::enableForeignKeyConstraints()` |
| `DB::statement("SET session_replication_role = 'replica'")` | `Schema::disableForeignKeyConstraints()` |
| `DB::statement('DROP TABLE ...')` | `Schema::dropIfExists('table')` |
| `DB::statement('ALTER TABLE ...')` | `Schema::table('table', fn ($t) => ...)` |
| `DB::statement('CREATE INDEX ...')` | Use `$table->index()` in migrations |

If a raw statement is truly unavoidable, document why and guard it with a driver check:

```php
$driver = DB::connection()->getDriverName(); // 'pgsql', 'mysql', 'sqlite'
```

## Database ID Standards

- **Primary Keys**: Use `id()` (UNSIGNED BIGINT auto-increment)
- **Foreign Keys**: Use `foreignId()` (UNSIGNED BIGINT)
- **No UUIDs** for primary keys unless explicitly required

## Development Workflow

### Reseeding in Development

**Primary approach — add `--dev` to any migrate command:**

```bash
# Full wipe + reseed (most common)
php artisan migrate:fresh --seed --dev

# Layer dev data onto current DB (no wipe)
php artisan migrate --seed --dev

# Dev seed only one module
php artisan migrate --seed --dev --module=Company
```

`--dev` implies `--seed`, creates the licensee company (id=1) if needed, then runs all dev seeders in dependency order. Only works when `APP_ENV=local`.

**Manual fallback** (if you need more control, e.g., only re-run specific dev seeders):

```bash
# Step 1: Fresh migrate + production seeders
php artisan migrate:fresh --seed

# Step 2: Create licensee company (required before any dev seeder)
php artisan tinker --execute="App\Modules\Core\Company\Models\Company::create(['name' => 'My Company', 'status' => 'active']);"

# Step 3: Run dev seeders in dependency order
php artisan migrate --seed --seeder=Company/Dev/DevCompanyAddressSeeder
php artisan migrate --seed --seeder=Company/Dev/DevDepartmentSeeder
php artisan migrate --seed --seeder=Employee/Dev/DevEmployeeSeeder
php artisan migrate --seed --seeder=User/Dev/DevUserSeeder
php artisan migrate --seed --seeder='App\Base\Authz\Database\Seeders\Dev\DevAuthzCompanyAssignmentSeeder'
```

**⚠️ Agent rule:** After running `migrate:fresh`, `migrate:reset`, or any operation that truncates tables on the local dev database, **always add `--dev`** (or run the manual steps above) to restore working dev data. An empty database is not usable for local testing.

If automated tests run on in-memory SQLite (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), this rule does not apply because those test refreshes do not modify the local dev database.

### Rollback by Batch (Preserve Data)

When you have development data to preserve, use batch rollback instead of migrate:fresh:

```bash
# Check batch numbers
php artisan migrate:status

# Rollback specific batch
php artisan migrate:rollback --batch=2

# Edit migrations, then re-run
php artisan migrate --seed
```

### Module-Specific Testing

```bash
# Rollback and re-run single module
php artisan migrate:rollback --module=Geonames
php artisan migrate --module=Geonames --seed
```

### Refactoring Dependencies

**Key Principles:**
1. Base modules first
2. Core modules next
3. Business modules next
4. Extension modules last
5. Migration dates enforce load order
6. Foreign key constraints respect dependency order
7. No circular dependencies allowed

If you need to break a circular dependency:

1. **Use nullable foreign keys** with deferred constraints
2. **Split into two migrations** (create table, then add constraint)
3. **Use pivot tables** for many-to-many relationships
4. **Redesign the relationship** if truly circular
