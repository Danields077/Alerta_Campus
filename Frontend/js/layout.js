/* Componentes compartidos de navegación y accesos rápidos por rol. */
(function () {
  function crearElemento(etiqueta, clase, texto) {
    const elemento = document.createElement(etiqueta);
    if (clase) elemento.className = clase;
    if (texto) elemento.textContent = texto;
    return elemento;
  }

  function renderizarNavbar(usuario, paginaActiva) {
    const destino = document.getElementById('app-navbar');
    if (!destino) return;

    const navbar = crearElemento('nav', 'navbar navbar-expand-lg navbar-dark navbar-campus shadow-sm');
    const contenedor = crearElemento('div', 'container');
    const marca = crearElemento('a', 'navbar-brand d-flex align-items-center gap-2');
    marca.href = 'inicio.html';
    const logo = document.createElement('img');
    logo.src = 'assets/logo-placeholder.svg';
    logo.alt = '';
    logo.width = 38;
    logo.height = 38;
    const nombreMarca = crearElemento('span', '', 'Alerta Campus');
    marca.append(logo, nombreMarca);

    const alternar = crearElemento('button', 'navbar-toggler');
    alternar.type = 'button';
    alternar.setAttribute('data-bs-toggle', 'collapse');
    alternar.setAttribute('data-bs-target', '#menu-principal');
    alternar.setAttribute('aria-controls', 'menu-principal');
    alternar.setAttribute('aria-expanded', 'false');
    alternar.setAttribute('aria-label', 'Abrir menú de navegación');
    alternar.append(crearElemento('span', 'navbar-toggler-icon'));

    const menu = crearElemento('div', 'collapse navbar-collapse');
    menu.id = 'menu-principal';
    const enlaces = crearElemento('ul', 'navbar-nav me-auto mb-2 mb-lg-0');
    const opciones = [{ texto: 'Inicio', url: 'inicio.html', pagina: 'inicio' }];
    if (['Estudiante', 'Profesor'].includes(usuario.rol)) {
      opciones.push({ texto: 'Mis reportes', url: 'reportes.html', pagina: 'reportes' });
    } else if (['Administrador', 'Jefe de departamento'].includes(usuario.rol)) {
      opciones.push({ texto: 'Consulta general', url: 'consulta_general.html', pagina: 'consulta' });
    }
    if (['Administrador', 'Jefe de departamento', 'Personal de atención'].includes(usuario.rol)) {
      opciones.push({ texto: 'Atención de reportes', url: 'area.html', pagina: 'area' });
    }
    if (usuario.rol === 'Jefe de departamento') {
      opciones.push({ texto: 'Control de calidad', url: 'control_calidad.html', pagina: 'calidad' });
    }
    if (usuario.rol === 'Administrador') {
      opciones.push({ texto: 'Usuarios', url: 'usuarios.html', pagina: 'usuarios' });
      opciones.push({ texto: 'Categorías', url: 'categorias.html', pagina: 'categorias' });
      opciones.push({ texto: 'Ubicaciones', url: 'ubicaciones.html', pagina: 'ubicaciones' });
    }
    opciones.push({ texto: 'Notificaciones', url: 'notificaciones.html', pagina: 'notificaciones' });

    opciones.forEach((opcion) => {
      const item = crearElemento('li', 'nav-item');
      const enlace = crearElemento('a', 'nav-link', opcion.texto);
      enlace.href = opcion.url;
      if (opcion.pagina === paginaActiva) {
        enlace.classList.add('active');
        enlace.setAttribute('aria-current', 'page');
      }
      item.append(enlace);
      enlaces.append(item);
    });

    const acciones = crearElemento('div', 'd-flex flex-column flex-lg-row align-items-lg-center gap-2');
    const identidad = crearElemento('span', 'navbar-text navbar-identidad', usuario.nombre + ' · ' + usuario.rol);
    const salir = crearElemento('button', 'btn btn-light btn-sm', 'Cerrar sesión');
    salir.type = 'button';
    salir.addEventListener('click', cerrarSesion);
    acciones.append(identidad, salir);
    menu.append(enlaces, acciones);
    contenedor.append(marca, alternar, menu);
    navbar.append(contenedor);
    destino.replaceChildren(navbar);
  }

  async function cerrarSesion() {
    try {
      const respuesta = await fetch('../Backend/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
      });
      if (!respuesta.ok) throw new Error('No se pudo cerrar la sesión.');
      window.location.href = 'index.html';
    } catch (error) {
      const destino = document.getElementById('mensaje-global');
      if (destino) {
        destino.replaceChildren(crearElemento('div', 'alert alert-danger', error.message));
      }
    }
  }

  function crearTarjeta(accesos, acceso) {
    const columna = crearElemento('div', 'col-md-6 col-xl-4');
    const tarjeta = crearElemento('article', 'card dashboard-card h-100');
    const cuerpo = crearElemento('div', 'card-body d-flex flex-column');
    const titulo = crearElemento('h3', 'h5 card-title', acceso.titulo);
    const descripcion = crearElemento('p', 'card-text text-muted', acceso.descripcion);
    const enlace = crearElemento('a', 'btn btn-primary mt-auto align-self-start', acceso.accion);
    enlace.href = acceso.url;
    cuerpo.append(titulo, descripcion, enlace);
    tarjeta.append(cuerpo);
    columna.append(tarjeta);
    accesos.append(columna);
  }

  function renderizarAccesos(usuario) {
    const destino = document.getElementById('acciones-inicio');
    if (!destino) return;
    destino.replaceChildren();
    let accesos = [];
    if (['Estudiante', 'Profesor'].includes(usuario.rol)) {
      accesos = [
        {
          titulo: 'Registrar incidencia',
          descripcion: 'Informa sobre un problema del campus y adjunta evidencia opcional.',
          accion: 'Crear reporte',
          url: 'reportes.html#form-reporte',
        },
        {
          titulo: 'Historial y estatus',
          descripcion: 'Consulta tus reportes, su estado actual y la línea de tiempo.',
          accion: 'Ver mis reportes',
          url: 'reportes.html#filtros-usuario',
        },
        {
          titulo: 'Notificaciones',
          descripcion: 'Consulta los avisos sobre el estado de tus reportes.',
          accion: 'Ver notificaciones',
          url: 'notificaciones.html',
        },
      ];
    } else if (usuario.rol === 'Administrador') {
      accesos = [{
        titulo: 'Consulta general',
        descripcion: 'Revisa reportes de todas las áreas, incluyendo los aún no asignados.',
        accion: 'Consultar reportes',
        url: 'consulta_general.html',
      }];
      accesos.push({
        titulo: 'Notificaciones',
        descripcion: 'Consulta los avisos generados por los reportes.',
        accion: 'Ver notificaciones',
        url: 'notificaciones.html',
      });
      accesos.push({
        titulo: 'Vista de atención',
        descripcion: 'Visualiza todos los reportes y filtra por área y estado.',
        accion: 'Ver todos los reportes',
        url: 'area.html',
      });
      accesos.push({
        titulo: 'Gestión de usuarios',
        descripcion: 'Registra, consulta, actualiza y administra las cuentas del campus.',
        accion: 'Administrar usuarios',
        url: 'usuarios.html',
      });
      accesos.push({
        titulo: 'Categorías',
        descripcion: 'Administra las categorías disponibles para clasificar incidencias.',
        accion: 'Gestionar categorías',
        url: 'categorias.html',
      });
      accesos.push({
        titulo: 'Ubicaciones',
        descripcion: 'Administra las ubicaciones disponibles para los reportes.',
        accion: 'Gestionar ubicaciones',
        url: 'ubicaciones.html',
      });
    } else if (usuario.rol === 'Jefe de departamento') {
      accesos = [{
        titulo: 'Reportes de mi área',
        descripcion: 'Consulta y filtra los reportes asignados a tu departamento.',
        accion: 'Consultar mi área',
        url: 'consulta_general.html',
      }];
      accesos.push({
        titulo: 'Atención de reportes',
        descripcion: 'Atiende, registra avances y verifica las incidencias asignadas.',
        accion: 'Abrir atención',
        url: 'area.html',
      });
      accesos.push({
        titulo: 'Control de calidad',
        descripcion: 'Revisa las soluciones de reportes y aprueba o devuelve su atención.',
        accion: 'Revisar reportes',
        url: 'control_calidad.html',
      });
      accesos.push({
        titulo: 'Notificaciones',
        descripcion: 'Consulta los avisos generados por los reportes de tu área.',
        accion: 'Ver notificaciones',
        url: 'notificaciones.html',
      });
    } else if (usuario.rol === 'Personal de atención') {
      accesos = [{
        titulo: 'Atención de reportes',
        descripcion: 'Atiende los reportes asignados a tu área y registra avances.',
        accion: 'Abrir atención',
        url: 'area.html',
      }];
      accesos.push({
        titulo: 'Notificaciones',
        descripcion: 'Consulta los avisos sobre los reportes asignados a tu área.',
        accion: 'Ver notificaciones',
        url: 'notificaciones.html',
      });
    }
    accesos.forEach((acceso) => crearTarjeta(destino, acceso));
  }

  window.AlertaCampusLayout = {
    renderizarNavbar,
    renderizarAccesos,
  };
})();
