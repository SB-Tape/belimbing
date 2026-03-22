# Scripts Agent Guide

## Scope

Applies to shell entrypoints and setup helpers under `scripts/`.

## Sonar / Shell Guardrails

- Treat shell helpers as one of three kinds and preserve their semantics:
  - **predicate** helpers return the exit status of a command or test
  - **pass-through** helpers return the exit status of the tool they invoke
  - **terminal** helpers end with `exit`
- Do **not** add a trailing `return 0` to predicate, pass-through, or terminal helpers just to satisfy a rule. That masks failures and changes behavior.
- Bind positional parameters to named locals near the top of the function when they are reused or fed into nested commands:
  - `local project_root=$1`
  - `local first_arg=${1:-}`
- Add a default `*) ;;` arm to `case` statements unless the branch is intentionally exhaustive and documented.
- Collapse nested `if` statements when the merged condition stays readable; prefer a single guard over two one-line wrappers.
- Extract repeated literals that appear three or more times into uppercase readonly variables when it improves clarity:
  - version fallbacks like `'unknown'`
  - repeated inline commands like `'echo PHP_VERSION;'`
- Prefer small helper functions or readonly variables over copying the same command pipeline in multiple places.
- After editing a script, run `bash -n` on the touched files before considering the work done.

## Safety

- Keep failure states explicit. Avoid `|| true` unless best-effort behavior is intentional and documented nearby.
- When a command is expected to fail sometimes, comment why the failure is acceptable.
- Do not swallow tool failures that callers rely on for control flow.
