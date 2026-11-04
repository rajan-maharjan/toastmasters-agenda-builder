const assert = require('assert').strict;
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(require('path').join(__dirname, '../assets/js/app.js'), 'utf8');
const start = source.indexOf('    function renderMembers(input)');
const end = source.indexOf('    function bindMemberInputs()', start);
assert.ok(start >= 0 && end > start);
const member = (name, mobile) => ({value: name + ' (No club)', dataset: {name, mobileNumber: mobile}});
const results = {children: [], style: {}, replaceChildren() { this.children = []; }, append(item) { this.children.push(item); }};
const context = vm.createContext({
    memberNodes: [member('Alice', '+977 9851-122778'), member('Bob', '9851999999'), member('Carol', ''), member('Dave', '9800000000')],
    results, selectedMember: () => null, memberBelongsToField: () => true,
    document: {createElement: () => ({setAttribute() {}, addEventListener() {}})},
    window: {innerWidth: 1200, innerHeight: 800},
});
vm.runInContext(source.slice(start, end), context);
function search(value) {
    context.renderMembers({value, closest: () => null, getBoundingClientRect: () => ({left: 0, bottom: 30, width: 300}), setAttribute() {}, removeAttribute() {}});
    return results.children.map(item => item.textContent).filter(label => label !== '+ Add new member');
}
assert.deepEqual(search('9851'), ['Alice (No club) · ***9851******', 'Bob (No club) · 9851******']);
assert.deepEqual(search('+977 9851'), ['Alice (No club) · 9779851******']);
assert.deepEqual(search('122-778'), ['Alice (No club) · *******122778']);
assert.deepEqual(search('alice'), ['Alice (No club)']);
assert.deepEqual(search('Carol'), ['Carol (No club)']);
assert.deepEqual(search('123456'), []);
assert.ok(search('').every(label => !label.includes(' · ')));
console.log('PASS: mobile/name matches, formatted queries, unmatched digits masked, name searches hide numbers.');
