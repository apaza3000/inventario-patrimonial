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
    async emit(name) { for (const handler of this.listeners[name] ?? []) await handler({ preventDefault() {} }); }
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

function setup({ conditionId = 1, conditionsFail = false } = {}) {
    const nodes = new Map();
    const lookup = (selector) => {
        if (!nodes.has(selector)) { const node = new Node(); node.lookup = lookup; nodes.set(selector, node); }
        return nodes.get(selector);
    };
    const root = new Node(); root.lookup = lookup;
    root.dataset = { maintenanceUrl: '/api/mantenimientos', bienesUrl: '/api/bienes', usersUrl: '/api/usuarios', typesUrl: '/api/tipos-mantenimiento', conditionsUrl: '/api/condiciones-bien', csrfUrl: '/sanctum/csrf-cookie', loginUrl: '/login' };
    const formDialog = lookup('[data-maintenance-form-dialog]');
    const detailDialog = lookup('[data-maintenance-detail-dialog]');
    const deleteDialog = lookup('[data-maintenance-delete-dialog]');
    root.all = (selector) => selector === 'dialog' ? [formDialog, detailDialog, deleteDialog] : [];
    const form = lookup('[data-maintenance-form]');
    form.elements = Object.fromEntries(['tipo_mantenimiento_id', 'condicion_id', 'fecha_mantenimiento', 'descripcion', 'diagnostico', 'trabajo_realizado', 'observaciones'].map((key) => [key, new Node()]));
    form.all = (selector) => selector === 'textarea, input[type="date"]' ? ['fecha_mantenimiento', 'descripcion', 'diagnostico', 'trabajo_realizado', 'observaciones'].map((key) => form.elements[key]) : [];
    for (const prefix of ['bien', 'user']) lookup(`[data-${prefix}-search]`).dialog = formDialog;
    const bien = { id: 20, cbi: 'PRUEBA', descripcion: 'Bien de prueba', condicion_id: conditionId, condicion: conditionId === null ? null : { id: 1, nombre: 'Condición original' } };
    const other = { id: 21, descripcion: 'Otro bien', condicion_id: 2, condicion: { id: 2, nombre: 'Otra condición' } };
    const record = { id: 77, bien_id: bien.id, bien, tecnico: null, tipo_mantenimiento_id: 43, tipo_mantenimiento: { nombre: 'Tipo real' }, fecha_mantenimiento: '2026-10-02', descripcion: 'Mantenimiento existente' };
    const requests = [], gets = [];
    const paged = (data, page = 1, last = 1) => ({ data: { data, current_page: page, last_page: last, total: data.length } });
    const axios = {
        async get(url, options) {
            gets.push({ url, options });
            if (url === '/api/mantenimientos') return paged([record]);
            if (url === '/api/mantenimientos/77') return { data: { data: record } };
            if (url === '/api/bienes') return paged(options?.params?.page === 2 ? [other] : [bien, other], options?.params?.page ?? 1, 2);
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
    vm.runInNewContext(source, { document: { querySelector: () => root, createElement: () => new Node() }, window: { axios, location: { assign() {} } }, Option: class extends Node { constructor(label, value) { super(); this.textContent = label; this.value = value; } }, Blob });
    const chooseBien = async (id) => { lookup('[data-bien-options]').value = String(id); await lookup('[data-bien-options]').emit('change'); };
    const register = async () => { await flush(); await lookup('[data-new-maintenance]').emit('click'); await flush(); await chooseBien(20); form.elements.tipo_mantenimiento_id.value = '43'; form.elements.fecha_mantenimiento.value = '2026-10-02'; form.elements.descripcion.value = 'Prueba'; };
    const submit = async () => { await form.emit('submit'); await flush(); return requests.at(-1); };
    const findAction = (label) => {
        const walk = (node) => node.textContent === label ? node : node.children.map(walk).find(Boolean);
        return walk(lookup('[data-maintenance-rows]'));
    };
    return { lookup, form, requests, gets, register, submit, chooseBien, findAction };
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

test('pagination keeps the selected good and condition; changing the good resets the choice', async () => {
    const ui = setup(); await ui.register(); ui.form.elements.condicion_id.value = '2';
    await ui.lookup('[data-bien-next]').emit('click'); await flush();
    assert.equal(ui.form.elements.condicion_id.value, '2');
    assert.equal(ui.lookup('[data-bien-options]').value, '20');
    assert.equal(ui.gets.filter(({ url }) => url === '/api/bienes').length, 2);
    await ui.chooseBien(21);
    assert.equal(ui.form.elements.condicion_id.value, '');
    assert.match(ui.lookup('[data-current-condition]').textContent, /Otra condición/);
});

test('editing after registration hides/disables condition, does not fetch the catalog or send its ID', async () => {
    const ui = setup(); await ui.register(); ui.form.elements.condicion_id.value = '2';
    ui.lookup('[data-maintenance-form-dialog]').close(); await flush();
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

test('a catalog error allows registration while preserving the current condition', async () => {
    const ui = setup({ conditionsFail: true }); await ui.register();
    assert.equal(ui.form.elements.condicion_id.disabled, true);
    assert.match(ui.lookup('[data-condition-message]').textContent, /Catálogo no disponible/);
    assert.equal(Object.hasOwn((await ui.submit()).data, 'condicion_id'), false);
});
