/* Consulta administrativa; el servidor aplica autorización y alcance de área. */
const API_GENERAL = '../Backend/';
let paginaGeneral = 1;
let modalGeneral;
let rolActual;

function alertaGeneral(texto, tipo = 'danger') {
  const destino = document.getElementById('mensaje-global');
  destino.replaceChildren();
  const alerta = document.createElement('div');
  alerta.className = 'alert alert-' + tipo;
  alerta.setAttribute('role', 'alert');
  alerta.textContent = texto;
  destino.append(alerta);
}

async function apiGeneral(ruta, opciones = {}) {
  const respuesta = await fetch(API_GENERAL + ruta, { credentials: 'same-origin', ...opciones });
  const datos = await respuesta.json().catch(() => ({}));
  if (respuesta.status === 401) {
    window.location.href = 'index.html';
    throw new Error('La sesión expiró.');
  }
  if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo completar la consulta.');
  return datos;
}

function opcionGeneral(select, id, nombre) {
  const opcion = document.createElement('option');
  opcion.value = id;
  opcion.textContent = nombre;
  select.append(opcion);
}

function reiniciarSelectorGeneral(select, prompt) {
  select.replaceChildren();
  opcionGeneral(select, '', prompt);
  select.disabled = true;
}

function celdaGeneral(fila, valor) {
  const celda = document.createElement('td');
  celda.textContent = valor || '';
  fila.append(celda);
  return celda;
}

function filtrosGeneral() {
  return new URLSearchParams(Array.from(new FormData(document.getElementById('filtros-general')).entries()).filter(([, v]) => v !== ''));
}

function establecerRangoPredeterminado() {
  const hoy = new Date();
  const desde = new Date(hoy);
  desde.setDate(desde.getDate() - 30);
  const fechaLocal = (fecha) => [
    fecha.getFullYear(),
    String(fecha.getMonth() + 1).padStart(2, '0'),
    String(fecha.getDate()).padStart(2, '0'),
  ].join('-');
  document.getElementById('g-desde').value = fechaLocal(desde);
  document.getElementById('g-hasta').value = fechaLocal(hoy);
}

async function cargarListaGeneral(pagina = 1) {
  paginaGeneral = pagina;
  const filtros = filtrosGeneral();
  filtros.set('pagina', String(pagina));
  try {
    const respuesta = await apiGeneral('consulta_general.php?' + filtros.toString());
    const tabla = document.getElementById('filas-generales');
    tabla.replaceChildren();
    respuesta.datos.forEach((reporte) => {
      const fila = document.createElement('tr');
      celdaGeneral(fila, reporte.folio);
      celdaGeneral(fila, reporte.nombre_categoria);
      celdaGeneral(fila, [reporte.edificio, reporte.piso, reporte.espacio_especifico].join(' · '));
      const area = celdaGeneral(fila, reporte.nombre_area || 'Sin área asignada');
      if (!reporte.nombre_area) area.classList.add('badge-sin-area');
      celdaGeneral(fila, reporte.nombre_estado);
      celdaGeneral(fila, reporte.fecha_creacion);
      if (rolActual === 'Administrador') celdaGeneral(fila, reporte.reportante + ' · ' + reporte.correo_reportante);
      const accion = celdaGeneral(fila, '');
      const boton = document.createElement('button');
      boton.type = 'button';
      boton.className = 'btn btn-sm btn-outline-primary';
      boton.textContent = 'Detalle';
      boton.addEventListener('click', () => verDetalleGeneral(reporte.folio));
      accion.append(boton);
      tabla.append(fila);
    });
    document.getElementById('sin-generales').hidden = respuesta.datos.length !== 0;
    pintarPaginas(respuesta.pagina, respuesta.paginas);
  } catch (error) {
    alertaGeneral(error.message);
  }
}

function pintarPaginas(pagina, paginas) {
  const contenedor = document.getElementById('paginacion-general');
  contenedor.replaceChildren();
  for (let n = 1; n <= paginas; n++) {
    const li = document.createElement('li');
    li.className = 'page-item' + (n === pagina ? ' active' : '');
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'page-link';
    boton.textContent = String(n);
    boton.addEventListener('click', () => cargarListaGeneral(n));
    li.append(boton);
    contenedor.append(li);
  }
}

async function opcionesCascada(select, accion, parametros, prompt) {
  const url = new URLSearchParams({ accion, ...parametros });
  const datos = await apiGeneral('catalogos_ubicaciones.php?' + url.toString());
  select.replaceChildren();
  opcionGeneral(select, '', prompt);
  datos.datos.forEach((dato) => {
    const valor = dato.id_ubicacion ?? dato.id_categoria ?? dato.id_estado ?? dato.etiqueta ?? dato.nombre_categoria ?? dato.nombre_estado;
    const texto = dato.etiqueta ?? dato.nombre_categoria ?? dato.nombre_estado;
    opcionGeneral(select, valor, texto);
  });
  select.disabled = datos.datos.length === 0;
}

