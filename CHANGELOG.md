# Changelog

All notable changes to this project will be documented in this file.

## [1.0.9] - 2026-09-08

### Bug Fixes

- **deps:** Update guzzlehttp/guzzle to patch security advisories
- **ci:** Publish release as draft until PHAR asset is attached

### CI/CD

- **release:** Generate CHANGELOG.md and release notes with git-cliff

### Documentation

- Document config:herd auto-unlink setting in README
- Add Buy Me a Coffee sponsor link
- Standardize README section structure

### Miscellaneous Tasks

- Add GitHub Sponsors to FUNDING.yml

## [1.0.8] - 2026-08-14

### CI/CD

- Pin actions to commit SHA, add dependabot cooldown/composer, trim dist archive

### Miscellaneous Tasks

- Bump guzzlehttp/guzzle and guzzlehttp/psr7 for security advisories

### Other

- Bump shivammathur/setup-php

Bumps [shivammathur/setup-php](https://github.com/shivammathur/setup-php) from b604ade2a87db23f8871b7182e69ec5e75effb45 to f3e473d116dcccaddc5834248c87452386958240.
- [Release notes](https://github.com/shivammathur/setup-php/releases)
- [Commits](https://github.com/shivammathur/setup-php/compare/b604ade2a87db23f8871b7182e69ec5e75effb45...f3e473d116dcccaddc5834248c87452386958240)

---
updated-dependencies:
- dependency-name: shivammathur/setup-php
  dependency-version: f3e473d116dcccaddc5834248c87452386958240
  dependency-type: direct:production
...

Signed-off-by: dependabot[bot] <support@github.com>
- Add global config to auto-unlink Herd before removing a worktree

When `config:herd enable` is set, `remove` and `clean` run `herd unlink`
on the worktree path before removing it, so stale nginx site configs
do not linger after the folder is gone. Off by default; warns (does
not fail) if the herd CLI is missing or the unlink itself fails.

## [1.0.7] - 2026-07-24

### Bug Fixes

- **add:** Use $this->confirm() so the prompt fires under non-TTY CI
- Remove timeout on recursive submodule init

### CI/CD

- **build:** Serialize builds with a concurrency group to avoid ref-lock race
- **release:** Use version.txt as the single source of truth for the version
- Replace split build/changelog/publish-phar workflows with a single release job

### Features

- Add `add` command for worktree creation
- **clean:** Protect branches from removal via flag and per-repo config
- **add:** Recursively init submodules in new worktrees
- Add remove, prune, path, open, shell-init + copy/run hooks on add

### Miscellaneous Tasks

- Refresh portfolio banner
- Bump version to 1.0.3

### Other

- Delete .github/workflows/dependabot-auto-merge.yml
- Merge feat/worktree-commands: remove/prune/path/open/shell-init + add copy/run hooks

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>

### Refactor

- Use jeffersongoncalves/laravel-zero-self-update package
- Consume shared laravel-zero-* packages

### Testing

- Fix parallel race in GitRepoBuilder::baseDir mkdir

## [0.0.5] - 2026-04-16

### Other

- Resolve build tag from workflow_run event, not git describe

On the release path, two tags (the previous and current release) share
the same commit because the post-release Update Changelog commit gets
the new tag only after it lands on main. git describe --tags --abbrev=0
is then ambiguous and returns the older tag, so build.yml embedded the
wrong version in the PHAR and the tag-move step moved the wrong tag.

The release event stores the tag name in workflow_run.head_branch, so
use that directly for workflow_run invocations. Regular pushes keep
using git describe.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>

## [0.0.4] - 2026-04-16

### Other

- Force main branch in workflow_run-triggered builds

When the release event fans out through Update Changelog, a workflow_run
trigger fires with github.event.workflow_run.head_branch set to the tag
name (v0.0.3) rather than the branch the release targets. The previous
expression fell back to that value for both checkout ref and commit
branch, so the build checked out the tag, committed on a detached HEAD,
and pushed to refs/heads/<tag>, which Git rejected because the tag
already exists.

Pin ref and branch to main for every workflow_run invocation.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>

## [0.0.2] - 2026-04-16

### Other

- Rebuild PHAR on release publish

When a release is created, build.yml now runs again against the release
target branch. This picks up the new tag via git describe and commits
the versioned PHAR back to main, so `composer global require` users who
install right after a release get the correct version baked into the
binary (instead of whatever was built before the tag existed).

Also fixes the cold-start case from v0.0.1 where builds/git-worktree
still advertised "unreleased" until a future push happened.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
- Chain build workflow after Update Changelog

Releases fan out to three workflows in parallel: publish-phar,
Update Changelog, and builds. Update Changelog force-pushes to main
(to rewrite CHANGELOG and version.txt), which raced with builds and
caused a non-fast-forward rejection when builds tried to commit the
regenerated PHAR.

Replace the direct release trigger on builds with workflow_run that
fires after Update Changelog finishes, so the PHAR rebuild always
commits on top of the freshly pushed CHANGELOG commit.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
- Move release tag to rebuilt commit after release

Release created tag T at commit A with the pre-release PHAR baked in.
workflow_run-triggered build then commits the rebuilt PHAR at commit
B on main, but T still points at A. `composer require` resolves T to
A and ends up with the wrong version embedded. Advance T to B after
a successful release-triggered rebuild so Packagist serves the PHAR
that matches the tag name.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>

## [0.0.1] - 2026-04-16

### Other

- Initial commit: git-worktree-cli

Laravel Zero CLI that audits git worktrees against a repository's main
branch. Ships with:

- check: reports merged, squash/rebase merged, same-as-main and unmerged
  worktree branches with ahead/behind counts
- clean: removes worktrees whose branches are already merged, with
  dry-run, confirmation prompt, optional branch deletion, and strict mode
- list-worktrees: lists worktrees tracked by the repo
- self-update: downloads the latest PHAR release from GitHub

Includes 31 Pest tests (Feature + Unit) using a local tests/tmp/ fixture
directory, GitHub workflows for build/publish-phar/tests/changelog, and
the standard release/self-update plumbing used by the sibling CLIs.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
- Fix tests for CI where the project itself is a git repo

When running inside a checkout, tests/tmp/ is nested under the project
.git, so `git rev-parse --is-inside-work-tree` walks up and reports the
outer repo. Two tests assumed tests/tmp/ was outside any repo:

- Unit/GitWorktreeServiceTest::it detects a git repository: dropped the
  negative assertion that used tests/tmp, replaced with a separate test
  covering a non-existent sys_get_temp_dir() path.
- Feature/CheckCommandTest::it fails when path is not a git repo: now
  points at a non-existent path and asserts the "does not exist" error.

Also:
- isGitRepository() early-returns false for non-existent paths (was
  throwing a Symfony Process RuntimeException).
- GitRepoBuilder::baseDir() sets GIT_CEILING_DIRECTORIES for child
  processes, which helps when git honors it.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>


