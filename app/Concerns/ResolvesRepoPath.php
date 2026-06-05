<?php

namespace App\Concerns;

trait ResolvesRepoPath
{
    protected function resolveCwd(): string
    {
        /** @var mixed $arg */
        $arg = $this->argument('path');
        $path = is_string($arg) && $arg !== '' ? $arg : getcwd();
        $real = realpath((string) $path);

        return $real !== false ? $real : (string) $path;
    }
}
