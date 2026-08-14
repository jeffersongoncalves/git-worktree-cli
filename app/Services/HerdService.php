<?php

namespace App\Services;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class HerdService
{
    public function isAvailable(): bool
    {
        return (new ExecutableFinder)->find('herd') !== null;
    }

    /**
     * Run `herd unlink` inside the given worktree path.
     *
     * @return array{0: bool, 1: string} success flag plus combined output
     */
    public function unlink(string $path): array
    {
        $process = new Process(['herd', 'unlink'], $path);
        $process->setTimeout(30);
        $process->run();

        $output = trim($process->getOutput().$process->getErrorOutput());

        return [$process->isSuccessful(), $output];
    }
}
