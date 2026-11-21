<?php
session_start();
if (!isset($_SESSION['id_usuario']) || !in_array(strtolower($_SESSION['rol'] ?? ''), ['administrador', 'directivo'], true)) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . "/../conexion.php";
require __DIR__ . "/TEMPLATE/seguridad.php";
admin_validar_sesion($conexion);
admin_requerir_roles(['administrador', 'directivo']);

$mensaje = "";
$error = "";
$csrf_token = admin_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';

    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualizá la página e intentá nuevamente.";
    } elseif ($id_usuario === false || $id_usuario <= 0) {
        $error = "El usuario seleccionado no es válido.";
    } else {
        $stmt_usuario = $conexion->prepare(
            "SELECT rol, usuario, nombre, apellido, activo FROM usuarios WHERE id_usuario = ?"
        );
        $stmt_usuario->bind_param("i", $id_usuario);
        $stmt_usuario->execute();
        $usuario_actual = $stmt_usuario->get_result()->fetch_assoc();
        $stmt_usuario->close();

        if (!$usuario_actual) {
            $error = "No se encontró el usuario seleccionado.";
        } elseif ($accion === 'estado') {
            $activo_nuevo = filter_var($_POST['activo_nuevo'] ?? null, FILTER_VALIDATE_INT);
            if (!in_array($activo_nuevo, [0, 1], true)) {
                $error = "El estado seleccionado no es válido.";
            } elseif ($activo_nuevo === 0 && (int) $id_usuario === (int) $_SESSION['id_usuario']) {
                $error = "No podés desactivar tu propia cuenta.";
            } elseif (
                $activo_nuevo === 0
                && in_array(strtolower($usuario_actual['rol']), ['administrador', 'directivo'], true)
                && (int) $usuario_actual['activo'] === 1
            ) {
                $resultado_gestores = $conexion->query(
                    "SELECT COUNT(*) AS total FROM usuarios
                     WHERE activo = 1 AND rol IN ('Administrador', 'Directivo')"
                );
                if ((int) $resultado_gestores->fetch_assoc()['total'] <= 1) {
                    $error = "No se puede desactivar al último usuario con permisos de gestión.";
                }
            }

            if (empty($error)) {
                $stmt_estado = $conexion->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
                $stmt_estado->bind_param("ii", $activo_nuevo, $id_usuario);
                $stmt_estado->execute();
                $stmt_estado->close();
                $mensaje = $activo_nuevo === 1
                    ? "La cuenta fue reactivada."
                    : "La cuenta fue desactivada.";
            }
        } elseif ($accion === 'guardar') {
            $rol = $_POST['rol'] ?? '';
            $cursos_post = $_POST['cursos'] ?? [];
            $clave_nueva = $_POST['clave_nueva'] ?? '';

            if (!in_array($rol, ['Administrador', 'Directivo', 'Preceptor'], true)) {
                $error = "El rol seleccionado no es válido.";
            } elseif (!is_array($cursos_post)) {
                $error = "La selección de cursos no es válida.";
            } elseif (!is_string($clave_nueva) || ($clave_nueva !== '' && strlen($clave_nueva) < 8)) {
                $error = "La nueva contraseña debe tener al menos 8 caracteres.";
            }

            $ids_cursos = [];
            if (empty($error)) {
                foreach ($cursos_post as $id_curso_post) {
                    $id_curso = filter_var($id_curso_post, FILTER_VALIDATE_INT);
                    if ($id_curso === false || $id_curso <= 0) {
                        $error = "La selección contiene un curso no válido.";
                        break;
                    }
                    $ids_cursos[] = $id_curso;
                }
                $ids_cursos = array_values(array_unique($ids_cursos));
            }

            if ($rol !== 'Preceptor') {
                $ids_cursos = [];
            }

            if (empty($error)) {
                $cursos_disponibles = [];
                $resultado_cursos = $conexion->query("SELECT id_curso FROM cursos");
                while ($curso = $resultado_cursos->fetch_assoc()) {
                    $cursos_disponibles[] = (int) $curso['id_curso'];
                }
                if (array_diff($ids_cursos, $cursos_disponibles)) {
                    $error = "Uno o más cursos seleccionados ya no existen.";
                }
            }

            $rol_actual_es_gestor = in_array(strtolower($usuario_actual['rol']), ['administrador', 'directivo'], true);
            $rol_nuevo_es_gestor = is_string($rol)
                && in_array(strtolower($rol), ['administrador', 'directivo'], true);
            if (
                empty($error)
                && $rol_actual_es_gestor
                && !$rol_nuevo_es_gestor
                && (int) $usuario_actual['activo'] === 1
            ) {
                if ((int) $id_usuario === (int) $_SESSION['id_usuario']) {
                    $error = "No podés quitarte tu propio rol de gestión.";
                } else {
                    $resultado_gestores = $conexion->query(
                        "SELECT COUNT(*) AS total FROM usuarios
                         WHERE activo = 1 AND rol IN ('Administrador', 'Directivo')"
                    );
                    if ((int) $resultado_gestores->fetch_assoc()['total'] <= 1) {
                        $error = "No se puede quitar el rol al último usuario con permisos de gestión.";
                    }
                }
            }
        } else {
            $error = "La acción solicitada no es válida.";
        }

        if (empty($error) && $accion === 'guardar') {
            try {
                $conexion->begin_transaction();

                if ($clave_nueva !== '') {
                    $clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
                    $stmt_rol = $conexion->prepare(
                        "UPDATE usuarios SET rol = ?, clave = ? WHERE id_usuario = ?"
                    );
                    $stmt_rol->bind_param("ssi", $rol, $clave_hash, $id_usuario);
                } else {
                    $stmt_rol = $conexion->prepare("UPDATE usuarios SET rol = ? WHERE id_usuario = ?");
                    $stmt_rol->bind_param("si", $rol, $id_usuario);
                }
                $stmt_rol->execute();
                $stmt_rol->close();

                $stmt_borrar = $conexion->prepare("DELETE FROM preceptor_curso WHERE id_usuario = ?");
                $stmt_borrar->bind_param("i", $id_usuario);
                $stmt_borrar->execute();
                $stmt_borrar->close();

                if ($rol === 'Preceptor') {
                    $stmt_asignar = $conexion->prepare("INSERT INTO preceptor_curso (id_usuario, id_curso) VALUES (?, ?)");
                    foreach ($ids_cursos as $id_curso) {
                        $stmt_asignar->bind_param("ii", $id_usuario, $id_curso);
                        $stmt_asignar->execute();
                    }
                    $stmt_asignar->close();
                }

                $conexion->commit();
                $mensaje = "Se actualizaron el rol y los cursos asignados de " . htmlspecialchars(
                    $usuario_actual['nombre'] . ' ' . $usuario_actual['apellido'] . ' (' . $usuario_actual['usuario'] . ')',
                    ENT_QUOTES,
                    'UTF-8'
                ) . ".";
                if ($clave_nueva !== '') {
                    $mensaje .= " También se cambió la contraseña.";
                }
            } catch (mysqli_sql_exception $e) {
                $conexion->rollback();
                error_log("No se pudo actualizar el rol/asignación del usuario: " . $e->getMessage());
                $error = "No se pudieron guardar los cambios. Verificá que exista la tabla preceptor_curso y volvé a intentarlo.";
            }
        }
    }
}

