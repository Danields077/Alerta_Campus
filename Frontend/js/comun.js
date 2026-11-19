/**
 * Utilidades compartidas por las páginas de los módulos 4, 6, 7 y 8
 * (alertas, llamadas a la API, tablas y paginación).
 * Se carga después de Bootstrap y antes del script de cada página.
 */
(function () {
  const API = '../Backend/';

  function crear(etiqueta, clase, texto) {
    const elemento = document.createElement(etiqueta);
    if (clase) elemento.className = clase;
    if (texto !== undefined && texto !== null && texto !== '') elemento.textContent = texto;
    return elemento;
  }

  /** Muestra una alerta Bootstrap en #mensaje-global (tipo: success | warning | danger | info). */
  function mostrarAlerta(texto, tipo = 'danger') {
    const destino = document.getElementById('mensaje-global');
    if (!destino) return;
    const alerta = crear('div', 'alert alert-' + tipo + ' alert-dismissible');
    alerta.setAttribute('role', 'alert');
    alerta.append(document.createTextNode(texto));
    const cerrar = crear('button', 'btn-close');
    cerrar.type = 'button';
    cerrar.setAttribute('data-bs-dismiss', 'alert');
    cerrar.setAttribute('aria-label', 'Cerrar');
    alerta.append(cerrar);
    destino.replaceChildren(alerta);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /**
   * Llama a un endpoint del Backend. Devuelve el JSON si todo salió bien; si no,
   * lanza un Error cuyo mensaje es "<mensaje>. <detalle>" y que lleva .tipo
   * ('warning' o 'danger') para elegir el color de la alerta.
   */
  async function llamarApi(ruta, opciones = {}) {
    const respuesta = await fetch(API + ruta, { credentials: 'same-origin', ...opciones });
    const datos = await respuesta.json().catch(() => ({}));
    if (respuesta.status === 401) {
      window.location.href = 'index.html';
      throw new Error('La sesión expiró.');
    }
    if (respuesta.status === 403) {
      window.location.href = 'inicio.html';
      throw new Error(datos.mensaje || 'No tienes permisos para esta sección.');
    }
    if (!respuesta.ok || !datos.ok) {
      const base = String(datos.mensaje || 'No se pudo completar la solicitud').replace(/\.$/, '');
      const error = new Error(datos.detalle ? base + '. ' + datos.detalle : base + (datos.mensaje ? '' : '.'));
      error.tipo = datos.tipo || (respuesta.status === 409 || respuesta.status === 400 ? 'warning' : 'danger');
      throw error;
    }
    return datos;
  }

  /** POST con cuerpo JSON. */
  function enviarJson(ruta, datos) {
    return llamarApi(ruta, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(datos),
    });
  }

  function celda(fila, texto) {
    const td = crear('td');
    td.textContent = texto === null || texto === undefined || texto === '' ? '—' : texto;
    fila.append(td);
    return td;
  }

  function opcion(select, valor, etiqueta) {
    const o = crear('option', '', etiqueta);
    o.value = valor;
    select.append(o);
  }

  /** "2026-11-17 18:20:00" -> "17/11/2026 18:20" */
  function formatearFecha(texto) {
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(texto || ''));
    return m ? m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] : (texto || '—');
  }

  function ubicacionTexto(r) {
    return [r.edificio, r.piso, r.espacio_especifico].filter(Boolean).join(' · ');
  }

  /** Dibuja los botones de paginación de Bootstrap. */
  function paginacion(contenedor, pagina, paginas, alCambiar) {
    contenedor.replaceChildren();
    if (paginas <= 1) return;
    for (let n = 1; n <= paginas; n++) {
      const item = crear('li', 'page-item' + (n === pagina ? ' active' : ''));
      const boton = crear('button', 'page-link', String(n));
      boton.type = 'button';
      boton.addEventListener('click', () => alCambiar(n));
      item.append(boton);
      contenedor.append(item);
    }
  }

  /** Valores no vacíos de un formulario como URLSearchParams. */
  function parametrosDeFormulario(formulario) {
    return new URLSearchParams(
      Array.from(new FormData(formulario).entries()).filter(([, valor]) => String(valor).trim() !== '')
    );
  }

  /** Verifica la sesión y que el perfil esté permitido; dibuja la barra de navegación. */
  async function iniciarPagina(rolesPermitidos, paginaActiva) {
    const sesion = await llamarApi('me.php');
    if (!rolesPermitidos.includes(sesion.usuario.rol)) {
      window.location.href = 'inicio.html';
      throw new Error('Sin permiso');
    }
    window.AlertaCampusLayout.renderizarNavbar(sesion.usuario, paginaActiva);
    return sesion.usuario;
  }

  window.AlertaCampusComun = {
    crear, mostrarAlerta, llamarApi, enviarJson, celda, opcion, formatearFecha,
    ubicacionTexto, paginacion, parametrosDeFormulario, iniciarPagina,
  };
})();
