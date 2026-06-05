<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class DisableCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:disable
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Disable the protected-branches config for this repository (clean ignores it)';

    public function handle(ConfigService $service): int
    {
        $cwd = $this->resolveCwd();
        $config = $service->load($cwd);
        $config->enabled = false;
        $service->save($cwd, $config);

        $this->components->task('Protected-branches config <comment>disabled</comment>');

        return self::SUCCESS;
    }
}
