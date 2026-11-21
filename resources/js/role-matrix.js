(function () {
    function boot(root) {
        const payloadNode = root.querySelector('[data-rm-payload]');
        if (!payloadNode) return;
        const payload = JSON.parse(payloadNode.textContent || '{}');
        const state = {};
        payload.roles.forEach((role) => {
            state[role.id] = JSON.parse(JSON.stringify(role.grants || {}));
        });
        const original = JSON.parse(JSON.stringify(state));
        const focus = root.querySelector('[data-rm-focus]');
        let anchor = null;

        const text = (key) => (payload.i18n && payload.i18n[key]) || key;

        function cell(roleId, key) {
            return state[roleId][key] || { on: false, conditions: null };
        }

        function setCell(roleId, key, on, conditions) {
            const role = payload.roles.find((item) => item.id === roleId);
            if (role && role.locked) return;
            state[roleId][key] = { on: !!on, conditions: conditions === undefined ? (state[roleId][key] || {}).conditions || null : conditions };
        }

        function requirements(key) {
            return (payload.requires && payload.requires[key]) || [];
        }

        function paint() {
            root.querySelectorAll('[data-grant]').forEach((input) => {
                const grant = cell(input.dataset.role, input.dataset.grant);
                input.checked = !!grant.on;
                input.disabled = input.dataset.locked === '1';
                const wrap = input.closest('[data-cell]');
                if (!wrap) return;
                wrap.classList.toggle('rm-has-condition', !!(grant.conditions && (grant.conditions.all || grant.conditions.any)));
            });
            root.querySelectorAll('[data-master]').forEach((input) => {
                const boxes = [...root.querySelectorAll(`[data-role="${input.dataset.role}"][data-module="${input.dataset.master}"]:not(:disabled)`)];
                const on = boxes.filter((box) => box.checked).length;
                input.checked = boxes.length > 0 && on === boxes.length;
                input.indeterminate = on > 0 && on < boxes.length;
            });
            root.querySelectorAll('[data-progress]').forEach((bar) => {
                const boxes = [...root.querySelectorAll(`[data-role="${bar.dataset.role}"][data-module="${bar.dataset.progress}"]`)];
                const on = boxes.filter((box) => box.checked).length;
                const span = bar.querySelector('span');
                if (span) span.style.width = boxes.length ? `${Math.round((on / boxes.length) * 100)}%` : '0';
                const label = bar.parentElement && bar.parentElement.querySelector('[data-count]');
                if (label) label.textContent = `${on}/${boxes.length}`;
            });
            const dirty = diff().length;
            const badge = root.querySelector('[data-rm-dirty]');
            if (badge) {
                badge.hidden = dirty === 0;
                badge.textContent = `${dirty} ${text('unsaved')}`;
            }
            const save = root.querySelector('[data-rm-save]');
            if (save) save.disabled = dirty === 0;
        }

        function diff() {
            const changes = [];
            payload.roles.forEach((role) => {
                if (role.locked) return;
                const grant = [];
                const revoke = [];
                const conditions = {};
                const before = original[role.id] || {};
                const after = state[role.id] || {};
                const keys = new Set([...Object.keys(before), ...Object.keys(after)]);
                keys.forEach((key) => {
                    const was = !!(before[key] && before[key].on);
                    const now = !!(after[key] && after[key].on);
                    if (!was && now) grant.push(key);
                    if (was && !now) revoke.push(key);
                    const left = JSON.stringify((before[key] && before[key].conditions) || null);
                    const right = JSON.stringify((after[key] && after[key].conditions) || null);
                    if (now && left !== right) conditions[key] = after[key].conditions;
                });
                if (grant.length || revoke.length || Object.keys(conditions).length) {
                    changes.push({ role_id: role.id, grant, revoke, conditions });
                }
            });
            return changes;
        }

        function toast(message) {
            let node = root.querySelector('[data-rm-toast]');
            if (!node) {
                node = document.createElement('div');
                node.className = 'rm-toast';
                node.setAttribute('data-rm-toast', '');
                root.appendChild(node);
            }
            node.textContent = message;
            node.hidden = false;
            clearTimeout(node._t);
            node._t = setTimeout(() => { node.hidden = true; }, 2200);
        }

        function enable(roleId, key, on) {
            const role = payload.roles.find((item) => item.id === roleId);
            if (!role || role.locked) return;
            if (on && payload.dangerous && payload.dangerous.some((name) => key.endsWith('.' + name))) {
                if (!window.confirm(text('danger_confirm'))) return;
            }
            setCell(roleId, key, on);
            if (on) {
                requirements(key).forEach((needed) => {
                    if (!cell(roleId, needed).on) {
                        setCell(roleId, needed, true);
                        toast(text('dependency'));
                    }
                });
            } else {
                const dependents = (payload.required_by && payload.required_by[key]) || [];
                dependents.forEach((child) => {
                    if (cell(roleId, child).on) setCell(roleId, child, false);
                });
            }
            paint();
        }

        function applyFilter() {
            const query = (root.querySelector('[data-rm-search]')?.value || '').trim().toLowerCase();
            const mode = root.querySelector('[data-rm-filter].is-on')?.dataset.rmFilter || 'all';
            const group = root.querySelector('[data-rm-group].is-on')?.dataset.rmGroup || '';
            root.querySelectorAll('[data-module-row]').forEach((row) => {
                const haystack = (row.dataset.search || '').toLowerCase();
                const matchesQuery = query === '' || haystack.includes(query);
                const matchesGroup = group === '' || row.dataset.group === group;
                let matchesMode = true;
                if (mode !== 'all') {
                    const boxes = [...row.querySelectorAll('[data-grant]')];
                    if (mode === 'selected') matchesMode = boxes.some((box) => box.checked);
                    if (mode === 'unselected') matchesMode = boxes.some((box) => !box.checked);
                    if (mode === 'conditional') matchesMode = boxes.some((box) => cell(box.dataset.role, box.dataset.grant).conditions);
                }
                row.classList.toggle('rm-hidden', !(matchesQuery && matchesGroup && matchesMode));
            });
        }

        root.addEventListener('change', (event) => {
            const input = event.target;
            if (!(input instanceof HTMLInputElement)) return;
            if (input.dataset.grant) {
                enable(input.dataset.role, input.dataset.grant, input.checked);
            }
            if (input.dataset.master) {
                root.querySelectorAll(`[data-role="${input.dataset.role}"][data-module="${input.dataset.master}"]`).forEach((box) => {
                    enable(input.dataset.role, box.dataset.grant, input.checked);
                });
            }
            applyFilter();
        });

        root.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLInputElement && target.dataset.grant && event.shiftKey && anchor instanceof HTMLInputElement) {
                const boxes = [...root.querySelectorAll(`[data-role="${target.dataset.role}"][data-grant]`)];
                const start = boxes.indexOf(anchor);
                const end = boxes.indexOf(target);
                if (start >= 0 && end >= 0) {
                    const [from, to] = start < end ? [start, end] : [end, start];
                    for (let i = from; i <= to; i++) {
                        enable(boxes[i].dataset.role, boxes[i].dataset.grant, target.checked);
                    }
                }
            }
            if (target instanceof HTMLInputElement && target.dataset.grant) anchor = target;

            const button = target.closest ? target.closest('button') : null;
            if (!button) return;
            if (button.hasAttribute('data-rm-filter') || button.hasAttribute('data-rm-group')) {
                const attr = button.hasAttribute('data-rm-filter') ? 'data-rm-filter' : 'data-rm-group';
                root.querySelectorAll(`[${attr}]`).forEach((chip) => chip.classList.remove('is-on'));
                button.classList.add('is-on');
                button.setAttribute('aria-pressed', 'true');
                applyFilter();
            }
            if (button.dataset.rmDensity) {
                root.classList.toggle('is-compact');
                localStorage.setItem('role-matrix-density', root.classList.contains('is-compact') ? 'compact' : 'comfortable');
            }
            if (button.dataset.rmView) {
                const focusMode = button.dataset.rmView === 'focus';
                const columns = root.querySelector('[data-rm-columns]');
                if (columns) columns.hidden = focusMode;
                if (focus) {
                    focus.hidden = !focusMode;
                    if (focusMode) renderFocus();
                    else focus.replaceChildren();
                }
                localStorage.setItem('role-matrix-view', button.dataset.rmView);
            }
            if (button.dataset.column && button.dataset.action) {
                const roleId = button.dataset.column;
                const action = button.dataset.action;
                const boxes = [...root.querySelectorAll(`[data-role="${roleId}"][data-grant]`)].filter((box) => box.dataset.grant.endsWith('.' + action));
                const turnOn = boxes.some((box) => !box.checked);
                boxes.forEach((box) => enable(roleId, box.dataset.grant, turnOn));
            }
            if (button.dataset.rmCollapse) {
                const collapsed = button.dataset.rmCollapse === 'all';
                root.querySelectorAll('[data-module-row]').forEach((row) => {
                    if (row.hasAttribute('data-rm-extra')) row.hidden = collapsed;
                });
            }
            if (button.dataset.preset) {
                const module = button.dataset.module;
                const role = button.dataset.role;
                const preset = button.dataset.preset;
                root.querySelectorAll(`[data-role="${role}"][data-module="${module}"]`).forEach((box) => {
                    const action = box.dataset.grant.split('.').pop();
                    const on = preset === 'full' || (preset === 'read' && action === 'view') || (preset === 'standard' && ['view', 'create', 'update'].includes(action));
                    enable(role, box.dataset.grant, preset === 'none' ? false : on);
                });
            }
            if (button.dataset.rmDiscard) {
                Object.keys(state).forEach((id) => { state[id] = JSON.parse(JSON.stringify(original[id] || {})); });
                paint();
            }
            if (button.dataset.rmSave) openConfirm();
            if (button.dataset.rmConfirm) commit();
            if (button.dataset.rmClose) button.closest('.rm-modal,.rm-drawer').hidden = true;
            if (button.dataset.condition) openDrawer(button.dataset.role, button.dataset.condition);
            if (button.hasAttribute('data-rm-apply-condition')) applyDrawer();
        });

        root.querySelector('[data-rm-search]')?.addEventListener('input', applyFilter);
        root.querySelector('[data-rm-focus-role]')?.addEventListener('change', () => {
            if (focus && !focus.hidden) renderFocus();
        });
        root.querySelector('[data-rm-compare-a]')?.addEventListener('change', compareRoles);
        root.querySelector('[data-rm-compare-b]')?.addEventListener('change', compareRoles);

        function compareRoles() {
            const left = root.querySelector('[data-rm-compare-a]')?.value || '';
            const right = root.querySelector('[data-rm-compare-b]')?.value || '';
            root.querySelectorAll('[data-grant]').forEach((input) => input.closest('td')?.classList.remove('rm-diff'));
            if (left === '' || right === '' || left === right) return;
            const keys = new Set([...Object.keys(state[left] || {}), ...Object.keys(state[right] || {})]);
            keys.forEach((key) => {
                if (!!cell(left, key).on === !!cell(right, key).on) return;
                root.querySelectorAll(`[data-grant="${key}"][data-role="${left}"], [data-grant="${key}"][data-role="${right}"]`).forEach((input) => {
                    input.closest('td')?.classList.add('rm-diff');
                });
            });
        }

        function renderFocus() {
            if (!focus) return;
            const roleId = root.querySelector('[data-rm-focus-role]')?.value || (payload.roles[0] && payload.roles[0].id) || '';
            focus.replaceChildren();
            (payload.groups || []).forEach((group) => {
                (group.modules || []).forEach((module) => {
                    const card = document.createElement('article');
                    card.className = 'rm-card';
                    const title = document.createElement('strong');
                    title.textContent = module.label;
                    card.append(title);
                    (module.actions || []).forEach((action) => {
                        const label = document.createElement('label');
                        const input = document.createElement('input');
                        input.type = 'checkbox';
                        input.className = 'rm-check';
                        input.dataset.grant = action.key;
                        input.dataset.role = roleId;
                        input.dataset.module = module.slug;
                        const role = payload.roles.find((item) => item.id === roleId);
                        input.dataset.locked = role && role.locked ? '1' : '0';
                        input.disabled = input.dataset.locked === '1';
                        label.append(input, document.createTextNode(' ' + action.label));
                        card.append(label);
                    });
                    focus.append(card);
                });
            });
            paint();
        }

        function openConfirm() {
            const changes = diff();
            const modal = root.querySelector('[data-rm-modal]');
            const list = root.querySelector('[data-rm-diff]');
            list.replaceChildren();
            changes.forEach((change) => {
                const role = payload.roles.find((item) => item.id === change.role_id);
                const item = document.createElement('li');
                item.textContent = `${role ? role.name : change.role_id}: +${change.grant.length} / -${change.revoke.length}`;
                list.appendChild(item);
            });
            const danger = changes.some((change) => change.revoke.some((key) => (payload.dangerous || []).some((name) => key.endsWith('.' + name))) || (payload.roles.find((role) => role.id === change.role_id)?.users || 0) >= (payload.danger_user_threshold || 10));
            root.querySelector('[data-rm-danger-ack]').hidden = !danger;
            modal.hidden = false;
        }

        function commit() {
            const ack = root.querySelector('[data-rm-danger-ack] input');
            if (ack && !ack.closest('[data-rm-danger-ack]').hidden && !ack.checked) {
                toast(text('danger_confirm'));
                return;
            }
            const changes = diff();
            const wireRoot = root.closest('[wire\\:id]');
            if (wireRoot && window.Livewire) {
                window.Livewire.find(wireRoot.getAttribute('wire:id')).call('save', changes);
            } else {
                const form = root.querySelector('[data-rm-form]');
                form.querySelector('[name="changes"]').value = JSON.stringify(changes);
                form.submit();
            }
        }

        function openDrawer(roleId, key) {
            const drawer = root.querySelector('[data-rm-drawer]');
            drawer.dataset.role = roleId;
            drawer.dataset.key = key;
            const grant = cell(roleId, key);
            const summary = root.querySelector('[data-rm-summary]');
            summary.value = JSON.stringify(grant.conditions || { all: [], any: [] }, null, 2);
            renderBuilder();
            drawer.hidden = false;
        }

        function renderBuilder() {
            const box = root.querySelector('[data-rm-builder]');
            const fields = root.querySelector('[data-rm-fields]');
            const readable = root.querySelector('[data-rm-readable]');
            const summary = root.querySelector('[data-rm-summary]');
            if (!box || !summary) return;
            box.replaceChildren();
            const select = document.createElement('select');
            select.className = 'rm-select';
            (payload.rules || []).forEach((rule) => {
                const option = document.createElement('option');
                option.value = rule.type;
                option.textContent = rule.label;
                select.appendChild(option);
            });
            const bucket = document.createElement('select');
            bucket.className = 'rm-select';
            ['all', 'any'].forEach((name) => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                bucket.appendChild(option);
            });
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'rm-btn';
            add.textContent = text('apply');
            add.addEventListener('click', () => {
                const parsed = readConditions();
                const rule = (payload.rules || []).find((item) => item.type === select.value) || { fields: [] };
                const config = { rule: select.value };
                (rule.fields || []).forEach((field) => { config[field.name] = field.default ?? ''; });
                parsed[bucket.value] = Array.isArray(parsed[bucket.value]) ? parsed[bucket.value] : [];
                parsed[bucket.value].push(config);
                writeConditions(parsed);
            });
            box.append(select, bucket, add);
            writeConditions(readConditions());

            function readConditions() {
                try {
                    const parsed = JSON.parse(summary.value || '{}');
                    return { all: parsed.all || [], any: parsed.any || [] };
                } catch (error) {
                    return { all: [], any: [] };
                }
            }

            function writeConditions(parsed) {
                summary.value = JSON.stringify(parsed, null, 2);
                if (readable) readable.textContent = describe(parsed);
                if (!fields) return;
                fields.replaceChildren();
                ['all', 'any'].forEach((name) => {
                    (parsed[name] || []).forEach((rule, index) => {
                        const known = (payload.rules || []).find((item) => item.type === rule.rule);
                        const block = document.createElement('div');
                        block.className = 'rm-card';
                        const heading = document.createElement('strong');
                        heading.textContent = (known ? known.label : rule.rule) + ' (' + name + ')';
                        block.append(heading);
                        (known && known.fields ? known.fields : []).forEach((field) => {
                            const label = document.createElement('label');
                            label.append(document.createTextNode(field.label || field.name));
                            const input = field.type === 'toggle' ? document.createElement('input') : document.createElement(field.type === 'select' ? 'select' : 'input');
                            if (field.type === 'toggle') input.type = 'checkbox';
                            if (field.type === 'select') {
                                Object.entries(field.options || {}).forEach(([value, caption]) => {
                                    const option = document.createElement('option');
                                    option.value = value;
                                    option.textContent = caption;
                                    input.append(option);
                                });
                            }
                            if (field.type === 'toggle') input.checked = !!rule[field.name];
                            else input.value = rule[field.name] ?? '';
                            input.addEventListener('change', () => {
                                rule[field.name] = field.type === 'toggle' ? input.checked : input.value;
                                summary.value = JSON.stringify(parsed, null, 2);
                                if (readable) readable.textContent = describe(parsed);
                            });
                            label.append(input);
                            block.append(label);
                        });
                        const remove = document.createElement('button');
                        remove.type = 'button';
                        remove.className = 'rm-btn';
                        remove.textContent = text('discard');
                        remove.addEventListener('click', () => {
                            parsed[name].splice(index, 1);
                            writeConditions(parsed);
                        });
                        block.append(remove);
                        fields.append(block);
                    });
                });
            }
        }

        function describe(conditions) {
            const rules = [...(conditions.all || []), ...(conditions.any || [])];
            return rules.map((rule) => {
                const known = (payload.rules || []).find((item) => item.type === rule.rule);
                return known ? known.label : rule.rule;
            }).join(' · ');
        }

        function applyDrawer() {
            const drawer = root.querySelector('[data-rm-drawer]');
            try {
                const parsed = JSON.parse(root.querySelector('[data-rm-summary]').value || root.querySelector('[data-rm-summary]').textContent || '{}');
                setCell(drawer.dataset.role, drawer.dataset.key, true, parsed);
                paint();
                drawer.hidden = true;
            } catch (error) {
                toast('Invalid conditions JSON');
            }
        }

        root.addEventListener('keydown', (event) => {
            const current = document.activeElement;
            if (!(current instanceof HTMLInputElement) || !current.dataset.grant) return;
            const boxes = [...root.querySelectorAll('[data-grant]:not(:disabled)')];
            const index = boxes.indexOf(current);
            const columns = payload.roles.filter((role) => !role.locked).length || 1;
            let next = index;
            if (event.key === 'ArrowRight') next = index + 1;
            if (event.key === 'ArrowLeft') next = index - 1;
            if (event.key === 'ArrowDown') next = index + columns;
            if (event.key === 'ArrowUp') next = index - columns;
            if (next !== index && boxes[next]) {
                event.preventDefault();
                boxes[next].focus();
            }
        });

        if (localStorage.getItem('role-matrix-density') === 'compact') root.classList.add('is-compact');
        paint();
        applyFilter();
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-rm]').forEach(boot);
    });
    document.addEventListener('livewire:navigated', () => {
        document.querySelectorAll('[data-rm]').forEach(boot);
    });
})();
