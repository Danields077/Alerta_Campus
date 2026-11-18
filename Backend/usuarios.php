<?php
require_once __DIR__ . '/api_comun.php';
require_once __DIR__ . '/usuarios_validacion.php';

$administrador = requerirSesion(['Administrador']);
$perfiles = [
    'Estudiante',
    'Profesor',
    'Personal de atención',
    'Jefe de departamento',
    'Administrador',
];
$perfilesConArea = ['Personal de atención', 'Jefe de departamento'];

function responderUsuarios(int $codigo, array $datos): void
{
    responder($codigo, $datos);
}

function validarAreaUsuario(PDO $conn, string $perfil, $areaEntrada): ?int
{
    global $perfilesConArea;
    if (!in_array($perfil, $perfilesConArea, true)) {
        return null;
    }
    $idArea = filter_var($areaEntrada, FILTER_VALIDATE_INT);
    if ($idArea === false || $idArea === null || $idArea < 1) {
        return -1;
    }
    $consulta = $conn->prepare('SELECT id_area FROM areas_atencion WHERE id_area = ?');
    $consulta->execute([$idArea]);
    return $consulta->fetchColumn() === false ? -1 : (int) $idArea;
}

function correoDuplicado(PDO $conn, string $correo, int $idUsuario = 0): bool
{
    $consulta = $conn->prepare(
        'SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario <> ? LIMIT 1'
    );
    $consulta->execute([$correo, $idUsuario]);
    return (bool) $consulta->fetchColumn();
}

