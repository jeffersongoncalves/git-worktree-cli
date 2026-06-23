<?php

namespace App\Commands;

use JeffersonGoncalves\LaravelZero\SelfUpdate\PharUpdater;
use JeffersonGoncalves\LaravelZero\SelfUpdate\SelfUpdateCommand as BaseSelfUpdateCommand;

class SelfUpdateCommand extends BaseSelfUpdateCommand
{
    protected $description = 'Update the git-worktree CLI to the latest version';

    protected function githubRepo(): string
    {
        return 'jeffersongoncalves/git-worktree-cli';
    }

    protected function assetName(): string
    {
        return 'git-worktree.phar';
    }

    protected function tempPrefix(): string
    {
        return 'git_worktree_';
    }

    protected function currentVersion(): string
    {
        return (string) config('app.version', 'unreleased');
    }

    protected function makeUpdater(): PharUpdater
    {
        return $this->getLaravel()->make(PharUpdater::class);
    }
}
