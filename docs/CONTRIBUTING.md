# Contributing

Thank you for contributing to SeoKitBundle.

## Table of contents

- [Setup](#setup)
- [Git hygiene (REQ-GIT-001)](#git-hygiene-req-git-001)
- [Quality checks](#quality-checks)
- [End-to-end tests and screenshots (REQ-DEMO-013)](#end-to-end-tests-and-screenshots-req-demo-013)
- [Code of Conduct](#code-of-conduct)
- [Pull requests](#pull-requests)
- [AI co-author trailers](#ai-co-author-trailers)
- [Cursor Agent (REQ-IDE-006)](#cursor-agent-req-ide-006)

## Setup

```bash
make up
make install
make setup-hooks
```

`make setup-hooks` installs `.githooks/commit-msg`, which strips Cursor co-author trailers before commits are created.

## Git hygiene (REQ-GIT-001)

Never add `Co-authored-by: Cursor <cursoragent@cursor.com>` to commit messages.

Verify history:

```bash
make check-no-cursor-coauthor
```

If CI fails because trailers were already pushed:

```bash
make strip-cursor-coauthor-from-history
git push --force-with-lease origin main
```

See [GITHUB_CI.md](GITHUB_CI.md) and `.cursor/rules/01-git-commits.mdc`.

## Quality checks

```bash
make qa
make phpstan
make igor
make test-coverage
make release-check
```

## End-to-end tests and screenshots (REQ-DEMO-013)

The Symfony 8 demo ships a Playwright suite in `demo/symfony8/e2e/` (official Playwright image, nothing to install on the host):

```bash
make -C demo/symfony8 test-e2e          # starts the demo if needed, runs the happy-path specs
make -C demo/symfony8 demo-screenshots  # recaptures docs/images/demo/*.webp (PNG → WebP via sharp)
```

Refresh the screenshots when the demo UI changes and commit only the `.webp` files.

## Code of Conduct

This project follows the [Contributor Covenant](../CODE_OF_CONDUCT.md). Report issues to hectorfranco@nowo.tech.

## Pull requests

Use the GitHub PR template. Update `docs/CHANGELOG.md` for user-visible changes and `specs/001-baseline/code-inventory.md` when adding production files under `src/`.

## AI co-author trailers

Do **not** add AI co-author trailers to commit messages: neither `Co-authored-by: Cursor` / `cursoragent@cursor.com` nor `Co-Authored-By: Claude …` / `noreply@anthropic.com` (REQ-GIT-001). Run `make setup-hooks` so the `commit-msg` hook strips them.

## Cursor Agent (REQ-IDE-006)

Contributors using Cursor Agent on this repository should:

1. **Submit with Ctrl+Enter** (macOS: **⌘+Enter**). **Enter** inserts a newline; **Ctrl+Enter** / **⌘+Enter** submits the prompt. UI: **Cursor Settings → Agents / Chat → Submit with Ctrl + Enter**.
2. **Queue follow-ups** while the agent is running. In user `settings.json` (not a repo file):

```json
{
  "cursor.composer.queueMessageDefaultBehavior": "queue"
}
```

Do **not** use `"steer"` as the org default: steer interrupts the running agent as soon as a follow-up is sent.
