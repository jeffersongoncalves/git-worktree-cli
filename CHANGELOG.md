# Changelog

All notable changes to `git-worktree-cli` will be documented in this file.

## v0.0.2 - 2026-04-16

### What's Changed

- **Fix**: fresh PHAR installs no longer report `unreleased`. The `build.yml` workflow now also runs on release publish, rebuilds the PHAR against the new tag, and commits `builds/git-worktree` back to `main`, so `composer global require` users get the correct version baked in from the start.
- **Fix (tests)**: tests now run correctly inside the project's own git checkout. Two assertions that used `tests/tmp/` to stand in for a non-git path were rewritten (and `GitWorktreeService::isGitRepository()` short-circuits on non-existent paths).

### Upgrading

```bash
# via self-update (already installed)
git-worktree self-update

# or via Composer
composer global update jeffersongoncalves/git-worktree-cli

```
## v0.0.1 - 2026-04-16

Initial release. CLI to audit git worktrees and report whether their branches have been merged into the main branch, with a `clean` command to remove merged worktrees and a `self-update` mechanism for PHAR installs.
