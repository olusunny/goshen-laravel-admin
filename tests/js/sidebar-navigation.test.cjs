const { test } = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const vm = require('node:vm')

function sidebar() {
    const classList = () => {
        const values = new Set()
        return { toggle: (key, enabled) => enabled ? values.add(key) : values.delete(key), contains: key => values.has(key) }
    }
    const group = (label, labels) => ({
        dataset: { groupLabel: label }, classList: classList(),
        items: labels.map(textContent => ({ classList: classList(), querySelector: () => ({ textContent }) })),
        querySelectorAll() { return this.items },
    })
    const groups = [group('Settings', ['Referral Settings', 'Ticket PDF Templates']), group('Goshen Retreat', ['Referral Points', 'Wallet Auto Top-up Plans'])]
    const listeners = {}
    const input = { value: '', dataset: {}, addEventListener: (event, callback) => { listeners[event] = callback } }
    const status = { textContent: '' }
    const store = {
        collapsedGroups: ['Settings', 'Goshen Retreat'],
        groupIsCollapsed(label) { return this.collapsedGroups.includes(label) },
        toggleCollapsedGroup(label) { this.collapsedGroups = this.groupIsCollapsed(label) ? this.collapsedGroups.filter(value => value !== label) : [...this.collapsedGroups, label] },
    }
    const document = {
        querySelectorAll: () => groups,
        querySelector: selector => selector === '[data-goshen-menu-search]' ? input : selector === '[data-goshen-menu-search-status]' ? status : { classList: classList() },
        addEventListener: (event, callback) => { listeners[event] = callback },
    }
    const script = fs.readFileSync('resources/views/filament/sidebar-navigation-behavior.blade.php', 'utf8').replace(/^<script>\s*|\s*<\/script>\s*$/g, '')
    vm.runInNewContext(script, { document, window: { Alpine: { store: () => store } }, setTimeout: callback => callback() })
    return { groups, store, status, listeners, search(value) { input.value = value; listeners.input() } }
}

test('search matches each item independently and expands matching collapsed groups', () => {
    const ui = sidebar()
    ui.search('  REFERRAL ')
    assert.equal(ui.status.textContent, '2 menu items shown.')
    assert.equal(ui.groups[0].items[0].classList.contains('goshen-nav-hidden'), false)
    assert.equal(ui.groups[0].items[1].classList.contains('goshen-nav-hidden'), true)
    assert.equal(ui.groups[1].items[1].classList.contains('goshen-nav-hidden'), true)
    assert.equal(ui.store.collapsedGroups.length, 0)
    ui.search('')
    assert.equal(ui.store.collapsedGroups.join(','), 'Settings,Goshen Retreat')
})

test('group-name search shows its children and navigation restores prior collapse state', () => {
    const ui = sidebar()
    ui.search('settings')
    assert.equal(ui.status.textContent, '2 menu items shown.')
    assert.equal(ui.groups[1].classList.contains('goshen-nav-hidden'), true)
    ui.listeners['livewire:navigate']()
    assert.equal(ui.store.collapsedGroups.join(','), 'Settings,Goshen Retreat')
})

test('a failed search hides every group and announces no matches', () => {
    const ui = sidebar()
    ui.search('no such menu')
    assert.equal(ui.status.textContent, 'No matching menu items.')
    assert.equal(ui.groups.every(group => group.classList.contains('goshen-nav-hidden')), true)
})
