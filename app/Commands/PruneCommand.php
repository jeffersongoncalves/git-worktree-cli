<?php

namespace App\Commands;

use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;

class PruneCommand extends Command
{
    protected $signature = 'prune
        {path? : Path to the git repository (defaults to the current directory)}
        {--dry-run : Show what would be pruned without removing anything}';

    protected $description = 'Prune stale worktree administrative records (git worktree prune)';

    public function handle(GitWorktreeService $service): int
    {
        $cwd = $this->resolveCwd();

        if (! $service->isGitRepository($cwd)) {
            $this->components->error("Not a git repository: {$cwd}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        [$ok, $output] = $service->pruneWorktrees($cwd, $dryRun);

        if (! $ok) {
            $this->components->error("Failed to prune worktrees: {$output}");

            return self::FAILURE;
        }

        if ($output === '') {
            $this->components->info('Nothing to prune — no stale worktree records.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line($output);
        $this->newLine();

        $this->components->info($dryRun ? 'Dry run: no records were removed.' : 'Pruned stale worktree records.');

        return self::SUCCESS;
    }

    private function resolveCwd(): string
    {
        $arg = $this->argument('path');
        $path = is_string($arg) && $arg !== '' ? $arg : getcwd();
        $real = realpath((string) $path);

        return $real !== false ? $real : (string) $path;
    }
}
