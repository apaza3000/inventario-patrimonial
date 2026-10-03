import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/movimientos.js', import.meta.url), 'utf8');
const flush = async () => { for (let i = 0; i < 8; i++) await new Promise(setImmediate); };

class Element {
    constructor() { this.listeners = {}; this.elements = {}; this.value = ''; this.dataset = {}; this.options = []; }
    addEventListener(name, callback) { this.listeners[name] = callback; }
    emit(name) { return this.listeners[name]?.({ preventDefault() {}, currentTarget: this }); }
    querySelectorAll() { return []; }
    showModal() { this.open = true; }
    close() { this.open = false; this.emit('close'); }
    reset() { Object.values(this.elements).forEach((item) => { item.value = ''; }); }
    reportValidity() { return true; }
    replaceChildren(...options) { this.options = [...options]; }
    add(option) { this.options.push(option); }
}

function setup(instant = '2026-10-02T15:00:00Z', signature = '2026-10-02T16:30:00Z') {
    const nodes = new Map();
    const find = (selector) => {
        if (selector === '[data-movements-list]') return null;
        if (!nodes.has(selector)) {
            const node = new Element(); node.querySelector = find;
            nodes.set(selector, node);
        }
        return nodes.get(selector);
    };
    const root = new Element(); root.querySelector = find;
    root.dataset = { movementsUrl: '/api/movimientos', optionsUrl: '/api/movimientos/opciones', csrfUrl: '/sanctum/csrf-cookie' };
    const form = find('[data-movement-create-form]');
    for (const field of ['bien_id', 'ambiente_destino_id', 'ordenado_por', 'ejecutado_por', 'fecha_movimiento', 'motivo', 'observaciones']) {
        form.elements[field] = new Element();
    }
    find('[data-movement-lookup-form]').elements.id = new Element();
    const calls = [];
    const axios = {
        async get(url) {
            if (url.endsWith('/opciones')) return { data: { data: {
                bienes: [{ id: 1, ambiente_id: 2 }], ambientes: [{ id: 2, nombre: 'Origen' }, { id: 3, nombre: 'Destino' }], responsables: [],
            } } };
            if (url.endsWith('/csrf-cookie')) return { data: {} };
            return { data: { data: { id: 1, estado: 'finalizado', fecha_movimiento: '2026-10-02T15:00:00.000000Z', fecha_firma: signature } } };
        },
        async post(url, payload) { calls.push({ url, payload }); return { data: { data: { id: 1, estado: 'pendiente_firma' } } }; },
    };
    class Clock extends Date {
        constructor(...args) { super(...(args.length ? args : [instant])); }
    }
    vm.runInNewContext(source, {
        document: { querySelector: () => root }, window: { axios }, Date: Clock,
        Option: class { constructor(label, value) { this.textContent = label; this.value = value; } },
        Intl, console,
    });
    return { find, form, calls };
}

for (const browserZone of ['UTC', 'Asia/Tokyo']) {
    test(`movement and signature display Lima times with browser in ${browserZone}`, async () => {
        const previous = process.env.TZ; process.env.TZ = browserZone;
        try {
            const { find } = setup();
            find('[data-movement-lookup-form]').elements.id.value = '1';
            find('[data-movement-lookup-form]').emit('submit'); await flush();
            assert.match(find('[data-detail-fecha]').textContent, /10:00:00/);
            assert.match(find('[data-detail-firma]').textContent, /11:30:00/);
        } finally { if (previous === undefined) delete process.env.TZ; else process.env.TZ = previous; }
    });
}

test('registration defaults to Lima across the date boundary and submits the local time unchanged', async () => {
    const { find, form, calls } = setup('2026-01-01T03:04:05Z');
    find('[data-new-movement]').emit('click'); await flush();
    assert.equal(form.elements.fecha_movimiento.value, '2025-12-31T22:04:05');
    form.elements.bien_id.value = '1'; form.elements.ambiente_destino_id.value = '3';
    form.elements.ordenado_por.value = '1'; form.elements.ejecutado_por.value = '1'; form.elements.motivo.value = 'Prueba';
    await form.emit('submit'); await flush();
    assert.equal(calls[0].payload.fecha_movimiento, '2025-12-31 22:04:05');
});

test('Lima midnight uses hour 00 and a missing signature remains a dash', async () => {
    const { find, form } = setup('2026-01-01T05:00:00Z', null);
    find('[data-new-movement]').emit('click'); await flush();
    assert.equal(form.elements.fecha_movimiento.value, '2026-01-01T00:00:00');
    find('[data-movement-lookup-form]').elements.id.value = '1';
    find('[data-movement-lookup-form]').emit('submit'); await flush();
    assert.equal(find('[data-detail-firma]').textContent, '—');
});
