<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

/**
 * Request-scoped team id. Cleared at the end of every request so Octane
 * workers cannot leak one tenant into the next.
 */
final class TeamContext
{
    private ?string $teamId = null;

    private bool $explicit = false;

    public function set(?string $teamId): void
    {
        $this->teamId = $teamId === '' ? null : $teamId;
        $this->explicit = true;
    }

    public function current(): ?string
    {
        if (! RoleMatrixConfig::teamsEnabled()) {
            return null;
        }

        if ($this->explicit) {
            return $this->teamId;
        }

        if (app()->bound('request')) {
            $fromRequest = request()->attributes->get('role_matrix.team_id');

            if (is_string($fromRequest) && $fromRequest !== '') {
                return $fromRequest;
            }
        }

        return null;
    }

    public function scopeKey(?string $teamId = null): string
    {
        $teamId ??= $this->current();

        return $teamId ?? '';
    }

    public function reset(): void
    {
        $this->teamId = null;
        $this->explicit = false;
    }
}