$resultado_usuarios = $conexion->query("SELECT id_usuario, usuario, nombre, apellido, rol, activo FROM usuarios ORDER BY apellido, nombre");
$usuarios = $resultado_usuarios->fetch_all(MYSQLI_ASSOC);
$resultado_cursos = $conexion->query("SELECT id_curso, anio, division, especialidad, turno FROM cursos ORDER BY anio, division");
$cursos = $resultado_cursos->fetch_all(MYSQLI_ASSOC);
$asignaciones = [];
$resultado_asignaciones = $conexion->query("SELECT id_usuario, id_curso FROM preceptor_curso");
while ($asignacion = $resultado_asignaciones->fetch_assoc()) {
    $asignaciones[(int) $asignacion['id_usuario']][] = (int) $asignacion['id_curso'];
}

include 'TEMPLATE/header.php';
?>

<div class="mb-4">
    <h1 class="h4 text-primary mb-1">Gestión de usuarios y preceptores</h1>
    <p class="text-muted mb-0">Cambiá el rol y seleccioná los cursos que puede administrar cada preceptor.</p>
    <a href="crear_usuario.php" class="btn btn-primary btn-sm mt-3">Registrar usuario</a>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= $mensaje; ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php foreach ($usuarios as $usuario): ?>
    <?php $id_usuario_lista = (int) $usuario['id_usuario']; ?>
    <?php $activo_usuario = (int) $usuario['activo'] === 1; ?>
    <form action="usuarios_listado.php" method="POST" class="card shadow-sm border-0 p-3 mb-3">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id_usuario" value="<?= $id_usuario_lista; ?>">
        <input type="hidden" name="activo_nuevo" value="<?= $activo_usuario ? 0 : 1; ?>">
        <div class="row g-3">
            <div class="col-lg-3">
                <strong><?= htmlspecialchars($usuario['apellido'] . ', ' . $usuario['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <div class="small text-muted"><?= htmlspecialchars($usuario['usuario'], ENT_QUOTES, 'UTF-8'); ?></div>
                <span class="badge <?= $activo_usuario ? 'bg-success' : 'bg-secondary'; ?>">
                    <?= $activo_usuario ? 'Activa' : 'Inactiva'; ?>
                </span>
            </div>
            <div class="col-lg-2">
                <label for="rol-<?= $id_usuario_lista; ?>" class="form-label">Rol</label>
                <select id="rol-<?= $id_usuario_lista; ?>" name="rol" class="form-select">
                    <option value="Preceptor" <?= strtolower($usuario['rol']) === 'preceptor' ? 'selected' : ''; ?>>Preceptor</option>
                    <option value="Directivo" <?= strtolower($usuario['rol']) === 'directivo' ? 'selected' : ''; ?>>Directivo</option>
                    <option value="Administrador" <?= strtolower($usuario['rol']) === 'administrador' ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>
            <div class="col-lg-5">
                <span class="form-label d-block">Cursos asignados si es preceptor</span>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($cursos as $curso): ?>
                        <?php $id_curso_lista = (int) $curso['id_curso']; ?>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="cursos[]" value="<?= $id_curso_lista; ?>"
                                <?= in_array($id_curso_lista, $asignaciones[$id_usuario_lista] ?? [], true) ? 'checked' : ''; ?>>
                            <span class="form-check-label"><?= htmlspecialchars($curso['anio'] . '° ' . $curso['division'] . ' - ' . $curso['especialidad'] . ' (' . $curso['turno'] . ')', ENT_QUOTES, 'UTF-8'); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($cursos)): ?>
                        <span class="text-muted">No hay cursos creados.</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-2">
                <label for="clave-<?= $id_usuario_lista; ?>" class="form-label">Nueva contraseña</label>
                <input type="password" id="clave-<?= $id_usuario_lista; ?>" name="clave_nueva"
                       class="form-control" minlength="8" autocomplete="new-password"
                       placeholder="Dejar vacía para no cambiar">
            </div>
            <div class="col-lg-2 d-flex flex-column justify-content-end gap-2">
                <button type="submit" name="accion" value="guardar" class="btn btn-outline-primary w-100">Guardar cambios</button>
                <?php if ($id_usuario_lista !== (int) $_SESSION['id_usuario']): ?>
                    <button type="submit" name="accion" value="estado"
                            class="btn <?= $activo_usuario ? 'btn-outline-danger' : 'btn-outline-success'; ?> w-100"
                            onclick="return confirm('¿Querés <?= $activo_usuario ? 'desactivar' : 'reactivar'; ?> esta cuenta?');">
                        <?= $activo_usuario ? 'Dar de baja' : 'Reactivar'; ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
<?php endforeach; ?>

<?php include 'TEMPLATE/footer.php'; ?>
