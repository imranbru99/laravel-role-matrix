<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use Illuminate\Support\Str;

/**
 * Lets services write one audit batch while model observers stay quiet.
 * Mutable on purpose, and reset at the end of the request.
 */
final class AuditContext
{
    private bool $paused = false;

    private ?string $batch = null;

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
    }

    public function paused(): bool
    {
        return $this->paused;
    }

    public function batch(): string
    {
        return $this->batch ??= (string) Str::uuid();
    }

    public function newBatch(): string
    {
        $this->batch = (string) Str::uuid();

        return $this->batch;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withoutObservers(callable $callback): mixed
    {
        $previous = $this->paused;
        $this->paused = true;

        try {
            return $callback();
        } finally {
            $this->paused = $previous;
        }
    }

    public function reset(): void
    {
        $this->paused = false;
        $this->batch = null;
    }
}
