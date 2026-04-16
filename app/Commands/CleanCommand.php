<?php

namespace App\Commands;

use App\DTOs\MergeStatus;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

use function Laravel\Prompts\confirm;

class CleanCommand extends Command
{
    protected $signature = 'clean
        {path? : Path to the git repository (defaults to the current directory)}
        {--main= : Name of the main branch (auto-detected when omitted)}
        {--dry-run : Show what would be removed without removing anything}
        {--delete-branch : Also delete the local branch after removing the worktree}
        {--force : Force removal (pass --force to git worktree remove, use -D to delete branch)}
        {--strict : Only consider branches directly merged (exclude squash/rebase detection)}
        {--y|yes : Skip confirmation prompt}';

    protected $description = 'Remove worktrees whose branches are already merged into the main branch';

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

        if (! $service->hasLinkedWorktrees($worktrees)) {
            $this->components->error('No linked worktrees found. Nothing to clean.');

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
        $candidates = $this->filterCandidates($results);

        if ($candidates === []) {
            $this->newLine();
            $this->components->info('Nothing to clean — no merged worktree branches found.');

            return self::SUCCESS;
        }

        $this->renderPreview($candidates);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->components->info('Dry run: no changes were made.');

            return self::SUCCESS;
        }

        if (! $this->option('yes') && ! $this->confirmRemoval(count($candidates))) {
            $this->components->warn('Aborted.');

            return self::SUCCESS;
        }

        return $this->removeAll($service, $cwd, $candidates);
    }

    /**
     * @param  list<MergeStatus>  $results
     * @return list<MergeStatus>
     */
    private function filterCandidates(array $results): array
    {
        $allowed = [MergeStatus::MERGED, MergeStatus::SAME_AS_MAIN];

        if (! $this->option('strict')) {
            $allowed[] = MergeStatus::SQUASH_MERGED;
        }

        return array_values(array_filter(
            $results,
            fn (MergeStatus $r) => in_array($r->status, $allowed, true),
        ));
    }

    /**
     * @param  list<MergeStatus>  $candidates
     */
    private function renderPreview(array $candidates): void
    {
        $rows = [];
        foreach ($candidates as $result) {
            $rows[] = [
                $result->worktree->shortBranch() ?? $result->worktree->label(),
                $this->formatStatus($result),
                $this->shortPath($result->worktree->path),
            ];
        }

        $this->newLine();
        $this->components->info('Worktrees to remove:');
        $this->table(['Branch', 'Status', 'Worktree'], $rows);
    }

    /**
     * @param  list<MergeStatus>  $candidates
     */
    private function removeAll(GitWorktreeService $service, string $cwd, array $candidates): int
    {
        $removed = 0;
        $failed = 0;
        $branchesDeleted = 0;
        $force = (bool) $this->option('force');
        $deleteBranch = (bool) $this->option('delete-branch');

        foreach ($candidates as $result) {
            $wt = $result->worktree;
            $label = $wt->shortBranch() ?? $wt->label();

            [$ok, $output] = $service->removeWorktree($cwd, $wt->path, $force);

            if (! $ok) {
                $failed++;
                $this->components->error("Failed to remove {$label}: {$output}");

                continue;
            }

            $removed++;
            $this->components->task("Removed worktree <comment>{$label}</comment>");

            if ($deleteBranch && $wt->branch !== null) {
                [$branchOk, $branchOutput] = $service->deleteBranch($cwd, (string) $wt->shortBranch(), $force);

                if ($branchOk) {
                    $branchesDeleted++;
                    $this->components->task("Deleted branch <comment>{$label}</comment>");
                } else {
                    $this->components->warn("Could not delete branch {$label}: {$branchOutput}");
                }
            }
        }

        $this->newLine();

        if ($removed > 0) {
            $this->components->info("Removed {$removed} worktree(s).");
        }

        if ($branchesDeleted > 0) {
            $this->components->info("Deleted {$branchesDeleted} branch(es).");
        }

        if ($failed > 0) {
            $this->components->warn("{$failed} worktree(s) could not be removed. Re-run with --force if they have untracked or modified files.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function confirmRemoval(int $count): bool
    {
        if (! $this->input->isInteractive()) {
            $this->components->error('Refusing to remove worktrees without confirmation. Pass --yes or --dry-run.');

            return false;
        }

        return confirm(
            label: "Remove {$count} worktree(s)?",
            default: false,
        );
    }

    private function formatStatus(MergeStatus $result): string
    {
        return match ($result->status) {
            MergeStatus::MERGED => '<fg=green>merged</>',
            MergeStatus::SQUASH_MERGED => '<fg=green>squash/rebase merged</>',
            MergeStatus::SAME_AS_MAIN => '<fg=green>same as main</>',
            default => $result->human(),
        };
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
