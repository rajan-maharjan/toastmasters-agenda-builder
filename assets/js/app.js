document.addEventListener('DOMContentLoaded', () => {
    const orderDropdown = document.getElementById('orderby');

    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
        });
    });
    const form = document.getElementById('agenda-form');
    if (!form) return;
    const all = (selector, parent = form) => Array.from(parent.querySelectorAll(selector));
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
    function refreshTimes() {
        const start = document.getElementById('start_time').value;
        const validStart = /^([01]\d|2[0-3]):[0-5]\d$/.test(start);
        const [h, m] = start.split(':').map(Number);
        const initial = validStart ? h * 60 + m : NaN;
        let cursor = initial;
        all('.schedule-block').forEach(block => {
            block.querySelector('[data-clock]').textContent = Number.isFinite(cursor) ? clock(cursor) : '--:--';
            const minutes = all('[data-duration]', block).reduce((sum, input) => sum + duration(input), 0);
            const total = block.querySelector('[data-total]');
            if (total) total.textContent = Number.isFinite(minutes) ? `${minutes} mins` : 'Check time';
            cursor += minutes;
        });
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
        const role = key => form.querySelector(`[data-role-value="${key}"]`).value;
        const candidates = [[`Timer: ${role('timer')}`, `Ah-Counter: ${role('ah_counter')}`, `Grammarian: ${role('grammarian')}`],
            names('evaluators').filter(Boolean), names('prepared_speakers').filter(Boolean), names('tt_speakers').map((name, index) => `TT Speaker ${index + 1}`)];
        all('.ballot-block ul').forEach((list, index) => {
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
        if (input.matches('[data-club-select], [data-club-number]')) refreshClubs();
        if (input.dataset.role) {
            all(`[data-role="${input.dataset.role}"]`).forEach(other => { if (other !== input) other.value = input.value; });
            form.querySelector(`[data-role-value="${input.dataset.role}"]`).value = input.value;
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
        refreshTimes();
        if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); }
    });
    form.addEventListener('change', event => {
        if (event.target.matches('[data-club-select]')) refreshClubs();
        if (event.target.id === 'start_time') refreshTimes();
    });
    refresh();
});
