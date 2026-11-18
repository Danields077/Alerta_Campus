const API_USUARIOS = '../Backend/usuarios.php';
const modalUsuario = new bootstrap.Modal(document.getElementById('modal-usuario'));
const modalEliminar = new bootstrap.Modal(document.getElementById('modal-eliminar-usuario'));
const usuariosPorId = new Map();
let paginaUsuarios = 1;
let idEliminar = null;

function mostrarAlertaUsuarios(texto, tipo = 'danger', idEditar = null) {
  const destino = document.getElementById('mensaje-global');
  destino.replaceChildren();
  const alerta = document.createElement('div');
  alerta.className = 'alert alert-' + tipo + ' alert-dismissible';
  alerta.setAttribute('role', 'alert');
  alerta.append(document.createTextNode(texto));
  if (idEditar) {
    const editar = document.createElement('button');
    editar.type = 'button';
    editar.className = 'btn btn-sm btn-outline-primary ms-2';
    editar.textContent = 'Editar usuario';
    editar.addEventListener('click', () => abrirEdicion(idEditar));
    alerta.append(editar);
  }
  const cerrar = document.createElement('button');
  cerrar.type = 'button';
  cerrar.className = 'btn-close';
  cerrar.setAttribute('data-bs-dismiss', 'alert');
  cerrar.setAttribute('aria-label', 'Cerrar');
  alerta.append(cerrar);
  destino.append(alerta);
}

async function llamarApiUsuarios(url, opciones = {}) {
  const respuesta = await fetch(url, { credentials: 'same-origin', ...opciones });
  const datos = await respuesta.json().catch(() => ({}));
  if (respuesta.status === 401) {
    window.location.href = 'index.html';
    throw new Error('La sesión expiró.');
  }
  if (respuesta.status === 403) {
    window.location.href = 'inicio.html';
    throw new Error(datos.mensaje || 'No tienes permisos para administrar usuarios.');
  }
  if (!respuesta.ok || !datos.ok) {
    const error = new Error(datos.detalle || datos.mensaje || 'No se pudo completar la solicitud.');
    error.idUsuario = datos.id_usuario || null;
    throw error;
  }
  return datos;
}

function agregarOpcionUsuario(select, valor, etiqueta) {
  const opcion = document.createElement('option');
  opcion.value = valor;
  opcion.textContent = etiqueta;
  select.append(opcion);
}

function mostrarPaginacionUsuarios(pagina, paginas) {
  const contenedor = document.getElementById('paginacion-usuarios');
  contenedor.replaceChildren();
  for (let numero = 1; numero <= paginas; numero++) {
    const item = document.createElement('li');
    item.className = 'page-item' + (numero === pagina ? ' active' : '');
    const boton = document.createElement('button');
    boton.type = 'button';
    boton.className = 'page-link';
    boton.textContent = String(numero);
    boton.addEventListener('click', () => cargarUsuarios(numero));
    item.append(boton);
    contenedor.append(item);
  }
}

function crearCeldaUsuario(fila, texto) {
  const celda = document.createElement('td');
  celda.textContent = texto || '—';
  fila.append(celda);
  return celda;
}

async function cargarUsuarios(pagina = 1) {
  paginaUsuarios = pagina;
  const parametros = new URLSearchParams(
    Array.from(new FormData(document.getElementById('filtros-usuarios')).entries())
      .filter(([, valor]) => valor !== '')
  );
  parametros.set('accion', 'listar');
  parametros.set('pagina', String(pagina));
  try {
    const resultado = await llamarApiUsuarios(API_USUARIOS + '?' + parametros.toString());
    paginaUsuarios = resultado.pagina;
    const filas = document.getElementById('filas-usuarios');
    filas.replaceChildren();
    usuariosPorId.clear();
    resultado.datos.forEach((usuario) => {
      usuariosPorId.set(Number(usuario.id_usuario), usuario);
      const fila = document.createElement('tr');
      crearCeldaUsuario(fila, usuario.nombre_completo);
      crearCeldaUsuario(fila, usuario.correo);
      crearCeldaUsuario(fila, usuario.nombre_rol);
      crearCeldaUsuario(fila, usuario.nombre_area || '—');
      const estado = crearCeldaUsuario(fila, '');
      const insignia = document.createElement('span');
      insignia.className = 'badge text-bg-' + (usuario.estado === 'Activo' ? 'success' : 'secondary');
      insignia.textContent = usuario.estado;
      estado.append(insignia);
      const acciones = crearCeldaUsuario(fila, '');
      const editar = document.createElement('button');
      editar.type = 'button';
      editar.className = 'btn btn-sm btn-outline-primary me-1';
      editar.textContent = 'Editar';
      editar.addEventListener('click', () => abrirEdicion(usuario.id_usuario));
      const eliminar = document.createElement('button');
      eliminar.type = 'button';
      eliminar.className = 'btn btn-sm btn-outline-danger';
      eliminar.textContent = 'Eliminar';
      eliminar.addEventListener('click', () => solicitarEliminacion(usuario.id_usuario));
      acciones.append(editar, eliminar);
      filas.append(fila);
    });
    document.getElementById('sin-usuarios').hidden = resultado.datos.length > 0;
    mostrarPaginacionUsuarios(resultado.pagina, resultado.paginas);
  } catch (error) {
    mostrarAlertaUsuarios(error.message);
  }
}

