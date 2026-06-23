<?php

use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-add-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob($this->tmp.'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('creates a worktree for an existing local branch', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feat');
    $repo->checkout('main');

    $expected = dirname($repo->path()).DIRECTORY_SEPARATOR.basename($repo->path()).'-feature';

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
    ])
        ->expectsOutputToContain('existing local branch')
        ->assertExitCode(0);

    expect(is_dir($expected))->toBeTrue();

    $list = $repo->git(['worktree', 'list']);
    expect($list)->toContain('feature');
});

it('uses the suffix after the last slash for the worktree directory name', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature/foo');
    $repo->commitFile('f.txt', 'feat');
    $repo->checkout('main');

    $expected = dirname($repo->path()).DIRECTORY_SEPARATOR.basename($repo->path()).'-foo';

    $this->artisan('add', [
        'branch' => 'feature/foo',
        'path' => $repo->path(),
        '--no-fetch' => true,
    ])->assertExitCode(0);

    expect(is_dir($expected))->toBeTrue();
});

it('creates a brand-new branch from the detected main when --yes is provided', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $expected = dirname($repo->path()).DIRECTORY_SEPARATOR.basename($repo->path()).'-brand-new';

    $this->artisan('add', [
        'branch' => 'brand-new',
        'path' => $repo->path(),
        '--no-fetch' => true,
        '--yes' => true,
    ])
        ->expectsOutputToContain('new branch from main')
        ->assertExitCode(0);

    expect(is_dir($expected))->toBeTrue();

    $list = $repo->git(['worktree', 'list']);
    expect($list)->toContain('brand-new');
});

it('aborts when the user declines the create-branch confirmation', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('add', [
        'branch' => 'never-created',
        'path' => $repo->path(),
        '--no-fetch' => true,
    ])
        ->expectsConfirmation("Branch 'never-created' does not exist. Create new branch and worktree?", 'no')
        ->expectsOutputToContain('Aborted')
        ->assertExitCode(0);

    $list = $repo->git(['branch', '--list', 'never-created']);
    expect(trim($list))->toBe('');
});

it('fails when the target path already exists', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feat');
    $repo->checkout('main');

    $existing = dirname($repo->path()).DIRECTORY_SEPARATOR.basename($repo->path()).'-feature';
    @mkdir($existing, 0777, true);

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
    ])
        ->expectsOutputToContain('already exists')
        ->assertExitCode(1);
});

it('copies requested files into the new worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    file_put_contents($repo->path('.env'), 'APP_ENV=local');

    $target = dirname($repo->path()).DIRECTORY_SEPARATOR.basename($repo->path()).'-feature';

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
        '--yes' => true,
        '--copy' => ['.env'],
    ])
        ->expectsOutputToContain('Copied')
        ->assertExitCode(0);

    expect(is_file($target.DIRECTORY_SEPARATOR.'.env'))->toBeTrue()
        ->and(file_get_contents($target.DIRECTORY_SEPARATOR.'.env'))->toBe('APP_ENV=local');
});

it('warns when a file to copy is missing in the main worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
        '--yes' => true,
        '--copy' => ['.env'],
    ])
        ->expectsOutputToContain('Skipped copy')
        ->assertExitCode(0);
});

it('runs post-create hooks inside the new worktree', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
        '--yes' => true,
        '--run' => ['git status'],
    ])
        ->expectsOutputToContain('Ran')
        ->assertExitCode(0);
});

it('honors --target to override the worktree directory', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feat');
    $repo->checkout('main');

    $custom = dirname($repo->path()).DIRECTORY_SEPARATOR.'custom-wt-'.bin2hex(random_bytes(2));

    $this->artisan('add', [
        'branch' => 'feature',
        'path' => $repo->path(),
        '--no-fetch' => true,
        '--target' => $custom,
    ])->assertExitCode(0);

    expect(is_dir($custom))->toBeTrue();

    GitRepoBuilder::rrmdir($custom);
});
