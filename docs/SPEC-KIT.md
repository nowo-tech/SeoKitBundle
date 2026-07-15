# GitHub Spec Kit

This bundle is prepared for [GitHub Spec Kit](https://github.com/github/spec-kit) with Cursor Agent integration.

## Baseline artifacts

| Path | Purpose |
| --- | --- |
| `.specify/memory/constitution.md` | Project principles |
| `specs/001-baseline/spec.md` | Product specification |
| `specs/001-baseline/code-inventory.md` | Full `src/` inventory |

## Cursor skills

When Spec Kit is initialized (`specify init --integration cursor-agent`), skills appear under `.cursor/skills/speckit-*`:

- `/speckit.specify` — draft or refine specs
- `/speckit.plan` — implementation plan
- `/speckit.tasks` — task breakdown
- `/speckit.implement` — guided implementation
- `/speckit.analyze` — consistency check against constitution

## Adding a new feature spec

1. Create `specs/002-my-feature/spec.md` from the baseline template.
2. Link new `src/` files in `code-inventory.md`.
3. Reference FR-* IDs in tests or docstrings where helpful.

## Related

- [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md)
- [constitution.md](../.specify/memory/constitution.md)
