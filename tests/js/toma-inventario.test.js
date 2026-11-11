import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/toma-inventario.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };

class Element {
    constructor(tag = '') { this.tag = tag; this.children = []; this.listeners = {}; this.elements = {}; this.dataset = {}; this.value = ''; this.textContent = ''; }
    addEventListener(name, callback) { (this.listeners[name] ??= []).push(callback); }
    async emit(name) { for (const callback of this.listeners[name] ?? []) await callback({ preventDefault() {}, currentTarget: this }); }
    querySelectorAll() { return []; }
    closest() { return this.dialog ?? null; }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
    setAttribute(key, value) { this[key] = value; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    reset() { Object.values(this.elements).forEach((field) => { field.value = ''; }); }
    reportValidity() { return true; }
}

function setup({ manage = false, reject = null } = {}) {
    const nodes = new Map();
    const find = (selector) => {
        if (!manage && /data-(edit|delete)-inventory-(dialog|form)/.test(selector)) return null;
        if (!nodes.has(selector)) { const node = new Element(); node.querySelector = find; nodes.set(selector, node); }
        return nodes.get(selector);
    };
    const root = new Element(); root.querySelector = find;
    root.dataset = {
        inventoriesUrl: '/api/inventarios', bienesUrl: manage ? '/api/bienes' : '/api/inventarios/bienes',
        csrfUrl: '/sanctum/csrf-cookie', loginUrl: '/login',
    };
    const create = find('[data-create-inventory-form]');
    create.elements = { anio: new Element(), inventario: new Element() };
    if (manage) find('[data-edit-inventory-form]').elements.inventario = new Element();
    const record = { bien_id: 1, anio: 2025, inventario: 'INV-1', bien: { id: 1, cbi: '0000001', descripcion: 'Bien simulado' } };
    const gets = [], writes = [];
    const axios = {
        async get(url, config) {
            gets.push({ url, page: config?.params?.page });
            if (url === root.dataset.bienesUrl) {
                const page = config.params.page;
                return { data: { data: [{ id: page, cbi: `000000${page}`, descripcion: `Laboratorio ${page}` }], current_page: page, last_page: 2, total: 2 } };
            }
            if (url === root.dataset.inventoriesUrl) return { data: { data: [record], current_page: 1, last_page: 1, total: 1 } };
            return { data: { data: record } };
        },
        async request(options) {
            writes.push(JSON.parse(JSON.stringify(options)));
            if (reject) throw { response: reject };
            return { data: {} };
        },
    };
    vm.runInNewContext(source, { document: { querySelector: () => root, createElement: (tag) => new Element(tag) }, window: { axios } });
    const actions = () => find('[data-inventory-rows]').children[0].children.at(-1).children;
    const open = async () => { await find('[data-new-inventory]').emit('click'); await flush(); };
    const select = async () => { await find('[data-picker-rows]').children[0].children[0].children[0].emit('change'); };
    return { find, create, root, gets, writes, actions, open, select };
}

test('assistant registers with the scoped paginated picker and has no edit or delete actions', async () => {
    const s = setup(); await flush();
    assert.deepEqual(s.actions().map((button) => button.textContent), ['Ver detalle']);
    await s.actions()[0].emit('click');
    assert.equal(s.find('[data-detail-inventario]').textContent, 'INV-1');
    await s.open();
    assert.deepEqual(s.gets.filter((item) => item.url === '/api/inventarios/bienes'), [{ url: '/api/inventarios/bienes', page: 1 }]);
    await s.select();
    s.create.elements.anio.value = '2026'; s.create.elements.inventario.value = 'LAB-2026';
    await s.create.emit('submit');
    assert.deepEqual(s.writes, [{ method: 'post', url: '/api/inventarios', data: { bien_id: 1, anio: 2026, inventario: 'LAB-2026' } }]);
    assert.ok(s.gets.some((item) => item.url === '/sanctum/csrf-cookie'));
    assert.ok(!s.gets.some((item) => item.url === '/api/bienes'));
});

test('picker search stays on the visible page and selection survives paging', async () => {
    const s = setup(); await s.open(); await s.select();
    s.find('[data-picker-search]').value = '0000002';
    await s.find('[data-picker-search]').emit('input');
    assert.match(s.find('[data-picker-summary]').textContent, /^0 coincidencias/);
    assert.equal(s.gets.filter((item) => item.url === '/api/inventarios/bienes').length, 1);
    await s.find('[data-picker-next]').emit('click');
    assert.match(s.find('[data-selected-bien]').textContent, /Bien ID 1/);
    assert.equal(s.gets.at(-1).page, 2);
    await s.select();
    assert.match(s.find('[data-selected-bien]').textContent, /Bien ID 2/);
});

for (const reject of [
    { status: 403, data: { message: 'Fuera del alcance de laboratorios.' } },
    { status: 422, data: { errors: { anio: ['Ya existe un inventario para este bien y año.'] } } },
]) {
    test(`registration displays the backend ${reject.status} error without closing the form`, async () => {
        const s = setup({ reject }); await s.open(); await s.select();
        s.create.elements.anio.value = '2026'; s.create.elements.inventario.value = 'PRUEBA';
        await s.create.emit('submit');
        assert.equal(s.find('[data-create-inventory-dialog]').open, true);
        assert.equal(s.find('[data-form-message]').textContent, reject.data.message ?? reject.data.errors.anio[0]);
        assert.equal(s.find('[data-save-inventory]').disabled, false);
    });
}

test('full access retains editing only inventario and deleting by the composite identity', async () => {
    const s = setup({ manage: true }); await flush();
    assert.deepEqual(s.actions().map((button) => button.textContent), ['Ver detalle', 'Editar', 'Eliminar']);
    await s.actions()[1].emit('click');
    s.find('[data-edit-inventory-form]').elements.inventario.value = 'ACTUALIZADO';
    await s.find('[data-edit-inventory-form]').emit('submit');
    assert.deepEqual(s.writes[0], { method: 'patch', url: '/api/inventarios/1/2025', data: { inventario: 'ACTUALIZADO' } });
    await s.actions()[2].emit('click');
    await s.find('[data-delete-inventory-form]').emit('submit');
    assert.deepEqual(s.writes[1], { method: 'delete', url: '/api/inventarios/1/2025' });
    await s.open();
    assert.ok(s.gets.some((item) => item.url === '/api/bienes'));
});
