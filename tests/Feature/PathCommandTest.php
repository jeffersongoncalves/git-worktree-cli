<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-path-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('prints the path of a worktree by branch name', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $wtPath = $repo->addWorktree('wt', 'feature');

    $this->artisan('path', ['target' => 'feature', 'path' => $repo->path()])
        ->expectsOutputToContain(basename($wtPath))
        ->assertExitCode(0);
});

it('fails for an unknown worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('path', ['target' => 'ghost', 'path' => $repo->path()])
        ->expectsOutputToContain('No worktree found')
        ->assertExitCode(1);
});
