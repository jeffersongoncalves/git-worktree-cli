<?php

namespace App\DTOs;

class RepoConfig
{
    /**
     * @param  list<string>  $branches
     * @param  list<string>  $copyOnAdd
     * @param  list<string>  $postAdd
     */
    public function __construct(
        public bool $enabled = true,
        public array $branches = [],
        public array $copyOnAdd = [],
        public array $postAdd = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $protect = is_array($data['protect'] ?? null) ? $data['protect'] : [];

        $enabled = ! array_key_exists('enabled', $protect) || (bool) $protect['enabled'];

        $branches = is_array($protect['branches'] ?? null) ? $protect['branches'] : [];
        $branches = self::cleanList($branches);

        $add = is_array($data['add'] ?? null) ? $data['add'] : [];
        $copyOnAdd = self::cleanList(is_array($add['copy'] ?? null) ? $add['copy'] : []);
        $postAdd = self::cleanList(is_array($add['run'] ?? null) ? $add['run'] : []);

        return new self($enabled, self::normalize($branches), $copyOnAdd, $postAdd);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'protect' => [
                'enabled' => $this->enabled,
                'branches' => $this->branches,
            ],
        ];

        if ($this->copyOnAdd !== [] || $this->postAdd !== []) {
            $out['add'] = [
                'copy' => $this->copyOnAdd,
                'run' => $this->postAdd,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private static function cleanList(array $values): array
    {
        return array_values(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $values),
            static fn (string $v): bool => $v !== '',
        ));
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
