<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * Helper to spin up throwaway git repositories inside tests.
 */
class GitRepoBuilder
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public static function baseDir(): string
    {
        $dir = dirname(__DIR__).DIRECTORY_SEPARATOR.'tmp';

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function createIn(?string $baseDir = null, string $name = 'repo'): self
    {
        $baseDir ??= self::baseDir();
        $path = rtrim($baseDir, '/\\').DIRECTORY_SEPARATOR.$name;

        if (is_dir($path)) {
            self::rrmdir($path);
        }

        mkdir($path, 0777, true);

        $builder = new self($path);

        $builder->git(['init', '-q', '-b', 'main']);
        $builder->git(['config', 'user.email', 'test@test.test']);
        $builder->git(['config', 'user.name', 'Test']);
        $builder->commitFile('README.md', 'initial');

        return $builder;
    }

    public function path(?string $sub = null): string
    {
        return $sub === null ? $this->root : $this->root.DIRECTORY_SEPARATOR.$sub;
    }

    public function commitFile(string $file, string $content): void
    {
        file_put_contents($this->path($file), $content.PHP_EOL);
        $this->git(['add', $file]);
        $this->git(['commit', '-q', '-m', "update {$file}"]);
    }

    public function checkoutNewBranch(string $branch): void
    {
        $this->git(['checkout', '-q', '-b', $branch]);
    }

    public function checkout(string $branch): void
    {
        $this->git(['checkout', '-q', $branch]);
    }

    public function merge(string $branch, bool $squash = false): void
    {
        if ($squash) {
            $this->git(['merge', '--squash', $branch]);
            $this->git(['commit', '-q', '-m', "squash {$branch}"]);

            return;
        }

        $this->git(['merge', '--no-ff', '-q', $branch, '-m', "merge {$branch}"]);
    }

    public function addWorktree(string $subdir, string $branch): string
    {
        $path = dirname($this->root).DIRECTORY_SEPARATOR.basename($this->root).'-'.$subdir;

        if (is_dir($path)) {
            self::rrmdir($path);
        }

        $this->git(['worktree', 'add', $path, $branch]);

        return $path;
    }

    public function git(array $args): string
    {
        $process = new Process(['git', ...$args], $this->root);
        $process->setTimeout(60);
        $process->mustRun();

        return $process->getOutput();
    }

    public static function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir.DIRECTORY_SEPARATOR.$item;
            if (is_dir($full) && ! is_link($full)) {
                self::rrmdir($full);
            } else {
                @chmod($full, 0666);
                @unlink($full);
            }
        }
        @rmdir($dir);
    }
}
