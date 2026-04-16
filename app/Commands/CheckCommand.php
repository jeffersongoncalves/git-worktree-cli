<?php

namespace App\Commands;

use App\DTOs\MergeStatus;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class CheckCommand extends Command
{
    protected $signature = 'check
        {path? : Path to the git repository (defaults to the current directory)}
        {--main= : Name of the main branch (auto-detected when omitted)}
        {--only-unmerged : Only show worktrees whose branches are not merged}';

    protected $description = 'Check whether worktree branches have been merged into the main branch';

    public function handle(GitWorktreeService $service): int
    {
        $cwd = $this->resolveCwd();

        if (! is_dir($cwd)) {
            $this->components->error("Path does not exist: {$cwd}");

            return self::FAILURE;
        }

        if (! $service->isGitRepository($cwd)) {
            $this->components->error("Not a git repository: {$cwd}");

            return self::FAILURE;
        }

        try {
            $worktrees = $service->listWorktrees($cwd);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $service->hasLinkedWorktrees($worktrees)) {
            $this->components->error('No linked worktrees found. This command requires a repository with at least one worktree beyond the main checkout.');
            $this->line('  Create one with: <comment>git worktree add ../my-branch my-branch</comment>');

            return self::FAILURE;
        }

        $mainBranch = $service->detectMainBranch($cwd, $this->option('main'));

        if ($mainBranch === null) {
            $this->components->error('Could not detect the main branch. Provide it explicitly with --main=<branch>.');

            return self::FAILURE;
        }

        $this->components->info("Repository: <comment>{$cwd}</comment>");
        $this->components->info("Main branch: <comment>{$mainBranch}</comment>");

        $results = $service->analyzeWorktrees($cwd, $worktrees, $mainBranch);

        if ($results === []) {
            $this->components->warn('No worktree branches to analyze.');

            return self::SUCCESS;
        }

        $this->renderTable($results);

        $unmergedCount = count(array_filter($results, fn (MergeStatus $r) => $r->status === MergeStatus::NOT_MERGED));

        $this->newLine();
        if ($unmergedCount === 0) {
            $this->components->info('All worktree branches are merged into '.$mainBranch.'.');

            return self::SUCCESS;
        }

        $this->components->warn("{$unmergedCount} worktree branch(es) not merged into {$mainBranch}.");

        return self::SUCCESS;
    }

    /**
     * @param  list<MergeStatus>  $results
     */
    private function renderTable(array $results): void
    {
        if ($this->option('only-unmerged')) {
            $results = array_values(array_filter(
                $results,
                fn (MergeStatus $r) => $r->status === MergeStatus::NOT_MERGED,
            ));
        }

        if ($results === []) {
            $this->newLine();
            $this->components->info('Nothing to display.');

            return;
        }

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result->worktree->shortBranch() ?? $result->worktree->label(),
                $this->formatStatus($result),
                $this->formatAheadBehind($result),
                $this->shortPath($result->worktree->path),
            ];
        }

        $this->newLine();
        $this->table(['Branch', 'Status', 'Ahead/Behind', 'Worktree'], $rows);
    }

    private function formatStatus(MergeStatus $result): string
    {
        return match ($result->status) {
            MergeStatus::MERGED => '<fg=green>merged</>',
            MergeStatus::SQUASH_MERGED => '<fg=green>squash/rebase merged</>',
            MergeStatus::SAME_AS_MAIN => '<fg=green>same as main</>',
            MergeStatus::NOT_MERGED => '<fg=red>not merged</>',
            MergeStatus::SKIPPED => '<fg=yellow>'.$result->human().'</>',
            default => $result->human(),
        };
    }

    private function formatAheadBehind(MergeStatus $result): string
    {
        if ($result->aheadCount === null && $result->behindCount === null) {
            return '-';
        }

        return sprintf('+%d / -%d', (int) $result->aheadCount, (int) $result->behindCount);
    }

    private function shortPath(string $path): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        if (is_string($home) && $home !== '' && str_starts_with($path, $home)) {
            return '~'.substr($path, strlen($home));
        }

        return $path;
    }

    private function resolveCwd(): string
    {
        $arg = $this->argument('path');

        $path = is_string($arg) && $arg !== '' ? $arg : getcwd();

        $real = realpath((string) $path);

        return $real !== false ? $real : (string) $path;
    }
}
