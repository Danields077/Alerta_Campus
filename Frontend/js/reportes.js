/* Alta, consulta, detalle y exportación de reportes del usuario. */
const API = '../Backend/';
let paginaActual = 1;
let modalDetalle;

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
  cerrar.setAttribute('data-bs-dismiss', 'alert');
  cerrar.setAttribute('aria-label', 'Cerrar');
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

function agregarOpcion(select, valor, etiqueta) {
  const opcion = document.createElement('option');
  opcion.value = valor;
  opcion.textContent = etiqueta;
  select.append(opcion);
}

function reiniciarSelector(select, etiqueta) {
  select.replaceChildren();
  agregarOpcion(select, '', etiqueta);
  select.disabled = true;
}

async function cargarOpciones(select, accion, parametros = {}, etiqueta = 'Selecciona') {
  const consulta = new URLSearchParams({ accion, ...parametros });
  const respuesta = await llamarApi('catalogos_ubicaciones.php?' + consulta.toString());
  select.replaceChildren();
  agregarOpcion(select, '', etiqueta);
  respuesta.datos.forEach((dato) => {
    const valor = dato.id_ubicacion ?? dato.id_categoria ?? dato.id_estado ?? dato.etiqueta ?? dato.nombre_categoria ?? dato.nombre_estado;
    const texto = dato.etiqueta ?? dato.nombre_categoria ?? dato.nombre_estado;
    agregarOpcion(select, valor, texto);
  });
  select.disabled = respuesta.datos.length === 0;
}

function parametrosFiltro(formulario) {
  return new URLSearchParams(Array.from(new FormData(formulario).entries()).filter(([, valor]) => valor !== ''));
}

function crearCelda(fila, texto) {
  const celda = document.createElement('td');
  celda.textContent = texto || '';
  fila.append(celda);
  return celda;
}

function configurarCascada() {
  const edificio = document.getElementById('edificio');
  const piso = document.getElementById('piso');
  const area = document.getElementById('area-general');
  const espacio = document.getElementById('espacio');
  cargarOpciones(edificio, 'edificios', {}, 'Selecciona edificio').catch((e) => mostrarAlerta(e.message));
  edificio.addEventListener('change', async () => {
    reiniciarSelector(piso, 'Selecciona piso');
    reiniciarSelector(area, 'Selecciona área');
    reiniciarSelector(espacio, 'Selecciona espacio');
    if (!edificio.value) return;
    try {
      await cargarOpciones(piso, 'pisos', { edificio: edificio.value }, 'Selecciona piso');
    } catch (error) {
      mostrarAlerta(error.message);
    }
  });
  piso.addEventListener('change', async () => {
    reiniciarSelector(area, 'Selecciona área');
    reiniciarSelector(espacio, 'Selecciona espacio');
    if (!edificio.value || !piso.value) return;
    try {
      await cargarOpciones(area, 'areas', { edificio: edificio.value, piso: piso.value }, 'Selecciona área');
    } catch (error) {
      mostrarAlerta(error.message);
    }
  });
  area.addEventListener('change', () => {
    reiniciarSelector(espacio, 'Selecciona espacio');
    if (!edificio.value || !piso.value || !area.value) return;
    cargarOpciones(
      espacio, 'espacios', { edificio: edificio.value, piso: piso.value, area: area.value }, 'Selecciona espacio'
    ).catch((error) => mostrarAlerta(error.message));
  });
}

