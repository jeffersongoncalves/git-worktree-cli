<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class ShellInitCommand extends Command
{
    protected $signature = 'shell-init
        {shell? : Target shell: bash, zsh or fish (auto-detected from $SHELL when omitted)}';

    protected $description = 'Print a shell function enabling `gwt cd <branch>` to change directory';

    public function handle(): int
    {
        $shell = $this->resolveShell();

        $snippet = match ($shell) {
            'fish' => $this->fish(),
            default => $this->posix(),
        };

        foreach (explode("\n", $snippet) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    private function resolveShell(): string
    {
        $arg = $this->argument('shell');

        if (is_string($arg) && $arg !== '') {
            return strtolower($arg);
        }

        $env = (string) (getenv('SHELL') ?: '');

        if (str_contains($env, 'fish')) {
            return 'fish';
        }

        return 'posix';
    }

    private function posix(): string
    {
        return <<<'SH'
        # git-worktree-cli shell integration. Add to ~/.bashrc or ~/.zshrc:
        #   eval "$(git-worktree shell-init)"
        gwt() {
            if [ "$1" = "cd" ]; then
                shift
                local dir
                dir="$(git-worktree path "$@")" && cd "$dir" || return
            else
                git-worktree "$@"
            fi
        }
        SH;
    }

    private function fish(): string
    {
        return <<<'FISH'
        # git-worktree-cli shell integration. Add to ~/.config/fish/config.fish:
        #   git-worktree shell-init fish | source
        function gwt
            if test "$argv[1]" = "cd"
                set -e argv[1]
                set -l dir (git-worktree path $argv); and cd $dir
            else
                git-worktree $argv
            end
        end
        FISH;
    }
}
