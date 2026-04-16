<?php

namespace App\Commands;

use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class ListCommand extends Command
{
    protected $signature = 'list-worktrees
        {path? : Path to the git repository (defaults to the current directory)}';

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
}
