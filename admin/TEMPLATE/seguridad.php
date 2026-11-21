<?php
function admin_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function admin_validar_csrf($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function admin_es_gestor(): bool
{
    return in_array(strtolower($_SESSION['rol'] ?? ''), ['administrador', 'directivo'], true);
}

function admin_validar_sesion(mysqli $conexion): void
{
    $id_usuario = filter_var($_SESSION['id_usuario'] ?? null, FILTER_VALIDATE_INT);
    if ($id_usuario === false || $id_usuario <= 0) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    $stmt = $conexion->prepare(
        "SELECT usuario, nombre, apellido, rol, activo FROM usuarios WHERE id_usuario = ?"
    );
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario || (int) $usuario['activo'] !== 1) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    $_SESSION['usuario'] = $usuario['usuario'];
    $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
    $_SESSION['rol'] = $usuario['rol'];
}

function admin_requerir_roles(array $roles): void
{
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: ../login.php");
        exit();
    }

    if (!in_array(strtolower($_SESSION['rol'] ?? ''), $roles, true)) {
        http_response_code(403);
        exit('No tenés permisos para acceder a esta sección.');
    }
}

function admin_usuario_puede_acceder_alumno(mysqli $conexion, int $id_alumno, int $id_usuario): bool
{
    $stmt = $conexion->prepare(
        "SELECT 1
         FROM alumnos a
         INNER JOIN preceptor_curso pc ON pc.id_curso = a.id_curso
         WHERE a.id_alumno = ? AND pc.id_usuario = ?
         LIMIT 1"
    );
    $stmt->bind_param("ii", $id_alumno, $id_usuario);
    $stmt->execute();
    $puede_acceder = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    return $puede_acceder;
}

function admin_csv_celda($valor)
{
    if (is_string($valor) && preg_match('/^[\t\r\n ]*[=+\-@]/', $valor)) {
        return "'" . $valor;
    }

    return $valor;
}
