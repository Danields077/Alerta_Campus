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
    const password = document.getElementById('password').value;
    const confirmar = document.getElementById('confirmar').value;

    if (!nombre || !identificadorInstitucional || !correo || !password || !confirmar) {
      return mostrarMensaje('Completa todos los campos', 'error');
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
        password
      });
      if (r.ok) {
        mostrarMensaje('Cuenta creada. Redirigiendo al inicio de sesión…', 'exito');
        setTimeout(() => (window.location.href = 'index.html'), 1400);
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

// ---------- PANTALLA DE INICIO (requiere sesión) ----------
const btnSalir = document.getElementById('btn-salir');
if (btnSalir) {
  llamarApi('me').then((r) => {
    if (r.ok) {
      document.getElementById('saludo').textContent = 'Bienvenido, ' + r.usuario.nombre;
      document.getElementById('detalle').textContent = 'Sesión iniciada con ' + r.usuario.correo;
    } else {
      window.location.href = 'index.html';
    }
  }).catch(() => { window.location.href = 'index.html'; });

  btnSalir.addEventListener('click', async () => {
    await llamarApi('logout', {}).catch(() => {});
    window.location.href = 'index.html';
  });
}
