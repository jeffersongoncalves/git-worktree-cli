<?php

namespace App\Services;

use App\DTOs\MergeStatus;
use App\DTOs\Worktree;
use RuntimeException;
use Symfony\Component\Process\Process;

class GitWorktreeService
{
    /**
     * Candidate names for the "main" branch, in order of preference.
     *
     * @var list<string>
     */
    private const DEFAULT_MAIN_CANDIDATES = ['main', 'master', 'develop', 'trunk'];

    public function isGitRepository(string $cwd): bool
    {
        if (! is_dir($cwd)) {
            return false;
        }

        $process = $this->git($cwd, ['rev-parse', '--is-inside-work-tree']);
        $process->run();

        return $process->isSuccessful() && trim($process->getOutput()) === 'true';
    }

    /**
     * Return the absolute path of the common .git dir.
     */
    public function commonDir(string $cwd): ?string
    {
        $process = $this->git($cwd, ['rev-parse', '--git-common-dir']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $path = trim($process->getOutput());

        return $path === '' ? null : $path;
    }

    /**
     * Parse `git worktree list --porcelain` and return the list of worktrees.
     *
     * @return list<Worktree>
     */
    public function listWorktrees(string $cwd): array
    {
        $process = $this->git($cwd, ['worktree', 'list', '--porcelain']);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Failed to list git worktrees: '.trim($process->getErrorOutput()));
        }

        $output = $process->getOutput();
        $blocks = preg_split('/\R\R+/', trim($output)) ?: [];

        $worktrees = [];
        $first = true;

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $path = null;
            $head = null;
            $branch = null;
            $detached = false;
            $bare = false;

            foreach (preg_split('/\R/', $block) as $line) {
                if (str_starts_with($line, 'worktree ')) {
                    $path = substr($line, 9);
                } elseif (str_starts_with($line, 'HEAD ')) {
                    $head = substr($line, 5);
                } elseif (str_starts_with($line, 'branch ')) {
                    $branch = substr($line, 7);
                } elseif ($line === 'detached') {
                    $detached = true;
                } elseif ($line === 'bare') {
                    $bare = true;
                }
            }

            if ($path === null) {
                continue;
            }

            $worktrees[] = new Worktree(
                path: $path,
                head: $head,
                branch: $branch,
                detached: $detached,
                bare: $bare,
                isMainWorktree: $first,
            );

            $first = false;
        }

