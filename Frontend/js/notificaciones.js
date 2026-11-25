/* Módulo 8.1 — Notificaciones del usuario (todos los perfiles). */
const { crear, mostrarAlerta, llamarApi, enviarJson, formatearFecha, paginacion, iniciarPagina } = window.AlertaCampusComun;

const INTERVALO_ACTUALIZACION_MS = 30000;
let paginaActual = 1;
let firmaLista = '';
let ultimoResultado = null;

const firmaDe = (r) => JSON.stringify([r.pagina, r.total, r.datos.map((n) => [n.id_notificacion, Number(n.leida)])]);
let rolUsuario = '';

const TITULOS = {
  'nuevo reporte asignado': 'Nuevo reporte asignado',
  'reporte sin área asignada': 'Reporte sin área asignada',
  'actualizacion reporte': 'Cambio de estado',
  'reporte en verificacion': 'Solución por validar',
  'reporte devuelto': 'Reporte devuelto',
  'reporte cerrado': 'Reporte cerrado',
};

/** A qué pantalla conviene ir según el aviso y el perfil de quien lo recibe. */
function destinoDe(notificacion) {
  switch (notificacion.tipo_evento) {
    case 'nuevo reporte asignado':
    case 'reporte devuelto':
      return { url: 'area.html', texto: 'Ir a atención de reportes' };
    case 'reporte en verificacion':
      return { url: 'control_calidad.html', texto: 'Ir a control de calidad' };
    case 'reporte sin área asignada':
      return { url: 'consulta_general.html', texto: 'Ir a consulta general' };
    default:
      return ['Estudiante', 'Profesor'].includes(rolUsuario)
        ? { url: 'reportes.html', texto: 'Ir a mis reportes' }
        : { url: 'consulta_general.html', texto: 'Ir a consulta general' };
  }
}

async function marcarLeida(notificacion, elemento) {
  if (Number(notificacion.leida) === 1) return;
  try {
    const resultado = await enviarJson('notificaciones.php', {
      accion: 'marcar_leida',
      id_notificacion: notificacion.id_notificacion,
    });
    notificacion.leida = 1;
    if (ultimoResultado) firmaLista = firmaDe(ultimoResultado); // no redibujar lo que se está leyendo
    elemento.classList.remove('no-leida');
    elemento.querySelector('.etiqueta-nueva')?.remove();
    actualizarResumen(resultado.no_leidas);
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  }
}

function crearElementoNotificacion(notificacion) {
  const item = crear('div', 'list-group-item notificacion-item' + (Number(notificacion.leida) === 0 ? ' no-leida' : ''));
  item.setAttribute('role', 'button');
  item.tabIndex = 0;

  const cabecera = crear('div', 'd-flex flex-wrap justify-content-between align-items-start gap-2');
  const titulo = crear('div');
  if (Number(notificacion.leida) === 0) titulo.append(crear('span', 'badge text-bg-primary me-2 etiqueta-nueva', 'Nueva'));
  titulo.append(document.createTextNode(TITULOS[notificacion.tipo_evento] || 'Notificación'));
  cabecera.append(titulo, crear('small', 'text-muted fw-normal', formatearFecha(notificacion.fecha_generacion)));

  const mensaje = crear('div', 'mt-1 fw-normal', notificacion.mensaje);

  // Detalle: se muestra al abrir la notificación, que es cuando se marca como leída.
  const detalle = crear('div', 'notificacion-detalle mt-2 pt-2 border-top');
  detalle.hidden = true;
  detalle.append(crear('div', 'small text-muted mb-2', 'Folio del reporte: ' + notificacion.folio));
  const destino = destinoDe(notificacion);
  const enlace = crear('a', 'btn btn-sm btn-primary', destino.texto);
  enlace.href = destino.url;
  enlace.addEventListener('click', (evento) => evento.stopPropagation());
  detalle.append(enlace);

  const abrir = () => {
    detalle.hidden = !detalle.hidden;
    item.setAttribute('aria-expanded', String(!detalle.hidden));
    if (!detalle.hidden) marcarLeida(notificacion, item);
  };
  item.addEventListener('click', abrir);
  item.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter' || evento.key === ' ') {
      evento.preventDefault();
      abrir();
    }
  });

  item.append(cabecera, mensaje, detalle);
  return item;
}

function actualizarResumen(noLeidas) {
  document.getElementById('resumen-notificaciones').textContent =
    noLeidas > 0 ? noLeidas + ' sin leer' : 'Todo al día';
}

/**
 * Carga la lista. En las actualizaciones automáticas (sondeo) se pide con sondeo=1
 * para no renovar la sesión y solo se vuelve a dibujar si algo cambió, para no
 * cerrarle al usuario la notificación que está leyendo.
 */
async function cargarNotificaciones(pagina = paginaActual, esSondeo = false) {
  const parametros = new URLSearchParams({ accion: 'listar', pagina: String(pagina) });
  const estado = document.getElementById('filtro-estado').value;
  if (estado) parametros.set('estado', estado);
  if (esSondeo) parametros.set('sondeo', '1');
  try {
    const resultado = await llamarApi('notificaciones.php?' + parametros.toString());
    paginaActual = resultado.pagina;
    actualizarResumen(resultado.no_leidas);

    const firma = firmaDe(resultado);
    if (esSondeo && firma === firmaLista) return;
    firmaLista = firma;
    ultimoResultado = resultado;

    const lista = document.getElementById('lista-notificaciones');
    lista.replaceChildren(...resultado.datos.map(crearElementoNotificacion));
    document.getElementById('sin-notificaciones').hidden = resultado.datos.length > 0;
    paginacion(document.getElementById('paginacion-notificaciones'), resultado.pagina, resultado.paginas, (n) => cargarNotificaciones(n));
  } catch (error) {
    if (!esSondeo) mostrarAlerta(error.message, error.tipo);
  }
}

document.getElementById('filtro-estado').addEventListener('change', () => {
  firmaLista = '';
  cargarNotificaciones(1);
});

document.getElementById('marcar-todas').addEventListener('click', async () => {
  try {
    const resultado = await enviarJson('notificaciones.php', { accion: 'marcar_todas' });
    mostrarAlerta(resultado.mensaje, 'success');
    firmaLista = '';
    await cargarNotificaciones(paginaActual);
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  }
});

async function iniciar() {
  const usuario = await iniciarPagina(['Estudiante', 'Profesor', 'Personal de atención', 'Jefe de departamento', 'Administrador'], 'notificaciones');
  rolUsuario = usuario.rol;
  await cargarNotificaciones(1);
  window.setInterval(() => { if (!document.hidden) cargarNotificaciones(paginaActual, true); }, INTERVALO_ACTUALIZACION_MS);
}

iniciar().catch((error) => mostrarAlerta(error.message, error.tipo));
