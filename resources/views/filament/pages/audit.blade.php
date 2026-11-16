@php
    $logs = \ImranDevBD\RoleMatrix\Models\AuditLog::query()->latest('id')->limit(100)->get();
@endphp
<div class="rm">
    <h2>{{ __('role-matrix::ui.audit') }}</h2>
    <div class="rm-scroll">
        <table class="rm-table">
            <thead>
                <tr>
                    <th>{{ __('role-matrix::ui.when') }}</th>
                    <th>{{ __('role-matrix::ui.event') }}</th>
                    <th>{{ __('role-matrix::ui.actor') }}</th>
                    <th>{{ __('role-matrix::ui.change') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->created_at }}</td>
                        <td>{{ $log->event instanceof \ImranDevBD\RoleMatrix\Enums\AuditEvent ? $log->event->value : $log->event }}</td>
                        <td>{{ $log->actor_type }} #{{ $log->actor_id }}</td>
                        <td><code>{{ json_encode($log->new_values ?? $log->old_values) }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="4">{{ __('role-matrix::ui.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
