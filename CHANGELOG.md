# Changelog

All notable changes to `git-worktree-cli` will be documented in this file.

## v1.0.8 - 2026-08-14

Release v1.0.8

## v1.0.7 - 2026-07-24

Release v1.0.7

## 1.0.6 - 2026-06-24

### Fixed

- `add`: removed the fixed 60s timeout on recursive submodule init. `git submodule update --init --recursive` is network-bound and could exceed the timeout for large or numerous submodules; it now runs without a timeout.

## 1.0.5 - 2026-06-23

### Added

- `remove <branch|path>` — remove a single worktree by branch name or path, regardless of merge status (refuses the main worktree, prompts on dirty state). Supports `--yes`, `--force`, `--delete-branch`.
- `prune` — wrap `git worktree prune` to clear stale administrative records (`--dry-run` supported).
- `path <branch|path>` — print a worktree's absolute path for shell `cd` integration.
- `shell-init [bash|zsh|fish]` — print a `gwt` shell function so `gwt cd <branch>` changes directory.
- `open <branch|path>` — open a worktree in `$VISUAL`/`$EDITOR` (falls back to `code`); override with `--editor`.
- `add --copy=<path>` — copy untracked files (e.g. `.env`) from the main worktree into the new one (repeatable).
- `add --run=<command>` — run setup commands inside the new worktree after creation (repeatable).
- Per-repo `add` config (`add.copy`, `add.run`) read on every `add`; surfaced in `config:show`. Skip with `--no-config`.
- `list-worktrees --status` — include merge status against the main branch plus a clean/dirty flag.

## 1.0.4 - 2026-06-23

### Added

- `add` now recursively initializes submodules (`git submodule update --init --recursive`) in the new worktree when the repo declares a `.gitmodules`. Use `--no-submodules` to opt out.

## 1.0.3 - 2026-06-06

Validate the version.txt release flow (no tag-move). Embedded version resolved from version.txt; release tag lands on a commit whose PHAR already embeds 1.0.3.

## 1.0.2 - 2026-06-04

Protect branches from `clean` removal via `--protect` flag (repeatable, glob-aware) and a per-repo config file at ~/.config/git-worktree/<owner>-<repo>.json. New `config:show|protect|unprotect|enable|disable` commands. `--no-config` skips the file per run.

## 1.0.1 - 2026-05-07

### What's Changed

- **Fix (`add` command)**: replaced the `Laravel\Prompts\confirm()` path with `$this->confirm()`. The previous code short-circuited when `$this->input->isInteractive()` returned false (GitHub Actions runner), so under CI the create-branch prompt was never displayed and `expectsConfirmation` in the test suite failed. `$this->confirm()` integrates cleanly with Pest's `expectsConfirmation` regardless of TTY state.

#### Upgrading

```bash
git-worktree self-update
# or
composer global update jeffersongoncalves/git-worktree-cli







```
## 1.0.0 - 2026-05-07

### What's Changed

- **Feature**: new `add` command creates a worktree for a new or existing branch. Worktree path defaults to `<repo-parent>/<repo>-<suffix>` where the suffix is the segment after the last `/` in the branch name (or the full branch name when no slash). Validates the branch on the remote via `ls-remote`; tracks `origin/<branch>` when found, otherwise prompts to create a brand-new branch from the auto-detected main.

#### Usage

```bash
git-worktree add feature
git-worktree add feature/foo          # → <repo>-foo
git-worktree add my-feat --yes        # create new branch from main
git-worktree add my-feat --target=/tmp/wt-myfeat








```
#### Upgrading

```bash
git-worktree self-update
# or
composer global update jeffersongoncalves/git-worktree-cli








```
## v0.0.5 - 2026-04-16

### What's Changed

- **Fix (release pipeline)**: `build.yml` now resolves the build tag from `workflow_run.head_branch` instead of `git describe --tags --abbrev=0`. After Update Changelog commits the version bump, two tags (previous and current release) share a commit and `git describe` returned the older one, so the rebuilt PHAR was embedded with the wrong version and the tag-move step moved the wrong tag. Regular push builds still use `git describe`.

**v0.0.4 is broken** — its attached PHAR is fine (built by publish-phar.yml with the correct version), but `composer require v0.0.4` resolves to a PHAR that still reports v0.0.3 because of the bug above. Upgrade to v0.0.5.

## v0.0.4 - 2026-04-16

### What's Changed

- **Fix (release pipeline)**: the tag-move step in `build.yml` now correctly targets `main` during `workflow_run`-triggered rebuilds. The previous version fell back to `github.event.workflow_run.head_branch` which, for release events, resolves to the tag name — the commit landed on a detached HEAD and the push was rejected as a ref collision with the existing tag. This release validates the fully automated flow end-to-end.

## v0.0.3 - 2026-04-16

### What's Changed

- **Fix (release pipeline)**: the release tag now automatically moves to the rebuilt-PHAR commit after a release. Previously the tag stayed on the pre-rebuild commit, so `composer require` pulled a PHAR with the previous version embedded — users had to run `git-worktree self-update` after install to get the correct version.

### Upgrading

```bash
git-worktree self-update
# or
composer global update jeffersongoncalves/git-worktree-cli











```
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
