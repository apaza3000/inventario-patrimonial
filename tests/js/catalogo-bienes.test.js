import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const detailSource = readFileSync(new URL('../../resources/js/bien-detail.js', import.meta.url), 'utf8').replace('export function', 'function');
const source = detailSource + readFileSync(new URL('../../resources/js/catalogo-bienes.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
const flush = async () => { for (let i = 0; i < 10; i++) await new Promise(setImmediate); };
class Element {
    constructor(tag = '') {
        this.tag = tag; this.children = []; this.listeners = {}; this.dataset = {}; this.options = []; this.elements = {};
        this.value = ''; this.textContent = ''; this.classList = { toggle() {} };
    }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    emit(name, event = {}) { return this.listeners[name]?.({ preventDefault() {}, ...event }); }
    setAttribute(key, value) { this[key] = value; }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = [...nodes]; this.options = [...nodes]; this.value = nodes[0]?.value ?? ''; }
    add(option) { this.options.push(option); }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    focus() {}
    reportValidity() { return true; }
    reset() { Object.values(this.elements).forEach((field) => { field.value = ''; }); this.elements.activo.value = '1'; }
}

function setup({ failList = false, failSave = false, deferFirstList = false } = {}) {
    const nodes = new Map();
    const find = (selector) => {
        if (!nodes.has(selector)) nodes.set(selector, new Element());
        return nodes.get(selector);
    };
    const form = find('[data-bien-form]');
    const groups = {
        equipo: ['tipo_equipo_id', 'marca_id', 'modelo', 'serie'],
        computadoras: ['procesador', 'ram', 'almacenamiento', 'detalle_adicional'],
        monitores: ['tamano_pantalla', 'detalle_pantalla'], mobiliario: ['tipo_mueble_id', 'material', 'color'],
    };
    for (const name of ['categoria', 'descripcion', 'cbi', 'estado_id', 'condicion_id', 'ambiente_id', 'activo', 'fecha_registro', 'observaciones', ...Object.values(groups).flat()]) {
        form.elements[name] = new Element(); form.elements[name].name = name;
    }
    form.elements.activo.value = '1';
    const sections = Object.entries(groups).map(([key, fields]) => {
        const section = new Element(); section.dataset.categoryFields = key;
        section.querySelectorAll = () => fields.map((key) => form.elements[key]);
        return section;
    });
    const categories = ['todos', 'computadoras', 'monitores', 'mobiliario', 'otros-equipos'].map((key) => {
        const button = new Element(); button.dataset.catalogCategory = key; return button;
    });
    const catalog = new Element(); catalog.querySelector = find;
    catalog.querySelectorAll = (selector) => selector === '[data-category-fields]' ? sections : selector === '[data-catalog-category]' ? categories : [];
    catalog.dataset = { bienesUrl: '/api/bienes', optionsUrl: '/api/bienes/opciones', sedesUrl: '/api/sedes', ambientesUrl: '/api/ambientes', estadosUrl: '/api/estados-bien', condicionesUrl: '/api/condiciones-bien', csrfUrl: '/sanctum/csrf-cookie' };
    const gets = [], posts = [];
    let resolveFirstList;
    let detail = { id: 1, descripcion: 'Bien simulado', activo: true };
    const axios = {
        async get(url, config) {
            gets.push({ url, params: config?.params });
            if (url === '/api/bienes') {
                if (deferFirstList) {
                    deferFirstList = false;
                    return new Promise((resolve) => { resolveFirstList = resolve; });
                }
                if (failList) { failList = false; throw { response: { status: 500 } }; }
                return { data: { data: [{ id: 1, cbi: '0001', descripcion: 'Bien simulado', activo: true }], current_page: config.params.page, last_page: 2, total: 16, per_page: 15 } };
            }
            if (url === '/api/bienes/opciones') return { data: { data: {
                marcas: [{ id: 17, nombre: 'Marca real del catálogo' }], tipos_mueble: [{ id: 33, nombre: 'Silla' }],
                tipos_equipo: [{ id: 41, nombre: 'CPU', categoria: 'computadoras' }, { id: 57, nombre: 'LAPTOP', categoria: 'computadoras' }, { id: 83, nombre: 'MONITOR', categoria: 'monitores' }, { id: 92, nombre: 'EQUIPO', categoria: 'otros-equipos' }],
            } } };
            if (url === '/api/bienes/1') return { data: { data: detail } };
            return { data: { data: [{ id: 1, nombre: 'Opción' }] } };
        },
        async post(url, payload) {
            posts.push({ url, payload: JSON.parse(JSON.stringify(payload)) });
            if (failSave) throw { response: { status: 422, data: { errors: { tipo_equipo_id: ['Tipo incompatible.'] } } } };
            return { data: { data: { id: 99 } } };
        },
    };
    vm.runInNewContext(source, { document: { querySelector: () => catalog, createElement: (tag) => new Element(tag) }, window: { axios }, Date, Option: class { constructor(text, value) { this.textContent = text; this.value = value; } } });
    const category = async (key) => { categories.find((button) => button.dataset.catalogCategory === key).emit('click'); await flush(); };
    const open = async () => { find('[data-open-bien-dialog]').emit('click'); await flush(); };
    const technicalCategory = (key) => { form.elements.categoria.value = key; form.elements.categoria.emit('change'); };
    const show = async (record) => {
        detail = record;
        find('[data-catalog-rows]').emit('click', { target: { closest: () => ({ dataset: { viewBien: '1' } }) } }); await flush();
    };
    return { find, form, sections, categories, gets, posts, category, technicalCategory, open, show, resolveOldList: () => resolveFirstList({ data: { data: [], current_page: 1, last_page: 1, total: 999, per_page: 15 } }) };
}

test('category tabs request server pagination and reset the page without downloading the catalog', async () => {
    const s = setup(); await flush();
    await s.find('[data-catalog-next]').emit('click'); await flush();
    await s.category('mobiliario');
    const requests = s.gets.filter((item) => item.url === '/api/bienes');
    assert.deepEqual(requests.map((item) => JSON.parse(JSON.stringify(item.params))), [
        { page: 1, categoria: 'todos' }, { page: 2, categoria: 'todos' }, { page: 1, categoria: 'mobiliario' },
    ]);
    assert.equal(s.categories[3]['aria-pressed'], 'true');
    assert.equal(s.categories[1]['aria-pressed'], 'false');
});

test('registration loads actual option IDs, switches technical fields and omits hidden data', async () => {
    const s = setup(); await flush(); await s.category('computadoras'); await s.open();
    assert.equal(s.form.elements.categoria.value, 'computadoras');
    assert.deepEqual(s.form.elements.tipo_equipo_id.options.map((item) => item.value), ['', '41', '57']);
    assert.deepEqual(s.form.elements.marca_id.options.map((item) => item.value), ['', '17']);
    s.form.elements.ram.value = '16 GB'; s.form.elements.tipo_equipo_id.value = '41';
    s.technicalCategory('mobiliario');
    assert.equal(s.sections[0].disabled, true); assert.equal(s.sections[3].disabled, false);
    s.form.elements.descripcion.value = 'Silla de prueba'; s.form.elements.tipo_mueble_id.value = '33'; s.form.elements.material.value = 'Madera';
    await s.form.emit('submit'); await flush();
    assert.equal(s.posts.length, 1);
    assert.equal(s.posts[0].payload.tipo_mueble_id, 33);
    assert.equal(s.posts[0].payload.material, 'Madera');
    assert.ok(!('ram' in s.posts[0].payload)); assert.ok(!('tipo_equipo_id' in s.posts[0].payload));
    assert.ok(s.gets.some((item) => item.url === '/sanctum/csrf-cookie'));
});

test('computer and monitor forms submit the existing technical field names', async () => {
    for (const [category, type, values] of [['computadoras', '41', { ram: '8 GB', almacenamiento: '256 GB' }], ['monitores', '83', { tamano_pantalla: '24 pulgadas' }]]) {
        const s = setup(); await flush(); await s.category(category); await s.open();
        s.form.elements.descripcion.value = 'Prueba'; s.form.elements.tipo_equipo_id.value = type;
        for (const [key, value] of Object.entries(values)) s.form.elements[key].value = value;
        await s.form.emit('submit');
        assert.equal(s.posts[0].payload.tipo_equipo_id, Number(type));
        for (const [key, value] of Object.entries(values)) assert.equal(s.posts[0].payload[key], value);
    }
});

test('detail shows technical data by relation and dashes for missing or empty values', async () => {
    const s = setup(); await flush();
    await s.show({ id: 1, equipo: { marca: { nombre: 'Marca' }, modelo: null, serie: '', equipo_computo: { procesador: 'CPU', ram: '16 GB', almacenamiento: '512 GB' } } });
    const technical = () => Object.fromEntries(s.find('[data-technical-fields]').children.map((group) => group.children.map((item) => item.textContent)));
    assert.equal(technical()['Marca'], 'Marca'); assert.equal(technical()['Modelo'], '—'); assert.equal(technical()['Número de serie'], '—');
    assert.equal(technical()['Memoria RAM'], '16 GB'); assert.equal(technical()['Almacenamiento'], '512 GB');
    await s.show({ id: 1, mueble: { tipo_mueble: { nombre: 'Silla' }, material: 'Madera', color: null } });
    assert.deepEqual(technical(), { 'Tipo de mueble': 'Silla', Material: 'Madera', Color: '—' });
    await s.show({ id: 1, equipo: { monitor: { tamanio_pantalla: '24 pulgadas', detalle_pantalla: null } } });
    assert.equal(technical()['Tamaño de pantalla'], '24 pulgadas');
    await s.show({ id: 1 });
    assert.equal(s.find('[data-technical-empty]').hidden, false);
    assert.equal(s.find('[data-technical-fields]').children.length, 0);
});

test('failed category loads clear stale rows and registration errors retain the form', async () => {
    const s = setup({ failList: true, failSave: true }); await flush();
    assert.equal(s.find('[data-catalog-rows]').children.length, 0);
    await s.category('otros-equipos'); await s.open();
    s.form.elements.descripcion.value = 'Prueba'; s.form.elements.tipo_equipo_id.value = '92';
    await s.form.emit('submit');
    assert.equal(s.find('[data-bien-dialog]').open, true);
    assert.equal(s.find('[data-bien-form-message]').textContent, 'Tipo incompatible.');
    assert.equal(s.find('[data-save-bien]').disabled, false);
});

test('a late previous list response cannot overwrite the category loaded after registration', async () => {
    const s = setup({ deferFirstList: true }); await s.open();
    s.technicalCategory('mobiliario'); s.form.elements.descripcion.value = 'Silla'; s.form.elements.tipo_mueble_id.value = '33';
    await s.form.emit('submit');
    assert.equal(s.categories[3]['aria-pressed'], 'true');
    s.resolveOldList(); await flush();
    assert.match(s.find('[data-catalog-count]').textContent, /^16 /);
    assert.equal(s.find('[data-catalog-rows]').children.length, 1);
});
