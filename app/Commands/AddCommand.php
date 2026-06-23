<?php

namespace App\Commands;

use App\Services\ConfigService;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class AddCommand extends Command
{
    protected $signature = 'add
        {branch : Branch name (new or existing)}
        {path? : Path to the git repository (defaults to the current directory)}
        {--from= : Base ref for a brand-new branch (auto-detected main when omitted)}
        {--remote=origin : Remote used to validate the branch}
        {--no-fetch : Skip `git fetch` before validating the branch on the remote}
        {--target= : Override the worktree directory (defaults to <repo-parent>/<repo>-<suffix>)}
        {--no-submodules : Skip recursive submodule init in the new worktree}
        {--copy=* : Copy a file/dir (e.g. .env) from the main worktree into the new one (repeatable)}
        {--run=* : Run a command inside the new worktree after creation (repeatable)}
        {--no-config : Ignore the per-repo add.copy / add.run config}
        {--y|yes : Skip confirmation when creating a new branch}';

    protected $description = 'Create a worktree for a new or existing branch';

    public function handle(GitWorktreeService $service, ConfigService $config): int
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

        $this->copyFiles($config, $mainPath, $targetPath);

        if (! $this->option('no-submodules') && $service->hasSubmodules($targetPath)) {
            [$ok, $output] = $service->updateSubmodules($targetPath);

            if ($ok) {
                $this->components->task('Initialized submodules <comment>(recursive)</comment>');
            } else {
                $this->components->warn("Worktree created, but submodule init failed: {$output}");
            }
        }

        $this->runHooks($config, $targetPath);

        return self::SUCCESS;
    }

    /**
     * Copy configured/requested files from the main worktree into the new one.
     */
    private function copyFiles(ConfigService $config, string $mainPath, string $targetPath): void
    {
        foreach ($this->resolveList('copy', $config) as $relative) {
            $source = rtrim($mainPath, '/\\').DIRECTORY_SEPARATOR.$relative;
            $dest = rtrim($targetPath, '/\\').DIRECTORY_SEPARATOR.$relative;

            if (! file_exists($source)) {
                $this->components->warn("Skipped copy (not found in main worktree): {$relative}");

                continue;
            }

            if (! $this->copyPath($source, $dest)) {
                $this->components->warn("Failed to copy: {$relative}");

                continue;
            }

            $this->components->task("Copied <comment>{$relative}</comment>");
        }
    }

    /**
     * Run configured/requested commands inside the new worktree.
     */
    private function runHooks(ConfigService $config, string $targetPath): void
    {
        foreach ($this->resolveList('run', $config) as $command) {
            $this->components->info("Running: <comment>{$command}</comment>");

            $process = Process::fromShellCommandline($command, $targetPath);
            $process->setTimeout(600);
            $process->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });

            if ($process->isSuccessful()) {
                $this->components->task("Ran <comment>{$command}</comment>");
            } else {
                $this->components->warn("Command failed (exit {$process->getExitCode()}): {$command}");
            }
        }
    }

    /**
     * Merge CLI flag values with the per-repo config list for the given key.
     *
     * @return list<string>
     */
    private function resolveList(string $key, ConfigService $config): array
    {
        $fromFlag = array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), (array) $this->option($key)),
            static fn (string $v): bool => $v !== '',
        ));

        $fromConfig = [];

        if (! $this->option('no-config')) {
            $repo = $config->load($this->resolveCwd());
            $fromConfig = $key === 'copy' ? $repo->copyOnAdd : $repo->postAdd;
        }

        return array_values(array_unique([...$fromConfig, ...$fromFlag]));
    }

    private function copyPath(string $source, string $dest): bool
    {
        if (is_dir($source)) {
            return $this->copyDir($source, $dest);
        }

        $parent = dirname($dest);

        if (! is_dir($parent) && ! @mkdir($parent, 0777, true) && ! is_dir($parent)) {
            return false;
        }

        return @copy($source, $dest);
    }

    private function copyDir(string $source, string $dest): bool
    {
        if (! is_dir($dest) && ! @mkdir($dest, 0777, true) && ! is_dir($dest)) {
            return false;
        }

        $entries = scandir($source) ?: [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $from = $source.DIRECTORY_SEPARATOR.$entry;
            $to = $dest.DIRECTORY_SEPARATOR.$entry;

            $ok = is_dir($from) ? $this->copyDir($from, $to) : @copy($from, $to);

            if (! $ok) {
                return false;
            }
        }

        return true;
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

        return $this->confirm(
            "Branch '{$branch}' does not exist. Create new branch and worktree?",
            true,
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
