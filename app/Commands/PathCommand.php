<?php

namespace App\Commands;

use App\Concerns\ResolvesRepoPath;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

class PathCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'path
        {target : Branch name or path of the worktree}
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Print the absolute path of a worktree (for shell cd integration)';

    public function handle(GitWorktreeService $service): int
    {
        $cwd = $this->resolveCwd();

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

        $wt = $service->findWorktree($worktrees, (string) $this->argument('target'));

        if ($wt === null) {
            $this->components->error("No worktree found matching '{$this->argument('target')}'.");

            return self::FAILURE;
        }

        // Raw path to stdout so it can be captured by a shell: cd "$(git-worktree path foo)"
        $this->line($wt->path);

        return self::SUCCESS;
    }
}
