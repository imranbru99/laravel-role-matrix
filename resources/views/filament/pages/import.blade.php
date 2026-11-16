<div class="rm">
    <h2>{{ __('role-matrix::ui.import') }}</h2>
    <form method="POST" action="{{ route('role-matrix.import') }}" class="rm-card">
        @csrf
        <label>{{ __('role-matrix::ui.mode') }}
            <select class="rm-select" name="mode">
                <option value="dry-run">dry-run</option>
                <option value="merge">merge</option>
                <option value="replace">replace</option>
            </select>
        </label>
        <textarea name="payload" rows="14" style="width:100%" required placeholder='{"schema":"role-matrix/1"}'></textarea>
        <label><input type="checkbox" name="create_missing" value="1"> {{ __('role-matrix::ui.create_missing') }}</label>
        <button class="rm-btn primary" type="submit">{{ __('role-matrix::ui.apply') }}</button>
    </form>
    @if (session('role_matrix_import'))
        <pre>{{ json_encode(session('role_matrix_import'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    @endif
</div>
