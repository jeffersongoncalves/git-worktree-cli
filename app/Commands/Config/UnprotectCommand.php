<?php

namespace App\Commands\Config;

use App\Concerns\ResolvesRepoPath;
use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class UnprotectCommand extends Command
{
    use ResolvesRepoPath;

    protected $signature = 'config:unprotect
        {branch : Branch name or glob to remove from the protected list}
        {path? : Path to the git repository (defaults to the current directory)}';

    protected $description = 'Remove a branch (or glob) from the protected list for this repository';

    public function handle(ConfigService $service): int
    {
        $branch = trim((string) $this->argument('branch'));

        if ($branch === '') {
            $this->components->error('Branch name is required.');

            return self::FAILURE;
        }

        $cwd = $this->resolveCwd();
        $config = $service->load($cwd);

        if (! $config->removeBranch($branch)) {
            $this->components->warn("Not protected: {$branch}");

            return self::SUCCESS;
        }

        $service->save($cwd, $config);
        $this->components->task("Unprotected <comment>{$branch}</comment>");

        return self::SUCCESS;
    }
}
