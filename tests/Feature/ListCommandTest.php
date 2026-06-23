<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-list-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('lists worktrees without status by default', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $repo->addWorktree('wt', 'feature');

    $this->artisan('list-worktrees', ['path' => $repo->path()])
        ->expectsOutputToContain('feature')
        ->assertExitCode(0);
});

it('includes merge status and dirty flag with --status', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $repo->checkoutNewBranch('feat-unmerged');
    $repo->commitFile('u.txt', 'u');
    $repo->checkout('main');
    $repo->addWorktree('unmerged', 'feat-unmerged');

    $this->artisan('list-worktrees', ['path' => $repo->path(), '--status' => true])
        ->expectsOutputToContain('Merge')
        ->expectsOutputToContain('not merged')
        ->expectsOutputToContain('clean')
        ->assertExitCode(0);
});
