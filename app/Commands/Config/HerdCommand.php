<?php

namespace App\Commands\Config;

use App\Services\ConfigService;
use LaravelZero\Framework\Commands\Command;

class HerdCommand extends Command
{
    protected $signature = 'config:herd
        {action=status : enable, disable, or status}';

    protected $description = 'Toggle automatically running `herd unlink` before removing a worktree (global setting)';

    public function handle(ConfigService $service): int
    {
        $action = (string) $this->argument('action');

        if (! in_array($action, ['enable', 'disable', 'status'], true)) {
            $this->components->error("Unknown action '{$action}'. Use enable, disable, or status.");

            return self::FAILURE;
        }

        if ($action === 'status') {
            $enabled = $service->loadGlobal()->herdUnlinkOnRemove;
            $this->components->info('Herd unlink on remove: <comment>'.($enabled ? 'enabled' : 'disabled').'</comment>');

            return self::SUCCESS;
        }

        $config = $service->loadGlobal();
        $config->herdUnlinkOnRemove = $action === 'enable';
        $service->saveGlobal($config);

        $this->components->task('Herd unlink on remove <comment>'.($config->herdUnlinkOnRemove ? 'enabled' : 'disabled').'</comment>');

        return self::SUCCESS;
    }
}
