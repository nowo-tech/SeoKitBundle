# Spec-driven development

SeoKitBundle uses three layers of specification:

1. **Product spec** — `specs/001-baseline/spec.md` (user stories, FR-* requirements)
2. **Code inventory** — `specs/001-baseline/code-inventory.md` (100% `src/` traceability)
3. **Integrator docs** — `docs/CONFIGURATION.md`, `docs/USAGE.md`, etc.

## Workflow

1. Clarify behavior in the baseline spec (or a new `specs/00N-*` folder).
2. Update `code-inventory.md` when adding production files.
3. Implement with tests (`make test`, `make phpstan`).
4. Update `CHANGELOG.md` / `UPGRADING.md` for releases.
5. Validate with `make release-check`.

## REQ-* traceability

| ID | Where enforced |
| --- | --- |
| REQ-GIT-001 | `make check-no-cursor-coauthor`, CI `git-hygiene` job |
| REQ-MAKE-008 | `make update-deps` via shared Makefile include |
| REQ-DOCS-002 | README `## Documentation` section |
| REQ-TEST-001 | PHPUnit + CI badge |

## Engram

Use [Engram](ENGRAM.md) to persist decisions that span multiple sessions.

## GitHub Spec Kit

See [SPEC-KIT.md](SPEC-KIT.md) for Cursor Agent skills and `/speckit.*` commands.
