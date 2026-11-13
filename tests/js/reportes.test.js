import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/reportes.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };
class Element {
    constructor() { this.value = ''; this.checked = false; this.listeners = {}; this.options = []; this.attributes = {}; this.dataset = {}; }
    addEventListener(name, callback) { (this.listeners[name] ??= []).push(callback); }
    async emit(name) { for (const callback of this.listeners[name] ?? []) await callback({ preventDefault() {} }); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    replaceChildren(...items) { this.options = items; this.value = ''; }
    add(item) { this.options.push(item); }
    reportValidity() { return true; }
}
function setup({ total = 17, pending = false, optionsFail = false, summaryFail = null, downloadFail = null } = {}) {
    const cards = ['inventario', 'equipos', 'mantenimientos'].map((type) => {
        const nodes = new Map();
        const find = (selector) => { if (!nodes.has(selector)) nodes.set(selector, new Element()); return nodes.get(selector); };
        const card = new Element(); card.querySelector = find;
        card.dataset = { optionsUrl: `/api/reportes/${type}/opciones`, summaryUrl: `/api/reportes/${type}/resumen` };
        const form = find('[data-report-form]');
        const names = ['q', 'sede_id', 'ambiente_id', 'especialidad_id', 'categoria', 'tipo_equipo_id', 'estado_id', 'condicion_id', ...(type === 'inventario' ? ['anio'] : type === 'mantenimientos' ? ['desde', 'hasta', 'tipo_mantenimiento_id'] : [])];
        form.elements = Object.fromEntries(names.map((name) => { const field = new Element(); field.name = name; return [name, field]; }));
        form.reset = () => { Object.values(form.elements).forEach((field) => { field.value = ''; }); find('[data-report-complete]').checked = false; };
        find('[data-report-filters]').querySelectorAll = () => Object.values(form.elements);
        const downloads = ['pdf', 'excel'].map((format) => {
            const button = new Element(); button.dataset = { reportUrl: `/api/reportes/${type}/${format}`, reportFilename: `reporte-${type}.${format === 'pdf' ? 'pdf' : 'xlsx'}` }; return button;
        });
        card.querySelectorAll = () => downloads;
        return { card, find, fields: form.elements, form, downloads };
    });
    const root = new Element(); root.dataset.loginUrl = '/login'; root.querySelectorAll = () => cards.map(({ card }) => card);
    const calls = [], downloaded = [], redirects = [], resolutions = [];
    const options = { sedes: [{ id: 1, nombre: 'Sede 1' }, { id: 2, nombre: 'Sede 2' }], ambientes: [{ id: 10, nombre: 'Lab 1', sede_id: 1 }, { id: 20, nombre: 'Lab 2', sede_id: 2 }], especialidades: [{ id: 1, nombre: 'Carrera' }], estados: [{ id: 1, nombre: 'Estado' }], condiciones: [{ id: 2, nombre: 'Condicion' }], tipos_equipo: [{ id: 7, nombre: 'CPU' }], anios: [2025, 2026], tipos_mantenimiento: [{ id: 9, nombre: 'Tipo real' }] };
    const axios = { async get(url, config) {
        calls.push({ url, config: config && JSON.parse(JSON.stringify(config)) });
        if (url.endsWith('/opciones')) {
            if (optionsFail) { optionsFail = false; throw { response: { status: 500 } }; }
            return { data: { data: options } };
        }
        if (url.endsWith('/resumen')) {
            if (summaryFail) throw summaryFail;
            const response = { data: { data: { total } } };
            return pending ? new Promise((resolve) => resolutions.push(() => resolve(response))) : response;
        }
        if (downloadFail) throw downloadFail;
        return { data: new Blob(['mock export']) };
    } };
    vm.runInNewContext(source, {
        document: { querySelector: () => root, body: { append() {} }, createElement: () => ({ click() { downloaded.push(this.download); }, remove() {} }) },
        window: { axios, location: { assign: (url) => redirects.push(url) }, setTimeout: (callback) => callback() },
        URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} }, Blob,
        Option: class { constructor(label, value) { this.textContent = label; this.value = value; } },
    });
    const first = cards[0];
    return { ...first, cards, calls, downloaded, redirects,
        open: async () => { first.find('[data-report-panel]').open = true; await first.find('[data-report-panel]').emit('toggle'); await flush(); },
        resolve: () => resolutions.shift()(),
    };
}

