/* Módulo 4.1 — Control de calidad: el Jefe de departamento valida las soluciones. */
const { crear, mostrarAlerta, llamarApi, enviarJson, celda, formatearFecha, ubicacionTexto, parametrosDeFormulario, iniciarPagina } = window.AlertaCampusComun;

const modalRevision = new bootstrap.Modal(document.getElementById('modal-revision'));
let folioEnRevision = null;

function mostrarAvisoModal(texto) {
  const aviso = document.getElementById('aviso-modal');
  aviso.textContent = texto || '';
  aviso.hidden = !texto;
}

async function cargarReportes() {
  const parametros = parametrosDeFormulario(document.getElementById('filtros-revision'));
  parametros.set('accion', 'listar');
  try {
    const resultado = await llamarApi('revision_reportes.php?' + parametros.toString());
    const filas = document.getElementById('filas-revision');
    filas.replaceChildren();
    resultado.datos.forEach((reporte) => {
      const fila = crear('tr');
      celda(fila, reporte.folio);
      celda(fila, reporte.nombre_categoria);
      celda(fila, ubicacionTexto(reporte));
      celda(fila, formatearFecha(reporte.fecha_envio || reporte.fecha_creacion));
      const acciones = celda(fila, '');
      const revisar = crear('button', 'btn btn-sm btn-primary', 'Revisar');
      revisar.type = 'button';
      revisar.addEventListener('click', () => abrirRevision(reporte.folio, revisar));
      acciones.append(revisar);
      filas.append(fila);
    });
    document.getElementById('sin-revision').hidden = resultado.datos.length > 0;
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  }
}

function imagenEvidencia(evidencia, texto) {
  const enlace = crear('a', 'd-inline-block me-2 mb-2');
  enlace.href = '../Backend/evidencia.php?id=' + encodeURIComponent(evidencia.id_evidencia);
  enlace.target = '_blank';
  enlace.rel = 'noopener';
  const imagen = crear('img', 'revision-evidencia');
  imagen.src = enlace.href;
  imagen.alt = texto + ' (' + formatearFecha(evidencia.fecha_creacion) + ')';
  imagen.loading = 'lazy';
  enlace.append(imagen);
  return enlace;
}

function pintarEvidencias(contenedorId, evidencias, texto, vacio) {
  const contenedor = document.getElementById(contenedorId);
  contenedor.replaceChildren();
  if (!evidencias.length) {
    contenedor.append(crear('span', 'text-muted', vacio));
    return;
  }
  evidencias.forEach((e) => contenedor.append(imagenEvidencia(e, texto)));
}

function dato(lista, termino, valor) {
  lista.append(crear('dt', 'col-sm-4', termino), crear('dd', 'col-sm-8 mb-1', valor || '—'));
}

async function abrirRevision(folio, boton) {
  if (boton) boton.disabled = true;
  try {
    const resultado = await llamarApi('revision_reportes.php?accion=detalle&folio=' + encodeURIComponent(folio));
    const { reporte, comentario_solucion: comentario, evidencias, historial } = resultado.datos;
    folioEnRevision = reporte.folio;

    document.getElementById('modal-revision-titulo').textContent = 'Revisión del reporte ' + reporte.folio;
    const datos = document.getElementById('revision-datos');
    datos.replaceChildren();
    dato(datos, 'Categoría', reporte.nombre_categoria);
    dato(datos, 'Ubicación', [reporte.edificio, reporte.piso, reporte.area_general, reporte.espacio_especifico].join(' · '));
    dato(datos, 'Área asignada', reporte.nombre_area);
    dato(datos, 'Fecha del reporte', formatearFecha(reporte.fecha_creacion));
    dato(datos, 'Estado actual', reporte.nombre_estado);

    document.getElementById('revision-descripcion').textContent = reporte.descripcion;
    const textoComentario = document.getElementById('revision-comentarios');
    textoComentario.textContent = comentario ? comentario.texto : 'El personal no registró comentarios.';
    textoComentario.className = comentario ? 'mb-3' : 'mb-3 text-muted';

    pintarEvidencias('revision-evidencia-solucion', evidencias.filter((e) => e.tipo_evidencia === 'solucion'), 'Evidencia de la solución', 'Sin fotografía de solución.');
    pintarEvidencias('revision-evidencia-reporte', evidencias.filter((e) => e.tipo_evidencia === 'reporte'), 'Evidencia del reporte', 'El reporte no incluyó fotografía.');

    const linea = document.getElementById('revision-historial');
    linea.replaceChildren();
    historial.forEach((evento) => {
      const item = crear('li');
      item.append(crear('strong', '', evento.nombre_estado), document.createTextNode(' · ' + formatearFecha(evento.fecha_hora) + ' · ' + evento.responsable));
      item.append(crear('div', 'small text-muted', evento.accion_realizada));
      linea.append(item);
    });

    document.getElementById('form-revision').reset();
    document.getElementById('eval-aprobado').checked = true;
    actualizarObligatoriedad();
    mostrarAvisoModal('');
    document.getElementById('guardar-revision').disabled = reporte.nombre_estado !== 'En verificación';
    modalRevision.show();
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  } finally {
    if (boton) boton.disabled = false;
  }
}

function actualizarObligatoriedad() {
  const rechaza = document.getElementById('eval-rechazado').checked;
  document.getElementById('observaciones-requeridas').hidden = !rechaza;
  document.getElementById('observaciones-opcionales').hidden = rechaza;
  document.getElementById('revision-observaciones').required = rechaza;
}
document.querySelectorAll('input[name="evaluacion"]').forEach((radio) => radio.addEventListener('change', actualizarObligatoriedad));

document.getElementById('form-revision').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const evaluacion = document.querySelector('input[name="evaluacion"]:checked').value;
  const observaciones = document.getElementById('revision-observaciones').value.trim();
  if (evaluacion === 'Rechazado' && !observaciones) {
    mostrarAvisoModal('Las observaciones son obligatorias cuando el reporte se rechaza.');
    return;
  }
  const boton = document.getElementById('guardar-revision');
  boton.disabled = true;
  try {
    const resultado = await enviarJson('revision_reportes.php', { folio: folioEnRevision, evaluacion, observaciones });
    modalRevision.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await cargarReportes();
  } catch (error) {
    mostrarAvisoModal(error.message);
    boton.disabled = false;
  }
});

document.getElementById('filtros-revision').addEventListener('submit', (evento) => {
  evento.preventDefault();
  cargarReportes();
});
document.getElementById('limpiar-filtros').addEventListener('click', () => window.setTimeout(cargarReportes, 0));

async function iniciar() {
  await iniciarPagina(['Jefe de departamento'], 'calidad');
  await cargarReportes();
}

iniciar().catch((error) => mostrarAlerta(error.message, error.tipo));
