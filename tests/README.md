# Tests

BLB uses [Pest 4](https://pestphp.com/) on top of PHPUnit.

## Directory Layout

```
tests/
├── Feature/           # Integration tests (HTTP, Livewire, database)
├── Unit/              # Isolated unit tests
├── Support/           # Shared helpers, fixtures, builders
├── Pest.php           # Global bindings and helpers
├── TestCase.php       # Base test case
└── TestingBaselineSeeder.php
```

## Extension Tests

Extension tests live inside the extension module at `extensions/{owner}/{module}/Tests/`. This keeps licensee tests co-located with licensee code and outside BLB's core test suite.

```
extensions/sb-group/quality/
└── Tests/
    ├── Feature/
    └── Unit/
```

Run extension tests with:

```bash
php artisan test extensions/sb-group/quality/Tests
```

**Guides:**

- [AGENTS.md](AGENTS.md) — test seeding, shared helpers, environment notes, quality guidelines
