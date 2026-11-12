export function renderBienDetail(root, bien, sede) {
    const setDetail = (field, value) => {
        root.querySelector(`[data-detail-${field}]`).textContent = value ?? '—';
    };
    const technical = root.querySelector('[data-technical-fields]');
    technical.replaceChildren();
    const equipment = bien.equipo;
    const furniture = bien.mueble;
    const fields = equipment ? [
        ['Tipo de equipo', equipment.tipo_equipo?.nombre], ['Marca', equipment.marca?.nombre],
        ['Modelo', equipment.modelo], ['Número de serie', equipment.serie],
    ] : furniture ? [['Tipo de mueble', furniture.tipo_mueble?.nombre], ['Material', furniture.material], ['Color', furniture.color]] : [];
    if (equipment?.equipo_computo) {
        const pc = equipment.equipo_computo;
        fields.push(['Procesador', pc.procesador], ['Memoria RAM', pc.ram], ['Almacenamiento', pc.almacenamiento], ['Detalle adicional', pc.detalle_adicional]);
    }
    if (equipment?.monitor) fields.push(['Tamaño de pantalla', equipment.monitor.tamano_pantalla ?? equipment.monitor.tamanio_pantalla], ['Detalle de pantalla', equipment.monitor.detalle_pantalla]);
    fields.forEach(([label, value]) => {
        const group = document.createElement('div');
        const title = document.createElement('dt'); title.textContent = label;
        const content = document.createElement('dd'); content.textContent = value === null || value === undefined || String(value).trim() === '' ? '—' : value;
        group.append(title, content); technical.append(group);
    });
    root.querySelector('[data-technical-empty]').hidden = fields.length > 0;
    setDetail('id', bien.id);
    setDetail('cbi', bien.cbi);
    setDetail('descripcion', bien.descripcion);
    setDetail('sede', sede ?? bien.ambiente?.sede?.nombre);
    setDetail('ambiente', bien.ambiente?.nombre);
    setDetail('estado', bien.estado?.nombre);
    setDetail('condicion', bien.condicion?.nombre);
    setDetail('activo', bien.activo ? 'Sí' : 'No');
    setDetail('fecha', bien.fecha_registro ? String(bien.fecha_registro).slice(0, 10) : null);
    setDetail('observaciones', bien.observaciones);
}