function identificadorDuplicado(PDO $conn, string $identificador, int $idUsuario = 0): bool
{
    $consulta = $conn->prepare(
        'SELECT id_usuario FROM usuarios WHERE identificador_institucional = ? AND id_usuario <> ? LIMIT 1'
    );
    $consulta->execute([$identificador, $idUsuario]);
    return (bool) $consulta->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $accion = (string) ($_GET['accion'] ?? 'listar');
    if ($accion === 'catalogos') {
        $roles = $conn->query('SELECT nombre_rol FROM roles ORDER BY nombre_rol')->fetchAll(PDO::FETCH_COLUMN);
        $areas = $conn->query('SELECT id_area, nombre_area FROM areas_atencion ORDER BY nombre_area')->fetchAll();
        responder(200, ['ok' => true, 'roles' => $roles, 'areas' => $areas]);
    }
    if ($accion !== 'listar') {
        responder(400, ['ok' => false, 'mensaje' => 'Consulta de usuarios no válida.']);
    }

    $nombre = trim((string) ($_GET['nombre'] ?? ''));
    $correo = trim((string) ($_GET['correo'] ?? ''));
    $perfil = trim((string) ($_GET['perfil'] ?? ''));
    $estado = trim((string) ($_GET['estado'] ?? ''));
    if (mb_strlen($nombre) > 150 || mb_strlen($correo) > 190) {
        responder(400, ['ok' => false, 'mensaje' => 'El filtro supera la longitud permitida.']);
    }
    $where = [];
    $valores = [];
    foreach (['nombre' => ['u.nombre_completo', $nombre], 'correo' => ['u.correo', $correo]] as $campo => $filtro) {
        if ($filtro[1] !== '') {
            $literal = strtr($filtro[1], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $where[] = $filtro[0] . " LIKE :$campo ESCAPE '!'";
            $valores[$campo] = '%' . $literal . '%';
        }
    }
    if ($perfil !== '') {
        if (!in_array($perfil, $perfiles, true)) {
            responder(400, ['ok' => false, 'mensaje' => 'El perfil seleccionado no es válido.']);
        }
        $where[] = 'r.nombre_rol = :perfil';
        $valores['perfil'] = $perfil;
    }
    if ($estado !== '') {
        if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
            responder(400, ['ok' => false, 'mensaje' => 'El estado seleccionado no es válido.']);
        }
        $where[] = 'u.estado = :estado';
        $valores['estado'] = $estado;
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $base = ' FROM usuarios u JOIN roles r ON r.id_rol = u.id_rol
              LEFT JOIN areas_atencion a ON a.id_area = u.id_area';
    $conteo = $conn->prepare('SELECT COUNT(*)' . $base . $whereSql);
    $conteo->execute($valores);
    $total = (int) $conteo->fetchColumn();
    $paginaEntrada = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT);
    $pagina = $paginaEntrada === false || $paginaEntrada < 1 ? 1 : $paginaEntrada;
    $limite = 15;
    $paginas = (int) ceil($total / $limite);
    if ($paginas > 0) {
        $pagina = min($pagina, $paginas);
    }
    $consulta = $conn->prepare(
        'SELECT u.id_usuario, u.identificador_institucional, u.nombre_completo, u.correo,
                u.id_rol, u.id_area, u.estado, r.nombre_rol, a.nombre_area' .
        $base . $whereSql . ' ORDER BY u.nombre_completo, u.id_usuario LIMIT :limite OFFSET :offset'
    );
    foreach ($valores as $clave => $valor) {
        $consulta->bindValue(':' . $clave, $valor, PDO::PARAM_STR);
    }
    $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);
    $consulta->bindValue(':offset', ($pagina - 1) * $limite, PDO::PARAM_INT);
    $consulta->execute();
    responder(200, [
        'ok' => true,
        'datos' => $consulta->fetchAll(),
        'total' => $total,
        'pagina' => $pagina,
        'paginas' => $paginas,
        'mensaje' => $total === 0 ? 'No se encontraron usuarios' : '',
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$entrada = obtenerEntradaJson();
$accion = trim((string) ($entrada['accion'] ?? ''));
if (!in_array($accion, ['crear', 'editar', 'eliminar'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'Acción de usuarios no válida.']);
}
$idUsuario = filter_var($entrada['id_usuario'] ?? null, FILTER_VALIDATE_INT);
if ($accion !== 'crear' && ($idUsuario === false || $idUsuario === null || $idUsuario < 1)) {
    responder(400, ['ok' => false, 'mensaje' => 'El identificador del usuario no es válido.']);
}

if ($accion === 'eliminar') {
    if ($idUsuario === (int) $administrador['id_usuario']) {
        responder(403, ['ok' => false, 'mensaje' => 'El usuario no se eliminó exitosamente', 'detalle' => 'No puedes eliminar tu propia cuenta.']);
    }
    try {
        $conn->beginTransaction();
        $existente = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ? FOR UPDATE');
        $existente->execute([$idUsuario]);
        if (!$existente->fetchColumn()) {
            $conn->rollBack();
            responder(404, ['ok' => false, 'mensaje' => 'El usuario no se eliminó exitosamente', 'detalle' => 'El usuario no existe.']);
        }
        $referencias = $conn->prepare(
            'SELECT 1 FROM reportes WHERE id_usuario_creador = ? OR id_jefe_verificador = ?
             UNION ALL
             SELECT 1 FROM historial_reportes WHERE id_usuario_accion = ?
             LIMIT 1'
        );
        $referencias->execute([$idUsuario, $idUsuario, $idUsuario]);
        if ($referencias->fetchColumn()) {
            $conn->rollBack();
            responder(409, [
                'ok' => false,
                'mensaje' => 'El usuario no se eliminó exitosamente',
                'detalle' => 'El usuario tiene reportes o acciones en el historial asociados. Se recomienda cambiar su estado a Inactivo.',
                'id_usuario' => (int) $idUsuario,
            ]);
        }
        $eliminar = $conn->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
        $eliminar->execute([$idUsuario]);
        $conn->commit();
        responder(200, ['ok' => true, 'mensaje' => 'El usuario se eliminó exitosamente']);
    } catch (PDOException $error) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($error->getCode() === '23000') {
            responder(409, [
                'ok' => false,
                'mensaje' => 'El usuario no se eliminó exitosamente',
                'detalle' => 'Hay información relacionada que impide eliminar la cuenta. Se recomienda cambiar su estado a Inactivo.',
                'id_usuario' => (int) $idUsuario,
            ]);
        }
        error_log('Error al eliminar usuario: ' . $error->getMessage());
        responder(500, ['ok' => false, 'mensaje' => 'El usuario no se eliminó exitosamente']);
    }
}

$nombre = trim((string) ($entrada['nombre'] ?? ''));
$identificador = trim((string) ($entrada['identificador_institucional'] ?? ''));
$correo = mb_strtolower(trim((string) ($entrada['correo'] ?? '')), 'UTF-8');
$perfil = trim((string) ($entrada['perfil'] ?? ''));
$estado = $accion === 'crear' ? 'Activo' : trim((string) ($entrada['estado'] ?? ''));
$area = validarAreaUsuario($conn, $perfil, $entrada['id_area'] ?? null);
$password = (string) ($entrada['password'] ?? '');

if ($nombre === '' || $identificador === '' || $correo === '' || $perfil === '' || $estado === '') {
    responder(400, ['ok' => false, 'mensaje' => 'Completa todos los campos obligatorios.']);
}
if (mb_strlen($nombre) > 150 || mb_strlen($identificador) > 50) {
    responder(400, ['ok' => false, 'mensaje' => 'El nombre o identificador supera la longitud permitida.']);
}
if (!in_array($perfil, $perfiles, true)) {
    responder(400, ['ok' => false, 'mensaje' => 'El perfil seleccionado no es válido.']);
}
if (!validarCorreoInstitucional($correo)) {
    responder(400, ['ok' => false, 'mensaje' => 'Ingresa un correo válido del dominio institucional.']);
}
if ($area === -1) {
    responder(400, ['ok' => false, 'mensaje' => 'Selecciona un área válida para este perfil.']);
}
if (!in_array($estado, ['Activo', 'Inactivo'], true)) {
    responder(400, ['ok' => false, 'mensaje' => 'El estado seleccionado no es válido.']);
}
if ($accion === 'crear' && $password === '') {
    responder(400, ['ok' => false, 'mensaje' => 'La contraseña es obligatoria.']);
}
if ($password !== '' && !validarPasswordUsuario($password)) {
    responder(400, ['ok' => false, 'mensaje' => 'La contraseña debe tener al menos 8 caracteres, letras y números.']);
}
if (correoDuplicado($conn, $correo, $accion === 'editar' ? (int) $idUsuario : 0)) {
    responder(409, ['ok' => false, 'mensaje' => 'El correo ya pertenece a otro usuario.']);
}
if (identificadorDuplicado($conn, $identificador, $accion === 'editar' ? (int) $idUsuario : 0)) {
    responder(409, ['ok' => false, 'mensaje' => 'El identificador institucional ya pertenece a otro usuario.']);
}

$idRol = obtenerIdRol($conn, $perfil);
if ($idRol === null) {
    responder(400, ['ok' => false, 'mensaje' => 'El perfil seleccionado no está configurado.']);
}

if ($accion === 'editar') {
    $actual = $conn->prepare('SELECT id_usuario, id_rol, estado FROM usuarios WHERE id_usuario = ?');
    $actual->execute([$idUsuario]);
    $usuarioActual = $actual->fetch();
    if (!$usuarioActual) {
        responder(404, ['ok' => false, 'mensaje' => 'El usuario no se actualizó exitosamente']);
    }
    if ((int) $idUsuario === (int) $administrador['id_usuario']
        && ($perfil !== 'Administrador' || $estado !== 'Activo')) {
        responder(403, [
            'ok' => false,
            'mensaje' => 'El usuario no se actualizó exitosamente',
            'detalle' => 'No puedes quitarte el perfil de Administrador ni desactivar tu propia cuenta.',
        ]);
    }
}

try {
    if ($accion === 'crear') {
        $insertar = $conn->prepare(
            'INSERT INTO usuarios
                (id_rol, identificador_institucional, nombre_completo, correo, password_hash, estado, id_area)
             VALUES (?, ?, ?, ?, ?, \'Activo\', ?)'
        );
        $insertar->execute([
            $idRol, $identificador, $nombre, $correo,
            password_hash($password, PASSWORD_DEFAULT), $area,
        ]);
        responder(201, ['ok' => true, 'mensaje' => 'El usuario se registró exitosamente']);
    }

    if ($password === '') {
        $actualizar = $conn->prepare(
            'UPDATE usuarios SET id_rol = ?, identificador_institucional = ?, nombre_completo = ?,
                correo = ?, estado = ?, id_area = ? WHERE id_usuario = ?'
        );
        $actualizar->execute([$idRol, $identificador, $nombre, $correo, $estado, $area, $idUsuario]);
    } else {
        $actualizar = $conn->prepare(
            'UPDATE usuarios SET id_rol = ?, identificador_institucional = ?, nombre_completo = ?,
                correo = ?, password_hash = ?, estado = ?, id_area = ? WHERE id_usuario = ?'
        );
        $actualizar->execute([
            $idRol, $identificador, $nombre, $correo, password_hash($password, PASSWORD_DEFAULT),
            $estado, $area, $idUsuario,
        ]);
    }
    responder(200, ['ok' => true, 'mensaje' => 'El usuario se actualizó exitosamente']);
} catch (PDOException $error) {
    if (esErrorDuplicado($error)) {
        responder(409, ['ok' => false, 'mensaje' => 'El correo o identificador institucional ya pertenece a otro usuario.']);
    }
    error_log('Error al ' . ($accion === 'crear' ? 'registrar' : 'actualizar') . ' usuario: ' . $error->getMessage());
    $mensaje = $accion === 'crear'
        ? 'El usuario no se registró exitosamente'
        : 'El usuario no se actualizó exitosamente';
    responder(500, ['ok' => false, 'mensaje' => $mensaje]);
}
