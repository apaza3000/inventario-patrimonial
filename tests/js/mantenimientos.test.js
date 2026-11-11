import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Execute the production module with a small DOM and Axios fixture. No server writes.
class Node {
    constructor() { this.children = []; this.dataset = {}; this.listeners = {}; this.value = ''; this.textContent = ''; this.disabled = false; this.hidden = false; this.open = false; }
    querySelector(selector) { return this.lookup(selector); }
    querySelectorAll(selector) { return this.all?.(selector) ?? []; }
    addEventListener(name, handler) { (this.listeners[name] ??= []).push(handler); }
    async emit(name, event = {}) { for (const handler of this.listeners[name] ?? []) await handler({ preventDefault() {}, ...event }); }
    append(...children) { this.children.push(...children); }
    add(option) { this.append(option); }
    replaceChildren(...children) { this.children = children; this.value = ''; }
    closest() { return this.dialog ?? null; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    reset() { Object.values(this.elements).forEach((field) => { field.value = ''; }); }
    reportValidity() { return true; }
}
const source = readFileSync(new URL('../../resources/js/mantenimientos.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 12; i++) await new Promise(setImmediate); };

function setup({ conditionId = 1, conditionsFail = false, page = true, deferSearch = false, missingDetail = false, currentCbi = '0243831' } = {}) {
    const nodes = new Map();
    const lookup = (selector) => {
        if (selector === '[data-maintenance-form-dialog]' && page) return null;
        if (selector === '[data-maintenance-form-page]' && !page) return null;
        if (page && ['[data-maintenance-detail-dialog]', '[data-maintenance-delete-dialog]', '[data-maintenance-delete-form]'].includes(selector)) return null;
        if (!nodes.has(selector)) { const node = new Node(); node.lookup = lookup; nodes.set(selector, node); }
        return nodes.get(selector);
    };
    const root = new Node(); root.lookup = lookup;
    root.dataset = { maintenanceUrl: '/api/mantenimientos', bienesUrl: '/api/bienes', usersUrl: '/api/usuarios', typesUrl: '/api/tipos-mantenimiento', conditionsUrl: '/api/condiciones-bien', csrfUrl: '/sanctum/csrf-cookie', loginUrl: '/login', listUrl: '/mantenimientos' };
    const formDialog = lookup(page ? '[data-maintenance-form-page]' : '[data-maintenance-form-dialog]');
    const detailDialog = lookup('[data-maintenance-detail-dialog]');
    const deleteDialog = lookup('[data-maintenance-delete-dialog]');
    root.all = (selector) => selector === 'dialog' ? (page ? [] : [formDialog, detailDialog, deleteDialog]) : [];
    const form = lookup('[data-maintenance-form]');
    form.elements = Object.fromEntries(['tipo_mantenimiento_id', 'condicion_id', 'fecha_mantenimiento', 'descripcion', 'diagnostico', 'trabajo_realizado', 'observaciones'].map((key) => [key, new Node()]));
    form.all = (selector) => selector === 'textarea, input[type="date"]' ? ['fecha_mantenimiento', 'descripcion', 'diagnostico', 'trabajo_realizado', 'observaciones'].map((key) => form.elements[key]) : [];
    for (const prefix of ['bien', 'user']) lookup(`[data-${prefix}-search]`).dialog = formDialog;
    const bien = { id: 20, cbi: currentCbi, descripcion: 'Bien de prueba', condicion_id: conditionId, condicion: conditionId === null ? null : { id: 1, nombre: 'Condición original' }, equipo: { modelo: null, serie: '', marca: { nombre: 'Marca' }, equipo_computo: { ram: '16 GB', almacenamiento: '512 GB' } } };
    const other = { id: 21, cbi: '0000021', descripcion: 'Otro bien', condicion_id: 2, condicion: { id: 2, nombre: 'Otra condición' }, mueble: { tipo_mueble: { nombre: 'Silla' }, material: 'Madera', color: null } };
    const monitor = { id: 22, cbi: '0000022', descripcion: 'Monitor', equipo: { monitor: { tamanio_pantalla: '24 pulgadas', detalle_pantalla: null } } };
    const record = { id: 77, bien_id: bien.id, bien, tecnico: null, tipo_mantenimiento_id: 43, tipo_mantenimiento: { nombre: 'Tipo real' }, fecha_mantenimiento: '2026-10-02', descripcion: 'Mantenimiento existente' };
    const requests = [], gets = [];
    const redirects = [];
    let resolveSearch;
    const paged = (data, page = 1, last = 1) => ({ data: { data, current_page: page, last_page: last, total: data.length } });
    const axios = {
        async get(url, options) {
            gets.push({ url, options });
            if (url === '/api/mantenimientos') return paged([record]);
            if (url === '/api/mantenimientos/77') return { data: { data: record } };
            if (url === '/api/bienes') {
                if (deferSearch) { deferSearch = false; return new Promise((resolve) => { resolveSearch = resolve; }); }
                return paged([bien, other, monitor].filter((item) => item.cbi === options?.params?.cbi));
            }
            if (['/api/bienes/20', '/api/bienes/21', '/api/bienes/22'].includes(url)) {
                if (missingDetail) throw { response: { status: 404 } };
                return { data: { data: url.endsWith('/20') ? bien : url.endsWith('/21') ? other : monitor } };
            }
            if (url === '/api/usuarios') return paged([]);
            if (url === '/api/tipos-mantenimiento') return { data: { data: [{ id: 43, nombre: 'Tipo real' }] } };
            if (url === '/api/condiciones-bien') {
                if (conditionsFail) throw { response: { status: 404, data: { message: 'Catálogo no disponible.' } } };
                return { data: { data: [{ id: 1, nombre: 'Condición original' }, { id: 2, nombre: 'Otra condición' }] } };
            }
            if (url === '/sanctum/csrf-cookie') return {};
            throw new Error(`Unexpected GET ${url}`);
        },
        async request(args) { requests.push(args); return { data: { message: 'Guardado' } }; },
    };
    vm.runInNewContext(source, { document: { querySelector: () => root, createElement: () => new Node() }, window: { axios, location: { assign(url) { redirects.push(url); } } }, Option: class extends Node { constructor(label, value) { super(); this.textContent = label; this.value = value; } }, Blob });
    const chooseBien = async (id) => { lookup('[data-bien-options]').value = String(id); await lookup('[data-bien-options]').emit('change'); };
    const search = async (cbi) => { lookup('[data-bien-search]').value = cbi; await lookup('[data-bien-search]').emit('input'); await lookup('[data-bien-search-button]').emit('click'); await flush(); };
    const register = async () => { await flush(); await search('0243831'); await chooseBien(20); form.elements.tipo_mantenimiento_id.value = '43'; form.elements.fecha_mantenimiento.value = '2026-10-02'; form.elements.descripcion.value = 'Prueba'; };
    const submit = async () => { await form.emit('submit'); await flush(); return requests.at(-1); };
    const findAction = (label) => {
        const walk = (node) => node.textContent === label ? node : node.children.map(walk).find(Boolean);
        return walk(lookup('[data-maintenance-rows]'));
    };
    return { lookup, form, requests, gets, redirects, register, submit, chooseBien, findAction, search, resolveOldSearch: () => resolveSearch(paged([bien])) };
}

test('registration shows current condition and sends no change by default or for the same ID', async () => {
    for (const choice of ['', '1']) {
        const ui = setup(); await ui.register();
        assert.equal(ui.lookup('[data-condition-section]').hidden, false);
        assert.match(ui.lookup('[data-current-condition]').textContent, /Condición original/);
        ui.form.elements.condicion_id.value = choice;
        const request = await ui.submit();
        assert.equal(request.method, 'post');
        assert.equal(Object.hasOwn(request.data, 'condicion_id'), false);
    }
});

test('registration sends only a distinct condition ID, without an estado_id', async () => {
    const ui = setup(); await ui.register(); ui.form.elements.condicion_id.value = '2';
    const request = await ui.submit();
    assert.equal(request.data.condicion_id, 2);
    assert.equal(Object.hasOwn(request.data, 'estado_id'), false);
    assert.ok(ui.gets.some(({ url }) => url === '/sanctum/csrf-cookie'));
});

test('a null current condition stays as a dash and can be maintained or changed', async () => {
    for (const choice of ['', '2']) {
        const ui = setup({ conditionId: null }); await ui.register();
        assert.equal(ui.lookup('[data-current-condition]').textContent, 'Condición actual: —');
        ui.form.elements.condicion_id.value = choice;
        const request = await ui.submit();
        assert.equal(Object.hasOwn(request.data, 'condicion_id'), choice !== '');
    }
});

test('new CBI searches keep selection and condition until another good is explicitly chosen', async () => {
    const ui = setup(); await ui.register(); ui.form.elements.condicion_id.value = '2';
    await ui.search('0000021');
    assert.equal(ui.form.elements.condicion_id.value, '2');
    assert.equal(ui.lookup('[data-bien-options]').value, '20');
    assert.equal(ui.gets.filter(({ url }) => url === '/api/bienes').length, 2);
    await ui.chooseBien(21);
    assert.equal(ui.form.elements.condicion_id.value, '');
    assert.match(ui.lookup('[data-current-condition]').textContent, /Otra condición/);
});

test('editing hides/disables condition, does not fetch the catalog or send its ID', async () => {
    const ui = setup({ page: false }); await flush();
    const before = ui.gets.filter(({ url }) => url === '/api/condiciones-bien').length;
    await ui.findAction('Editar').emit('click'); await flush();
    assert.equal(ui.lookup('[data-condition-section]').hidden, true);
    assert.equal(ui.form.elements.condicion_id.disabled, true);
    assert.equal(ui.gets.filter(({ url }) => url === '/api/condiciones-bien').length, before);
    // Even a stale/injected value must never enter an edit payload.
    ui.form.elements.condicion_id.value = '2';
    const request = await ui.submit();
    assert.equal(request.method, 'patch');
    assert.equal(Object.hasOwn(request.data, 'condicion_id'), false);
});

test('global CBI search preserves leading zeros and requires explicit selection and technical detail', async () => {
    const ui = setup(); await flush();
    assert.equal(ui.gets.some(({ url }) => url === '/api/bienes'), false);
    await ui.search('0243831');
    assert.equal(ui.gets.find(({ url }) => url === '/api/bienes').options.params.cbi, '0243831');
    assert.equal(ui.lookup('[data-save-maintenance]').disabled, true);
    await ui.chooseBien(20);
    const fields = Object.fromEntries(ui.lookup('[data-bien-technical-fields]').children.map((group) => group.children.map((node) => node.textContent)));
    assert.equal(fields['Memoria RAM'], '16 GB'); assert.equal(fields.Almacenamiento, '512 GB');
    assert.equal(fields.Modelo, '—'); assert.equal(fields['Número de serie'], '—');
    await ui.search('NO-EXISTE');
    assert.match(ui.lookup('[data-bien-message]').textContent, /No hay bienes/);
    assert.match(ui.lookup('[data-selected-bien]').textContent, /0243831/);
});

test('late CBI results are discarded when the input changes', async () => {
    const ui = setup({ deferSearch: true }); await flush();
    ui.lookup('[data-bien-search]').value = '0243831';
    const pending = ui.lookup('[data-bien-search-button]').emit('click'); await flush();
    ui.lookup('[data-bien-search]').value = ''; await ui.lookup('[data-bien-search]').emit('input');
    ui.resolveOldSearch(); await pending;
    assert.equal(ui.lookup('[data-bien-options]').children.length, 1);
    assert.equal(ui.lookup('[data-save-maintenance]').disabled, true);
});

test('a deleted good cannot be selected and successful creation returns to the list', async () => {
    const missing = setup({ missingDetail: true }); await missing.register();
    assert.equal(missing.lookup('[data-save-maintenance]').disabled, true);
    assert.match(missing.lookup('[data-bien-message]').textContent, /no existe/);
    const ui = setup(); await ui.register(); await ui.submit();
    assert.deepEqual(ui.redirects, ['/mantenimientos?registrado=1']);
});

test('technical cards adapt to furniture and monitors without retaining previous computer data', async () => {
    const ui = setup(); await ui.register(); await ui.search('0000021'); await ui.chooseBien(21);
    const fields = () => Object.fromEntries(ui.lookup('[data-bien-technical-fields]').children.map((group) => group.children.map((node) => node.textContent)));
    assert.equal(fields().Material, 'Madera'); assert.equal(fields().Color, '—');
    assert.equal(Object.hasOwn(fields(), 'Memoria RAM'), false);
    await ui.search('0000022'); await ui.chooseBien(22);
    assert.equal(fields()['Tamaño de pantalla'], '24 pulgadas');
    assert.equal(fields()['Detalle de pantalla'], '—'); assert.equal(Object.hasOwn(fields(), 'Material'), false);
});

test('editing retains an already assigned good without a CBI and does not run an unfiltered search', async () => {
    const ui = setup({ page: false, currentCbi: null }); await flush();
    await ui.findAction('Editar').emit('click'); await flush();
    assert.match(ui.lookup('[data-selected-bien]').textContent, /ID 20.*CBI —/);
    assert.equal(ui.lookup('[data-bien-options]').value, '20');
    assert.equal(ui.gets.some(({ url }) => url === '/api/bienes'), false);
    assert.equal((await ui.submit()).data.bien_id, 20);
});

test('a catalog error allows registration while preserving the current condition', async () => {
    const ui = setup({ conditionsFail: true }); await ui.register();
    assert.equal(ui.form.elements.condicion_id.disabled, true);
    assert.match(ui.lookup('[data-condition-message]').textContent, /Catálogo no disponible/);
    assert.equal(Object.hasOwn((await ui.submit()).data, 'condicion_id'), false);
});
