<?php

namespace App\DTOs;

class MergeStatus
{
    public const MERGED = 'merged';

    public const SQUASH_MERGED = 'squash_merged';

    public const NOT_MERGED = 'not_merged';

    public const SAME_AS_MAIN = 'same_as_main';

    public const SKIPPED = 'skipped';

    public function __construct(
        public readonly Worktree $worktree,
        public readonly string $status,
        public readonly ?int $aheadCount = null,
        public readonly ?int $behindCount = null,
        public readonly ?string $reason = null,
    ) {}

    public function isClean(): bool
    {
        return in_array($this->status, [self::MERGED, self::SQUASH_MERGED, self::SAME_AS_MAIN], true);
    }

    public function human(): string
    {
        return match ($this->status) {
            self::MERGED => 'merged',
            self::SQUASH_MERGED => 'squash/rebase merged',
            self::SAME_AS_MAIN => 'same as main',
            self::NOT_MERGED => 'not merged',
            self::SKIPPED => 'skipped'.($this->reason !== null ? ' ('.$this->reason.')' : ''),
            default => $this->status,
        };
    }
}
