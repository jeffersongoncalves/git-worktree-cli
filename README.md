<div class="filament-hidden">

![Git Worktree CLI](https://raw.githubusercontent.com/jeffersongoncalves/git-worktree-cli/main/art/jeffersongoncalves-git-worktree-cli.png)

</div>

# git-worktree-cli

CLI tool to audit git worktrees in a repository and report whether each
worktree's branch has already been merged into the main branch. Includes
a `clean` command to remove merged worktrees and keep the workspace tidy.

Built with [Laravel Zero](https://laravel-zero.com) and modeled on the
other CLIs in this monorepo.

## Requirements

- PHP `^8.2`
- `git` available on `PATH`
- A git repository with at least one linked worktree

## Install

### Global (recommended)

```bash
composer global require jeffersongoncalves/git-worktree-cli
```

The binary `git-worktree` will be on your `PATH` as long as Composer's
global `vendor/bin` is in it.

### From source

```bash
git clone https://github.com/jeffersongoncalves/git-worktree-cli.git
cd git-worktree-cli
composer install
```

## Usage

```bash
# Audit the current directory (the default command)
git-worktree

# Audit a specific repo
git-worktree check /path/to/repo

# Force a specific main branch (otherwise auto-detected)
git-worktree check --main=develop

# Only show worktree branches that are NOT merged
git-worktree check --only-unmerged
```

### Clean merged worktrees

```bash
# Preview — doesn't touch anything
git-worktree clean --dry-run

# Prompt to confirm, then remove
git-worktree clean

# Skip confirmation + also delete the local branch
git-worktree clean --yes --delete-branch

# Force removal (worktrees with dirty state, branches with -D)
git-worktree clean --yes --delete-branch --force

# Only remove branches directly merged (exclude squash/rebase detection)
git-worktree clean --strict
```

### List worktrees

```bash
git-worktree list-worktrees
```

### Keep the CLI up to date

When installed from the released PHAR, self-update from the terminal:

```bash
git-worktree self-update          # download and install the latest release
git-worktree self-update --check  # only check, don't install
```

When installed via Composer, use Composer to update:

```bash
composer global update jeffersongoncalves/git-worktree-cli
```

## How the merge check works

For each linked worktree (the main worktree and bare repos are skipped)
the tool inspects the branch checked out in that worktree and compares
it to the main branch:

1. If the worktree is detached or on the main branch itself, it is skipped.
2. If branch tip equals main tip → **same as main**.
3. If branch tip is an ancestor of main (direct/fast-forward/merge commit)
   → **merged**.
4. Otherwise `git cherry main branch` is used to detect **squash/rebase
   merges** — if every commit on the branch has an equivalent patch on
   main, the branch is considered merged.
5. Anything else is reported as **not merged**.

`ahead/behind` counts come from `git rev-list --left-right --count`
between the branch and the main branch.

## Main branch detection

In order of priority:

1. `--main=<name>` flag if provided and the ref exists
2. The remote default branch (`origin/HEAD`)
3. Conventional names: `main`, `master`, `develop`, `trunk`

## Validation

The command fails fast when:

- The target path is not a git repository
- The repository has no linked worktrees (only the main checkout)
- The main branch cannot be resolved

## Development

```bash
composer install
composer test       # Pest tests + Pint lint
composer lint       # Auto-fix style
composer build      # Build the PHAR into builds/git-worktree
```

The PHAR is emitted at `builds/git-worktree`. The `build.yml` workflow
rebuilds and commits it back to `main` on every push, using the latest
git tag as the embedded version.

Fresh git repositories used by the test suite are created under
`tests/tmp/` (which is gitignored).

## Release

1. Merge changes to `main` — CI builds a fresh `builds/git-worktree`
   against the latest tag and commits it back.
2. Create a new GitHub release (tag `vX.Y.Z`).
3. The `publish-phar.yml` workflow attaches `git-worktree.phar` to the
   release and `update-changelog.yml` updates `CHANGELOG.md` + `version.txt`.

The `self-update` command pulls the PHAR asset from the latest release.
