<?php

use App\DTOs\GlobalConfig;
use App\Services\ConfigService;
use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-herd-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);

    $this->home = $this->tmp.'/home';
    @mkdir($this->home, 0777, true);
    $this->prevHome = getenv('HOME');
    $this->prevUserProfile = getenv('USERPROFILE');
    putenv('HOME='.$this->home);
    putenv('USERPROFILE='.$this->home);
});

afterEach(function () {
    putenv($this->prevHome === false ? 'HOME' : 'HOME='.$this->prevHome);
    putenv($this->prevUserProfile === false ? 'USERPROFILE' : 'USERPROFILE='.$this->prevUserProfile);
    GitRepoBuilder::rrmdir($this->tmp);
});

it('toggles the global herd-unlink setting', function () {
    $this->artisan('config:herd')
        ->expectsOutputToContain('disabled')
        ->assertExitCode(0);

    $this->artisan('config:herd', ['action' => 'enable'])
        ->expectsOutputToContain('enabled')
        ->assertExitCode(0);

    expect((new ConfigService)->loadGlobal()->herdUnlinkOnRemove)->toBeTrue();

    $this->artisan('config:herd', ['action' => 'disable'])
        ->expectsOutputToContain('disabled')
        ->assertExitCode(0);

    expect((new ConfigService)->loadGlobal()->herdUnlinkOnRemove)->toBeFalse();
});

it('rejects an unknown action', function () {
    $this->artisan('config:herd', ['action' => 'bogus'])
        ->expectsOutputToContain('Unknown action')
        ->assertExitCode(1);
});

it('attempts a herd unlink before removing a worktree when enabled', function () {
    (new ConfigService)->saveGlobal(new GlobalConfig(herdUnlinkOnRemove: true));

    $repo = GitRepoBuilder::createIn($this->tmp.'/repo');
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $wtPath = $repo->addWorktree('wt', 'feature');

    $this->artisan('remove', ['target' => 'feature', 'path' => $repo->path(), '--yes' => true])
        ->expectsOutputToContain('Herd')
        ->assertExitCode(0);

    expect(is_dir($wtPath))->toBeFalse();
});

it('does not attempt a herd unlink when disabled', function () {
    $repo = GitRepoBuilder::createIn($this->tmp.'/repo');
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $wtPath = $repo->addWorktree('wt', 'feature');

    $this->artisan('remove', ['target' => 'feature', 'path' => $repo->path(), '--yes' => true])
        ->doesntExpectOutputToContain('Herd')
        ->assertExitCode(0);

    expect(is_dir($wtPath))->toBeFalse();
});
