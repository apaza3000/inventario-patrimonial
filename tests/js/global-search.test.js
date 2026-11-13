import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/bien-detail.js', import.meta.url), 'utf8').replace('export function', 'function')
    + readFileSync(new URL('../../resources/js/global-search.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
const settle = async () => { await new Promise(setImmediate); };
class Element {
    constructor() { this.children = []; this.listeners = {}; this.dataset = {}; this.value = ''; this.hidden = false; this.disabled = false; this.textContent = ''; this.attributes = {}; }
    addEventListener(name, fn) { (this.listeners[name] ??= []).push(fn); }
    async emit(name, event = {}) { for (const fn of this.listeners[name] ?? []) await fn({ preventDefault() {}, ...event }); }
    append(...children) { this.children.push(...children); }
    replaceChildren() { this.children = []; }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    removeAttribute(name) { delete this.attributes[name]; }
    contains(target) { return target === this; }
    focus() { this.focused = true; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
}
function setup({ fail = null, deferred = false, deferDetail = false } = {}) {
    const nodes = new Map(), detailNodes = new Map();
    const lookup = (map, selector) => { if (!map.has(selector)) map.set(selector, new Element()); return map.get(selector); };
    const search = new Element(); search.querySelector = (selector) => lookup(nodes, selector);
    search.dataset = { searchUrl: '/api/busqueda/bienes', loginUrl: '/login' };
    const detail = new Element(); detail.querySelector = (selector) => lookup(detailNodes, selector);
    const document = new Element();
    document.querySelector = (selector) => selector === '[data-global-search]' ? search : detail;
    document.createElement = () => new Element();
    const calls = [], redirects = [], timers = new Map(); let sequence = 0, resolveSearch, resolveDetail;
    const bien = { id: 20, cbi: '0243831', descripcion: 'Computadora', ambiente: { nombre: 'Laboratorio', sede: { nombre: 'Sede' } }, equipo: { serie: 'SER-001', equipo_computo: { ram: '16 GB' } } };
    const response = (page = 1) => ({ data: { data: [bien], total: 16, current_page: page, last_page: 2 } });
    const axios = { async get(url, config) {
        calls.push({ url, params: config?.params });
        if (fail) throw { response: { status: fail } };
        if (url.endsWith('/20')) {
            if (deferDetail) { deferDetail = false; return new Promise((resolve) => { resolveDetail = resolve; }); }
            return { data: { data: bien } };
        }
        if (deferred) { deferred = false; return new Promise((resolve) => { resolveSearch = resolve; }); }
        return response(config.params.page);
    } };
    vm.runInNewContext(source, { document, window: { axios, location: { assign: (url) => redirects.push(url) } },
        setTimeout: (fn, ms) => { timers.set(++sequence, { fn, ms }); return sequence; }, clearTimeout: (id) => timers.delete(id) });
    const find = (name) => lookup(nodes, `[data-global-search-${name}]`);
    const input = async (value) => { find('input').value = value; await find('input').emit('input'); };
    const tick = () => { const pending = [...timers.values()]; timers.clear(); return Promise.all(pending.map(({ fn }) => fn())); };
    return { find, input, tick, timers, calls, redirects, document, search, detail, bien, response,
        detailFind: (selector) => lookup(detailNodes, selector), resolveSearch: (value) => resolveSearch(value), resolveDetail: (value) => resolveDetail(value) };
}

test('two characters and debounce are required; Enter searches immediately with leading zeros', async () => {
    const app = setup();
    await app.input('0'); await app.tick(); assert.equal(app.calls.length, 0);
    await app.find('form').emit('submit'); assert.equal(app.calls.length, 0);
    await app.input('02'); await app.input('0243');
    assert.equal(app.timers.size, 1); assert.equal([...app.timers.values()][0].ms, 300);
    assert.equal(app.calls.length, 0);
    await app.find('form').emit('submit'); await settle();
    assert.equal(app.timers.size, 0); assert.equal(app.calls[0].params.q, '0243');
    assert.equal(app.calls.length, 1);
    assert.match(app.find('list').children[0].children[0].children[0].textContent, /0243831/);
    assert.equal(app.detailFind('[data-bien-detail-dialog]').open, undefined);
});

test('station asset buttons reuse the scoped read-only detail and restore focus to their origin', async () => {
    const app = setup();
    const trigger = new Element(); trigger.dataset.viewBienDetail = '20'; trigger.isConnected = true;
    trigger.closest = () => trigger;
    await app.document.emit('click', { target: trigger });
    assert.equal(app.calls.length, 1);
    assert.equal(app.calls[0].url, '/api/busqueda/bienes/20');
    assert.equal(app.detailFind('[data-bien-detail-dialog]').open, true);
    assert.equal(app.detailFind('[data-detail-cbi]').textContent, '0243831');
    const technical = app.detailFind('[data-technical-fields]').children;
    assert.equal(technical.find((group) => group.children[0].textContent === 'Memoria RAM').children[1].textContent, '16 GB');
    await app.detailFind('[data-close-bien-detail]').emit('click');
    assert.equal(trigger.focused, true);
    assert.equal(app.find('input').focused, undefined);
});

test('station detail errors respect API permissions and never display stale asset fields', async () => {
    for (const status of [403, 404]) {
        const app = setup({ fail: status });
        const trigger = new Element(); trigger.dataset.viewBienDetail = '20'; trigger.closest = () => trigger;
        await app.document.emit('click', { target: trigger });
        assert.equal(app.detailFind('[data-bien-detail-fields]').hidden, true);
        assert.match(app.detailFind('[data-bien-detail-message]').textContent, status === 403 ? /permiso/ : /fuera de tu alcance/);
    }
    const app = setup();
    for (const id of ['undefined', '0', '../20']) {
        const trigger = new Element(); trigger.dataset.viewBienDetail = id; trigger.closest = () => trigger;
        await app.document.emit('click', { target: trigger });
    }
    const trigger = new Element(); trigger.dataset.viewBienDetail = '20'; trigger.disabled = true; trigger.closest = () => trigger;
    await app.document.emit('click', { target: trigger });
    assert.equal(app.calls.length, 0);
});

test('results paginate on the server and a new search resets to the first page', async () => {
    const app = setup(); await app.input('SER'); await app.tick();
    await app.find('next').emit('click'); assert.equal(app.calls[1].params.page, 2);
    await app.input('Computadora'); await app.tick(); assert.equal(app.calls[2].params.page, 1);
    assert.equal(app.calls.length, 3);
});

test('late results are discarded when the query changes or is cleared', async () => {
    const app = setup({ deferred: true });
    await app.input('viejo'); const pending = app.tick();
    assert.equal(app.find('panel').attributes['aria-busy'], 'true');
    await app.input('nuevo'); await app.tick();
    assert.equal(app.find('list').children.length, 1);
    app.resolveSearch({ data: { data: [], total: 0, current_page: 1, last_page: 1 } }); await pending;
    assert.equal(app.find('list').children.length, 1);
    await app.input(''); assert.equal(app.find('panel').hidden, true); assert.equal(app.find('list').children.length, 0);
});

test('selecting a result opens the shared read-only detail with technical data and missing-value dashes', async () => {
    const app = setup(); await app.input('0243'); await app.tick();
    await app.find('list').children[0].children[0].emit('click');
    assert.equal(app.calls[1].url, '/api/busqueda/bienes/20');
    assert.equal(app.detailFind('[data-bien-detail-dialog]').open, true);
    assert.equal(app.detailFind('[data-detail-sede]').textContent, 'Sede');
    assert.equal(app.detailFind('[data-detail-cbi]').textContent, '0243831');
    assert.equal(app.detailFind('[data-detail-condicion]').textContent, '—');
    const technical = app.detailFind('[data-technical-fields]').children;
    assert.equal(technical.find((group) => group.children[0].textContent === 'Memoria RAM').children[1].textContent, '16 GB');
    await app.detailFind('[data-close-bien-detail]').emit('click');
    assert.equal(app.detailFind('[data-bien-detail-dialog]').open, false);
});

test('Escape and clicking outside close results; a late detail cannot repopulate a closed modal', async () => {
    const app = setup({ deferDetail: true }); await app.input('0243'); await app.tick();
    await app.document.emit('keydown', { key: 'Escape' }); assert.equal(app.find('panel').hidden, true);
    await app.input('0243'); await app.tick();
    await app.document.emit('pointerdown', { target: {} }); assert.equal(app.find('panel').hidden, true);
    await app.find('form').emit('submit'); await settle();
    const pending = app.find('list').children[0].children[0].emit('click');
    await app.detailFind('[data-close-bien-detail]').emit('click');
    app.resolveDetail({ data: { data: app.bien } }); await pending;
    assert.equal(app.detailFind('[data-bien-detail-fields]').hidden, true);
});

test('empty results and authentication/permission/server errors have explicit states', async () => {
    const empty = setup({ deferred: true }); await empty.input('vacio'); const pending = empty.tick();
    empty.resolveSearch({ data: { data: [], total: 0, current_page: 1, last_page: 1 } }); await pending;
    assert.match(empty.find('status').textContent, /No hay coincidencias/);
    for (const status of [401, 403, 404, 422, 500]) {
        const app = setup({ fail: status }); await app.input('busqueda'); await app.tick();
        assert.ok(app.find('status').textContent); assert.equal(app.find('list').children.length, 0);
        assert.equal(app.find('panel').attributes['aria-busy'], undefined);
        assert.equal(app.redirects.length, status === 401 ? 1 : 0);
    }
});
