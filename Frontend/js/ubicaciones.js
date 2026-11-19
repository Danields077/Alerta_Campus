/* Módulo 7 — Gestión de ubicaciones (solo Administrador). */
const { crear, mostrarAlerta, llamarApi, enviarJson, celda, paginacion, parametrosDeFormulario, iniciarPagina } = window.AlertaCampusComun;

const modalUbicacion = new bootstrap.Modal(document.getElementById('modal-ubicacion'));
const modalEliminar = new bootstrap.Modal(document.getElementById('modal-eliminar-ubicacion'));
const ubicacionesPorId = new Map();
let paginaActual = 1;
let idEliminar = null;

const CAMPOS = {
  edificio: 'ubicacion-edificio',
  piso: 'ubicacion-piso',
  area_general: 'ubicacion-area',
  espacio_especifico: 'ubicacion-espacio',
};

function mostrarAvisoModal(texto) {
  const aviso = document.getElementById('aviso-modal');
  aviso.textContent = texto || '';
  aviso.hidden = !texto;
}

function etiquetaUbicacion(u) {
  return [u.edificio, u.piso, u.area_general, u.espacio_especifico].join(' · ');
}

async function cargarUbicaciones(pagina = 1) {
  const parametros = parametrosDeFormulario(document.getElementById('filtros-ubicaciones'));
  parametros.set('accion', 'listar');
  parametros.set('pagina', String(pagina));
  try {
    const resultado = await llamarApi('ubicaciones.php?' + parametros.toString());
    paginaActual = resultado.pagina;
    const filas = document.getElementById('filas-ubicaciones');
    filas.replaceChildren();
    ubicacionesPorId.clear();
    resultado.datos.forEach((ubicacion) => {
      ubicacionesPorId.set(Number(ubicacion.id_ubicacion), ubicacion);
      const fila = crear('tr');
      celda(fila, ubicacion.edificio);
      celda(fila, ubicacion.piso);
      celda(fila, ubicacion.area_general);
      celda(fila, ubicacion.espacio_especifico);
      celda(fila, ubicacion.total_reportes).className = 'text-center';
      const acciones = celda(fila, '');
      const editar = crear('button', 'btn btn-sm btn-outline-primary me-1', 'Editar');
      editar.type = 'button';
      editar.addEventListener('click', () => abrirEdicion(ubicacion.id_ubicacion));
      const eliminar = crear('button', 'btn btn-sm btn-outline-danger', 'Eliminar');
      eliminar.type = 'button';
      eliminar.addEventListener('click', () => solicitarEliminacion(ubicacion.id_ubicacion));
      acciones.append(editar, eliminar);
      filas.append(fila);
    });
    document.getElementById('sin-ubicaciones').hidden = resultado.datos.length > 0;
    document.getElementById('total-ubicaciones').textContent =
      resultado.total > 0 ? resultado.total + ' ubicaciones registradas' : '';
    paginacion(document.getElementById('paginacion-ubicaciones'), resultado.pagina, resultado.paginas, cargarUbicaciones);
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  }
}

/** Sugerencias (datalist) con lo que ya existe, para escribir igual que el resto. */
async function cargarSugerencias() {
  const catalogo = await llamarApi('ubicaciones.php?accion=catalogos');
  [['lista-edificios', catalogo.edificios], ['lista-pisos', catalogo.pisos], ['lista-areas', catalogo.areas]]
    .forEach(([id, valores]) => {
      const lista = document.getElementById(id);
      lista.replaceChildren();
      valores.forEach((valor) => {
        const opcion = crear('option');
        opcion.value = valor;
        lista.append(opcion);
      });
    });
}

function prepararNueva() {
  document.getElementById('form-ubicacion').reset();
  document.getElementById('id-ubicacion').value = '';
  document.getElementById('modal-ubicacion-titulo').textContent = 'Nueva ubicación';
  mostrarAvisoModal('');
  modalUbicacion.show();
}

function abrirEdicion(id) {
  const ubicacion = ubicacionesPorId.get(Number(id));
  if (!ubicacion) {
    mostrarAlerta('No se pudo cargar la información de la ubicación.');
    return;
  }
  document.getElementById('form-ubicacion').reset();
  document.getElementById('id-ubicacion').value = ubicacion.id_ubicacion;
  Object.entries(CAMPOS).forEach(([campo, idCampo]) => {
    document.getElementById(idCampo).value = ubicacion[campo];
  });
  document.getElementById('modal-ubicacion-titulo').textContent = 'Editar ubicación';
  mostrarAvisoModal('');
  modalUbicacion.show();
}

function solicitarEliminacion(id) {
  const ubicacion = ubicacionesPorId.get(Number(id));
  idEliminar = Number(id);
  document.getElementById('nombre-eliminar').textContent = ubicacion ? etiquetaUbicacion(ubicacion) : '';
  modalEliminar.show();
}

document.getElementById('form-ubicacion').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const id = document.getElementById('id-ubicacion').value;
  const datos = {};
  for (const [campo, idCampo] of Object.entries(CAMPOS)) {
    datos[campo] = document.getElementById(idCampo).value.trim();
  }
  if (Object.values(datos).some((valor) => !valor)) {
    mostrarAvisoModal('Completa todos los campos: edificio, piso, área general y aula o espacio.');
    return;
  }
  const boton = document.getElementById('guardar-ubicacion');
  boton.disabled = true;
  try {
    const resultado = await enviarJson('ubicaciones.php', {
      accion: id ? 'editar' : 'crear',
      id_ubicacion: id || undefined,
      ...datos,
    });
    modalUbicacion.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await Promise.all([cargarUbicaciones(paginaActual), cargarSugerencias()]);
  } catch (error) {
    mostrarAvisoModal(error.message);
  } finally {
    boton.disabled = false;
  }
});

document.getElementById('confirmar-eliminar').addEventListener('click', async () => {
  if (!idEliminar) return;
  try {
    const resultado = await enviarJson('ubicaciones.php', { accion: 'eliminar', id_ubicacion: idEliminar });
    modalEliminar.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await cargarUbicaciones(paginaActual);
  } catch (error) {
    modalEliminar.hide();
    mostrarAlerta(error.message, error.tipo);
  } finally {
    idEliminar = null;
  }
});

document.getElementById('filtros-ubicaciones').addEventListener('submit', (evento) => {
  evento.preventDefault();
  cargarUbicaciones(1);
});
document.getElementById('limpiar-filtros').addEventListener('click', () => window.setTimeout(() => cargarUbicaciones(1), 0));
document.getElementById('nueva-ubicacion').addEventListener('click', prepararNueva);

async function iniciar() {
  await iniciarPagina(['Administrador'], 'ubicaciones');
  await Promise.all([cargarUbicaciones(1), cargarSugerencias()]);
}

iniciar().catch((error) => mostrarAlerta(error.message, error.tipo));
