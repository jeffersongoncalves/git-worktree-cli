<?php

use App\DTOs\RepoConfig;
use App\Services\ConfigService;
use Tests\Support\GitRepoBuilder;

/**
 * Spin up a repo with one merged worktree branch ready to be cleaned.
 */
function mergedRepo(string $tmp, string $branch = 'feat-merged'): GitRepoBuilder
{
    $repo = GitRepoBuilder::createIn($tmp);
    $repo->checkoutNewBranch($branch);
    $repo->commitFile('m.txt', 'm');
    $repo->checkout('main');
    $repo->merge($branch);
    $repo->addWorktree('wt', $branch);

    return $repo;
}

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-clean-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);

    // Keep config files inside the throwaway dir, never the real ~/.config.
    $this->xdg = $this->tmp.'/xdg';
    @mkdir($this->xdg, 0777, true);
    $this->prevXdg = getenv('XDG_CONFIG_HOME');
    putenv('XDG_CONFIG_HOME='.$this->xdg);
});

afterEach(function () {
    if ($this->prevXdg === false) {
        putenv('XDG_CONFIG_HOME');
    } else {
        putenv('XDG_CONFIG_HOME='.$this->prevXdg);
    }

    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('dry-run does not remove anything', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feat-merged');
    $repo->commitFile('m.txt', 'm');
    $repo->checkout('main');
    $repo->merge('feat-merged');
    $repo->addWorktree('wt', 'feat-merged');

    $this->artisan('clean', ['path' => $repo->path(), '--dry-run' => true])
        ->expectsOutputToContain('feat-merged')
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    $before = $repo->git(['worktree', 'list']);
    expect($before)->toContain('feat-merged');
});

it('removes merged worktrees when --yes is provided', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $repo->checkoutNewBranch('feat-merged');
    $repo->commitFile('m.txt', 'm');
    $repo->checkout('main');
    $repo->merge('feat-merged');

    $repo->checkoutNewBranch('feat-unmerged');
    $repo->commitFile('u.txt', 'u');
    $repo->checkout('main');

    $repo->addWorktree('merged', 'feat-merged');
    $repo->addWorktree('unmerged', 'feat-unmerged');

    $this->artisan('clean', ['path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('Removed 1 worktree')
        ->assertExitCode(0);

    $after = $repo->git(['worktree', 'list']);
    expect($after)
        ->not->toContain('feat-merged')
        ->and($after)->toContain('feat-unmerged');
});

it('reports nothing to clean when no branch is merged', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feat-unmerged');
    $repo->commitFile('u.txt', 'u');
    $repo->checkout('main');
    $repo->addWorktree('wt', 'feat-unmerged');

    $this->artisan('clean', ['path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('Nothing to clean')
        ->assertExitCode(0);
});

it('keeps a merged branch protected via --protect', function () {
    $repo = mergedRepo($this->tmp);

    $this->artisan('clean', ['path' => $repo->path(), '--protect' => ['feat-merged'], '--yes' => true])
        ->expectsOutputToContain('Nothing to clean')
        ->assertExitCode(0);

    expect($repo->git(['worktree', 'list']))->toContain('feat-merged');
});

it('keeps a merged branch protected via the config file', function () {
    $repo = mergedRepo($this->tmp);
    (new ConfigService)->save($repo->path(), new RepoConfig(enabled: true, branches: ['feat-merged']));

    $this->artisan('clean', ['path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('Nothing to clean')
        ->assertExitCode(0);

    expect($repo->git(['worktree', 'list']))->toContain('feat-merged');
});

it('ignores the config file with --no-config', function () {
    $repo = mergedRepo($this->tmp);
    (new ConfigService)->save($repo->path(), new RepoConfig(enabled: true, branches: ['feat-merged']));

    $this->artisan('clean', ['path' => $repo->path(), '--no-config' => true, '--yes' => true])
        ->expectsOutputToContain('Removed 1 worktree')
        ->assertExitCode(0);

    expect($repo->git(['worktree', 'list']))->not->toContain('feat-merged');
});

it('protects branches by glob pattern', function () {
    $repo = mergedRepo($this->tmp, 'release/1.0');

    $this->artisan('clean', ['path' => $repo->path(), '--protect' => ['release/*'], '--yes' => true])
        ->expectsOutputToContain('Nothing to clean')
        ->assertExitCode(0);

    expect($repo->git(['worktree', 'list']))->toContain('release/1.0');
});
