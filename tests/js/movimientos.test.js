import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/movimientos.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };

class Element {
    constructor() { this.listeners = {}; this.elements = {}; this.dataset = {}; this.options = []; this._value = ''; this.textContent = ''; }
    get value() { return this._value; }
    set value(value) { this._value = this.isSelect && !this.options.some((option) => option.value === String(value)) ? '' : String(value); }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    emit(name) { return this.listeners[name]?.({ preventDefault() {}, currentTarget: this }); }
    querySelectorAll() { return []; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    reset() { Object.values(this.elements).forEach((field) => { field.value = ''; }); }
    reportValidity() { return Object.values(this.elements).every((field) => !field.required || field.value !== ''); }
    replaceChildren(...options) { this.options = [...options]; this.value = options[0]?.value ?? ''; }
    add(option) { this.options.push(option); }
}

function setup({ lookup = true, failOptions = false } = {}) {
    const nodes = new Map();
    const find = (selector) => {
        if (selector === '[data-movements-list]' || (!lookup && selector === '[data-movement-lookup-form]')) return null;
        if (!nodes.has(selector)) { const node = new Element(); node.querySelector = find; nodes.set(selector, node); }
        return nodes.get(selector);
    };
    const root = new Element(); root.querySelector = find;
    root.dataset = { movementsUrl: '/api/movimientos', optionsUrl: '/api/movimientos/opciones', csrfUrl: '/sanctum/csrf-cookie' };
    const form = find('[data-movement-create-form]');
    for (const key of ['bien_id', 'ambiente_destino_id', 'ordenado_por', 'ejecutado_por', 'fecha_movimiento', 'motivo', 'observaciones']) {
        const field = form.elements[key] = new Element();
        field.isSelect = ['bien_id', 'ambiente_destino_id', 'ordenado_por', 'ejecutado_por'].includes(key);
        field.required = key !== 'observaciones';
    }
    if (lookup) find('[data-movement-lookup-form]').elements.id = new Element();
    const options = {
        bienes: Array.from({ length: 32 }, (_, i) => ({ id: i + 1, cbi: String(700001 + i).padStart(8, '0'), descripcion: `Bien ${i + 1}`, ambiente_id: i === 31 ? 2 : 1 })),
        ambientes: [{ id: 1, nombre: 'Origen A' }, { id: 2, nombre: 'Origen B' }, { id: 3, nombre: 'Destino' }],
        responsables: [{ id: 1, nombres: 'Usuario', apellidos: 'Simulado' }],
    };
    options.bienes.push({ id: 33, cbi: null, descripcion: 'Bien sin CBI', ambiente_id: null });
    const gets = [], posts = [];
    const axios = {
        async get(url) {
            gets.push(url);
            if (url.endsWith('/opciones')) {
                if (failOptions) { failOptions = false; throw new Error('Simulated network error'); }
                return { data: { data: options } };
            }
            if (url.endsWith('/csrf-cookie')) return { data: {} };
            return { data: { data: { id: 9, estado: 'pendiente_firma' } } };
        },
        async post(url, payload) { posts.push({ url, payload: JSON.parse(JSON.stringify(payload)) }); return { data: { data: { id: 9, estado: 'pendiente_firma' } } }; },
    };
    vm.runInNewContext(source, {
        document: { querySelector: () => root }, window: { axios }, Date, Intl,
        Option: class { constructor(label, value) { this.textContent = label; this.value = value; } },
    });
    const open = async () => { find('[data-new-movement]').emit('click'); await flush(); };
    const search = (query) => { find('[data-bien-search]').value = query; find('[data-bien-search]').emit('input'); };
    const select = (id) => { form.elements.bien_id.value = String(id); form.elements.bien_id.emit('change'); };
    return { find, form, options, gets, posts, open, search, select };
}

test('CBI search covers the complete options response, preserves zeros and never auto-selects', async () => {
    const s = setup({ lookup: false }); await s.open();
    s.search('00700032');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.match(s.form.elements.bien_id.options[1].textContent, /00700032.*Bien 32/);
    assert.equal(s.form.elements.bien_id.value, '');
    assert.deepEqual(s.gets, ['/api/movimientos/opciones']);
    s.search('0070003');
    assert.equal(s.form.elements.bien_id.options.length, 4);
    s.search('Bien 32');
    assert.equal(s.form.elements.bien_id.options.length, 1);
    assert.match(s.find('[data-bien-search-summary]').textContent, /^0 coincidencias/);
});

test('selection survives searches and clearing, while changing the good updates origin and destination', async () => {
    const s = setup(); await s.open(); s.search('00700032'); s.select(32);
    assert.equal(s.find('[data-origin-label]').value, 'Origen B');
    assert.ok(!s.form.elements.ambiente_destino_id.options.some((option) => option.value === '2'));
    s.form.elements.ambiente_destino_id.value = '3';
    s.search('no existe');
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.match(s.find('[data-bien-search-summary]').textContent, /0 coincidencias.*Se conserva la selección/);
    assert.equal(s.find('[data-origin-label]').value, 'Origen B');
    assert.equal(s.form.elements.ambiente_destino_id.value, '3');
    s.search('');
    assert.equal(s.form.elements.bien_id.options.length, 34);
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.equal(s.find('[data-bien-search-summary]').textContent, '');
    s.select(1);
    assert.equal(s.find('[data-origin-label]').value, 'Origen A');
    s.select(33);
    assert.equal(s.find('[data-origin-label]').value, '—');
    assert.match(s.find('[data-selected-bien]').textContent, /CBI —.*Bien sin CBI/);
    s.select('');
    assert.equal(s.find('[data-selected-bien]').textContent, 'Ningún bien seleccionado.');
});

test('submitting requires explicit selection and preserves the existing registration payload and CSRF flow', async () => {
    const s = setup(); await s.open(); s.search('00700032');
    s.form.elements.ambiente_destino_id.value = '3';
    s.form.elements.ordenado_por.value = '1'; s.form.elements.ejecutado_por.value = '1';
    s.form.elements.fecha_movimiento.value = '2026-11-10T10:00:00';
    s.form.elements.motivo.value = 'Traslado de prueba';
    await s.form.emit('submit'); assert.equal(s.posts.length, 0);
    s.select(32); await s.form.emit('submit');
    assert.deepEqual(s.posts, [{ url: '/api/movimientos', payload: {
        bien_id: 32, ambiente_origen_id: 2, ambiente_destino_id: 3, ordenado_por: 1, ejecutado_por: 1,
        fecha_movimiento: '2026-11-10 10:00:00', motivo: 'Traslado de prueba', observaciones: null,
    } }]);
    assert.equal(s.gets.at(-1), '/sanctum/csrf-cookie');
});

test('refreshing options preserves the selected good and refreshes its current origin', async () => {
    const s = setup(); await s.open(); s.search('00700032'); s.select(32);
    s.options.bienes[31].ambiente_id = 3;
    s.find('[data-options-retry]').emit('click'); await flush();
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.equal(s.find('[data-origin-label]').value, 'Destino');
    s.find('[data-movement-create-dialog]').close(); await s.open();
    assert.equal(s.form.elements.bien_id.value, '');
    assert.equal(s.find('[data-bien-search]').value, '');
    assert.equal(s.find('[data-bien-search-summary]').textContent, '');
    assert.equal(s.find('[data-origin-label]').value, '—');
});

test('option loading failure keeps saving and searching disabled until a successful retry', async () => {
    const s = setup({ failOptions: true }); await s.open();
    assert.equal(s.find('[data-save-movement]').disabled, true);
    assert.equal(s.find('[data-bien-search]').disabled, true);
    s.find('[data-options-retry]').emit('click'); await flush();
    assert.equal(s.find('[data-save-movement]').disabled, false);
    assert.equal(s.find('[data-bien-search]').disabled, false);
    s.search('00700032');
    assert.equal(s.form.elements.bien_id.options.length, 2);
});
