<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Services;

use Illuminate\Support\Facades\Log;
use ImranDevBD\RoleMatrix\Contracts\AuditServiceContract;
use ImranDevBD\RoleMatrix\Enums\AuditEvent;
use ImranDevBD\RoleMatrix\Jobs\WriteAuditLogJob;
use ImranDevBD\RoleMatrix\Models\AuditLog;
use ImranDevBD\RoleMatrix\Support\AuditContext;

final class AuditService implements AuditServiceContract
{
    public function __construct(private readonly AuditContext $context) {}

    public function record(
        AuditEvent $event,
        ?string $auditableType = null,
        int|string|null $auditableId = null,
        ?array $old = null,
        ?array $new = null,
        ?string $batch = null,
    ): void {
        if (! (bool) config('role-matrix.audit.enabled', true)) {
            return;
        }

        $row = [
            'event' => $event->value,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId === null ? null : (string) $auditableId,
            'actor_type' => $this->actorType(),
            'actor_id' => $this->actorId(),
            'old_values' => $this->redact($old),
            'new_values' => $this->redact($new),
            'ip' => app()->bound('request') ? request()->ip() : null,
            'user_agent' => app()->bound('request') ? substr((string) request()->userAgent(), 0, 2000) : null,
            'batch_uuid' => $batch ?? $this->context->batch(),
            'created_at' => now(),
        ];

        if ((bool) config('role-matrix.audit.queue', false)) {
            WriteAuditLogJob::dispatch($row);

            return;
        }

        $this->write($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function write(array $row): void
    {
        $driver = (string) config('role-matrix.audit.driver', 'db');

        if (in_array($driver, ['db', 'stack'], true)) {
            AuditLog::query()->create($row);
        }

        if (in_array($driver, ['log', 'stack'], true)) {
            Log::info('role-matrix.audit', $row);
        }
    }

    public function prune(int $days): int
    {
        return AuditLog::query()->where('created_at', '<', now()->subDays($days))->delete();
    }

    private function actorType(): ?string
    {
        $user = auth()->user();

        return $user instanceof \Illuminate\Database\Eloquent\Model ? $user->getMorphClass() : null;
    }

    private function actorId(): ?string
    {
        $user = auth()->user();

        return $user instanceof \Illuminate\Database\Eloquent\Model ? (string) $user->getKey() : null;
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (['password', 'password_confirmation', 'remember_token', 'two_factor_secret'] as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}
