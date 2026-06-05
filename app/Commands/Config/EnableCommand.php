<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class EnableCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:enable
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Enable the protected-branches config for this repository';

    public function handle(ConfigService $service): int
    {
        $cwd = $this->resolveCwd();
        $config = $service->load($cwd);
        $config->enabled = true;
        $service->save($cwd, $config);

        $this->components->task('Protected-branches config <comment>enabled</comment>');

        return self::SUCCESS;
    }
}
