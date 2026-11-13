import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/bien-detail.js', import.meta.url), 'utf8').replace('export function', 'function')
    + readFileSync(new URL('../../resources/js/global-search.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '')
    + '\n' + readFileSync(new URL('../../resources/js/estaciones.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 10; i++) await new Promise(setImmediate); };
class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.listeners = {}; this.attributes = {}; this.dataset = {}; this.value = ''; this.textContent = ''; this.isConnected = true; this.nodes = new Map(); }
    querySelector(selector) { if (!this.nodes.has(selector)) this.nodes.set(selector, new Element()); return this.nodes.get(selector); }
    querySelectorAll() { return []; }
    append(...items) { items.forEach((item) => { item.parentElement = this; this.children.push(item); }); }
    replaceChildren(...items) { this.children = []; this.append(...items); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    getAttribute(name) { return this.attributes[name] ?? null; }
    removeAttribute(name) { delete this.attributes[name]; }
    addEventListener(name, callback) { (this.listeners[name] ??= []).push(callback); }
    async emit(name, event = {}) { for (const callback of this.listeners[name] ?? []) await callback({ preventDefault() {}, target: this, ...event }); }
    closest(selector) { return selector === '[data-view-bien-detail]' ? (this.dataset.viewBienDetail ? this : this.parentElement?.closest(selector)) : null; }
    contains(target) { return target === this || this.children.some((child) => child.contains(target)); }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    focus() { this.focused = true; }
    reset() { Object.values(this.elements ?? {}).forEach((field) => { field.value = ''; }); }
    reportValidity() { return true; }
}
const walk = (node) => [node, ...node.children.flatMap(walk)];
function setup() {
    const root = new Element(), header = new Element(), shared = new Element(), document = new Element();
    const find = (selector) => root.querySelector(selector);
    root.dataset = { stationsUrl: '/api/estaciones-pc', componentsUrl: '/api/estacion-componentes', bienesUrl: '/api/bienes', csrfUrl: '/sanctum/csrf-cookie', loginUrl: '/login' };
    header.dataset = { searchUrl: '/api/busqueda/bienes', loginUrl: '/login' };
    document.querySelector = (selector) => selector === '[data-stations-root]' ? root : selector === '[data-global-search]' ? header : shared;
    document.createElement = (tag) => new Element(tag);
    for (const [selector, fields] of [['[data-station-form]', ['codigo', 'descripcion', 'activo']], ['[data-assignment-form]', ['fecha_asignacion']], ['[data-withdraw-form]', ['fecha_retiro']], ['[data-delete-form]', []]]) {
        find(selector).elements = Object.fromEntries(fields.map((name) => [name, new Element('input')]));
    }
    const goods = [
        { id: 1, cbi: '0000001', descripcion: 'Computadora', activo: true, equipo: { marca: { nombre: 'Marca real' }, modelo: 'Modelo', serie: '000SERIE', equipo_computo: { procesador: 'Procesador', ram: '16 GB', almacenamiento: '512 GB', detalle_adicional: 'Detalle actual' } } },
        { id: 2, cbi: '0000002', descripcion: 'Monitor', equipo: { monitor: { tamanio_pantalla: '24 pulgadas' } } },
        { id: 3, cbi: null, descripcion: 'Mueble', mueble: { material: 'Madera', color: null } },
    ];
    const basic = (item) => ({ id: item.id, cbi: item.cbi, descripcion: item.descripcion });
    const station = { id: 7, codigo: 'EST-07', descripcion: 'Estacion simulada', activo: true, componentes: [
        { id: 11, estacion_id: 7, bien_id: 1, bien: basic(goods[0]), activo: true, fecha_asignacion: '2026-01-02', fecha_retiro: null },
        { id: 12, estacion_id: 7, bien_id: 2, bien: basic(goods[1]), activo: false, fecha_asignacion: null, fecha_retiro: null },
        { id: 13, estacion_id: 7, bien_id: 3, bien: basic(goods[2]), activo: true, fecha_asignacion: null, fecha_retiro: null },
    ] };
    const calls = [], writes = [];
    const axios = {
        async get(url) {
            calls.push(url);
            if (url === '/api/estaciones-pc') return { data: { data: [station], total: 1, current_page: 1, last_page: 1 } };
            if (url === '/api/estaciones-pc/7') return { data: { data: station } };
            if (url === '/api/bienes') return { data: { data: goods, total: 3, current_page: 1, last_page: 1 } };
            if (url.startsWith('/api/busqueda/bienes/')) return { data: { data: goods.find((item) => item.id === Number(url.split('/').at(-1))) } };
            if (url === '/sanctum/csrf-cookie') return {};
            throw new Error(`Unexpected GET ${url}`);
        },
        async request(args) {
            writes.push(JSON.parse(JSON.stringify(args)));
            if (args.method === 'patch') Object.assign(station.componentes.find((item) => item.id === Number(args.url.split('/').at(-1))), args.data);
            if (args.method === 'post') station.componentes.push({ id: 14, ...args.data, bien: basic(goods.find((item) => item.id === args.data.bien_id)) });
            return { data: { message: 'Guardado' } };
        },
    };
    vm.runInNewContext(source, { document, window: { axios }, setTimeout, clearTimeout });
    const open = async () => { await flush(); const button = walk(find('[data-stations-rows]')).find((node) => node.textContent === 'Ver detalle'); await button.emit('click'); await flush(); };
    const assetButtons = (selector) => walk(find(selector)).filter((node) => node.dataset.viewBienDetail);
    const technical = () => Object.fromEntries(shared.querySelector('[data-technical-fields]').children.map((group) => group.children.map((part) => part.textContent)));
    const view = async (button) => { await document.emit('click', { target: button }); await flush(); };
    return { find, shared, document, calls, writes, station, open, assetButtons, technical, view };
}

test('station components link to physical assets while dates, null CBIs and retired assignments remain intact', async () => {
    const s = setup(); await s.open();
    assert.equal(s.find('[data-active-components]').children.length, 2);
    assert.equal(s.find('[data-components-history]').children.length, 3);
    assert.deepEqual(s.assetButtons('[data-active-components]').map((node) => node.dataset.viewBienDetail), ['1', '3']);
    assert.deepEqual(s.assetButtons('[data-components-history]').map((node) => node.dataset.viewBienDetail), ['1', '2', '3']);
    const noCbi = s.find('[data-active-components]').children[1];
    assert.equal(noCbi.children[0].textContent, '—');
    assert.equal(noCbi.children[2].textContent, '—');
    const retired = s.find('[data-components-history]').children[1];
    assert.equal(retired.children[2].textContent, '—'); assert.equal(retired.children[3].textContent, '—');
    assert.equal(s.calls.some((url) => url.startsWith('/api/busqueda/bienes/')), false);
});

test('a computer component opens its current technical card and closing returns to the still-open station', async () => {
    const s = setup(); await s.open(); const trigger = s.assetButtons('[data-active-components]')[0];
    await s.view(trigger);
    assert.equal(s.shared.querySelector('[data-bien-detail-dialog]').open, true);
    assert.equal(s.find('[data-station-detail-dialog]').open, true);
    assert.deepEqual(s.technical(), { 'Tipo de equipo': '—', Marca: 'Marca real', Modelo: 'Modelo', 'Número de serie': '000SERIE', Procesador: 'Procesador', 'Memoria RAM': '16 GB', Almacenamiento: '512 GB', 'Detalle adicional': 'Detalle actual' });
    assert.equal(s.shared.querySelector('[data-detail-cbi]').textContent, '0000001');
    await s.shared.querySelector('[data-close-bien-detail]').emit('click');
    assert.equal(trigger.focused, true);
    assert.equal(s.find('[data-station-detail-dialog]').open, true);
    assert.equal(s.writes.length, 0);
});

test('history opens current monitor or furniture details without retaining computer fields', async () => {
    const s = setup(); await s.open();
    await s.view(s.assetButtons('[data-components-history]')[0]);
    await s.shared.querySelector('[data-close-bien-detail]').emit('click');
    await s.view(s.assetButtons('[data-components-history]')[1]);
    assert.equal(s.technical()['Tamaño de pantalla'], '24 pulgadas');
    assert.equal(Object.hasOwn(s.technical(), 'Memoria RAM'), false);
    s.shared.querySelector('[data-bien-detail-dialog]').close();
    await s.view(s.assetButtons('[data-components-history]')[2]);
    assert.equal(s.shared.querySelector('[data-detail-cbi]').textContent, '—');
    assert.equal(s.technical().Material, 'Madera'); assert.equal(s.technical().Color, '—');
    assert.equal(Object.hasOwn(s.technical(), 'Procesador'), false);
    assert.equal(s.writes.length, 0);
});

test('viewing a component preserves withdrawal, assignment payloads and CSRF without losing history', async () => {
    const s = setup(); await s.open();
    await s.view(s.assetButtons('[data-active-components]')[0]); s.shared.querySelector('[data-bien-detail-dialog]').close();
    const withdraw = walk(s.find('[data-active-components]')).find((node) => node.textContent === 'Retirar');
    await withdraw.emit('click'); s.find('[data-withdraw-form]').elements.fecha_retiro.value = '2026-11-13';
    await s.find('[data-withdraw-form]').emit('submit'); await flush();
    assert.deepEqual(s.writes[0], { method: 'patch', url: '/api/estacion-componentes/11', data: { activo: false, fecha_retiro: '2026-11-13' } });
    assert.equal(s.find('[data-components-history]').children.length, 3);
    await s.find('[data-assign-bien]').emit('click'); await flush();
    const radio = walk(s.find('[data-bienes-rows]')).find((node) => node.type === 'radio' && node.value === '2');
    await radio.emit('change'); await s.find('[data-assignment-form]').emit('submit'); await flush();
    assert.deepEqual(s.writes[1], { method: 'post', url: '/api/estacion-componentes', data: { estacion_id: 7, bien_id: 2, activo: true } });
    assert.equal(s.find('[data-components-history]').children.length, 4);
    assert.equal(s.calls.filter((url) => url === '/sanctum/csrf-cookie').length, 2);
});
