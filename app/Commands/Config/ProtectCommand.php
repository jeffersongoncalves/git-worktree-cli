<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class ProtectCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:protect
        {branch : Branch name or glob to protect (e.g. develop or release/*)}
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Add a branch (or glob) to the protected list for this repository';

    public function handle(ConfigService $service): int
    {
        $branch = trim((string) $this->argument('branch'));

        if ($branch === '') {
            $this->components->error('Branch name is required.');

            return self::FAILURE;
        }

        $cwd = $this->resolveCwd();
        $config = $service->load($cwd);

        if (! $config->addBranch($branch)) {
            $this->components->warn("Already protected: {$branch}");

            return self::SUCCESS;
        }

        $service->save($cwd, $config);
        $this->components->task("Protected <comment>{$branch}</comment>");

        return self::SUCCESS;
    }
}