async function verDetalleGeneral(folio) {
  try {
    const datos = await apiGeneral('detalle_reporte.php?folio=' + encodeURIComponent(folio));
    const contenido = document.getElementById('detalle-general-contenido');
    contenido.replaceChildren();
    const reporte = datos.datos.reporte;
    document.getElementById('modal-general-titulo').textContent = 'Reporte ' + reporte.folio;
    [
      ['Categoría', reporte.nombre_categoria],
      ['Ubicación', [reporte.edificio, reporte.piso, reporte.area_general, reporte.espacio_especifico].join(' · ')],
      ['Área', reporte.nombre_area || 'Sin área asignada'],
      ['Estado', reporte.nombre_estado],
      ['Descripción', reporte.descripcion],
    ].forEach(([etiqueta, valor]) => {
      const p = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = etiqueta + ': ';
      p.append(strong, document.createTextNode(valor || '—'));
      contenido.append(p);
    });
    if (datos.datos.reportante) {
      const p = document.createElement('p');
      p.textContent = 'Reportante: ' + datos.datos.reportante.nombre_completo + ' · ' + datos.datos.reportante.correo;
      contenido.append(p);
    }
    datos.datos.evidencias.forEach((evidencia) => {
      const enlace = document.createElement('a');
      enlace.href = '../Backend/evidencia.php?id=' + encodeURIComponent(evidencia.id_evidencia);
      enlace.target = '_blank';
      enlace.rel = 'noopener';
      enlace.textContent = 'Ver evidencia';
      enlace.className = 'd-block mb-2';
      contenido.append(enlace);
    });
    const titulo = document.createElement('h3');
    titulo.className = 'h6 mt-4';
    titulo.textContent = 'Línea de tiempo';
    contenido.append(titulo);
    datos.datos.historial.forEach((evento) => {
      const item = document.createElement('div');
      item.className = 'timeline-item';
      item.textContent = evento.fecha_hora + ' · ' + evento.nombre_estado + ' · ' + evento.accion_realizada + ' · ' + evento.responsable;
      contenido.append(item);
    });
    modalGeneral.show();
  } catch (error) {
    alertaGeneral(error.message);
  }
}

async function iniciarConsultaGeneral() {
  const sesion = await apiGeneral('me.php');
  rolActual = sesion.usuario.rol;
  if (!['Administrador', 'Jefe de departamento'].includes(rolActual)) {
    window.location.href = 'inicio.html';
    return;
  }
  window.AlertaCampusLayout.renderizarNavbar(sesion.usuario, 'consulta');
  const soloAdmin = rolActual === 'Administrador';
  document.getElementById('exportar-global').hidden = !soloAdmin;
  document.getElementById('filtro-area').hidden = !soloAdmin;
  document.getElementById('th-reportante').hidden = !soloAdmin;
  if (!soloAdmin) document.querySelector('#filas-generales').closest('table').querySelector('thead tr').lastElementChild.before(
    document.getElementById('th-reportante')
  );
  modalGeneral = new bootstrap.Modal(document.getElementById('modal-detalle-general'));
  const categorias = await apiGeneral('catalogos_ubicaciones.php?accion=categorias');
  categorias.datos.forEach((dato) => opcionGeneral(document.getElementById('g-categoria'), dato.id_categoria, dato.nombre_categoria));
  const estados = await apiGeneral('catalogos_ubicaciones.php?accion=estados');
  estados.datos.forEach((dato) => opcionGeneral(document.getElementById('g-estado'), dato.id_estado, dato.nombre_estado));
  if (soloAdmin) {
    const areas = await apiGeneral('catalogos_ubicaciones.php?accion=areas_atencion');
    areas.datos.forEach((dato) => opcionGeneral(document.getElementById('g-area'), dato.id_area, dato.nombre_area));
  }
  const edificio = document.getElementById('g-edificio');
  const piso = document.getElementById('g-piso');
  const area = document.getElementById('g-area-general');
  const espacio = document.getElementById('g-espacio');
  establecerRangoPredeterminado();
  opcionesCascada(edificio, 'edificios', {}, 'Todos').catch((e) => alertaGeneral(e.message));
  edificio.addEventListener('change', () => {
    reiniciarSelectorGeneral(piso, 'Todos');
    reiniciarSelectorGeneral(area, 'Todas');
    reiniciarSelectorGeneral(espacio, 'Todos');
    if (edificio.value) opcionesCascada(piso, 'pisos', { edificio: edificio.value }, 'Todos').catch((e) => alertaGeneral(e.message));
  });
  piso.addEventListener('change', () => {
    reiniciarSelectorGeneral(area, 'Todas');
    reiniciarSelectorGeneral(espacio, 'Todos');
    if (edificio.value && piso.value) opcionesCascada(area, 'areas', { edificio: edificio.value, piso: piso.value }, 'Todas').catch((e) => alertaGeneral(e.message));
  });
  area.addEventListener('change', () => {
    reiniciarSelectorGeneral(espacio, 'Todos');
    if (edificio.value && piso.value && area.value) opcionesCascada(espacio, 'espacios', { edificio: edificio.value, piso: piso.value, area: area.value }, 'Todos').catch((e) => alertaGeneral(e.message));
  });
  document.getElementById('filtros-general').addEventListener('submit', (e) => { e.preventDefault(); cargarListaGeneral(1); });
  document.getElementById('limpiar-general').addEventListener('click', () => {
    document.getElementById('filtros-general').reset();
    establecerRangoPredeterminado();
    piso.disabled = area.disabled = espacio.disabled = true;
    cargarListaGeneral(1);
  });
  document.getElementById('exportar-global').addEventListener('click', () => {
    window.location.href = API_GENERAL + 'pdf_general.php?' + filtrosGeneral().toString();
  });
  await cargarListaGeneral();
}

iniciarConsultaGeneral().catch((error) => alertaGeneral(error.message));
