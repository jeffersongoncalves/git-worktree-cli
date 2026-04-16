<?php

use App\DTOs\MergeStatus;
use App\DTOs\Worktree;

it('extracts short branch name', function () {
    $wt = new Worktree(
        path: '/tmp/x',
        head: 'abc',
        branch: 'refs/heads/feature/foo',
        detached: false,
        bare: false,
        isMainWorktree: false,
    );

    expect($wt->shortBranch())->toBe('feature/foo')
        ->and($wt->label())->toBe('feature/foo');
});

it('labels detached worktree with short sha', function () {
    $wt = new Worktree(
        path: '/tmp/x',
        head: 'abcdef1234567890',
        branch: null,
        detached: true,
        bare: false,
        isMainWorktree: false,
    );

    expect($wt->label())->toBe('(detached HEAD abcdef1)')
        ->and($wt->shortBranch())->toBeNull();
});

it('treats merged, squash-merged and same-as-main as clean', function () {
    $wt = new Worktree('/tmp', 'x', 'refs/heads/b', false, false, false);

    expect((new MergeStatus($wt, MergeStatus::MERGED))->isClean())->toBeTrue()
        ->and((new MergeStatus($wt, MergeStatus::SQUASH_MERGED))->isClean())->toBeTrue()
        ->and((new MergeStatus($wt, MergeStatus::SAME_AS_MAIN))->isClean())->toBeTrue()
        ->and((new MergeStatus($wt, MergeStatus::NOT_MERGED))->isClean())->toBeFalse()
        ->and((new MergeStatus($wt, MergeStatus::SKIPPED))->isClean())->toBeFalse();
});
