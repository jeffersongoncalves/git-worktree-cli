<?php

namespace App\Concerns;

use JeffersonGoncalves\LaravelZero\Console\ResolvesPath;

trait ResolvesRepoPath
{
    use ResolvesPath {
        resolveCwd as protected resolvePackageCwd;
    }

    protected function resolveCwd(): string
    {
        /** @var mixed $arg */
        $arg = $this->argument('path');

        return $this->resolvePath(is_string($arg) && $arg !== '' ? $arg : null);
    }
}
