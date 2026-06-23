<?php

namespace App\Commands;

use App\DTOs\MergeStatus;
use App\DTOs\Worktree;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class ListCommand extends Command
{
    protected $signature = 'list-worktrees
        {path? : Path to the git repository (defaults to the current directory)}
        {--status : Include merge status against the main branch and a dirty flag}
        {--main= : Name of the main branch (used with --status; auto-detected when omitted)}';

    protected $description = 'List all worktrees registered in the repository';

    public function handle(GitWorktreeService $service): int
    {
        $arg = $this->argument('path');
        $path = is_string($arg) && $arg !== '' ? $arg : getcwd();
        $cwd = realpath((string) $path) ?: (string) $path;

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

        if ($worktrees === []) {
            $this->components->warn('No worktrees found.');

            return self::SUCCESS;
        }

        return $this->option('status')
            ? $this->renderWithStatus($service, $cwd, $worktrees)
            : $this->renderBasic($worktrees);
    }

    /**
     * @param  list<Worktree>  $worktrees
     */
    private function renderBasic(array $worktrees): int
    {
        $rows = [];
        foreach ($worktrees as $wt) {
            $rows[] = [
                $wt->isMainWorktree ? 'main' : 'linked',
                $wt->label(),
                substr((string) $wt->head, 0, 7),
                $wt->path,
            ];
        }

        $this->newLine();
        $this->table(['Type', 'Branch', 'HEAD', 'Path'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  list<Worktree>  $worktrees
     */
    private function renderWithStatus(GitWorktreeService $service, string $cwd, array $worktrees): int
    {
        $mainBranch = $service->detectMainBranch($cwd, $this->option('main'));

        if ($mainBranch === null) {
            $this->components->error('Could not detect the main branch. Provide it explicitly with --main=<branch>.');

            return self::FAILURE;
        }

        $statuses = [];
        foreach ($service->analyzeWorktrees($cwd, $worktrees, $mainBranch) as $result) {
            $statuses[$result->worktree->path] = $result;
        }

        $rows = [];
        foreach ($worktrees as $wt) {
            $rows[] = [
                $wt->isMainWorktree ? 'main' : 'linked',
                $wt->label(),
                substr((string) $wt->head, 0, 7),
                $this->statusFor($wt, $statuses),
                $service->isDirty($wt->path) ? '<fg=yellow>dirty</>' : '<fg=green>clean</>',
                $wt->path,
            ];
        }

        $this->newLine();
        $this->table(['Type', 'Branch', 'HEAD', 'Merge', 'State', 'Path'], $rows);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, MergeStatus>  $statuses
     */
    private function statusFor(Worktree $wt, array $statuses): string
    {
        if ($wt->isMainWorktree) {
            return '-';
        }

        $result = $statuses[$wt->path] ?? null;

        if ($result === null) {
            return '-';
        }

        return match ($result->status) {
            MergeStatus::MERGED, MergeStatus::SQUASH_MERGED, MergeStatus::SAME_AS_MAIN => '<fg=green>'.$result->human().'</>',
            MergeStatus::NOT_MERGED => '<fg=red>not merged</>',
            default => '<fg=yellow>'.$result->human().'</>',
        };
    }
}
