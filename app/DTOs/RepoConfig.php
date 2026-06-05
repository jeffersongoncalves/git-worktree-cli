<?php

namespace App\DTOs;

class RepoConfig
{
    /**
     * @param  list<string>  $branches
     */
    public function __construct(
        public bool $enabled = true,
        public array $branches = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $protect = is_array($data['protect'] ?? null) ? $data['protect'] : [];

        $enabled = ! array_key_exists('enabled', $protect) || (bool) $protect['enabled'];

        $branches = is_array($protect['branches'] ?? null) ? $protect['branches'] : [];
        $branches = array_values(array_filter(
            array_map(static fn ($b): string => trim((string) $b), $branches),
            static fn (string $b): bool => $b !== '',
        ));

        return new self($enabled, self::normalize($branches));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'protect' => [
                'enabled' => $this->enabled,
                'branches' => $this->branches,
            ],
        ];
    }

    public function addBranch(string $branch): bool
    {
        $branch = trim($branch);

        if ($branch === '' || in_array($branch, $this->branches, true)) {
            return false;
        }

        $this->branches = self::normalize([...$this->branches, $branch]);

        return true;
    }

    public function removeBranch(string $branch): bool
    {
        $branch = trim($branch);
        $filtered = array_values(array_filter($this->branches, static fn (string $b): bool => $b !== $branch));

        if (count($filtered) === count($this->branches)) {
            return false;
        }

        $this->branches = $filtered;

        return true;
    }

    /**
     * @param  list<string>  $branches
     * @return list<string>
     */
    private static function normalize(array $branches): array
    {
        $branches = array_values(array_unique($branches));
        sort($branches);

        return $branches;
    }
}
