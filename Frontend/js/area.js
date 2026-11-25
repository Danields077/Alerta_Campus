const API = '../Backend/';
let accionPendiente = null;
let folioPendiente = null;
let modalAtencion;
let esAdministrador = false;
let esJefeDepartamento = false;

function mostrarAlerta(texto, tipo = 'danger') {
  const destino = document.getElementById('mensaje-global');
  destino.replaceChildren();
  const alerta = document.createElement('div');
  alerta.className = 'alert alert-' + tipo + ' alert-dismissible';
  alerta.setAttribute('role', 'alert');
  alerta.append(document.createTextNode(texto));
  const cerrar = document.createElement('button');
  cerrar.type = 'button';
  cerrar.className = 'btn-close';
  cerrar.dataset.bsDismiss = 'alert';
  alerta.append(cerrar);
  destino.append(alerta);
}

async function llamarApi(ruta, opciones = {}) {
  const respuesta = await fetch(API + ruta, { credentials: 'same-origin', ...opciones });
  const datos = await respuesta.json().catch(() => ({}));
  if (respuesta.status === 401) {
    window.location.href = 'index.html';
    throw new Error('La sesión expiró.');
  }
  if (!respuesta.ok || !datos.ok) throw new Error(datos.mensaje || 'No se pudo completar la solicitud.');
  return datos;
}

function crearCelda(fila, texto) {
  const celda = document.createElement('td');
  celda.textContent = texto || '';
  fila.append(celda);
  return celda;
}

function abrirAccion(folio, accion, titulo) {
  folioPendiente = folio;
  accionPendiente = accion;
  document.getElementById('modal-atencion-titulo').textContent = titulo;
  document.getElementById('detalle-atencion').textContent = 'Reporte ' + folio;
  document.getElementById('nota-atencion').value = '';
  modalAtencion.show();
}

function agregarBoton(contenedor, texto, clase, folio, accion, titulo) {
  const boton = document.createElement('button');
  boton.type = 'button';
  boton.className = 'btn btn-sm ' + clase + ' me-1 mb-1';
  boton.textContent = texto;
  boton.addEventListener('click', () => abrirAccion(folio, accion, titulo));
  contenedor.append(boton);
}

async function cargarReportes() {
  const estado = document.getElementById('filtro-estado').value;
  const parametros = new URLSearchParams();
  if (estado) parametros.set('estado', estado);
  const area = document.getElementById('filtro-area').value;
  if (esAdministrador && area) parametros.set('area', area);
  const consulta = parametros.size ? '?' + parametros.toString() : '';
  try {
    const resultado = await llamarApi('area_reportes.php' + consulta);
    const filas = document.getElementById('filas-area');
    filas.replaceChildren();
    resultado.datos.forEach((reporte) => {
      const fila = document.createElement('tr');
      crearCelda(fila, reporte.folio);
      crearCelda(fila, reporte.nombre_categoria);
      crearCelda(fila, reporte.nombre_area || 'Sin área asignada');
      crearCelda(fila, [reporte.edificio, reporte.piso, reporte.espacio_especifico].join(' · '));
      crearCelda(fila, reporte.reportante);
      crearCelda(fila, reporte.prioridad || 'Media');
      crearCelda(fila, reporte.nombre_estado);

      const acciones = crearCelda(fila, '');
      if (!esAdministrador && reporte.nombre_estado === 'Pendiente') {
        agregarBoton(acciones, 'Atender', 'btn-primary', reporte.folio, 'atender', 'Atender reporte');
      } else if (!esAdministrador && reporte.nombre_estado === 'En atención') {
        agregarBoton(acciones, 'Más tiempo', 'btn-outline-secondary', reporte.folio, 'mas_tiempo', 'Registrar avance');
        agregarBoton(acciones, 'Enviar a verificación', 'btn-success', reporte.folio, 'verificacion', 'Enviar solución');
      } else if (esJefeDepartamento && reporte.nombre_estado === 'En verificación') {
        agregarBoton(acciones, 'Cerrar', 'btn-success', reporte.folio, 'cerrar', 'Cerrar reporte');
      }
      filas.append(fila);
    });
    document.getElementById('sin-area').hidden = resultado.datos.length !== 0;
  } catch (error) {
    mostrarAlerta(error.message);
  }
}

async function confirmarAccion() {
  if (!folioPendiente || !accionPendiente) return;
  const boton = document.getElementById('confirmar-atencion');
  boton.disabled = true;
  try {
    const resultado = await llamarApi('area_reportes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        folio: folioPendiente,
        accion: accionPendiente,
        nota: document.getElementById('nota-atencion').value.trim(),
      }),
    });
    modalAtencion.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await cargarReportes();
  } catch (error) {
    mostrarAlerta(error.message);
  } finally {
    boton.disabled = false;
  }
}

async function iniciar() {
  const respuesta = await llamarApi('me.php');
  if (!['Administrador', 'Jefe de departamento', 'Personal de atención'].includes(respuesta.usuario.rol)) {
    window.location.href = 'inicio.html';
    return;
  }
  esAdministrador = respuesta.usuario.rol === 'Administrador';
  esJefeDepartamento = respuesta.usuario.rol === 'Jefe de departamento';
  document.getElementById('descripcion-area').textContent = esAdministrador
    ? 'Vista global de todos los reportes. El cierre y la devolución corresponden al Jefe de departamento.'
    : 'Reportes asignados a tu área.';
  if (esAdministrador) {
    const filtroArea = document.getElementById('filtro-area');
    filtroArea.hidden = false;
    const areas = await llamarApi('catalogos_ubicaciones.php?accion=areas_atencion');
    areas.datos.forEach((area) => {
      const opcion = document.createElement('option');
      opcion.value = area.id_area;
      opcion.textContent = area.nombre_area;
      filtroArea.append(opcion);
    });
    filtroArea.addEventListener('change', cargarReportes);
  }
  window.AlertaCampusLayout.renderizarNavbar(respuesta.usuario, 'area');
  modalAtencion = new bootstrap.Modal(document.getElementById('modal-atencion'));
  document.getElementById('filtro-estado').addEventListener('change', cargarReportes);
  document.getElementById('confirmar-atencion').addEventListener('click', confirmarAccion);
  await cargarReportes();
}

iniciar().catch((error) => mostrarAlerta(error.message));
