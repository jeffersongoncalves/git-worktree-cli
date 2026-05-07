<?php

namespace App\Commands;

use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;

class AddCommand extends Command
{
    protected $signature = 'add
        {branch : Branch name (new or existing)}
        {path? : Path to the git repository (defaults to the current directory)}
        {--from= : Base ref for a brand-new branch (auto-detected main when omitted)}
        {--remote=origin : Remote used to validate the branch}
        {--no-fetch : Skip `git fetch` before validating the branch on the remote}
        {--target= : Override the worktree directory (defaults to <repo-parent>/<repo>-<suffix>)}
        {--y|yes : Skip confirmation when creating a new branch}';

    protected $description = 'Create a worktree for a new or existing branch';

    public function handle(GitWorktreeService $service): int
    {
        $cwd = $this->resolveCwd();
        $branch = trim((string) $this->argument('branch'));

        if ($branch === '') {
            $this->components->error('Branch name is required.');

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

        $mainPath = $service->mainWorktreePath($worktrees);

        if ($mainPath === null) {
            $this->components->error('Could not resolve the main worktree path.');

            return self::FAILURE;
        }

        $targetPath = $this->resolveTargetPath($mainPath, $branch);

        if (file_exists($targetPath)) {
            $this->components->error("Target path already exists: {$targetPath}");

            return self::FAILURE;
        }

        $remote = (string) ($this->option('remote') ?: 'origin');

        if (! $this->option('no-fetch')) {
            [$ok, $output] = $service->fetch($cwd, $remote);

            if (! $ok) {
                $this->components->warn("Fetch failed (continuing with local refs): {$output}");
            }
        }

        $existsLocal = $service->branchExistsLocally($cwd, $branch);
        $existsRemote = $service->branchExistsOnRemote($cwd, $remote, $branch);

        if ($existsLocal) {
            $args = [$targetPath, $branch];
            $mode = 'existing local branch';
        } elseif ($existsRemote) {
            $args = ['--track', '-b', $branch, $targetPath, $remote.'/'.$branch];
            $mode = "tracking {$remote}/{$branch}";
        } else {
            if (! $this->confirmCreateNew($branch)) {
                $this->components->warn('Aborted.');

                return self::SUCCESS;
            }

            $from = trim((string) ($this->option('from') ?? ''));

            if ($from === '') {
                $detected = $service->detectMainBranch($cwd);

                if ($detected === null) {
                    $this->components->error('Could not detect the base ref. Provide it explicitly with --from=<ref>.');

                    return self::FAILURE;
                }

                $from = $detected;
            }

            $args = ['-b', $branch, $targetPath, $from];
            $mode = "new branch from {$from}";
        }

        $this->components->info("Repository: <comment>{$cwd}</comment>");
        $this->components->info("Worktree:   <comment>{$targetPath}</comment>");
        $this->components->info("Mode:       <comment>{$mode}</comment>");

        [$ok, $output] = $service->addWorktree($cwd, $args);

        if (! $ok) {
            $this->components->error("Failed to add worktree: {$output}");

            return self::FAILURE;
        }

        $this->components->task("Created worktree <comment>{$branch}</comment>");

        return self::SUCCESS;
    }

    private function resolveTargetPath(string $mainPath, string $branch): string
    {
        $override = $this->option('target');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $repoName = basename($mainPath);
        $parent = dirname($mainPath);
        $suffix = $this->branchSuffix($branch);

        return $parent.DIRECTORY_SEPARATOR.$repoName.'-'.$suffix;
    }

    private function branchSuffix(string $branch): string
    {
        $pos = strrpos($branch, '/');

        return $pos === false ? $branch : substr($branch, $pos + 1);
    }

    private function confirmCreateNew(string $branch): bool
    {
        if ($this->option('yes')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error("Branch '{$branch}' does not exist locally or on the remote. Pass --yes to create it.");

            return false;
        }

        return confirm(
            label: "Branch '{$branch}' does not exist. Create new branch and worktree?",
            default: true,
        );
    }

    private function resolveCwd(): string
    {
        $arg = $this->argument('path');
        $path = is_string($arg) && $arg !== '' ? $arg : getcwd();
        $real = realpath((string) $path);

        return $real !== false ? $real : (string) $path;
    }
}
