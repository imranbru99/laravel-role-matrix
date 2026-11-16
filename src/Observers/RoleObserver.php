<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Observers;

use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AuditService;
use ImranDevBD\RoleMatrix\Services\CacheService;
use ImranDevBD\RoleMatrix\Services\SuperAdminGuard;
use ImranDevBD\RoleMatrix\Support\AuditContext;

final class RoleObserver
{
    public function __construct(
        private readonly AuditContext $context,
        private readonly AuditService $audit,
        private readonly CacheService $cache,
        private readonly SuperAdminGuard $guard,
    ) {}

    public function updating(Role $role): void
    {
        if ($this->context->paused()) {
            return;
        }

        $this->guard->assertCanUpdate($role, $role->getDirty());
    }

    public function deleting(Role $role): void
    {
        if ($this->context->paused()) {
            return;
        }

        $this->guard->assertCanDelete($role);
    }

    public function deleted(Role $role): void
    {
        $this->cache->forgetAll();

        if ($this->context->paused()) {
            return;
        }

        $this->audit->record(
            $role->isForceDeleting() ? AuditEvent::RoleForceDeleted : AuditEvent::RoleDeleted,
            $role->getMorphClass(),
            $role->getKey(),
            $role->toArray(),
            null,
        );
    }

    public function restored(Role $role): void
    {
        $this->cache->forgetAll();
    }
}
