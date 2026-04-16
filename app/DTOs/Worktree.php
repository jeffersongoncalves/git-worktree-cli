<?php

namespace App\DTOs;

class Worktree
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $head,
        public readonly ?string $branch,
        public readonly bool $detached,
        public readonly bool $bare,
        public readonly bool $isMainWorktree,
    ) {}

    public function shortBranch(): ?string
    {
        if ($this->branch === null) {
            return null;
        }

        return preg_replace('#^refs/heads/#', '', $this->branch);
    }

    public function label(): string
    {
        if ($this->bare) {
            return '(bare)';
        }

        if ($this->detached) {
            return '(detached HEAD '.substr((string) $this->head, 0, 7).')';
        }

        return $this->shortBranch() ?? '(unknown)';
    }
}
