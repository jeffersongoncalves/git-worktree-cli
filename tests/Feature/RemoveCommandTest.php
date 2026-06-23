<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-remove-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('removes a worktree by branch name', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $wtPath = $repo->addWorktree('wt', 'feature');

    expect(is_dir($wtPath))->toBeTrue();

    $this->artisan('remove', ['target' => 'feature', 'path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('Removed worktree')
        ->assertExitCode(0);

    expect(is_dir($wtPath))->toBeFalse();
});

it('refuses to remove the main worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('remove', ['target' => 'main', 'path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('main worktree')
        ->assertExitCode(1);
});

it('fails for an unknown worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('remove', ['target' => 'ghost', 'path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('No worktree found')
        ->assertExitCode(1);
});
