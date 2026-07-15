# Contributing

Thank you for contributing to SeoKitBundle.

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

See [GITLAB_CI.md](GITLAB_CI.md) and `.cursor/rules/01-git-commits.mdc`.

## Quality checks

```bash
make qa
make phpstan
make test-coverage
make release-check
```

## Code of Conduct

This project follows the [Contributor Covenant](../CODE_OF_CONDUCT.md). Report issues to hectorfranco@nowo.tech.

## Pull requests

Use the GitHub PR template. Update `docs/CHANGELOG.md` for user-visible changes and `specs/001-baseline/code-inventory.md` when adding production files under `src/`.
