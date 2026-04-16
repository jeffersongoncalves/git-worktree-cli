<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-clean-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
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
