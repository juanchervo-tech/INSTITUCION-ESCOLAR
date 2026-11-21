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
        try {
            $stmt = $conexion->prepare("INSERT INTO cursos (anio, division, especialidad, turno) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $anio, $division, $especialidad, $turno);
            if ($stmt->execute()) {
                $mensaje = "¡Curso creado exitosamente!";
            } else {
                $error = "Error al registrar el curso: " . $conexion->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    } else {
        $error = "Por favor complete todos los campos obligatorios.";
    }
}

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
            <h2 class="h4 font-weight-bold text-primary mb-3">Registrar Nuevo Curso</h2>
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-success"><?= $mensaje; ?> <a href="cursos_listado.php">Ir al listado</a></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?= $error; ?></div>
            <?php endif; ?>
            <form action="curso_nuevo.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Año (*)</label>
                    <select name="anio" class="form-select" required>
                        <option value="">-- Seleccionar Año --</option>
                        <option value="1">1° Año</option>
                        <option value="2">2° Año</option>
                        <option value="3">3° Año</option>
                        <option value="4">4° Año</option>
                        <option value="5">5° Año</option>
                        <option value="6">6° Año</option>
                        <option value="7">7° Año</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">División / Sección (*)</label>
                    <input type="text" name="division" class="form-control" placeholder="Ej: 1ra, 2da, A, B..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Especialidad / Modalidad (*)</label>
                    <select name="especialidad" class="form-select" required>
                        <option value="">-- Seleccionar Especialidad --</option>
                        <option value="Ciclo Básico">Ciclo Básico</option>
                        <option value="Informática">Informática</option>
                        <option value="Electromecánica">Electromecánica</option>
                        <option value="Maestro Mayor de Obras">Maestro Mayor de Obras</option>
                        <option value="Construcciones">Construcciones (registro anterior)</option>
                        <option value="(MMO)">(MMO) (registro anterior)</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="form-label font-weight-bold">Turno (*)</label>
                    <select name="turno" class="form-select" required>
                        <option value="Mañana">Mañana</option>
                        <option value="Tarde">Tarde</option>
                        <option value="Noche">Noche</option>
                    </select>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4">Guardar Curso</button>
                </div>
            </form>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>