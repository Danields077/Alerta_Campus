/* Alerta Campus — lógica del front-end (login, registro y sesión) */

// ---------- Utilidades ----------
function mostrarMensaje(texto, tipo) {
  const caja = document.getElementById('mensaje');
  if (!caja) return;
  caja.textContent = texto;
  caja.className = 'mensaje visible ' + tipo; // tipo: "error" | "exito"
}

// Rutas relativas: funciona desde cualquier página dentro de Frontend/.
async function llamarApi(archivo, cuerpo) {
  const opciones = cuerpo
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cuerpo) }
    : {};
  const respuesta = await fetch('../Backend/' + archivo + '.php', opciones);
  const datos = await respuesta.json().catch(() => ({}));
  return { status: respuesta.status, ...datos };
}

// ---------- Botón "Ver / Ocultar" contraseña ----------
document.querySelectorAll('.ver-password').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.target);
    const oculto = input.type === 'password';
    input.type = oculto ? 'text' : 'password';
    btn.textContent = oculto ? 'Ocultar' : 'Ver';
  });
});

// ---------- LOGIN ----------
const formLogin = document.getElementById('form-login');
if (formLogin) {
  if (new URLSearchParams(window.location.search).get('registro') === 'ok') {
    mostrarMensaje('El usuario se registró exitosamente', 'exito');
  }
  // Si ya hay sesión, pasar directo al inicio
  llamarApi('me').then((r) => { if (r.ok) window.location.href = 'inicio.html'; }).catch(() => {});

  formLogin.addEventListener('submit', async (e) => {
    e.preventDefault();
    const boton = document.getElementById('btn-enviar');
    const correo = document.getElementById('correo').value.trim();
    const password = document.getElementById('password').value;

    if (!correo || !password) {
      return mostrarMensaje('Ingresa tu correo y contraseña', 'error');
    }

    boton.disabled = true;
    try {
      const r = await llamarApi('login', { correo, password });
      if (r.ok) {
        window.location.href = 'inicio.html';
      } else {
        mostrarMensaje(r.mensaje || 'No se pudo iniciar sesión', 'error');
      }
    } catch (err) {
      mostrarMensaje('No se pudo conectar. ¿Está encendido Apache en XAMPP?', 'error');
    } finally {
      boton.disabled = false;
    }
  });
}

// ---------- REGISTRO ----------
const formRegistro = document.getElementById('form-registro');
if (formRegistro) {
  formRegistro.addEventListener('submit', async (e) => {
    e.preventDefault();
    const boton = document.getElementById('btn-enviar');
    const nombre = document.getElementById('nombre').value.trim();
    const identificadorInstitucional = document.getElementById('identificador_institucional').value.trim();
    const correo = document.getElementById('correo').value.trim();
    const perfil = document.getElementById('perfil').value;
    const password = document.getElementById('password').value;
    const confirmar = document.getElementById('confirmar').value;

    if (!nombre || !identificadorInstitucional || !correo || !perfil || !password || !confirmar) {
      return mostrarMensaje('Completa todos los campos', 'error');
    }
    if (password.length < 8 || !/\p{L}/u.test(password) || !/[0-9]/.test(password)) {
      return mostrarMensaje('La contraseña debe tener al menos 8 caracteres, letras y números.', 'error');
    }
    if (password !== confirmar) {
      return mostrarMensaje('Las contraseñas no coinciden', 'error');
    }

    boton.disabled = true;
    try {
      const r = await llamarApi('registro', {
        nombre,
        identificador_institucional: identificadorInstitucional,
        correo,
        perfil,
        password,
        confirmar
      });
      if (r.ok) {
        window.location.href = 'index.html?registro=ok';
      } else {
        mostrarMensaje(r.mensaje || 'No se pudo crear la cuenta', 'error');
        boton.disabled = false;
      }
    } catch (err) {
      mostrarMensaje('No se pudo conectar. ¿Está encendido Apache en XAMPP?', 'error');
      boton.disabled = false;
    }
  });
}

// ---------- Panel de control (requiere sesión) ----------
const accionesInicio = document.getElementById('acciones-inicio');
if (accionesInicio) {
  llamarApi('me').then((r) => {
    if (!r.ok || !r.usuario) {
      window.location.href = 'index.html';
      return;
    }
    window.AlertaCampusLayout.renderizarNavbar(r.usuario, 'inicio');
    window.AlertaCampusLayout.renderizarAccesos(r.usuario);
    document.getElementById('saludo').textContent = 'Bienvenido, ' + r.usuario.nombre;
    document.getElementById('detalle').textContent = r.usuario.correo + ' · ' + r.usuario.rol;
  }).catch(() => { window.location.href = 'index.html'; });
}