function actualizarCampoArea() {
  const requiereArea = ['Personal de atención', 'Jefe de departamento'].includes(
    document.getElementById('usuario-perfil').value
  );
  const grupo = document.getElementById('grupo-area');
  const selector = document.getElementById('usuario-area');
  grupo.hidden = !requiereArea;
  selector.required = requiereArea;
  if (!requiereArea) selector.value = '';
}

function prepararNuevoUsuario() {
  const formulario = document.getElementById('form-usuario');
  formulario.reset();
  document.getElementById('id-usuario').value = '';
  document.getElementById('modal-usuario-titulo').textContent = 'Nuevo usuario';
  document.getElementById('grupo-estado').hidden = true;
  document.getElementById('usuario-password').required = true;
  document.getElementById('password-opcional').textContent = '';
  document.getElementById('password-ayuda').textContent = 'Mínimo 8 caracteres, con letras y números.';
  actualizarCampoArea();
  modalUsuario.show();
}

function abrirEdicion(id) {
  const usuario = usuariosPorId.get(Number(id));
  if (!usuario) {
    mostrarAlertaUsuarios('No se pudo cargar la información del usuario.');
    return;
  }
  const formulario = document.getElementById('form-usuario');
  formulario.reset();
  document.getElementById('id-usuario').value = usuario.id_usuario;
  document.getElementById('usuario-nombre').value = usuario.nombre_completo;
  document.getElementById('usuario-identificador').value = usuario.identificador_institucional;
  document.getElementById('usuario-correo').value = usuario.correo;
  document.getElementById('usuario-perfil').value = usuario.nombre_rol;
  document.getElementById('usuario-area').value = usuario.id_area || '';
  document.getElementById('usuario-estado').value = usuario.estado;
  document.getElementById('usuario-password').value = '';
  document.getElementById('usuario-password').required = false;
  document.getElementById('password-opcional').textContent = '(opcional)';
  document.getElementById('password-ayuda').textContent = 'Déjala vacía para conservar la contraseña actual.';
  document.getElementById('grupo-estado').hidden = false;
  document.getElementById('modal-usuario-titulo').textContent = 'Editar usuario';
  actualizarCampoArea();
  modalUsuario.show();
}

function solicitarEliminacion(id) {
  idEliminar = Number(id);
  modalEliminar.show();
}

async function inicializarUsuarios() {
  try {
    const sesion = await llamarApiUsuarios('../Backend/me.php');
    if (!sesion.usuario || sesion.usuario.rol !== 'Administrador') {
      window.location.href = 'inicio.html';
      return;
    }
    window.AlertaCampusLayout.renderizarNavbar(sesion.usuario, 'usuarios');
    const catalogos = await llamarApiUsuarios(API_USUARIOS + '?accion=catalogos');
    const filtroPerfil = document.getElementById('filtro-perfil');
    const perfil = document.getElementById('usuario-perfil');
    catalogos.roles.forEach((rol) => {
      agregarOpcionUsuario(filtroPerfil, rol, rol);
      agregarOpcionUsuario(perfil, rol, rol);
    });
    const area = document.getElementById('usuario-area');
    catalogos.areas.forEach((dato) => agregarOpcionUsuario(area, dato.id_area, dato.nombre_area));
    await cargarUsuarios();
  } catch (error) {
    mostrarAlertaUsuarios(error.message);
  }
}

document.getElementById('filtros-usuarios').addEventListener('submit', (evento) => {
  evento.preventDefault();
  cargarUsuarios(1);
});
document.getElementById('limpiar-filtros').addEventListener('click', () => {
  window.setTimeout(() => cargarUsuarios(1), 0);
});
document.getElementById('nuevo-usuario').addEventListener('click', prepararNuevoUsuario);
document.getElementById('usuario-perfil').addEventListener('change', actualizarCampoArea);

document.getElementById('form-usuario').addEventListener('submit', async (evento) => {
  evento.preventDefault();
  const formulario = evento.currentTarget;
  if (!formulario.reportValidity()) return;
  const password = document.getElementById('usuario-password').value;
  if (password && (password.length < 8 || !/\p{L}/u.test(password) || !/[0-9]/.test(password))) {
    mostrarAlertaUsuarios('La contraseña debe tener al menos 8 caracteres, letras y números.', 'warning');
    return;
  }
  const entrada = Object.fromEntries(new FormData(formulario).entries());
  entrada.accion = entrada.id_usuario ? 'editar' : 'crear';
  try {
    const resultado = await llamarApiUsuarios(API_USUARIOS, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(entrada),
    });
    modalUsuario.hide();
    mostrarAlertaUsuarios(resultado.mensaje, 'success');
    await cargarUsuarios(paginaUsuarios);
  } catch (error) {
    mostrarAlertaUsuarios(error.message, 'danger', error.idUsuario);
  }
});

document.getElementById('confirmar-eliminar').addEventListener('click', async () => {
  if (!idEliminar) return;
  try {
    const resultado = await llamarApiUsuarios(API_USUARIOS, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'eliminar', id_usuario: idEliminar }),
    });
    modalEliminar.hide();
    mostrarAlertaUsuarios(resultado.mensaje, 'success');
    await cargarUsuarios(paginaUsuarios);
  } catch (error) {
    modalEliminar.hide();
    mostrarAlertaUsuarios(error.message, 'warning', error.idUsuario);
  } finally {
    idEliminar = null;
  }
});

inicializarUsuarios();
