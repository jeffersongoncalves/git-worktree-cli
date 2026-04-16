<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-check-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('fails when path is not a git repo', function () {
    $this->artisan('check', ['path' => $this->tmp])
        ->expectsOutputToContain('Not a git repository')
        ->assertExitCode(1);
});

it('fails when repo has no linked worktrees', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('check', ['path' => $repo->path()])
        ->expectsOutputToContain('No linked worktrees found')
        ->assertExitCode(1);
});

it('reports merged and unmerged worktree branches', function () {
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

    $this->artisan('check', ['path' => $repo->path()])
        ->expectsOutputToContain('feat-merged')
        ->expectsOutputToContain('feat-unmerged')
        ->expectsOutputToContain('1 worktree branch(es) not merged into main')
        ->assertExitCode(0);
});
