<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\DTOs;

final readonly class PermissionExplanation
{
    /**
     * @param  list<array<string, mixed>>  $trace
     */
    public function __construct(
        public bool $allowed,
        public string $permission,
        public string $reason,
        public array $trace,
        public bool $superAdmin = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'permission' => $this->permission,
            'reason' => $this->reason,
            'super_admin' => $this->superAdmin,
            'trace' => $this->trace,
        ];
    }
}
