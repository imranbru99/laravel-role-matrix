<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\DTOs;

final readonly class SyncReport
{
    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     * @param  list<string>  $unchanged
     * @param  list<string>  $updated
     */
    public function __construct(
        public string $guard,
        public array $added,
        public array $removed,
        public array $unchanged,
        public array $updated,
        public bool $dryRun,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'guard' => $this->guard,
            'added' => $this->added,
            'removed' => $this->removed,
            'unchanged' => $this->unchanged,
            'updated' => $this->updated,
            'dry_run' => $this->dryRun,
        ];
    }
}
