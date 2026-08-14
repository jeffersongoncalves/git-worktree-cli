<?php

namespace App\Commands;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use App\Services\GitWorktreeService;
use App\Services\HerdService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;

class RemoveCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'remove
        {target : Branch name or path of the worktree to remove}
        {path? : Path to the git repository (defaults to the current directory)}
        {--delete-branch : Also delete the local branch after removing the worktree}
        {--force : Force removal (pass --force to git, use -D to delete branch)}
        {--y|yes : Skip confirmation prompt}';

    protected $description = 'Remove a single worktree by branch name or path';

    public function handle(GitWorktreeService $service, ConfigService $config, HerdService $herd): int
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

        $target = trim((string) $this->argument('target'));
        $wt = $service->findWorktree($worktrees, $target);

        if ($wt === null) {
            $this->components->error("No worktree found matching '{$target}'.");

            return self::FAILURE;
        }

        if ($wt->isMainWorktree) {
            $this->components->error('Refusing to remove the main worktree.');

            return self::FAILURE;
        }

        $label = $wt->shortBranch() ?? $wt->label();

        if (! $this->option('force') && $service->isDirty($wt->path)) {
            $this->components->warn("Worktree '{$label}' has uncommitted changes.");

            if (! $this->option('yes') && ! $this->confirmDirty($label)) {
                $this->components->warn('Aborted.');

                return self::SUCCESS;
            }
        }

        if (! $this->option('yes') && ! $this->confirmRemoval($label)) {
            $this->components->warn('Aborted.');

            return self::SUCCESS;
        }

        $this->herdUnlink($config, $herd, $wt->path);

        [$ok, $output] = $service->removeWorktree($cwd, $wt->path, (bool) $this->option('force'));

        if (! $ok) {
            $this->components->error("Failed to remove worktree: {$output}");

            return self::FAILURE;
        }

        $this->components->task("Removed worktree <comment>{$label}</comment>");

        if ($this->option('delete-branch') && $wt->branch !== null) {
            [$branchOk, $branchOutput] = $service->deleteBranch($cwd, (string) $wt->shortBranch(), (bool) $this->option('force'));

            if ($branchOk) {
                $this->components->task("Deleted branch <comment>{$label}</comment>");
            } else {
                $this->components->warn("Could not delete branch {$label}: {$branchOutput}");
            }
        }

        return self::SUCCESS;
    }

    private function herdUnlink(ConfigService $config, HerdService $herd, string $path): void
    {
        if (! $config->loadGlobal()->herdUnlinkOnRemove) {
            return;
        }

        if (! $herd->isAvailable()) {
            $this->components->warn('Herd unlink on remove is enabled but the `herd` CLI was not found.');

            return;
        }

        [$ok, $output] = $herd->unlink($path);

        if ($ok) {
            $this->components->task('Unlinked from Herd');
        } else {
            $this->components->warn("Could not unlink from Herd: {$output}");
        }
    }

    private function confirmDirty(string $label): bool
    {
        if (! $this->input->isInteractive()) {
            $this->components->error("Refusing to remove dirty worktree '{$label}' without --force or --yes.");

            return false;
        }

        return confirm(label: 'Remove anyway? Uncommitted changes will be lost.', default: false);
    }

    private function confirmRemoval(string $label): bool
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Refusing to remove a worktree without confirmation. Pass --yes.');

            return false;
        }

        return confirm(label: "Remove worktree '{$label}'?", default: false);
    }
}
