<?php

namespace App\Commands;

use App\Concerns\ResolvesRepoPath;
use App\Services\GitWorktreeService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class OpenCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'open
        {target : Branch name or path of the worktree}
        {path? : Path to the git repository (defaults to the current directory)}
        {--editor= : Editor command to use (defaults to $VISUAL, $EDITOR, then "code")}';

    protected $description = 'Open a worktree in your editor';

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

        $editor = $this->resolveEditor();

        if ($editor === null) {
            $this->components->error('No editor found. Set $EDITOR/$VISUAL or pass --editor=<command>.');

            return self::FAILURE;
        }

        $label = $wt->shortBranch() ?? $wt->label();
        $this->components->info("Opening <comment>{$label}</comment> in <comment>{$editor}</comment>");

        $process = Process::fromShellCommandline(
            sprintf('%s %s', $editor, escapeshellarg($wt->path)),
        );
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->components->error('Editor exited with an error: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveEditor(): ?string
    {
        $override = $this->option('editor');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        foreach (['VISUAL', 'EDITOR'] as $var) {
            $value = getenv($var);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'code';
    }
}
