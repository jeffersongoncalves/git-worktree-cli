<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-prune-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('reports nothing to prune on a clean repo', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('prune', ['path' => $repo->path()])
        ->expectsOutputToContain('Nothing to prune')
        ->assertExitCode(0);
});

it('prunes a stale worktree record after its directory is deleted', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $wtPath = $repo->addWorktree('wt', 'feature');

    // Delete the directory out from under git, leaving a stale admin record.
    GitRepoBuilder::rrmdir($wtPath);

    $this->artisan('prune', ['path' => $repo->path()])
        ->expectsOutputToContain('Pruned stale worktree records')
        ->assertExitCode(0);
});
