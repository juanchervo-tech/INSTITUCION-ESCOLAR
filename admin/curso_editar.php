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
$id_curso = intval($_GET['id'] ?? 0);
if ($id_curso <= 0) {
    header("Location: cursos_listado.php");
    exit();
}
$mensaje = "";
$error = "";
$csrf_token = admin_csrf_token();
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualizá la página e intentá nuevamente.";
    }
    $anio         = intval($_POST['anio'] ?? 0);
    $division     = trim($_POST['division'] ?? '');
    $especialidad = trim($_POST['especialidad'] ?? '');
    $turno        = $_POST['turno'] ?? '';
    $especialidades = ['Ciclo Básico', 'Informática', 'Electromecánica', 'Construcciones', '(MMO)', 'Maestro Mayor de Obras'];
    if (
        empty($error)
        &&
        $anio >= 1
        && $anio <= 7
        && $division !== ''
        && strlen($division) <= 10
        && in_array($especialidad, $especialidades, true)
        && in_array($turno, ['Mañana', 'Tarde', 'Noche'], true)
    ) {
        $stmt_upd = $conexion->prepare("UPDATE cursos SET anio=?, division=?, especialidad=?, turno=? WHERE id_curso=?");
        $stmt_upd->bind_param("isssi", $anio, $division, $especialidad, $turno, $id_curso);
        if ($stmt_upd->execute()) {
            $mensaje = "¡Curso actualizado correctamente!";
        } else {
            $error = "Error al actualizar el curso: " . $conexion->error;
        }
        $stmt_upd->close();
    } else {
        $error = "Complete todos los campos obligatorios.";
    }
}
// Cargar datos actuales
$stmt = $conexion->prepare("SELECT * FROM cursos WHERE id_curso = ?");
$stmt->bind_param("i", $id_curso);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin</a>
            <div class="d-flex text-white ms-auto">
                <a href="cursos_listado.php" class="btn btn-outline-light btn-sm">← Volver a Cursos</a>
            </div>
        </div>
    </nav>

        <div class="card shadow border-0 p-4 mx-auto" style="max-width: 600px;">
            <h2 class="h4 font-weight-bold text-primary mb-3">Editar Curso</h2>
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-success"><?= $mensaje; ?> <a href="cursos_listado.php">Volver al listado</a></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?= $error; ?></div>
            <?php endif; ?>
            <form action="curso_editar.php?id=<?= $id_curso; ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Año (*)</label>
                    <select name="anio" class="form-select" required>
                        <?php for($i=1; $i<=7; $i++): ?>
                            <option value="<?= $i; ?>" <?= ($curso['anio'] == $i) ? 'selected' : ''; ?>><?= $i; ?>° Año</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">División / Sección (*)</label>
                    <input type="text" name="division" class="form-control" value="<?= htmlspecialchars($curso['division']); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Especialidad / Modalidad (*)</label>
                    <select name="especialidad" class="form-select" required>
                        <?php foreach (['Ciclo Básico', 'Informática', 'Electromecánica', 'Maestro Mayor de Obras', 'Construcciones', '(MMO)'] as $especialidad_opcion): ?>
                            <option value="<?= htmlspecialchars($especialidad_opcion, ENT_QUOTES, 'UTF-8'); ?>"
                                <?= $curso['especialidad'] === $especialidad_opcion ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($especialidad_opcion, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label font-weight-bold">Turno (*)</label>
                    <select name="turno" class="form-select" required>
                        <option value="Mañana" <?= ($curso['turno'] == 'Mañana') ? 'selected' : ''; ?>>Mañana</option>
                        <option value="Tarde" <?= ($curso['turno'] == 'Tarde') ? 'selected' : ''; ?>>Tarde</option>
                        <option value="Noche" <?= ($curso['turno'] == 'Noche') ? 'selected' : ''; ?>>Noche</option>
                    </select>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-warning px-4">Guardar Cambios</button>
                </div>
            </form>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>