function configurarWizard() {
  let paso = 1;
  const form = document.getElementById('form-reporte');
  const siguiente = document.getElementById('paso-siguiente');
  const anterior = document.getElementById('paso-anterior');
  const enviar = document.getElementById('enviar-reporte');
  const cambiarPaso = (nuevo) => {
    paso = nuevo;
    document.querySelectorAll('[data-paso]').forEach((panel) => { panel.hidden = Number(panel.dataset.paso) !== paso; });
    document.querySelectorAll('[data-indicador]').forEach((indicador) => {
      indicador.classList.toggle('paso-activo', Number(indicador.dataset.indicador) === paso);
    });
    anterior.hidden = paso === 1;
    siguiente.hidden = paso === 3;
    enviar.hidden = paso !== 3;
  };
  siguiente.addEventListener('click', () => {
    const campos = paso === 1 ? [document.getElementById('categoria')] : [
      document.getElementById('edificio'), document.getElementById('piso'),
      document.getElementById('area-general'), document.getElementById('espacio')
    ];
    const invalido = campos.find((campo) => !campo.value);
    if (invalido) {
      invalido.focus();
      mostrarAlerta('Completa los campos obligatorios antes de continuar.', 'warning');
      return;
    }
    cambiarPaso(paso + 1);
  });
  anterior.addEventListener('click', () => cambiarPaso(paso - 1));
  document.getElementById('descripcion').addEventListener('input', (evento) => {
    document.getElementById('contador').textContent = String(evento.target.value.length);
  });
  document.getElementById('evidencia').addEventListener('change', (evento) => {
    const archivo = evento.target.files[0];
    if (archivo && (archivo.size > 5 * 1024 * 1024 || !['image/jpeg', 'image/png'].includes(archivo.type))) {
      evento.target.value = '';
      mostrarAlerta('La evidencia debe ser JPG/PNG y pesar como máximo 5 MB.', 'warning');
    }
  });
  form.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!document.getElementById('descripcion').value.trim() || !document.getElementById('confirmacion').checked) {
      mostrarAlerta('La descripción y la confirmación son obligatorias.', 'warning');
      return;
    }
    if (!form.reportValidity()) return;
    enviar.disabled = true;
    try {
      const resultado = await llamarApi('registrar_reporte.php', { method: 'POST', body: new FormData(form) });
      mostrarAlerta(resultado.mensaje, 'success');
      form.reset();
      document.getElementById('contador').textContent = '0';
      cambiarPaso(1);
      cargarHistorial();
    } catch (error) {
      mostrarAlerta(error.message, 'danger');
    } finally {
      enviar.disabled = false;
    }
  });
}

function mostrarPaginacion(contenedor, pagina, paginas, alCambiar) {
  contenedor.replaceChildren();
  for (let numero = 1; numero <= paginas; numero++) {
    const elemento = document.createElement('li');
    elemento.className = 'page-item' + (numero === pagina ? ' active' : '');
    const boton = document.createElement('button');
    boton.className = 'page-link';
    boton.type = 'button';
    boton.textContent = String(numero);
    boton.addEventListener('click', () => alCambiar(numero));
    elemento.append(boton);
    contenedor.append(elemento);
  }
}

async function cargarHistorial(pagina = 1) {
  paginaActual = pagina;
  const filtros = parametrosFiltro(document.getElementById('filtros-usuario'));
  filtros.set('pagina', String(pagina));
  try {
    const resultado = await llamarApi('historial_usuario.php?' + filtros.toString());
    const filas = document.getElementById('filas-reportes');
    filas.replaceChildren();
    resultado.datos.forEach((reporte) => {
      const fila = document.createElement('tr');
      fila.dataset.folio = reporte.folio;
      crearCelda(fila, reporte.folio);
      crearCelda(fila, reporte.nombre_categoria);
      crearCelda(fila, [reporte.edificio, reporte.piso, reporte.espacio_especifico].join(' · '));
      crearCelda(fila, reporte.fecha_creacion);
      const estado = crearCelda(fila, reporte.nombre_estado);
      estado.dataset.estado = 'actual';
      const accion = crearCelda(fila, '');
      const detalle = document.createElement('button');
      detalle.type = 'button';
      detalle.className = 'btn btn-sm btn-outline-primary';
      detalle.textContent = 'Ver detalle';
      detalle.addEventListener('click', () => verDetalle(reporte.folio));
      accion.append(detalle);
      document.getElementById('sin-reportes').hidden = resultado.datos.length !== 0;
      filas.append(fila);
    });
    document.getElementById('sin-reportes').hidden = resultado.datos.length !== 0;
    mostrarPaginacion(document.getElementById('paginacion'), resultado.pagina, resultado.paginas, cargarHistorial);
  } catch (error) {
    mostrarAlerta(error.message, 'danger');
  }
}

async function actualizarEstados() {
  try {
    const resultado = await llamarApi('estatus_usuario.php');
    const estados = new Map(resultado.datos.map((reporte) => [reporte.folio, reporte.nombre_estado]));
    document.querySelectorAll('#filas-reportes tr[data-folio]').forEach((fila) => {
      const estado = estados.get(fila.dataset.folio);
      const celda = fila.querySelector('[data-estado="actual"]');
      if (estado && celda) celda.textContent = estado;
    });
  } catch (error) {
    mostrarAlerta(error.message, 'danger');
  }
}

