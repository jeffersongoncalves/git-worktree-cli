<?php

use App\DTOs\RepoConfig;
use App\Services\ConfigService;
use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-cfg-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);

    $this->xdg = $this->tmp.'/xdg';
    @mkdir($this->xdg, 0777, true);
    $this->prevXdg = getenv('XDG_CONFIG_HOME');
    putenv('XDG_CONFIG_HOME='.$this->xdg);

    $this->service = new ConfigService;
});

afterEach(function () {
    if ($this->prevXdg === false) {
        putenv('XDG_CONFIG_HOME');
    } else {
        putenv('XDG_CONFIG_HOME='.$this->prevXdg);
    }

    GitRepoBuilder::rrmdir($this->tmp);
});

it('derives the config dir from XDG_CONFIG_HOME', function () {
    expect($this->service->configDir())
        ->toBe($this->xdg.DIRECTORY_SEPARATOR.'git-worktree');
});

it('derives an owner-repo slug from the origin remote', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->git(['remote', 'add', 'origin', 'git@github.com:Acme/My-Repo.git']);

    expect($this->service->repoSlug($repo->path()))->toBe('acme-my-repo');
});

it('parses https remotes too', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->git(['remote', 'add', 'origin', 'https://github.com/Acme/My-Repo.git']);

    expect($this->service->repoSlug($repo->path()))->toBe('acme-my-repo');
});

it('falls back to basename plus hash when there is no remote', function () {
    $repo = GitRepoBuilder::createIn($this->tmp, 'noremote');

    expect($this->service->repoSlug($repo->path()))
        ->toMatch('/^noremote-[0-9a-f]{6}$/');
});

it('returns a default config when no file exists', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $config = $this->service->load($repo->path());

    expect($config->enabled)->toBeTrue()
        ->and($config->branches)->toBe([]);
});

it('round-trips a saved config', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $config = new RepoConfig(enabled: true, branches: ['develop', 'release/*']);
    $this->service->save($repo->path(), $config);

    expect(is_file($this->service->path($repo->path())))->toBeTrue();

    $loaded = $this->service->load($repo->path());

    expect($loaded->enabled)->toBeTrue()
        ->and($loaded->branches)->toBe(['develop', 'release/*']);
});

it('round-trips add.copy and add.run config', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $config = new RepoConfig(
        enabled: true,
        branches: ['develop'],
        copyOnAdd: ['.env', 'storage/oauth-private.key'],
        postAdd: ['composer install', 'php artisan key:generate'],
    );
    $this->service->save($repo->path(), $config);

    $loaded = $this->service->load($repo->path());

    expect($loaded->copyOnAdd)->toBe(['.env', 'storage/oauth-private.key'])
        ->and($loaded->postAdd)->toBe(['composer install', 'php artisan key:generate']);
});

it('omits the add section when copy and run are empty', function () {
    $config = new RepoConfig(enabled: true, branches: ['develop']);

    expect($config->toArray())->not->toHaveKey('add');
});

it('returns no protected branches when config is disabled', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $this->service->save($repo->path(), new RepoConfig(enabled: false, branches: ['develop']));

    expect($this->service->protectedBranches($repo->path()))->toBe([]);
});

it('returns no protected branches when config is skipped', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $this->service->save($repo->path(), new RepoConfig(enabled: true, branches: ['develop']));

    expect($this->service->protectedBranches($repo->path(), useConfig: false))->toBe([]);
});

it('matches protected branches exactly and by glob', function () {
    $service = $this->service;

    expect($service->isProtected('develop', ['develop']))->toBeTrue()
        ->and($service->isProtected('release/1.0', ['release/*']))->toBeTrue()
        ->and($service->isProtected('feature/x', ['release/*']))->toBeFalse()
        ->and($service->isProtected('main', []))->toBeFalse();
});
