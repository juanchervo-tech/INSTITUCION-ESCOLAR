<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}
require __DIR__ . "/../conexion.php";
require __DIR__ . "/TEMPLATE/seguridad.php";
admin_validar_sesion($conexion);
admin_requerir_roles(['administrador', 'directivo']);
$mensaje = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['eliminar_id'])) {
    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualice la página e inténtelo nuevamente.";
    } else {
        $id_eliminar = filter_var($_POST['eliminar_id'], FILTER_VALIDATE_INT);

        if ($id_eliminar === false || $id_eliminar <= 0) {
            $error = "El curso seleccionado no es válido.";
        }
    }

    if (empty($error)) {
        // Verificar si el curso tiene alumnos asignados antes de borrar
        $check_alumnos = $conexion->prepare("SELECT COUNT(*) AS total FROM alumnos WHERE id_curso = ?");
        $check_alumnos->bind_param("i", $id_eliminar);
        $check_alumnos->execute();
        $res = $check_alumnos->get_result()->fetch_assoc();
        $check_alumnos->close();

        if ($res['total'] > 0) {
            $error = "No se puede eliminar el curso porque tiene " . $res['total'] . " alumno(s) asignado(s). Reasigne a los alumnos antes de eliminarlo.";
        } else {
            $check_preceptores = $conexion->prepare("SELECT COUNT(*) AS total FROM preceptor_curso WHERE id_curso = ?");
            $check_preceptores->bind_param("i", $id_eliminar);
            $check_preceptores->execute();
            $res_preceptores = $check_preceptores->get_result()->fetch_assoc();
            $check_preceptores->close();

            if ($res_preceptores['total'] > 0) {
                $error = "No se puede eliminar el curso porque está asignado a " . $res_preceptores['total'] . " preceptor(es). Quite las asignaciones antes de eliminarlo.";
            } else {
                try {
                    $stmt_del = $conexion->prepare("DELETE FROM cursos WHERE id_curso = ?");
                    $stmt_del->bind_param("i", $id_eliminar);
                    $stmt_del->execute();
                    $mensaje = $stmt_del->affected_rows === 1
                        ? "El curso ha sido eliminado correctamente."
                        : "No se encontró el curso seleccionado.";
                    $stmt_del->close();
                } catch (mysqli_sql_exception $e) {
                    error_log("No se pudo eliminar el curso: " . $e->getMessage());
                    $error = "No se pudo eliminar el curso porque tiene registros relacionados.";
                }
            }
        }
    }
}
$csrf_token = admin_csrf_token();

// Obtener lista de cursos junto con la cantidad de alumnos inscriptos
$sql = "SELECT c.*, COUNT(a.id_alumno) AS total_alumnos 
        FROM cursos c 
        LEFT JOIN alumnos a ON c.id_curso = a.id_curso 
        GROUP BY c.id_curso 
        ORDER BY c.anio ASC, c.division ASC";
$cursos_result = $conexion->query($sql);

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin</a>
            <div class="d-flex text-white ms-auto gap-2">
                <a href="curso_nuevo.php" class="btn btn-success btn-sm">+ Registrar Nuevo Curso</a>
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 font-weight-bold text-primary m-0">Gestión de Cursos y Divisiones</h2>
            <a href="curso_nuevo.php" class="btn btn-primary btn-sm">+ Nuevo Curso</a>
        </div>
        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= $mensaje; ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $error; ?></div>
        <?php endif; ?>
        <div class="card shadow border-0 p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Año</th>
                            <th>División</th>
                            <th>Especialidad</th>
                            <th>Turno</th>
                            <th class="text-center">Alumnos Inscriptos</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($cursos_result && $cursos_result->num_rows > 0): ?>
                            <?php while ($curso = $cursos_result->fetch_assoc()): ?>
                                <tr>
                                    <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($curso['anio']); ?>° Año</span></td>
                                    <td class="font-weight-bold"><?= htmlspecialchars($curso['division']); ?>ª Div</td>
                                    <td><?= htmlspecialchars($curso['especialidad']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($curso['turno']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="alumnos_listado.php?id_curso=<?= $curso['id_curso']; ?>" class="badge bg-info text-dark text-decoration-none p-2">
                                            👥 <?= $curso['total_alumnos']; ?> Alumnos
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="curso_editar.php?id=<?= $curso['id_curso']; ?>" class="btn btn-outline-warning" title="Editar">✏️ Editar</a>
                                            <form action="cursos_listado.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este curso?');">
                                                <input type="hidden" name="eliminar_id" value="<?= (int) $curso['id_curso']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Eliminar">🗑️</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No hay cursos cargados aún en la base de datos.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>