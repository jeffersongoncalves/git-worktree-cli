<?php

namespace App\DTOs;

class GlobalConfig
{
    public function __construct(
        public bool $herdUnlinkOnRemove = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $herd = is_array($data['herd'] ?? null) ? $data['herd'] : [];

        return new self((bool) ($herd['unlink_on_remove'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'herd' => [
                'unlink_on_remove' => $this->herdUnlinkOnRemove,
            ],
        ];
    }
}
