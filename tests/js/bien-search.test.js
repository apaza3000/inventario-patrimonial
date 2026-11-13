import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/bien-search.js', import.meta.url), 'utf8').replace('export function', 'function');
class Element {
    constructor() { this.children = []; this.listeners = {}; this.attributes = {}; this.value = ''; this.hidden = false; }
    addEventListener(name, handler) { this.listeners[name] = handler; }
    emit(name, event = {}) { return this.listeners[name]?.({ preventDefault() {}, stopPropagation() {}, ...event }); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    removeAttribute(name) { delete this.attributes[name]; }
    setCustomValidity(value) { this.validationMessage = value; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    focus() { this.emit('focus'); }
    scrollIntoView() { this.scrolled = true; }
}
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };
function setup({ pending = false, total = 286, initial = null, error = null } = {}) {
    const input = new Element(), list = new Element(), previous = new Element(), next = new Element(), clear = new Element(), pageLabel = new Element(), message = new Element();
    list.id = 'test-suggestions';
    const timers = new Map(), calls = [], resolutions = [], displayed = [];
    let counter = 0, selected = initial, loading = false;
    const assets = Array.from({ length: 20 }, (_, i) => ({ id: i + 1, cbi: `02000${i + 1}`, descripcion: `Bien ${i + 1}`, ambiente: { nombre: 'Laboratorio' } }));
    const response = { data: { data: total ? assets : [], total, current_page: 1, last_page: 20 } };
    const create = vm.runInNewContext(source + '\ncreateBienSearch;', {
        document: { createElement: () => new Element() },
        setTimeout(callback, delay) { const id = ++counter; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        window: { axios: { async get(url, config) {
            calls.push({ url, config });
            if (error) throw error;
            return pending ? new Promise((resolve) => resolutions.push(resolve)) : response;
        } } },
    });
    create({ input, panel: list, list, previous, next, clear, pageLabel, message, url: '/authorized-assets',
        selected: () => selected,
        onSelect: (item) => { selected = item; }, onResults: (items) => displayed.push(items),
        onClear: () => { selected = null; }, onLoading: (value) => { loading = value; }, errorText: (e) => e.message,
    });
    return { input, list, clear, message, calls, timers, displayed,
        selection: () => selected, loading: () => loading,
        resolve: () => resolutions.shift()(response),
        tick: async () => { const scheduled = [...timers.values()]; timers.clear(); scheduled.forEach(({ callback }) => callback()); await flush(); },
    };
}

test('shared suggestions debounce from two characters, cap the visible list and select by click', async () => {
    const s = setup();
    s.input.value = '0'; s.input.emit('input');
    assert.equal(s.timers.size, 0);
    s.input.value = '02'; s.input.emit('input');
    s.input.value = '020'; s.input.emit('input');
    assert.equal(s.timers.size, 1);
    assert.equal([...s.timers.values()][0].delay, 300);
    await s.tick();
    assert.equal(s.calls[0].config.params.q, '020');
    assert.equal(s.list.hidden, false);
    assert.equal(s.list.children.length, 15);
    assert.match(s.message.textContent, /286.*15/);
    assert.equal(s.selection(), null);
    const first = s.list.children[0];
    assert.match(first.children[0].textContent, /020001/);
    assert.equal(first.children[1].textContent, 'Bien 1');
    assert.match(first.children[2].textContent, /Laboratorio/);
    let prevented = false;
    first.emit('pointerdown', { preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    await first.emit('click');
    assert.equal(s.selection().id, 1);
    assert.equal(s.input.value, '020001');
    assert.equal(s.input.validationMessage, '');
    assert.equal(s.list.hidden, true);
});

test('arrows wrap, Escape closes only suggestions, Enter selects and clear restores required selection', async () => {
    const s = setup();
    s.input.value = '02'; s.input.emit('keydown', { key: 'Enter' }); await flush();
    s.input.emit('keydown', { key: 'ArrowUp' });
    assert.equal(s.input.attributes['aria-activedescendant'], 'test-suggestions-14');
    assert.equal(s.list.children[14].scrolled, true);
    let stopped = false;
    s.input.emit('keydown', { key: 'Escape', stopPropagation() { stopped = true; } });
    assert.equal(stopped, true);
    assert.equal(s.list.hidden, true);
    s.input.emit('keydown', { key: 'ArrowDown' });
    s.input.emit('keydown', { key: 'ArrowDown' });
    s.input.emit('keydown', { key: 'Enter' }); await flush();
    assert.equal(s.selection().id, 2);
    s.clear.emit('click');
    assert.equal(s.selection(), null);
    assert.ok(s.input.validationMessage);
    assert.equal(s.input.attributes['aria-expanded'], 'false');
});

test('Escape cancels pending suggestions so a late response cannot reopen the list or replace the selection', async () => {
    const initial = { id: 99, cbi: '000099' }, s = setup({ pending: true, initial });
    s.input.value = '02'; s.input.emit('keydown', { key: 'Enter' });
    assert.equal(s.loading(), true);
    assert.equal(s.input.attributes['aria-busy'], 'true');
    s.input.emit('keydown', { key: 'Escape' });
    s.resolve(); await flush();
    assert.equal(s.list.hidden, true);
    assert.equal(s.list.children.length, 0);
    assert.equal(s.selection(), initial);
    assert.equal(s.loading(), false);
    assert.equal(s.input.attributes['aria-busy'], 'false');
});

test('leaving the input closes suggestions and discards pending results without losing the chosen asset', async () => {
    const initial = { id: 99 }, s = setup({ pending: true, initial });
    s.input.value = '02'; s.input.emit('input'); await s.tick();
    s.input.emit('blur'); s.resolve(); await flush();
    assert.equal(s.list.hidden, true);
    assert.equal(s.selection(), initial);
});

test('empty results and failed requests have clear messages and preserve the existing selection', async () => {
    const initial = { id: 99 };
    for (const options of [{ total: 0 }, { error: new Error('No tienes permiso.') }]) {
        const s = setup({ ...options, initial });
        s.input.value = '02'; s.input.emit('keydown', { key: 'Enter' }); await flush();
        assert.equal(s.list.hidden, true);
        assert.match(s.message.textContent, options.error ? /No tienes permiso/ : /No hay coincidencias/);
        assert.equal(s.selection(), initial);
        assert.equal(s.loading(), false);
    }
});
