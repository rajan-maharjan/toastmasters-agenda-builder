const assert = require('assert').strict;
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require('path').join(__dirname, '../assets/js/app.js'), 'utf8');
const start = source.indexOf('    const openingPO =');
const end = source.indexOf('    function reorderSections()', start);
assert.ok(start >= 0 && end > start);
function setup(openingId = '', closingId = '') {
    const field = (role, id) => ({dataset: {role, memberId: id}, value: id ? 'Member ' + id : '', setCustomValidity() {}});
    const opening = field('presiding_officer', openingId);
    const closing = field('closing_presiding_officer', closingId);
    const fields = [opening, closing];
    const hidden = Object.fromEntries(fields.map(input => [input.dataset.role, {value: input.dataset.memberId}]));
    const context = vm.createContext({
        form: {querySelector(selector) {
            const role = selector.match(/="([^"]+)"/)[1];
            return selector.includes('data-role-value') ? hidden[role] : fields.find(input => input.dataset.role === role);
        }},
        all: selector => fields.filter(input => selector.includes('"' + input.dataset.role + '"')),
        selectedMember: input => input.dataset.memberId && input.value === 'Member ' + input.dataset.memberId ? {id: input.dataset.memberId} : null,
    });
    vm.runInContext(source.slice(start, end), context);
    const select = (input, id) => { input.value = 'Member ' + id; input.dataset.memberId = id; context.syncRole(input, true); };
    return {opening, closing, hidden, select, sync: context.syncRole};
}
const state = setup();
state.select(state.opening, '1');
assert.equal(state.closing.dataset.memberId, '1');
assert.equal(state.hidden.closing_presiding_officer.value, '1');
state.select(state.opening, '2');
assert.equal(state.closing.dataset.memberId, '2');
state.select(state.closing, '3');
assert.equal(state.opening.dataset.memberId, '2');
state.select(state.opening, '4');
assert.equal(state.closing.dataset.memberId, '3');
[state.opening, state.closing].forEach(state.sync);
assert.equal(state.hidden.presiding_officer.value, '4');
assert.equal(state.hidden.closing_presiding_officer.value, '3');
const edited = setup('1', '2');
edited.select(edited.opening, '3');
assert.equal(edited.closing.dataset.memberId, '2');
console.log('PASS: closing defaults to opening; closing overrides and saved separate assignments remain independent.');