function agregarTexto(contenedor, etiqueta, valor) {
  const parrafo = document.createElement('p');
  const negrita = document.createElement('strong');
  negrita.textContent = etiqueta + ': ';
  parrafo.append(negrita, document.createTextNode(valor || '—'));
  contenedor.append(parrafo);
}

async function verDetalle(folio) {
  try {
    const resultado = await llamarApi('detalle_reporte.php?folio=' + encodeURIComponent(folio));
    const { reporte, historial, evidencias } = resultado.datos;
    const contenido = document.getElementById('detalle-contenido');
    contenido.replaceChildren();
    document.getElementById('modal-titulo').textContent = 'Reporte ' + reporte.folio;
    agregarTexto(contenido, 'Categoría', reporte.nombre_categoria);
    agregarTexto(contenido, 'Ubicación', [reporte.edificio, reporte.piso, reporte.area_general, reporte.espacio_especifico].join(' · '));
    agregarTexto(contenido, 'Estado actual', reporte.nombre_estado);
    agregarTexto(contenido, 'Área', reporte.nombre_area || 'Sin área asignada');
    agregarTexto(contenido, 'Descripción', reporte.descripcion);
    evidencias.forEach((evidencia) => {
      const enlace = document.createElement('a');
      enlace.href = '../Backend/evidencia.php?id=' + encodeURIComponent(evidencia.id_evidencia);
      enlace.target = '_blank';
      enlace.rel = 'noopener';
      enlace.textContent = 'Ver evidencia ' + evidencia.tipo_evidencia;
      enlace.className = 'd-block mb-2';
      contenido.append(enlace);
    });
    const titulo = document.createElement('h3');
    titulo.className = 'h6 mt-4';
    titulo.textContent = 'Línea de tiempo';
    contenido.append(titulo);
    historial.forEach((evento) => {
      const item = document.createElement('div');
      item.className = 'timeline-item';
      item.textContent = evento.fecha_hora + ' · ' + evento.nombre_estado + ' · ' + evento.accion_realizada + ' · ' + evento.responsable;
      contenido.append(item);
    });
    if (reporte.nombre_estado === 'Cerrado') {
      const enlace = document.createElement('a');
      enlace.className = 'btn btn-outline-primary mt-3';
      enlace.href = '../Backend/pdf_comprobante.php?folio=' + encodeURIComponent(reporte.folio);
      enlace.textContent = 'Descargar comprobante PDF';
      contenido.append(enlace);
    }
    modalDetalle.show();
  } catch (error) {
    mostrarAlerta(error.message, 'danger');
  }
}

async function iniciar() {
  const usuario = await llamarApi('me.php');
  if (!['Estudiante', 'Profesor'].includes(usuario.usuario.rol)) {
    window.location.href = 'inicio.html';
    return;
  }
  window.AlertaCampusLayout.renderizarNavbar(usuario.usuario, 'reportes');
  modalDetalle = new bootstrap.Modal(document.getElementById('modal-detalle'));
  configurarCascada();
  configurarWizard();
  const cats = await llamarApi('catalogos_ubicaciones.php?accion=categorias');
  cats.datos.forEach((dato) => {
    agregarOpcion(document.getElementById('categoria'), dato.id_categoria, dato.nombre_categoria);
    agregarOpcion(document.getElementById('f-categoria'), dato.id_categoria, dato.nombre_categoria);
  });
  const estados = await llamarApi('catalogos_ubicaciones.php?accion=estados');
  estados.datos.forEach((dato) => agregarOpcion(document.getElementById('f-estado'), dato.id_estado, dato.nombre_estado));
  document.getElementById('filtros-usuario').addEventListener('submit', (e) => { e.preventDefault(); cargarHistorial(1); });
  document.getElementById('limpiar-filtros').addEventListener('click', () => {
    document.getElementById('filtros-usuario').reset();
    cargarHistorial(1);
  });
  document.getElementById('exportar-historial').addEventListener('click', () => {
    const filtros = parametrosFiltro(document.getElementById('filtros-usuario'));
    window.location.href = API + 'pdf_historial_usuario.php?' + filtros.toString();
  });
  await cargarHistorial();
  window.setInterval(actualizarEstados, 30000);
}

iniciar().catch((error) => mostrarAlerta(error.message, 'danger'));
