<?php

it('prints a posix shell function by default', function () {
    $this->artisan('shell-init', ['shell' => 'bash'])
        ->expectsOutputToContain('gwt()')
        ->expectsOutputToContain('git-worktree path')
        ->assertExitCode(0);
});

it('prints a fish shell function', function () {
    $this->artisan('shell-init', ['shell' => 'fish'])
        ->expectsOutputToContain('function gwt')
        ->assertExitCode(0);
});