test('report filters load only on opening and downloads require explicit criteria and a checked count', async () => {
    const s = setup();
    assert.equal(s.calls.length, 0);
    assert.ok(s.downloads.every((button) => button.disabled));
    await s.open();
    assert.equal(s.calls.length, 1);
    assert.equal(s.fields.anio.options[1].value, '2025');
    await s.form.emit('submit');
    assert.equal(s.calls.length, 1);
    assert.match(s.find('[data-report-status]').textContent, /Selecciona al menos un filtro/);
    s.fields.q.value = '02'; s.fields.anio.value = '2026'; s.fields.condicion_id.value = '2';
    await s.form.emit('submit');
    assert.deepEqual(s.calls.at(-1).config.params, { q: '02', condicion_id: '2', anio: '2026' });
    assert.match(s.find('[data-report-total]').textContent, /17 registros/);
    for (const button of s.downloads) await button.emit('click');
    const exports = s.calls.filter(({ config }) => config?.responseType === 'blob');
    assert.equal(exports.length, 2);
    assert.deepEqual(exports[0].config.params, exports[1].config.params);
    assert.equal(exports[0].config.params.q, '02');
    assert.deepEqual(s.downloaded, ['reporte-inventario.pdf', 'reporte-inventario.xlsx']);
});

test('site limits environment options and changing criteria or clearing invalidates old download counts', async () => {
    const s = setup(); await s.open();
    s.fields.ambiente_id.value = '20'; s.fields.sede_id.value = '1'; await s.fields.sede_id.emit('change');
    assert.deepEqual(s.fields.ambiente_id.options.map((item) => item.value), ['', '10']);
    assert.equal(s.fields.ambiente_id.value, '');
    await s.form.emit('submit');
    assert.equal(s.downloads[0].disabled, false);
    s.fields.q.value = '00002'; await s.fields.q.emit('input');
    assert.equal(s.downloads[0].disabled, true);
    await s.downloads[0].emit('click'); assert.equal(s.downloaded.length, 0);
    await s.find('[data-report-clear]').emit('click');
    assert.equal(s.fields.q.value, ''); assert.equal(s.fields.sede_id.value, '');
    assert.equal(s.fields.ambiente_id.options.length, 3);
    assert.equal(s.downloads[0].disabled, true);
});

test('full reports are explicitly requested and do not silently retain hidden filter values', async () => {
    const s = setup(); await s.open(); s.fields.q.value = '0000001';
    s.find('[data-report-complete]').checked = true; await s.find('[data-report-complete]').emit('change');
    assert.equal(s.find('[data-report-filters]').disabled, true);
    await s.form.emit('submit'); await s.downloads[0].emit('click');
    assert.deepEqual(s.calls.at(-1).config.params, { completo: 1 });
    await s.find('[data-report-clear]').emit('click');
    assert.equal(s.find('[data-report-complete]').checked, false);
    assert.equal(s.find('[data-report-filters]').disabled, false);
});

test('empty results cannot download files and late count responses cannot enable outdated criteria', async () => {
    const empty = setup({ total: 0 }); await empty.open(); empty.fields.q.value = 'no existe'; await empty.form.emit('submit');
    assert.match(empty.find('[data-report-total]').textContent, /No hay registros/);
    await empty.downloads[0].emit('click'); assert.equal(empty.downloaded.length, 0);
    const s = setup({ pending: true }); await s.open(); s.fields.q.value = '02';
    const pending = s.form.emit('submit'); await flush();
    s.fields.q.value = '03'; await s.fields.q.emit('input'); s.resolve(); await pending;
    assert.equal(s.downloads[0].disabled, true);
    assert.doesNotMatch(s.find('[data-report-total]').textContent, /17 registros/);
});

test('validation errors from blob downloads are readable and require a new count', async () => {
    const error = { response: { status: 422, data: new Blob([JSON.stringify({ errors: { reporte: ['No hay registros; no se genero archivo.'] } })]) } };
    const s = setup({ downloadFail: error }); await s.open(); s.fields.q.value = '02'; await s.form.emit('submit');
    await s.downloads[0].emit('click');
    assert.match(s.find('[data-report-status]').textContent, /No hay registros/);
    assert.equal(s.downloads[0].disabled, true); assert.equal(s.downloaded.length, 0);
});

test('catalog retries and expired sessions are handled without exposing other report cards', async () => {
    const s = setup({ optionsFail: true }); await s.open();
    assert.equal(s.find('[data-report-options-retry]').hidden, false);
    await s.find('[data-report-options-retry]').emit('click');
    assert.equal(s.find('[data-report-count]').disabled, false);
    assert.equal(s.cards[1].find('[data-report-count]').disabled, true);
    const expired = setup({ summaryFail: { response: { status: 401 } } }); await expired.open(); expired.fields.q.value = '02'; await expired.form.emit('submit');
    assert.deepEqual(expired.redirects, ['/login']);
    assert.equal(expired.downloads[0].disabled, true);
});
