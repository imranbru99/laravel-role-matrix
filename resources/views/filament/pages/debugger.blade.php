@php
    $result = null;
    $userId = request('user');
    $permission = request('permission');
    if (is_string($userId) && $userId !== '' && is_string($permission) && $permission !== '') {
        $model = \ImranDevBD\RoleMatrix\Support\RoleMatrixConfig::model('user');
        $user = $model::query()->find($userId);
        if ($user) {
            $result = app(\ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract::class)->explain($user, $permission);
        }
    }
@endphp
<div class="rm">
    <h2>{{ __('role-matrix::ui.debugger') }}</h2>
    <form method="GET" class="rm-toolbar">
        <input class="rm-search" name="user" value="{{ request('user') }}" placeholder="{{ __('role-matrix::ui.user_id') }}" required>
        <input class="rm-search" name="permission" value="{{ request('permission') }}" placeholder="inventory.view" required>
        <button class="rm-btn primary" type="submit">{{ __('role-matrix::ui.explain') }}</button>
    </form>
    @if ($result)
        <pre class="rm-card">{{ json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
