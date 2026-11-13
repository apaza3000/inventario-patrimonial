import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const pickerSource = readFileSync(new URL('../../resources/js/bien-search.js', import.meta.url), 'utf8').replace('export function', 'function');
const source = pickerSource + readFileSync(new URL('../../resources/js/movimientos.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };

class Element {
    constructor() { this.listeners = {}; this.elements = {}; this.dataset = {}; this.options = []; this._value = ''; this.textContent = ''; this.children = []; }
    get value() { return this._value; }
    set value(value) { this._value = this.isSelect && !this.options.some((option) => option.value === String(value)) ? '' : String(value); }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    emit(name, event = {}) { return this.listeners[name]?.({ preventDefault() {}, stopPropagation() {}, currentTarget: this, ...event }); }
    setAttribute(name, value) { (this.attributes ??= {})[name] = String(value); }
    getAttribute(name) { return this.attributes?.[name] ?? null; }
    removeAttribute(name) { delete this.attributes?.[name]; }
    setCustomValidity(value) { this.validationMessage = value; }
    focus() { this.emit('focus'); }
    scrollIntoView() {}
    querySelectorAll() { return []; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    reset() { Object.values(this.elements).forEach((field) => { field.value = ''; }); }
    reportValidity() { return Object.values(this.elements).every((field) => !field.required || field.value !== ''); }
    replaceChildren(...options) { this.children = [...options]; this.options = [...options]; this.value = options[0]?.value ?? ''; }
    append(...items) { this.children.push(...items); }
    add(option) { this.options.push(option); }
}

function setup({ lookup = true, failOptions = false, list = false } = {}) {
    const nodes = new Map();
    const find = (selector) => {
        if ((selector === '[data-movements-list]' && !list) || (!lookup && selector === '[data-movement-lookup-form]')) return null;
        if (!nodes.has(selector)) { const node = new Element(); node.querySelector = find; nodes.set(selector, node); }
        return nodes.get(selector);
    };
    const root = new Element(); root.querySelector = find;
    root.dataset = { movementsUrl: '/api/movimientos', optionsUrl: '/api/movimientos/opciones', bienSearchUrl: '/api/movimientos/bienes', environmentsUrl: '/api/ambientes', csrfUrl: '/sanctum/csrf-cookie' };
    const form = find('[data-movement-create-form]');
    for (const key of ['bien_id', 'ambiente_destino_id', 'ordenado_por', 'ejecutado_por', 'fecha_movimiento', 'motivo', 'observaciones']) {
        const field = form.elements[key] = new Element();
        field.isSelect = ['bien_id', 'ambiente_destino_id', 'ordenado_por', 'ejecutado_por'].includes(key);
        field.required = key !== 'observaciones';
    }
    if (lookup) find('[data-movement-lookup-form]').elements.id = new Element();
    const options = {
        bienes: Array.from({ length: 32 }, (_, i) => ({ id: i + 1, cbi: String(700001 + i).padStart(8, '0'), descripcion: `Bien ${i + 1}`, serie: `SERIE-${i + 1}`, ambiente_id: i === 31 ? 2 : 1 })),
        ambientes: [{ id: 1, nombre: 'Origen A' }, { id: 2, nombre: 'Origen B' }, { id: 3, nombre: 'Destino' }],
        responsables: [{ id: 1, nombres: 'Usuario', apellidos: 'Simulado' }],
    };
    options.bienes.push({ id: 33, cbi: null, descripcion: 'Bien sin CBI', ambiente_id: null });
    const gets = [], posts = [], queries = [];
    const axios = {
        async get(url, config) {
            gets.push(url); queries.push({ url, params: config?.params });
            if (url === '/api/movimientos') return { data: { data: [{ id: 9, estado: 'pendiente_firma', bien: options.bienes[0] }], current_page: config.params.page, last_page: 2, total: 16 } };
            if (url === '/api/ambientes') return { data: { data: options.ambientes } };
            if (url.endsWith('/opciones')) {
                if (failOptions) { failOptions = false; throw new Error('Simulated network error'); }
                return { data: { data: { ...options, bienes: [] } } };
            }
            if (url === '/api/movimientos/bienes') {
                const params = config.params; const items = options.bienes.filter((b) => params.bien_id ? b.id === Number(params.bien_id) : [b.cbi, b.descripcion, b.serie].some((value) => String(value ?? '').toLowerCase().includes(params.q.toLowerCase())));
                const page = params.page ?? 1;
                return { data: { data: items.slice((page - 1) * 15, page * 15).map((item) => ({ ...item, ambiente: options.ambientes.find((a) => a.id === item.ambiente_id), equipo: { serie: item.serie } })), total: items.length, current_page: page, last_page: Math.max(1, Math.ceil(items.length / 15)) } };
            }
            if (url.endsWith('/csrf-cookie')) return { data: {} };
            return { data: { data: { id: 9, estado: 'pendiente_firma' } } };
        },
        async post(url, payload) { posts.push({ url, payload: JSON.parse(JSON.stringify(payload)) }); return { data: { data: { id: 9, estado: 'pendiente_firma' } } }; },
    };
    vm.runInNewContext(source, { setTimeout, clearTimeout,
        document: { querySelector: () => root, createElement: () => new Element() }, window: { axios }, Date, Intl,
        Option: class { constructor(label, value) { this.textContent = label; this.value = value; } },
    });
    const open = async () => { find('[data-new-movement]').emit('click'); await flush(); };
    const search = async (query) => { find('[data-bien-search]').value = query; find('[data-bien-search]').emit('input'); await find('[data-bien-search]').emit('keydown', { key: 'Enter' }); await flush(); };
    const select = (id) => { form.elements.bien_id.value = String(id); form.elements.bien_id.emit('change'); };
    return { find, form, options, gets, posts, queries, open, search, select };
}

test('remote CBI and description search preserves zeros and requires explicit selection', async () => {
    const s = setup({ lookup: false }); await s.open();
    await s.search('00700032');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.match(s.form.elements.bien_id.options[1].textContent, /00700032.*Bien 32/);
    assert.equal(s.form.elements.bien_id.value, '');
    assert.deepEqual(s.gets, ['/api/movimientos/opciones', '/api/movimientos/bienes']);
    await s.search('0070003');
    assert.equal(s.form.elements.bien_id.options.length, 4);
    await s.search('Bien 32');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.match(s.find('[data-bien-search-summary]').textContent, /^1 coincidencias/);
});

test('selection survives searches and clearing, while changing the good updates origin and destination', async () => {
    const s = setup(); await s.open(); await s.search('00700032'); s.select(32);
    assert.equal(s.find('[data-origin-label]').value, 'Origen B');
    assert.ok(!s.form.elements.ambiente_destino_id.options.some((option) => option.value === '2'));
    s.form.elements.ambiente_destino_id.value = '3';
    await s.search('no existe');
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.match(s.find('[data-bien-search-summary]').textContent, /No hay coincidencias.*Se conserva la selección/);
    assert.equal(s.find('[data-origin-label]').value, 'Origen B');
    assert.equal(s.form.elements.ambiente_destino_id.value, '3');
    await s.search('');
    assert.equal(s.form.elements.bien_id.options.length, 2);
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.ok(s.find('[data-bien-search-summary]').textContent !== undefined);
    await s.search('Bien 1'); s.select(1);
    assert.equal(s.find('[data-origin-label]').value, 'Origen A');
    await s.search('Bien sin CBI'); s.select(33);
    assert.equal(s.find('[data-origin-label]').value, '—');
    assert.match(s.find('[data-selected-bien]').textContent, /CBI —.*Bien sin CBI/);
    s.select('');
    assert.equal(s.find('[data-selected-bien]').textContent, 'Selecciona un bien.');
});

test('submitting requires explicit selection and preserves the existing registration payload and CSRF flow', async () => {
    const s = setup(); await s.open(); await s.search('00700032');
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
    const s = setup(); await s.open(); await s.search('00700032'); s.select(32);
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
    await s.search('00700032');
    assert.equal(s.form.elements.bien_id.options.length, 2);
});

test('picker shows at most fifteen matches, pages remotely, searches series and explicitly clears selection', async () => {
    const s = setup(); await s.open(); await s.search('Bien');
    assert.equal(s.form.elements.bien_id.options.length, 16);
    assert.equal(s.form.elements.bien_id.value, '');
    assert.equal(s.queries[0].params.incluir_bienes, 0);
    await s.find('[data-bien-next]').emit('click'); await flush();
    assert.equal(s.queries.at(-1).params.page, 2);
    assert.ok(s.form.elements.bien_id.options.some((option) => /Bien 16/.test(option.textContent)));
    await s.search('SERIE-32'); s.select(32);
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.match(s.find('[data-selected-bien]').textContent, /Origen B/);
    await s.find('[data-bien-clear]').emit('click');
    assert.equal(s.form.elements.bien_id.value, '');
    assert.equal(s.find('[data-origin-label]').value, '—');
    assert.equal(s.find('[data-bien-search]').value, '');
});

test('movement list keeps combined server filters while paginating and resets on a new criterion', async () => {
    const s = setup({ list: true }); await flush();
    s.find('[data-movements-search]').value = '02';
    s.find('[data-movements-filter-estado]').value = 'pendiente_firma';
    s.find('[data-movements-filter-desde]').value = '2026-11-11';
    s.find('[data-movements-filter-ambiente_destino_id]').value = '2';
    await s.find('[data-movements-filter-estado]').emit('change'); await flush();
    await s.find('[data-movements-next]').emit('click'); await flush();
    assert.deepEqual(JSON.parse(JSON.stringify(s.queries.filter(({ url }) => url === '/api/movimientos').at(-1).params)), {
        page: 2, q: '02', desde: '2026-11-11', ambiente_destino_id: '2', estado: 'pendiente_firma',
    });
    s.find('[data-movements-filter-estado]').value = 'finalizado';
    await s.find('[data-movements-filter-estado]').emit('change'); await flush();
    assert.equal(s.queries.filter(({ url }) => url === '/api/movimientos').at(-1).params.page, 1);
    await s.find('[data-movements-clear]').emit('click'); await flush();
    assert.deepEqual(JSON.parse(JSON.stringify(s.queries.filter(({ url }) => url === '/api/movimientos').at(-1).params)), { page: 1 });
});

test('picker supports Enter, requires two characters and debounces requests', async () => {
    const s = setup(); await s.open();
    const input = s.find('[data-bien-search]');
    input.value = '0'; await input.emit('keydown', { key: 'Enter' }); await flush();
    assert.equal(s.queries.filter(({ url }) => url === '/api/movimientos/bienes').length, 0);
    input.value = '00'; input.emit('input');
    input.value = '007'; input.emit('input');
    await new Promise((resolve) => setTimeout(resolve, 360)); await flush();
    const searches = s.queries.filter(({ url }) => url === '/api/movimientos/bienes');
    assert.equal(searches.length, 1);
    assert.equal(searches[0].params.q, '007');
    input.value = 'Bien 32';
    await input.emit('keydown', { key: 'Enter' }); await flush();
    assert.equal(s.queries.at(-1).params.q, 'Bien 32');
    assert.match(s.form.elements.bien_id.options[1].textContent, /Bien 32/);
});

test('automatic suggestions select the movement asset by click and submit its actual ID and origin', async () => {
    const s = setup(); await s.open();
    const input = s.find('[data-bien-search]'), list = s.find('[data-bien-suggestions]');
    input.value = '00700032'; input.emit('input');
    await new Promise((resolve) => setTimeout(resolve, 360)); await flush();
    assert.equal(list.hidden, false);
    assert.equal(input.getAttribute('aria-expanded'), 'true');
    assert.match(list.children[0].children[0].textContent, /CBI 00700032/);
    assert.equal(list.children[0].children[1].textContent, 'Bien 32');
    assert.match(list.children[0].children[2].textContent, /Origen B/);
    assert.equal(s.form.elements.bien_id.value, '');
    assert.ok(input.validationMessage);
    await list.children[0].emit('click'); await flush();
    assert.equal(list.hidden, true);
    assert.equal(s.form.elements.bien_id.value, '32');
    assert.equal(s.find('[data-origin-label]').value, 'Origen B');
    assert.equal(input.validationMessage, '');
    s.form.elements.ambiente_destino_id.value = '3';
    s.form.elements.ordenado_por.value = '1'; s.form.elements.ejecutado_por.value = '1';
    s.form.elements.motivo.value = 'Prueba de sugerencias';
    await s.form.emit('submit'); await flush();
    assert.equal(s.posts[0].payload.bien_id, 32);
    assert.equal(s.posts[0].payload.ambiente_origen_id, 2);
});

test('movement suggestions support arrows, Escape and Enter without the separate selector', async () => {
    const s = setup(); await s.open(); await s.search('Bien');
    const input = s.find('[data-bien-search]'), list = s.find('[data-bien-suggestions]');
    assert.equal(list.children.length, 15);
    await input.emit('keydown', { key: 'ArrowDown' });
    assert.equal(list.children[0].getAttribute('aria-selected'), 'true');
    await input.emit('keydown', { key: 'Escape' });
    assert.equal(list.hidden, true);
    assert.equal(s.form.elements.bien_id.value, '');
    await input.emit('keydown', { key: 'ArrowDown' });
    await input.emit('keydown', { key: 'ArrowDown' });
    await input.emit('keydown', { key: 'Enter' }); await flush();
    assert.equal(s.form.elements.bien_id.value, '2');
    assert.match(s.find('[data-selected-bien]').textContent, /Bien 2/);
    await s.find('[data-bien-clear]').emit('click');
    assert.equal(s.form.elements.bien_id.value, '');
    assert.ok(input.validationMessage);
});
