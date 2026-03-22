# FEAT-DISCOVERY

Intent: extend BLB auto-discovery infrastructure instead of adding manual module registration.

## When To Use

- Adding framework-level discovery for routes, menus, providers, or Livewire components.
- Standardizing how modules are detected from path conventions.

## Do Not Use When

- Module can use existing discovery patterns without framework changes.
- Change is feature-local and does not affect framework bootstrapping.

## Minimal File Pack

- `app/Base/Foundation/Providers/ProviderRegistry.php`
- `app/Base/Routing/RouteDiscoveryService.php`
- `app/Base/Menu/Services/MenuDiscoveryService.php`

## Reference Shape

- Discovery services expose `discover()` returning structured paths or mappings.
- Service providers consume discovery output and register resources.
- Sorting and deterministic order are enforced before registration.
- Validation runs at registry level (example: menu circular parent checks).

## Module Config Discovery Convention

Framework-level modules auto-discover `Config/{name}.php` from all Base and Module directories, merging module-declared values into the framework config. This keeps module-specific concerns local — modules never edit framework configs.

| Framework Module | Discovered File | Merged Keys | Discovery Location |
|---|---|---|---|
| Authz | `Config/authz.php` | `capabilities`, `roles` | `Authz\ServiceProvider::discoverModuleAuthzConfigs()` |
| Menu | `Config/menu.php` | `items` | `Menu\Services\MenuDiscoveryService` |
| Audit | `Config/audit.php` | `exclude_models` | `Audit\ServiceProvider::discoverModuleAuditConfigs()` |

**Principle:** When a module needs to influence framework behavior (e.g., exclude a model from auditing, declare capabilities, add menu items), it declares a local `Config/{name}.php` returning only the keys it cares about. The framework module's discovery method globs `app/Base/*/Config/{name}.php` and `app/Modules/*/*/Config/{name}.php`, skipping its own base config, and merges arrays additively.

**When adding a new discoverable config key to a framework module:**

1. Add the merge logic in the framework module's ServiceProvider (follow `discoverModuleAuthzConfigs` pattern).
2. Update this table.
3. Add the new config file to the auto-discovery list in `feat-new-business-module.md`.

## Required Invariants

- Deterministic load order across runs.
- Independent module boot where possible; no hidden provider order assumptions.
- Fail fast on invalid provider classes.
- Prefer contracts and adapters over direct cross-module coupling.

## Implementation Skeleton

```php
public function discover(): array
{
    $items = [];

    foreach ($this->scanPatterns as $pattern) {
        foreach (glob(base_path($pattern)) ?: [] as $path) {
            $items[] = $path;
        }
    }

    sort($items);

    return $items;
}
```

## Test Checklist

- Newly placed module file is discovered without manual registration.
- Discovery order is stable.
- Invalid file/class handling fails clearly or logs deterministic warning.
- Existing modules remain loadable after changes.

## Common Pitfalls

- Adding one-off manual registration that bypasses discovery contract.
- Non-deterministic ordering causing flaky boot behavior.
- Scanning overly broad paths that increase startup cost.
