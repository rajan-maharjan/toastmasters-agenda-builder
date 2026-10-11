document.addEventListener('DOMContentLoaded', () => {
    const orderDropdown = document.getElementById('orderby');

    document.querySelectorAll('[data-print-page]').forEach(button => button.addEventListener('click', () => window.print()));
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    const form = document.getElementById('agenda-form');
    if (!form) return;
    const all = (selector, parent = form) => Array.from(parent.querySelectorAll(selector));
    form.noValidate = true;
    const serverErrors = document.getElementById('server-validation-errors');
    let validateAll = !!serverErrors;
    let serverMessages = serverErrors ? Array.from(serverErrors.querySelectorAll('li'), item => item.textContent) : [];
    if (serverErrors) serverErrors.hidden = true;
    const summary = document.createElement('div');
    summary.className = 'alert alert-danger no-print';
    summary.id = 'agenda-validation-summary';
    summary.setAttribute('role', 'alert');
    summary.hidden = true;
    form.prepend(summary);
    let errorCounter = 0;
    function renderValidation() {
        const entries = [];
        all('input:not([type="hidden"]), select, textarea').filter(input => !input.closest('dialog')).forEach(input => {
            const excluded = input.closest('.schedule-block.is-excluded');
            let message = '';
            const label = input.getAttribute('aria-label') || input.name || 'This field';
            if (!excluded && !input.disabled) {
                if (input.required && !input.value.trim()) message = `${label} is required.`;
                else if (!input.validity.valid) message = input.validationMessage;
            }
            const visible = message && (validateAll || input.dataset.validationTouched || input.validity.customError);
            let error = input._validationError;
            if (visible && !error) {
                error = document.createElement('span');
                error.id = `agenda-field-error-${++errorCounter}`;
                error.className = 'agenda-field-error';
                const wrapper = document.createElement('span');
                wrapper.className = 'agenda-validation-control';
                input.before(wrapper);
                wrapper.append(input, error);
                input._validationError = error;
                input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
            }
            input.classList.toggle('agenda-invalid', !!visible);
            input.setAttribute('aria-invalid', visible ? 'true' : 'false');
            if (error) { error.textContent = visible ? message : ''; error.hidden = !visible; }
            if (visible) entries.push({input, message});
        });
        summary.replaceChildren();
        if (entries.length || serverMessages.length) {
            const heading = document.createElement('strong');
            heading.textContent = 'Please correct the following:';
            summary.append(heading);
            const list = document.createElement('ul');
            const unique = new Set();
            entries.forEach(({input, message}) => {
                if (unique.has(message)) return;
                unique.add(message);
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'validation-error-link'; button.textContent = message;
                button.addEventListener('click', () => { input.focus(); input.scrollIntoView({block:'center'}); });
                li.append(button); list.append(li);
            });
            serverMessages.filter(message => !unique.has(message)).forEach(message => { const li = document.createElement('li'); li.textContent = message; list.append(li); });
            summary.append(list);
        }
        summary.hidden = !entries.length && !serverMessages.length;
        return entries;
    }
    form.addEventListener('focusout', event => {
        if (event.target.matches('input, select, textarea') && !event.target.closest('dialog')) {
            event.target.dataset.validationTouched = '1';
            renderValidation();
        }
    });

    const memberList = document.getElementById('role-member-options');
    const memberNodes = all('#role-member-options option');
    const memberOptions = new Map(memberNodes.map(option => [option.value, {id: option.dataset.memberId, name: option.dataset.name}]));
    const membersById = new Map(Array.from(memberOptions.values(), member => [member.id, member]));
    function selectedMember(input) {
        const member = membersById.get(input.dataset.memberId);
        return member && member.name === input.value ? member : null;
    }
    function filterMembers() {
        const clubs = new Set(all('[data-club-select]').map(select => select.value).filter(Boolean));
        const priority = option => JSON.parse(option.dataset.clubIds || '[]').some(id => clubs.has(String(id))) ? 0 : 1;
        memberNodes.sort((a, b) => priority(a) - priority(b) || a.value.localeCompare(b.value));
        if (activeRoleInput) renderMembers(activeRoleInput);
    }
    let activeRoleInput = null;
    let highlighted = -1;
    const results = document.createElement('div');
    results.id = 'role-member-results';
    results.setAttribute('role', 'listbox');
    results.hidden = true;
    document.body.append(results);
    function closeMembers() {
        if (activeRoleInput) {
            activeRoleInput.setAttribute('aria-expanded', 'false');
            activeRoleInput.removeAttribute('aria-activedescendant');
        }
        results.hidden = true;
        activeRoleInput = null;
    }
    function renderMembers(input) {
        activeRoleInput = input;
        highlighted = -1;
        const query = selectedMember(input) ? '' : input.value.trim().toLocaleLowerCase();
        const matches = memberNodes.filter(option => option.value.toLocaleLowerCase().includes(query)).slice(0, 60);
        results.replaceChildren();
        function addResult(label, action) {
            const item = document.createElement('button');
            item.type = 'button';
            item.id = 'member-result-' + results.children.length;
            item.setAttribute('role', 'option');
            item.textContent = label;
            item.addEventListener('mousedown', event => event.preventDefault());
            item.addEventListener('click', action);
            results.append(item);
        }
        matches.forEach(option => addResult(option.value, () => {
            input.value = option.dataset.name;
            input.dataset.memberId = option.dataset.memberId;
            syncRole(input);
            refreshBallots();
            closeMembers();
            input.focus();
            closeMembers();
        }));
        addResult('+ Add new member', () => { closeMembers(); openMemberDialog(input); });
        const rect = input.getBoundingClientRect();
        results.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - 368)) + 'px';
        results.style.top = Math.min(rect.bottom, window.innerHeight - 180) + 'px';
        results.style.width = Math.min(Math.max(rect.width, 360), window.innerWidth - 16) + 'px';
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        input.removeAttribute('aria-activedescendant');
    }
    function bindMemberInputs() {
    all('[data-role], [data-member-autocomplete]').forEach(input => {
        if (input.dataset.memberBound) return;
        input.dataset.memberBound = '1';
        input.addEventListener('focus', () => renderMembers(input));
        input.addEventListener('input', () => renderMembers(input));
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape' || event.key === 'Tab') { closeMembers(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (results.hidden) renderMembers(input);
                highlighted = (highlighted + (event.key === 'ArrowDown' ? 1 : -1) + results.children.length) % results.children.length;
                Array.from(results.children).forEach((item, index) => item.setAttribute('aria-selected', String(index === highlighted)));
                const item = results.children[highlighted];
                input.setAttribute('aria-activedescendant', item.id);
                item.scrollIntoView({block:'nearest'});
            } else if (event.key === 'Enter' && !results.hidden) {
                event.preventDefault();
                if (highlighted >= 0) results.children[highlighted].click();
            }
        });
    });
    }
    bindMemberInputs();
    document.addEventListener('mousedown', event => { if (!results.contains(event.target) && !event.target.matches('[data-role], [data-member-autocomplete]')) closeMembers(); });
    window.addEventListener('resize', closeMembers);
    window.addEventListener('scroll', event => { if (!results.contains(event.target)) closeMembers(); }, true);
    const memberDialog = document.getElementById('new-member-dialog');
    function openMemberDialog(input) {
        newMemberInput = input;
        const clubSelect = memberDialog.querySelector('[data-new-member="club_id"]');
        clubSelect.value = all('[data-club-select]').find(select => select.value)?.value || '';
        memberDialog.querySelector('[data-new-member="full_name"]').value = selectedMember(input) ? '' : input.value;
        memberDialog.querySelector('[data-member-error]').textContent = '';
        memberDialog.showModal();
    }
    let newMemberInput = null;
    memberDialog.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('input')) {
            event.preventDefault();
            memberDialog.querySelector('[data-create-member]').click();
        }
    });
    form.addEventListener('click', async event => {
        if (event.target.closest('[data-cancel-member]')) memberDialog.close();
        const create = event.target.closest('[data-create-member]');
        if (!create) return;
        create.disabled = true;
        try {
            const body = new URLSearchParams({csrf_token: form.querySelector('[name="csrf_token"]').value});
            all('[data-new-member]', memberDialog).forEach(input => body.set(input.dataset.newMember, input.value));
            const response = await fetch('create_member.php', {method:'POST', body});
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Could not add member.');
            const member = data.member;
            const option = new Option('', member.label);
            option.dataset.memberId = member.member_id;
            option.dataset.name = member.full_name;
            option.dataset.clubIds = JSON.stringify(member.club_ids);
            memberNodes.push(option);
            memberOptions.set(member.label, {id:member.member_id, name:member.full_name});
            membersById.set(String(member.member_id), {id:String(member.member_id), name:member.full_name});
            filterMembers();
            const input = newMemberInput;
            input.value = member.full_name;
            input.dataset.memberId = String(member.member_id);
            syncRole(input);
            refreshBallots();
            memberDialog.close();
            all('[data-new-member]', memberDialog).forEach(input => {input.value='';});
        } catch (error) {
            memberDialog.querySelector('[data-member-error]').textContent = error.message;
        } finally { create.disabled = false; }
    });
    function syncRole(input) {
        if (!input.dataset.role) {
            input.dispatchEvent(new Event('input', {bubbles:true}));
            return;
        }
        const member = selectedMember(input);
        all(`[data-role="${input.dataset.role}"]`).forEach(other => {
            if (other !== input) other.value = input.value;
            other.dataset.memberId = member ? member.id : '';
            other.setCustomValidity(input.value.trim() && !member ? 'Choose a member from the autocomplete suggestions.' : '');
        });
        form.querySelector(`[data-role-value="${input.dataset.role}"]`).value = member ? member.id : '';
    }

    function reorderSections() {
        const topicKeys = ['topics_intro', 'tt_speakers'];
        const speechKeys = ['tmoe_featured_speaker_intro', 'prepared_speakers'];
        const keys = orderDropdown.value === 'FS' ? [...speechKeys, ...topicKeys] : [...topicKeys, ...speechKeys];
        const anchor = form.querySelector('.schedule-block[data-key="evaluation_handover"]');
        const blocks = keys.map(key => form.querySelector(`.schedule-block[data-key="${key}"]`));
        if (!anchor || blocks.some(block => !block)) return;
        blocks.forEach(block => anchor.parentNode.insertBefore(block, anchor));
        refreshTimes();
    }
    if (orderDropdown) orderDropdown.addEventListener('change', reorderSections);
    all('input[type="date"], input[type="time"]').forEach(input => {
        input.addEventListener('click', event => {
            if (input.disabled || input.readOnly || typeof input.showPicker !== 'function') return;
            try {
                input.showPicker();
                event.preventDefault();
            } catch {
                // Keep the browser's normal input behavior if it cannot open a picker.
            }
        });
    });
    const lists = {};
    all('[data-list]').forEach(list => { lists[list.dataset.list] = list; });
    const counters = {};
    Object.entries(lists).forEach(([key, list]) => {
        counters[key] = Math.max(-1, ...all('[data-key]', list).map(row => Number(row.dataset.key))) + 1;
    });
    const clock = minutes => {
        const wrapped = ((minutes % 1440) + 1440) % 1440;
        return `${String(Math.floor(wrapped / 60)).padStart(2, '0')}:${String(wrapped % 60).padStart(2, '0')}`;
    };
    function duration(input) {
        const speaker = input.hasAttribute('data-speaker-duration');
        const range = speaker ? input.value.match(/^\s*([0-9]+)(?:\s*[-–—]\s*([0-9]+))?\s*(?:m|min|mins|minute|minutes)?\s*$/i) : null;
        const minutes = speaker ? (range ? Number(range[2] || range[1]) : NaN) : (/^[0-9]+$/.test(input.value) ? Number(input.value) : NaN);
        const minimum = Number(input.dataset.min || 0);
        const valid = Number.isInteger(minutes) && minutes >= minimum && minutes <= 240 && (!speaker || Number(range[1]) <= minutes);
        input.setCustomValidity(valid ? '' : (speaker ? 'Enter minutes or an ascending range, such as 5-7, with a maximum from 1 to 240.' : `Enter a whole number from ${minimum} to 240.`));
        return valid ? minutes + (speaker && range[2] !== undefined ? 1 : 0) : NaN;
    }
    function validateParticipantAssignments() {
        const seen = new Map();
        const messages = [];
        all('[data-person]').forEach(input => {
            if (input.dataset.assignmentConflict) {
                input.setCustomValidity('');
                delete input.dataset.assignmentConflict;
            }
        });
        [['prepared_speakers', 'Prepared speaker'], ['tt_speakers', 'TT speaker'], ['evaluators', 'Evaluator']].forEach(([kind, label]) => {
            if (form.querySelector(`.schedule-block[data-key="${kind}"] [data-section-toggle]`)?.checked === false) return;
            all('[data-person]', lists[kind]).forEach((input, index) => {
                const name = input.value.trim().replace(/\s+/g, ' ');
                const key = name.toLowerCase();
                if (!key || (kind === 'tt_speakers' && key === 'given during the meeting')) return;
                const position = `${label} ${index + 1}`;
                const previous = seen.get(key);
                if (previous) {
                    const crossRole = (kind === 'evaluators') !== (previous.kind === 'evaluators');
                    const message = `${name} is assigned to both ${previous.position} and ${position}. ` + (crossRole ? 'A person cannot be both speaker and evaluator.' : 'Choose a different person for each slot.');
                    messages.push(message);
                    [input, previous.input].forEach(field => { field.setCustomValidity(message); field.dataset.assignmentConflict = '1'; });
                } else seen.set(key, {input, position, kind});
            });
        });

    }
    function refreshTimes() {
        const start = document.getElementById('start_time').value;
        const validStart = /^([01]\d|2[0-3]):[0-5]\d$/.test(start);
        if (validStart) {
            const label = start < '12:00' ? 'TMoM' : (start < '17:00' ? 'TMoD' : 'TMoE');
            all('[data-agenda-title]').forEach(element => {
                const property = element.matches('input, textarea') ? 'value' : 'textContent';
                element[property] = element[property].replace(/\bTMo[EMD]\b/g, label);
            });
        }
        const [h, m] = start.split(':').map(Number);
        const initial = validStart ? h * 60 + m : NaN;
        let cursor = initial;
        all('.schedule-block').forEach(block => {
            const toggle = block.querySelector('[data-section-toggle]');
            const included = !toggle || toggle.checked;
            block.classList.toggle('is-excluded', !included);
            all('input:not([data-section-toggle]), select, textarea', block).forEach(input => {
                if (input.dataset.sectionRequired === undefined) input.dataset.sectionRequired = input.required ? '1' : '0';
                input.required = included && input.dataset.sectionRequired === '1';
                if (input.dataset.sectionPattern === undefined) input.dataset.sectionPattern = input.getAttribute('pattern') || '';
                if (included && input.dataset.sectionPattern) input.setAttribute('pattern', input.dataset.sectionPattern);
                else if (!included) input.removeAttribute('pattern');
                if (!included) input.setCustomValidity('');
            });
            if (!included) {
                block.querySelector('[data-clock]').textContent = '?';
                const total = block.querySelector('[data-total]');
                if (total) total.textContent = 'Excluded';
                return;
            }
            block.querySelector('[data-clock]').textContent = Number.isFinite(cursor) ? clock(cursor) : '--:--';
            const minutes = all('[data-duration]', block).reduce((sum, input) => sum + duration(input), 0);
            const total = block.querySelector('[data-total]');
            if (total) total.textContent = Number.isFinite(minutes) ? `${minutes} mins` : 'Check time';
            cursor += minutes;
        });
        validateParticipantAssignments();
        all('[data-role]').filter(input => !input.closest('.is-excluded')).forEach(syncRole);
        renderValidation();
        const valid = Number.isFinite(cursor);
        document.getElementById('computed-end').textContent = valid ? clock(cursor) : '--:--';
        document.getElementById('total-minutes').textContent = valid ? cursor - initial : '—';
        const days = Math.floor(cursor / 1440);
        document.getElementById('end-day').textContent = valid && days ? `(+${days} day${days === 1 ? '' : 's'})` : '';
    }
    function refreshSpeakers() {
        const speakers = all('.participant-row', lists.prepared_speakers).map(row => ({
            key: row.dataset.key, name: row.querySelector('[data-person]').value.trim()
        })).filter(speaker => speaker.name);
        all('.evaluator-speaker').forEach((select, index) => {
            const previous = select.value || select.dataset.selected || '';
            const linked = previous !== '';
            select.replaceChildren(new Option('Select a featured speaker', ''));
            speakers.forEach(speaker => select.add(new Option(speaker.name, speaker.key)));
            if (speakers.some(speaker => speaker.key === previous)) {
                select.value = previous;
            } else if (!linked && !select.dataset.cleared && speakers[index]) {
                select.value = speakers[index].key;
            } else if (linked) {
                // Removing a speaker must not silently reassign their evaluator.
                select.dataset.cleared = 'true';
            }
            select.dataset.selected = select.value;
        });
    }
    function refreshBallots() {
        const names = key => all('[data-person]', lists[key]).map(input => input.value.trim());
        const role = key => { const input = form.querySelector(`[data-role="${key}"]`); return input ? (selectedMember(input)?.name || input.value) : ''; };
        const candidates = [[`Timer: ${role('timer')}`, `Ah-Counter: ${role('ah_counter')}`, `Grammarian: ${role('grammarian')}`],
            names('evaluators').filter(Boolean), names('prepared_speakers').filter(Boolean), names('tt_speakers').map((name, index) => `TT Speaker ${index + 1}`)];
        const sectionKeys = [null, 'evaluators', 'prepared_speakers', 'tt_speakers'];
        all('.ballot-block ul').forEach((list, index) => {
            const selection = list.closest('.ballot-block').querySelector('input[type="checkbox"]');
            const key = sectionKeys[index];
            if (selection && key) {
                const enabled = form.querySelector(`.schedule-block[data-key="${key}"] [data-section-toggle]`)?.checked !== false;
                const available = enabled && candidates[index].length > 0;
                const previous = selection.dataset.available;
                if ((previous === undefined && selection.dataset.ballotExplicit !== '1') || (previous !== undefined && previous !== String(available))) {
                    selection.checked = available;
                }
                selection.dataset.available = String(available);
            }
            list.replaceChildren();
            candidates[index].forEach(name => {
                const li = document.createElement('li');
                const checkbox = document.createElement('span');
                checkbox.className = 'ballot-checkbox';
                const text = document.createElement('span');
                text.textContent = name;
                li.append(checkbox, text);
                list.append(li);
            });
        });
    }
    let previousClubSelection = null;
    function refreshClubs() {
        filterMembers();
        const clubs = all('.club-entry', lists.clubs);
        const shortName = name => name.trim().replace(/\s*\([^)]*\)\s*$/, '').replace(/\s+Toastmasters Club$/i, '').trim();
        const entries = clubs.map((row, index) => {
            row.querySelector('.club-number').textContent = index + 1;
            row.querySelector('[data-remove]').disabled = clubs.length === 1;
            const select = row.querySelector('[data-club-select]');
            const option = select.selectedOptions[0];
            return {key: row.dataset.key, clubId: select.value, officers: JSON.parse(option?.dataset.officers || '{}'), name: select.value ? shortName(option.textContent) : '',
                fullName: select.value ? option.textContent : '', area: select.value ? (option.dataset.area || '') : '',
                number: row.querySelector('[data-club-number]').value.trim().replace(/^[# ]+/, '')};
        });
        const selection = JSON.stringify(entries.filter(club => club.clubId).map(club => [club.clubId, club.area]));
        // Keep saved/manual values until the selected clubs actually change.
        if (previousClubSelection !== null && selection !== previousClubSelection) {
            const areas = [...new Set(entries.map(club => club.area.trim().toUpperCase()).filter(Boolean))];
            const divisions = [...new Set(areas.map(area => Array.from(area)[0]))];
            form.querySelector('[name="area"]').value = areas.length ? `Area ${areas.join(', ')}` : '';
            form.querySelector('[name="division"]').value = divisions.length ? `Division ${divisions.join(', ')}` : '';
        }
        previousClubSelection = selection;
        const parts = entries.filter(club => club.name).map(club => `${club.number ? '#' + club.number + ' ' : ''}${club.name}`);
        const title = parts.length > 1 ? parts.slice(0, -1).join(', ') + ' and ' + parts[parts.length - 1] : (parts[0] || '');
        document.getElementById('agenda-title').textContent = 'Agenda for Meeting' + (title ? ' ' + title : '');
        all('[data-officer-role]').forEach(section => {
            all('[data-club-key]', section).forEach(field => {
                if (!entries.some(club => club.key === field.dataset.clubKey)) field.remove();
            });
            entries.forEach(club => {
                let field = all('[data-club-key]', section).find(field => field.dataset.clubKey === club.key);
                if (!field) {
                    const template = document.getElementById(`template-officer-${section.dataset.officerRole}`);
                    section.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, club.key));
                    field = section.lastElementChild;
                }
                if (field.dataset.officerClub !== club.clubId) {
                    field.querySelector('input').value = club.officers[section.dataset.officerRole] || '';
                    field.dataset.officerClub = club.clubId;
                }
                field.querySelector('.officer-club-label').textContent = club.name || 'Club';
                field.querySelector('.officer-club-label').title = club.fullName;
                field.querySelector('input').setAttribute('aria-label', `${section.querySelector('strong').textContent} — ${club.name || 'Club'} name`);
            });
        });
    }
    function refresh() {
        bindMemberInputs();
        all('[data-tt-label]', lists.tt_speakers).forEach((label, index) => {
            label.textContent = `TT Speaker ${index + 1}`;
        });
        refreshClubs(); refreshSpeakers(); refreshTimes(); refreshBallots();
    }
    form.addEventListener('click', event => {
        const add = event.target.closest('[data-add]');
        if (add) {
            const kind = add.dataset.add;
            const template = document.getElementById(`template-${kind}`);
            const index = counters[kind]++;
            lists[kind].insertAdjacentHTML('beforeend', template.innerHTML.replace(/__INDEX__/g, String(index)));
            refresh();
            lists[kind].lastElementChild.querySelector('input, select').focus();
        }
        const remove = event.target.closest('[data-remove]');
        if (remove) {
            if (remove.closest('.club-entry') && lists.clubs.children.length === 1) return;
            remove.closest('.participant-row, .club-entry').remove();
            refresh();
        }
    });
    form.addEventListener('input', event => {
        const input = event.target;
        input.dataset.validationTouched = '1';
        serverMessages = [];
        if (input.matches('[data-club-select], [data-club-number]')) refreshClubs();
        if (input.dataset.role) {
            syncRole(input);
        }
        if (input.matches('.evaluator-speaker')) {
            input.dataset.selected = input.value;
            input.dataset.cleared = input.value ? '' : 'true';
        }
        if (input.matches('[data-person]') && input.closest('[data-list="prepared_speakers"]')) refreshSpeakers();
        refreshTimes();
        refreshBallots();
    });
    form.addEventListener('submit', event => {
        validateAll = true;
        serverMessages = [];
        refreshTimes();
        const errors = renderValidation();
        if (errors.length) {
            event.preventDefault();
            summary.scrollIntoView({block:'start', behavior:'smooth'});
            errors[0].input.focus({preventScroll:true});
        }
    });
    form.addEventListener('change', event => {
        if (event.target.matches('[data-section-toggle]')) { refreshTimes(); refreshBallots(); }
        if (event.target.matches('[data-club-select]')) refreshClubs();
        if (event.target.id === 'start_time') refreshTimes();
    });
    refresh();
});
