<?php

namespace App\Services;

use App\DTOs\RepoConfig;
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
            $slug = $this->slugFromRemote($url);

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
        return $this->configDir().DIRECTORY_SEPARATOR.$this->repoSlug($cwd).'.json';
    }

    public function load(string $cwd): RepoConfig
    {
        $path = $this->path($cwd);

        if (! is_file($path)) {
            return new RepoConfig;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return new RepoConfig;
        }

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return new RepoConfig;
        }

        return RepoConfig::fromArray($data);
    }

    public function save(string $cwd, RepoConfig $config): void
    {
        $dir = $this->configDir();

        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $json = json_encode($config->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return;
        }

        file_put_contents($this->path($cwd), $json.PHP_EOL);
        @chmod($this->path($cwd), 0600);
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

    private function slugFromRemote(string $url): ?string
    {
        $url = trim($url);
        $url = preg_replace('#\.git$#', '', $url) ?? $url;

        // git@host:owner/repo  ->  owner/repo
        // ssh|https://host/owner/repo  ->  owner/repo
        if (preg_match('#[:/]([^/:]+)/([^/]+)$#', $url, $m) !== 1) {
            return null;
        }

        $slug = $this->sanitize($m[1]).'-'.$this->sanitize($m[2]);

        return trim($slug, '-') !== '' ? $slug : null;
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
