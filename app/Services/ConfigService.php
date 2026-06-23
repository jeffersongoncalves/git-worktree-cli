<?php

namespace App\Services;

use App\DTOs\RepoConfig;
use JeffersonGoncalves\LaravelZero\Git\GitRemoteParser;
use JeffersonGoncalves\LaravelZero\JsonConfig\JsonConfigService;
use JeffersonGoncalves\LaravelZero\JsonConfig\Scopes\PerRepoScope;
use JeffersonGoncalves\LaravelZero\Support\Filesystem;
use Symfony\Component\Process\Process;

class ConfigService
{
    /**
     * Absolute path of the directory holding the per-repo config files.
     * Honors XDG_CONFIG_HOME, falling back to ~/.config.
     */
    public function configDir(): string
    {
        $xdg = getenv('XDG_CONFIG_HOME');

        if (is_string($xdg) && $xdg !== '') {
            $base = $xdg;
        } else {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: getcwd();
            $base = rtrim((string) $home, '/\\').DIRECTORY_SEPARATOR.'.config';
        }

        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.'git-worktree';
    }

    /**
     * Stable, human-readable slug identifying the repository.
     * Derived from the origin remote (owner-repo); falls back to the
     * working-tree basename plus a short hash of the common git dir.
     */
    public function repoSlug(string $cwd): string
    {
        $url = $this->gitOutput($cwd, ['config', '--get', 'remote.origin.url']);

        if ($url !== null && $url !== '') {
            $slug = GitRemoteParser::slug($url);

            if ($slug !== null) {
                return $slug;
            }
        }

        $top = $this->gitOutput($cwd, ['rev-parse', '--show-toplevel']) ?? $cwd;
        $common = $this->gitOutput($cwd, ['rev-parse', '--git-common-dir']) ?? $top;

        $base = $this->sanitize(basename($top));
        $hash = substr(sha1($common), 0, 6);

        return ($base !== '' ? $base : 'repo').'-'.$hash;
    }

    public function path(string $cwd): string
    {
        return $this->store($cwd)->path();
    }

    public function load(string $cwd): RepoConfig
    {
        return RepoConfig::fromArray($this->store($cwd)->all());
    }

    public function save(string $cwd, RepoConfig $config): void
    {
        Filesystem::writeJsonSecure($this->path($cwd), $config->toArray());
    }

    /**
     * Protected branch patterns that apply for a run.
     * Returns an empty list when the config file is disabled or skipped.
     *
     * @return list<string>
     */
    public function protectedBranches(string $cwd, bool $useConfig = true): array
    {
        if (! $useConfig) {
            return [];
        }

        $config = $this->load($cwd);

        return $config->enabled ? $config->branches : [];
    }

    /**
     * Whether a branch matches any protected pattern (exact or glob).
     *
     * @param  list<string>  $patterns
     */
    public function isProtected(string $branch, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $branch) {
                return true;
            }

            if (fnmatch($pattern, $branch)) {
                return true;
            }
        }

        return false;
    }

    private function store(string $cwd): JsonConfigService
    {
        return new JsonConfigService(new PerRepoScope('git-worktree', $this->repoSlug($cwd)));
    }

    private function sanitize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('#[^a-z0-9._-]+#', '-', $value) ?? $value;

        return trim($value, '-');
    }

    /**
     * @param  list<string>  $args
     */
    private function gitOutput(string $cwd, array $args): ?string
    {
        $process = new Process(['git', ...$args], $cwd);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $out = trim($process->getOutput());

        return $out === '' ? null : $out;
    }
}
