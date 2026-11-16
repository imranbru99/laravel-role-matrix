@php
    $payload = $payload ?? [];
    $guard = $payload['guard'] ?? 'web';
@endphp

<link rel="stylesheet" href="{{ route('role-matrix.assets.css') }}">

<div class="rm" data-rm data-theme="auto" role="region" aria-label="{{ __('role-matrix::ui.matrix') }}">
    <script type="application/json" data-rm-payload>@json($payload)</script>

    <div class="rm-toolbar">
        <input class="rm-search" data-rm-search type="search" placeholder="{{ __('role-matrix::ui.search') }}" aria-label="{{ __('role-matrix::ui.search') }}">
        <button type="button" class="rm-chip is-on" data-rm-group="" aria-pressed="true">{{ __('role-matrix::ui.all') }}</button>
        @foreach ($payload['groups'] ?? [] as $group)
            <button type="button" class="rm-chip" data-rm-group="{{ $group['name'] }}" aria-pressed="false">{{ $group['name'] }}</button>
        @endforeach
        <button type="button" class="rm-chip is-on" data-rm-filter="all" aria-pressed="true">{{ __('role-matrix::ui.all') }}</button>
        <button type="button" class="rm-chip" data-rm-filter="selected" aria-pressed="false">{{ __('role-matrix::ui.only_selected') }}</button>
        <button type="button" class="rm-chip" data-rm-filter="unselected" aria-pressed="false">{{ __('role-matrix::ui.only_unselected') }}</button>
        <button type="button" class="rm-chip" data-rm-filter="conditional" aria-pressed="false">{{ __('role-matrix::ui.only_conditional') }}</button>
        @foreach ($payload['guards'] ?? [] as $name)
            <a class="rm-btn {{ $name === $guard ? 'primary' : '' }}" href="?guard={{ $name }}">{{ $name }}</a>
        @endforeach
        <button type="button" class="rm-btn" data-rm-density>{{ __('role-matrix::ui.density') }}</button>
        <button type="button" class="rm-btn" data-rm-view="columns" aria-pressed="true">{{ __('role-matrix::ui.columns') }}</button>
        <label class="rm-sr">{{ __('role-matrix::ui.focus') }}
            <select class="rm-select" data-rm-focus-role>
                @foreach ($payload['roles'] ?? [] as $role)
                    <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" class="rm-btn" data-rm-view="focus">{{ __('role-matrix::ui.focus') }}</button>
        <label>{{ __('role-matrix::ui.compare') }}
            <select class="rm-select" data-rm-compare-a>
                <option value="">{{ __('role-matrix::ui.all') }}</option>
                @foreach ($payload['roles'] ?? [] as $role)
                    <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                @endforeach
            </select>
            <select class="rm-select" data-rm-compare-b>
                <option value="">{{ __('role-matrix::ui.all') }}</option>
                @foreach ($payload['roles'] ?? [] as $role)
                    <option value="{{ $role['id'] }}">{{ $role['name'] }}</option>
                @endforeach
            </select>
        </label>
        <button type="button" class="rm-btn" data-rm-collapse="all">{{ __('role-matrix::ui.collapse') }}</button>
        <button type="button" class="rm-btn" data-rm-collapse="none">{{ __('role-matrix::ui.expand') }}</button>
        <span class="rm-badge" data-rm-dirty hidden>0</span>
        <button type="button" class="rm-btn" data-rm-discard>{{ __('role-matrix::ui.discard') }}</button>
        <button type="button" class="rm-btn primary" data-rm-save disabled>{{ __('role-matrix::ui.save') }}</button>
    </div>

    <div data-rm-columns class="rm-scroll">
        <table class="rm-table" role="grid">
            <thead>
                <tr>
                    <th class="rm-sticky">{{ __('role-matrix::ui.module') }}</th>
                    @foreach ($payload['roles'] ?? [] as $role)
                        <th data-role-col="{{ $role['id'] }}" style="color: {{ $role['color'] }}">
                            <span class="rm-dot" style="background: {{ $role['color'] }}"></span>
                            {{ $role['name'] }}
                            @if ($role['locked']) <span title="{{ __('role-matrix::ui.locked') }}">🔒</span> @endif
                            <small style="display:block">{{ $role['users'] }}</small>
                            <span class="rm-actions">
                                @foreach (['view', 'create', 'update', 'delete'] as $shortcut)
                                    <button type="button" class="rm-btn" data-column="{{ $role['id'] }}" data-action="{{ $shortcut }}" @disabled($role['locked'])>{{ $shortcut }}</button>
                                @endforeach
                            </span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($payload['groups'] ?? [] as $group)
                    <tr class="rm-group"><td class="rm-sticky" colspan="{{ count($payload['roles'] ?? []) + 1 }}">{{ $group['name'] }}</td></tr>
                    @foreach ($group['modules'] as $index => $module)
                        @php
                            $search = strtolower($module['label'].' '.$module['slug'].' '.$module['description']);
                        @endphp
                        <tr data-module-row data-group="{{ $group['name'] }}" data-search="{{ $search }}" @if($index >= ($payload['virtualize_after'] ?? 30)) data-rm-extra hidden @endif>
                            <th class="rm-sticky" scope="row">
                                <div class="rm-module-label">
                                    <div>
                                        <strong class="rm-label">{{ $module['label'] }}</strong>
                                        <small>{{ $module['description'] }}</small>
                                    </div>
                                </div>
                            </th>
                            @foreach ($payload['roles'] ?? [] as $role)
                                <td data-role-col="{{ $role['id'] }}" class="rm-cell {{ $role['locked'] ? 'rm-locked' : '' }}">
                                    <label title="{{ __('role-matrix::ui.all') }}">
                                        <input type="checkbox" data-master="{{ $module['slug'] }}" data-role="{{ $role['id'] }}" data-locked="{{ $role['locked'] ? '1' : '0' }}" aria-label="{{ $role['name'] }} {{ $module['label'] }} {{ __('role-matrix::ui.all') }}">
                                    </label>
                                    <div class="rm-progress" data-progress="{{ $module['slug'] }}" data-role="{{ $role['id'] }}"><span></span> <small data-count>0/0</small></div>
                                    <div class="rm-actions">
                                        @foreach ($module['actions'] as $action)
                                            <label class="{{ $action['danger'] ? 'rm-danger' : '' }}" title="{{ $action['description'] ?? $action['label'] }}">
                                                <input class="rm-check" type="checkbox" data-cell data-grant="{{ $action['key'] }}" data-role="{{ $role['id'] }}" data-module="{{ $module['slug'] }}" data-locked="{{ $role['locked'] ? '1' : '0' }}" aria-label="{{ $role['name'] }} {{ $module['label'] }} {{ $action['label'] }}">
                                                <span>{{ $action['label'] }}</span>
                                            </label>
                                            <button type="button" class="rm-btn" data-condition="{{ $action['key'] }}" data-role="{{ $role['id'] }}" @disabled($role['locked'])>{{ __('role-matrix::ui.conditions') }}</button>
                                        @endforeach
                                    </div>
                                    <div class="rm-actions">
                                        @foreach (['none' => __('role-matrix::ui.none'), 'read' => __('role-matrix::ui.read_only'), 'standard' => __('role-matrix::ui.standard'), 'full' => __('role-matrix::ui.full')] as $preset => $label)
                                            <button type="button" class="rm-btn" data-preset="{{ $preset }}" data-module="{{ $module['slug'] }}" data-role="{{ $role['id'] }}" @disabled($role['locked'])>{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    <div data-rm-focus hidden class="rm-focus-grid"></div>

    <form data-rm-form method="POST" action="{{ $saveUrl ?? '' }}" hidden>
        @csrf
        <input type="hidden" name="guard" value="{{ $guard }}">
        <input type="hidden" name="changes" value="[]">
    </form>

    <div class="rm-modal" data-rm-modal hidden>
        <div class="rm-dialog" role="dialog" aria-modal="true" aria-labelledby="rm-confirm-title">
            <h2 id="rm-confirm-title">{{ __('role-matrix::ui.confirm_title') }}</h2>
            <ul data-rm-diff></ul>
            <label data-rm-danger-ack hidden><input type="checkbox"> {{ __('role-matrix::ui.danger_confirm') }}</label>
            <div class="rm-actions">
                <button type="button" class="rm-btn" data-rm-close>{{ __('role-matrix::ui.discard') }}</button>
                <button type="button" class="rm-btn primary" data-rm-confirm>{{ __('role-matrix::ui.save') }}</button>
            </div>
        </div>
    </div>

    <aside class="rm-drawer" data-rm-drawer hidden>
        <h2>{{ __('role-matrix::ui.conditions') }}</h2>
        <p>{{ __('role-matrix::ui.condition_help') }}</p>
        <div class="rm-actions" data-rm-builder></div>
        <p data-rm-readable></p>
        <textarea data-rm-summary rows="12" style="width:100%"></textarea>
        <div class="rm-actions">
            <button type="button" class="rm-btn" data-rm-close>{{ __('role-matrix::ui.close') }}</button>
            <button type="button" class="rm-btn primary" data-rm-apply-condition>{{ __('role-matrix::ui.apply') }}</button>
        </div>
    </aside>
</div>
<script src="{{ route('role-matrix.assets.js') }}"></script>
<style>.rm-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}</style>
