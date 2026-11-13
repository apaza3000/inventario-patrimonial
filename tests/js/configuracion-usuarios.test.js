import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/configuracion.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 12; i++) await new Promise(setImmediate); };
class Element {
    constructor(tag = 'div') { this.tag = tag; this.children = []; this.listeners = {}; this.dataset = {}; this.attributes = {}; this._value = ''; }
    get value() { return this._value; }
    set value(value) { this._value = String(value); }
    get options() { return this.children; }
    get selectedOptions() { return this.options.filter((option) => option.value === this.value); }
    append(...items) { this.children.push(...items); }
    add(item) { this.append(item); }
    replaceChildren(...items) { this.children = [...items]; this.value = ''; }
    addEventListener(name, handler) { (this.listeners[name] ??= []).push(handler); }
    async emit(name) { for (const handler of this.listeners[name] ?? []) await handler({ preventDefault() {} }); }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    querySelectorAll() { return []; }
    reset() {}
    focus() {}
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
}
const walk = (node) => [node, ...node.children.flatMap(walk)];
function setup({ role = 'asistente', specialty = 2, writeFail = false } = {}) {
    const nodes = new Map();
    const find = (selector) => { if (!nodes.has(selector)) nodes.set(selector, new Element()); return nodes.get(selector); };
    const root = new Element(); root.querySelector = find;
    root.dataset = { apiUrl: '/api', csrfUrl: '/sanctum/csrf-cookie', loginUrl: '/login', userId: '1' };
    const module = new Element('button'); module.dataset.configModule = 'usuarios';
    root.querySelectorAll = (selector) => selector === '[data-config-module]' ? [module] : [];
    const roles = [{ id: 1, nombre: 'superadmin' }, { id: 2, nombre: 'administrador' }, { id: 3, nombre: 'director' }, { id: 4, nombre: 'coordinador' }, { id: 5, nombre: 'asistente' }];
    const specialties = [1, 2, 3, 4, 5].map((id) => ({ id, nombre: `Especialidad ${id}` }));
    const currentRole = roles.find((r) => r.nombre === role);
    const record = { id: 20, nombres: 'Usuario', apellidos: 'Simulado', correo: 'fixture@example.test', rol_id: currentRole.id, rol: currentRole, especialidad_id: specialty, especialidad: specialties.find((item) => item.id === specialty) ?? null, activo: true };
    const form = find('[data-form]');
    const fields = () => walk(find('[data-form-fields]')).filter((node) => ['input', 'select'].includes(node.tag));
    const field = (name) => fields().find((node) => node.name === name);
    form.reportValidity = () => fields().every((node) => node.disabled || !node.required || Boolean(node.value));
    const gets = [], writes = [];
    const axios = {
        async get(url, config) {
            gets.push({ url, config });
            if (url === '/api/usuarios') return { data: { data: [record], current_page: 1, last_page: 1, total: 1 } };
            if (url === '/api/usuarios/20') return { data: { data: record } };
            if (url === '/api/roles') {
                const page = config.params.page;
                return { data: { data: page === 1 ? roles.slice(0, 4) : roles.slice(4), current_page: page, last_page: 2 } };
            }
            if (url === '/api/especialidades') return { data: { data: specialties } };
            if (url === '/sanctum/csrf-cookie') return {};
            throw new Error(`Unexpected GET ${url}`);
        },
        async request(args) {
            writes.push(JSON.parse(JSON.stringify(args)));
            if (writeFail) throw { response: { status: 422, data: { errors: { especialidad_id: ['La especialidad seleccionada no corresponde a una carrera academica.'] } } } };
            return { data: { message: 'Guardado' } };
        },
    };
    vm.runInNewContext(source, { document: { querySelector: () => root, createElement: (tag) => new Element(tag) }, window: { axios },
        Option: class extends Element { constructor(label, value) { super('option'); this.textContent = label; this.value = value; } } });
    const action = (label, container = find('[data-rows]')) => walk(container).find((node) => node.tag === 'button' && node.textContent === label);
    const open = async (edit = false) => { await module.emit('click'); await flush(); await (edit ? action('Editar') : find('[data-create]')).emit('click'); await flush(); };
    return { find, field, action, open, form, gets, writes };
}

test('registration selects assistant from paginated roles, requires a real career and sends its ID', async () => {
    const s = setup(); await s.open();
    assert.equal(s.field('especialidad_id').disabled, true);
    await s.action('Roles siguientes', s.find('[data-form-fields]')).emit('click'); await flush();
    assert.deepEqual(s.gets.filter(({ url }) => url === '/api/roles').map(({ config }) => config.params.page), [1, 2]);
    s.field('rol_id').value = '5'; await s.field('rol_id').emit('change');
    assert.equal(s.field('especialidad_id').required, true);
    assert.equal(s.field('especialidad_id').disabled, false);
    assert.deepEqual(s.field('especialidad_id').options.map((option) => option.value), ['', '1', '2', '3']);
    for (const [key, value] of Object.entries({ nombres: 'Nuevo', apellidos: 'Asistente', correo: 'new@example.test', password: 'Test-password-123!' })) s.field(key).value = value;
    await s.form.emit('submit'); assert.equal(s.writes.length, 0);
    s.field('especialidad_id').value = '3'; await s.form.emit('submit'); await flush();
    assert.equal(s.writes[0].method, 'post');
    assert.equal(s.writes[0].data.rol_id, 5);
    assert.equal(s.writes[0].data.especialidad_id, 3);
    assert.ok(s.gets.some(({ url }) => url === '/sanctum/csrf-cookie'));
});

test('editing preserves the saved specialty and off-page role and omits an unchanged password', async () => {
    for (const role of ['asistente', 'coordinador']) {
        const s = setup({ role }); await s.open(true);
        assert.equal(s.field('especialidad_id').value, '2');
        assert.equal(s.field('especialidad_id').required, true);
        assert.equal(s.field('especialidad_id').disabled, false);
        assert.equal(s.field('password').required, false);
        await s.action('Roles siguientes', s.find('[data-form-fields]')).emit('click'); await flush();
        assert.equal(s.field('especialidad_id').value, '2');
        s.field('nombres').value = 'Nombre actualizado'; await s.form.emit('submit'); await flush();
        assert.equal(s.writes[0].method, 'patch');
        assert.equal(s.writes[0].url, '/api/usuarios/20');
        assert.equal(s.writes[0].data.especialidad_id, 2);
        assert.equal(Object.hasOwn(s.writes[0].data, 'password'), false);
    }
});

test('switching to a role without specialty keeps the existing clearing behavior', async () => {
    const s = setup(); await s.open(true);
    s.field('rol_id').value = '3'; await s.field('rol_id').emit('change');
    assert.equal(s.field('especialidad_id').required, false);
    assert.equal(s.field('especialidad_id').disabled, true);
    assert.equal(s.field('especialidad_id').value, '');
    await s.form.emit('submit'); await flush();
    assert.equal(s.writes[0].data.rol_id, 3);
    assert.equal(s.writes[0].data.especialidad_id, null);
});

test('server validation errors leave the form, chosen specialty and blank password intact', async () => {
    const s = setup({ writeFail: true }); await s.open(true);
    s.field('especialidad_id').value = '3'; await s.form.emit('submit');
    assert.equal(s.find('[data-form-dialog]').open, true);
    assert.equal(s.field('especialidad_id').value, '3');
    assert.equal(s.field('password').value, '');
    assert.match(s.find('[data-form-message]').textContent, /especialidad.*carrera/);
});
