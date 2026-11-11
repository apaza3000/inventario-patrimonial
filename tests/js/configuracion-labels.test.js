import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/configuracion.js', import.meta.url), 'utf8');

// Exercise the actual field builder and role-dependent presentation with DOM doubles.
// No Axios requests, server or database are involved.
function setup() {
    const controls = new Map();
    const container = { children: [], append(child) { this.children.push(child); } };
    const element = (tag, text, parent, className = '') => {
        const node = { tag, textContent: text, className, children: [], attributes: {},
            append(child) { this.children.push(child); },
            setAttribute(key, value) { this.attributes[key] = value; } };
        parent?.append(node);
        return node;
    };
    const context = vm.createContext({ controls, element, find: () => container,
        document: { createElement: (tag) => element(tag) },
        reserved: [], config: { dataset: {} }, editKey: 'usuarios', editorState() {} });
    vm.runInContext(source.slice(source.indexOf('    const buildField ='), source.indexOf('    const fillSelect ='))
        + source.slice(source.indexOf('    const syncSpecialty ='), source.indexOf('    const openEditor ='))
        + '\nthis.buildField = buildField; this.syncSpecialty = syncSpecialty;', context);
    return { ...context, controls };
}

test('mandatory and optional fields retain their validation and group the red marker with the title', () => {
    const { buildField, controls } = setup();
    for (const required of [true, false]) {
        const { input, label } = buildField({ key: 'nombre', label: 'Nombre', type: 'text', required }, null, 'sedes');
        const title = label.children[0];
        assert.equal(title.className, 'form-field-label');
        assert.equal(title.textContent, 'Nombre');
        assert.equal(title.children[0], controls.get('nombre').requiredMarker);
        assert.equal(title.children[0].attributes['aria-hidden'], 'true');
        assert.equal(title.children[0].hidden, !required);
        assert.equal(input.required, required);
    }
});

test('password indicator is required for registration and optional in editing', () => {
    const { buildField, controls } = setup();
    for (const item of [null, { id: 5 }]) {
        const { input } = buildField({ key: 'password', label: 'Contraseña', type: 'password' }, item, 'usuarios');
        assert.equal(input.required, !item);
        assert.equal(controls.get('password').requiredMarker.hidden, Boolean(item));
    }
});

test('specialty indicator follows the existing coordinator validation without affecting other roles', () => {
    const { buildField, syncSpecialty, controls } = setup();
    const { input: role } = buildField({ key: 'rol_id', label: 'Rol', type: 'select', required: true }, null, 'usuarios');
    buildField({ key: 'especialidad_id', label: 'Especialidad', type: 'select' }, null, 'usuarios');
    for (const name of ['coordinador', 'director', 'coordinador', 'superadmin']) {
        role.selectedOptions = [{ textContent: name }];
        syncSpecialty();
        const specialty = controls.get('especialidad_id');
        assert.equal(specialty.input.required, name === 'coordinador');
        assert.equal(specialty.requiredMarker.hidden, name !== 'coordinador');
    }
});
