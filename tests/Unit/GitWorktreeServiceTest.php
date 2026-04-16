<?php

use App\DTOs\MergeStatus;
use App\Services\GitWorktreeService;
use Tests\Support\GitRepoBuilder;

beforeEach(function () {
    $this->tmp = GitRepoBuilder::baseDir().'/gwt-'.bin2hex(random_bytes(4));
    @mkdir($this->tmp, 0777, true);
    $this->service = new GitWorktreeService;
});

afterEach(function () {
    GitRepoBuilder::rrmdir($this->tmp);
    foreach (glob(GitRepoBuilder::baseDir().DIRECTORY_SEPARATOR.basename($this->tmp).'-*') ?: [] as $leftover) {
        GitRepoBuilder::rrmdir($leftover);
    }
});

it('detects a git repository', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    expect($this->service->isGitRepository($repo->path()))->toBeTrue();
});

it('reports a non-existent path as not a git repository', function () {
    $nowhere = sys_get_temp_dir().'/gwt-nowhere-'.bin2hex(random_bytes(4));

    expect($this->service->isGitRepository($nowhere))->toBeFalse();
});

it('reports no linked worktrees for a fresh repo', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $worktrees = $this->service->listWorktrees($repo->path());

    expect($worktrees)->toHaveCount(1)
        ->and($worktrees[0]->isMainWorktree)->toBeTrue()
        ->and($this->service->hasLinkedWorktrees($worktrees))->toBeFalse();
});

it('lists linked worktrees', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feature');
    $repo->commitFile('f.txt', 'feature');
    $repo->checkout('main');
    $linkedPath = $repo->addWorktree('wt', 'feature');

    $worktrees = $this->service->listWorktrees($repo->path());

    expect($worktrees)->toHaveCount(2)
        ->and($this->service->hasLinkedWorktrees($worktrees))->toBeTrue()
        ->and($worktrees[1]->shortBranch())->toBe('feature');

    $normalizedExpected = str_replace('\\', '/', $linkedPath);
    $normalizedActual = str_replace('\\', '/', $worktrees[1]->path);

    expect($normalizedActual)->toBe($normalizedExpected);
});

it('detects main branch from conventional names', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    expect($this->service->detectMainBranch($repo->path()))->toBe('main');
});

it('honors preferred main branch when ref exists', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('develop');
    $repo->commitFile('d.txt', 'dev');
    $repo->checkout('main');

    expect($this->service->detectMainBranch($repo->path(), 'develop'))->toBe('develop');
});

it('falls back to conventional name when preferred ref does not exist', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    expect($this->service->detectMainBranch($repo->path(), 'nope-not-a-branch'))->toBe('main');
});

it('classifies merged, squash-merged and unmerged worktrees', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);

    $repo->checkoutNewBranch('feat-merged');
    $repo->commitFile('m.txt', 'm');
    $repo->checkout('main');
    $repo->merge('feat-merged');

    $repo->checkoutNewBranch('feat-squash');
    $repo->commitFile('s.txt', 's');
    $repo->checkout('main');
    $repo->merge('feat-squash', squash: true);

    $repo->checkoutNewBranch('feat-unmerged');
    $repo->commitFile('u.txt', 'u');
    $repo->checkout('main');

    $repo->addWorktree('merged', 'feat-merged');
    $repo->addWorktree('squash', 'feat-squash');
    $repo->addWorktree('unmerged', 'feat-unmerged');

    $worktrees = $this->service->listWorktrees($repo->path());
    $results = $this->service->analyzeWorktrees($repo->path(), $worktrees, 'main');

    $byBranch = [];
    foreach ($results as $r) {
        $byBranch[$r->worktree->shortBranch()] = $r->status;
    }

    expect($byBranch)->toMatchArray([
        'feat-merged' => MergeStatus::MERGED,
        'feat-squash' => MergeStatus::SQUASH_MERGED,
        'feat-unmerged' => MergeStatus::NOT_MERGED,
    ]);
});

it('removes a worktree and deletes its branch', function () {
    $repo = GitRepoBuilder::createIn($this->tmp);
    $repo->checkoutNewBranch('feat');
    $repo->commitFile('f.txt', 'f');
    $repo->checkout('main');
    $repo->merge('feat');
    $wtPath = $repo->addWorktree('wt', 'feat');

    [$ok, $_] = $this->service->removeWorktree($repo->path(), $wtPath);
    expect($ok)->toBeTrue();

    [$branchOk, $__] = $this->service->deleteBranch($repo->path(), 'feat');
    expect($branchOk)->toBeTrue();
});