        return $worktrees;
    }

    /**
     * Return true when the repository has at least one linked worktree
     * beyond the main one.
     *
     * @param  list<Worktree>  $worktrees
     */
    public function hasLinkedWorktrees(array $worktrees): bool
    {
        $nonMain = array_filter($worktrees, fn (Worktree $w) => ! $w->isMainWorktree && ! $w->bare);

        return count($nonMain) > 0;
    }

    /**
     * Detect the main/default branch. Tries, in order:
     *   1. The explicit $preferred argument (if the ref exists).
     *   2. origin/HEAD (remote default branch).
     *   3. Known conventional names (main, master, develop, trunk).
     */
    public function detectMainBranch(string $cwd, ?string $preferred = null): ?string
    {
        if ($preferred !== null && $preferred !== '' && $this->refExists($cwd, $preferred)) {
            return $preferred;
        }

        $remoteHead = $this->resolveOriginHead($cwd);
        if ($remoteHead !== null) {
            return $remoteHead;
        }

        foreach (self::DEFAULT_MAIN_CANDIDATES as $candidate) {
            if ($this->refExists($cwd, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check merge status of every worktree against the provided main branch.
     *
     * @param  list<Worktree>  $worktrees
     * @return list<MergeStatus>
     */
    public function analyzeWorktrees(string $cwd, array $worktrees, string $mainBranch): array
    {
        $results = [];

        foreach ($worktrees as $wt) {
            if ($wt->isMainWorktree || $wt->bare) {
                continue;
            }

            if ($wt->detached || $wt->branch === null) {
                $results[] = new MergeStatus($wt, MergeStatus::SKIPPED, reason: 'detached HEAD');

                continue;
            }

            $branch = $wt->shortBranch();

            if ($branch === $mainBranch) {
                $results[] = new MergeStatus($wt, MergeStatus::SKIPPED, reason: 'main worktree branch');

                continue;
            }

            $results[] = $this->computeStatus($cwd, $wt, $mainBranch);
        }

        return $results;
    }

    /**
     * Remove a linked worktree at the given path.
     *
     * @return array{0: bool, 1: string} success flag plus combined output
     */
    public function removeWorktree(string $cwd, string $path, bool $force = false): array
    {
        $args = ['worktree', 'remove'];
        if ($force) {
            $args[] = '--force';
        }
        $args[] = $path;

        $process = $this->git($cwd, $args);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        return [$process->isSuccessful(), $output];
    }

    /**
     * Delete a local branch.
     *
     * @return array{0: bool, 1: string} success flag plus combined output
     */
    public function deleteBranch(string $cwd, string $branch, bool $force = false): array
    {
        $process = $this->git($cwd, ['branch', $force ? '-D' : '-d', $branch]);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        return [$process->isSuccessful(), $output];
    }

    private function computeStatus(string $cwd, Worktree $wt, string $mainBranch): MergeStatus
    {
        $branch = (string) $wt->shortBranch();

        $branchTip = $this->revParse($cwd, $branch);
        $mainTip = $this->revParse($cwd, $mainBranch);

        if ($branchTip === null || $mainTip === null) {
            return new MergeStatus($wt, MergeStatus::SKIPPED, reason: 'unable to resolve revisions');
        }

        if ($branchTip === $mainTip) {
            return new MergeStatus($wt, MergeStatus::SAME_AS_MAIN, aheadCount: 0, behindCount: 0);
        }

        [$ahead, $behind] = $this->aheadBehind($cwd, $branch, $mainBranch);

        if ($this->isAncestor($cwd, $branchTip, $mainTip)) {
            return new MergeStatus($wt, MergeStatus::MERGED, aheadCount: $ahead, behindCount: $behind);
        }

        if ($this->isSquashOrRebaseMerged($cwd, $branch, $mainBranch)) {
            return new MergeStatus($wt, MergeStatus::SQUASH_MERGED, aheadCount: $ahead, behindCount: $behind);
        }

        return new MergeStatus($wt, MergeStatus::NOT_MERGED, aheadCount: $ahead, behindCount: $behind);
    }

    private function isSquashOrRebaseMerged(string $cwd, string $branch, string $mainBranch): bool
    {
        $process = $this->git($cwd, ['cherry', $mainBranch, $branch]);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return true;
        }

        foreach (preg_split('/\R/', $output) as $line) {
            if (str_starts_with($line, '+ ')) {
                return false;
            }
        }

        return true;
    }

    private function isAncestor(string $cwd, string $ancestor, string $descendant): bool
    {
        $process = $this->git($cwd, ['merge-base', '--is-ancestor', $ancestor, $descendant]);
        $process->run();

        return $process->getExitCode() === 0;
    }

    /**
     * @return array{0: int, 1: int} ahead, behind counts relative to main
     */
    private function aheadBehind(string $cwd, string $branch, string $mainBranch): array
    {
        $process = $this->git($cwd, ['rev-list', '--left-right', '--count', $branch.'...'.$mainBranch]);
        $process->run();

        if (! $process->isSuccessful()) {
            return [0, 0];
        }

        $parts = preg_split('/\s+/', trim($process->getOutput())) ?: [];

        $ahead = isset($parts[0]) ? (int) $parts[0] : 0;
        $behind = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$ahead, $behind];
    }

    private function revParse(string $cwd, string $ref): ?string
    {
        $process = $this->git($cwd, ['rev-parse', '--verify', $ref.'^{commit}']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $sha = trim($process->getOutput());

        return $sha === '' ? null : $sha;
    }

    private function refExists(string $cwd, string $ref): bool
    {
        return $this->revParse($cwd, $ref) !== null;
    }

    private function resolveOriginHead(string $cwd): ?string
    {
        $process = $this->git($cwd, ['symbolic-ref', '--quiet', '--short', 'refs/remotes/origin/HEAD']);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $ref = trim($process->getOutput());
        if ($ref === '') {
            return null;
        }

        $branch = preg_replace('#^origin/#', '', $ref);

        return $branch !== null && $this->refExists($cwd, $branch) ? $branch : null;
    }

    /**
     * @param  list<string>  $args
     */
    private function git(string $cwd, array $args): Process
    {
        $process = new Process(['git', ...$args], $cwd);
        $process->setTimeout(60);

        return $process;
    }
}
