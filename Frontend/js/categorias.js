/* Módulo 6 — Gestión de categorías (solo Administrador). */
const { crear, mostrarAlerta, llamarApi, enviarJson, celda, opcion, parametrosDeFormulario, iniciarPagina } = window.AlertaCampusComun;

const modalCategoria = new bootstrap.Modal(document.getElementById('modal-categoria'));
const modalEliminar = new bootstrap.Modal(document.getElementById('modal-eliminar-categoria'));
const categoriasPorId = new Map();
let idEliminar = null;

function mostrarAvisoModal(texto) {
  const aviso = document.getElementById('aviso-modal');
  aviso.textContent = texto || '';
  aviso.hidden = !texto;
}

async function cargarCategorias() {
  const parametros = parametrosDeFormulario(document.getElementById('filtros-categorias'));
  parametros.set('accion', 'listar');
  try {
    const resultado = await llamarApi('categorias.php?' + parametros.toString());
    const filas = document.getElementById('filas-categorias');
    filas.replaceChildren();
    categoriasPorId.clear();
    resultado.datos.forEach((categoria) => {
      categoriasPorId.set(Number(categoria.id_categoria), categoria);
      const fila = crear('tr');
      celda(fila, categoria.id_categoria);
      celda(fila, categoria.nombre_categoria);
      const area = celda(fila, '');
      if (categoria.nombre_area) {
        area.textContent = categoria.nombre_area;
      } else {
        area.append(crear('span', 'badge text-bg-warning', 'Sin área asignada'));
      }
      const reportes = celda(fila, categoria.total_reportes);
      reportes.className = 'text-center';
      const acciones = celda(fila, '');
      const editar = crear('button', 'btn btn-sm btn-outline-primary me-1', 'Editar');
      editar.type = 'button';
      editar.addEventListener('click', () => abrirEdicion(categoria.id_categoria));
      const eliminar = crear('button', 'btn btn-sm btn-outline-danger', 'Eliminar');
      eliminar.type = 'button';
      eliminar.addEventListener('click', () => solicitarEliminacion(categoria.id_categoria));
      acciones.append(editar, eliminar);
      filas.append(fila);
    });
    document.getElementById('sin-categorias').hidden = resultado.datos.length > 0;
  } catch (error) {
    mostrarAlerta(error.message, error.tipo);
  }
}

function prepararNueva() {
  document.getElementById('form-categoria').reset();
  document.getElementById('id-categoria').value = '';
  document.getElementById('modal-categoria-titulo').textContent = 'Nueva categoría';
  mostrarAvisoModal('');
  modalCategoria.show();
}

function abrirEdicion(id) {
  const categoria = categoriasPorId.get(Number(id));
  if (!categoria) {
    mostrarAlerta('No se pudo cargar la información de la categoría.');
    return;
  }
  document.getElementById('form-categoria').reset();
  document.getElementById('id-categoria').value = categoria.id_categoria;
  document.getElementById('categoria-nombre').value = categoria.nombre_categoria;
  document.getElementById('categoria-area').value = categoria.id_area_responsable || '';
  document.getElementById('modal-categoria-titulo').textContent = 'Editar categoría';
  mostrarAvisoModal('');
  modalCategoria.show();
}

function solicitarEliminacion(id) {
  const categoria = categoriasPorId.get(Number(id));
  idEliminar = Number(id);
  document.getElementById('nombre-eliminar').textContent = categoria ? categoria.nombre_categoria : '';
  modalEliminar.show();
}

document.getElementById('form-categoria').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const id = document.getElementById('id-categoria').value;
  const nombre = document.getElementById('categoria-nombre').value.trim();
  const area = document.getElementById('categoria-area').value;
  if (!nombre || !area) {
    mostrarAvisoModal('Completa el nombre de la categoría y selecciona el área responsable.');
    return;
  }
  const boton = document.getElementById('guardar-categoria');
  boton.disabled = true;
  try {
    const resultado = await enviarJson('categorias.php', {
      accion: id ? 'editar' : 'crear',
      id_categoria: id || undefined,
      nombre_categoria: nombre,
      id_area_responsable: area,
    });
    modalCategoria.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await cargarCategorias();
  } catch (error) {
    // El error se queda dentro del modal para poder corregir sin perder lo capturado.
    mostrarAvisoModal(error.message);
  } finally {
    boton.disabled = false;
  }
});

document.getElementById('confirmar-eliminar').addEventListener('click', async () => {
  if (!idEliminar) return;
  try {
    const resultado = await enviarJson('categorias.php', { accion: 'eliminar', id_categoria: idEliminar });
    modalEliminar.hide();
    mostrarAlerta(resultado.mensaje, 'success');
    await cargarCategorias();
  } catch (error) {
    modalEliminar.hide();
    mostrarAlerta(error.message, error.tipo);
  } finally {
    idEliminar = null;
  }
});

document.getElementById('filtros-categorias').addEventListener('submit', (evento) => {
  evento.preventDefault();
  cargarCategorias();
});
document.getElementById('limpiar-filtros').addEventListener('click', () => window.setTimeout(cargarCategorias, 0));
document.getElementById('nueva-categoria').addEventListener('click', prepararNueva);

async function iniciar() {
  await iniciarPagina(['Administrador'], 'categorias');
  const catalogo = await llamarApi('categorias.php?accion=catalogos');
  const selector = document.getElementById('categoria-area');
  catalogo.areas.forEach((area) => opcion(selector, area.id_area, area.nombre_area));
  await cargarCategorias();
}

iniciar().catch((error) => mostrarAlerta(error.message, error.tipo));
