<?php

use App\Services\ConfigService;
use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-cfgcmd-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);

    $this->xdg = $this->tmp.'/xdg';
    @mkdir($this->xdg, 0777, true);
    $this->prevXdg = getenv('XDG_CONFIG_HOME');
    putenv('XDG_CONFIG_HOME='.$this->xdg);
});

afterEach(function () {
    if ($this->prevXdg === false) {
        putenv('XDG_CONFIG_HOME');
    } else {
        putenv('XDG_CONFIG_HOME='.$this->prevXdg);
    }

    GitRepoBuilder::rrmdir($this->tmp);
});

it('protects and shows a branch', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('config:protect', ['branch' => 'develop', 'path' => $repo->path()])
        ->expectsOutputToContain('Protected')
        ->assertExitCode(0);

    $config = (new ConfigService)->load($repo->path());
    expect($config->branches)->toBe(['develop']);

    $this->artisan('config:show', ['path' => $repo->path()])
        ->expectsOutputToContain('develop')
        ->assertExitCode(0);
});

it('is idempotent when protecting the same branch twice', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('config:protect', ['branch' => 'develop', 'path' => $repo->path()])->assertExitCode(0);
    $this->artisan('config:protect', ['branch' => 'develop', 'path' => $repo->path()])
        ->expectsOutputToContain('Already protected')
        ->assertExitCode(0);

    expect((new ConfigService)->load($repo->path())->branches)->toBe(['develop']);
});

it('unprotects a branch', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('config:protect', ['branch' => 'develop', 'path' => $repo->path()])->assertExitCode(0);
    $this->artisan('config:unprotect', ['branch' => 'develop', 'path' => $repo->path()])
        ->expectsOutputToContain('Unprotected')
        ->assertExitCode(0);

    expect((new ConfigService)->load($repo->path())->branches)->toBe([]);
});

it('warns when unprotecting a branch that is not protected', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('config:unprotect', ['branch' => 'ghost', 'path' => $repo->path()])
        ->expectsOutputToContain('Not protected')
        ->assertExitCode(0);
});

it('enables and disables the config', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $this->artisan('config:disable', ['path' => $repo->path()])->assertExitCode(0);
    expect((new ConfigService)->load($repo->path())->enabled)->toBeFalse();

    $this->artisan('config:enable', ['path' => $repo->path()])->assertExitCode(0);
    expect((new ConfigService)->load($repo->path())->enabled)->toBeTrue();
});
