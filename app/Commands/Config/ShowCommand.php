<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class ShowCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:show
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Show the resolved protected-branches config for this repository';

    public function handle(ConfigService $service): int
    {
        $cwd = $this->resolveCwd();
        $config = $service->load($cwd);

        $this->components->info('Slug:    <comment>'.$service->repoSlug($cwd).'</comment>');
        $this->components->info('File:    <comment>'.$service->path($cwd).'</comment>');
        $this->components->info('Enabled: <comment>'.($config->enabled ? 'yes' : 'no').'</comment>');

        if ($config->branches === []) {
            $this->newLine();
            $this->components->info('No protected branches configured.');
        } else {
            $this->newLine();
            $this->components->info('Protected branches:');
            $this->table(['Pattern'], array_map(static fn (string $b): array => [$b], $config->branches));
        }

        if ($config->copyOnAdd !== []) {
            $this->newLine();
            $this->components->info('Files copied on add:');
            $this->table(['Path'], array_map(static fn (string $p): array => [$p], $config->copyOnAdd));
        }

        if ($config->postAdd !== []) {
            $this->newLine();
            $this->components->info('Commands run on add:');
            $this->table(['Command'], array_map(static fn (string $c): array => [$c], $config->postAdd));
        }

        return self::SUCCESS;
    }
}
