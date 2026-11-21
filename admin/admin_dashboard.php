<?php
session_start();
// Control de acceso: si no hay sesión iniciada, vuelve al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . "/../conexion.php";
require __DIR__ . "/TEMPLATE/seguridad.php";
admin_validar_sesion($conexion);
admin_requerir_roles(['administrador', 'directivo', 'preceptor']);

$es_gestor = admin_es_gestor();
if ($es_gestor) {
    $total_alumnos = $conexion->query("SELECT COUNT(*) AS total FROM alumnos")->fetch_assoc()['total'];
    $total_cursos = $conexion->query("SELECT COUNT(*) AS total FROM cursos")->fetch_assoc()['total'];
} else {
    $stmt_totales = $conexion->prepare(
        "SELECT COUNT(DISTINCT a.id_alumno) AS total_alumnos,
                COUNT(DISTINCT c.id_curso) AS total_cursos
         FROM preceptor_curso pc
         INNER JOIN cursos c ON c.id_curso = pc.id_curso
         LEFT JOIN alumnos a ON a.id_curso = c.id_curso
         WHERE pc.id_usuario = ?"
    );
    $stmt_totales->bind_param("i", $_SESSION['id_usuario']);
    $stmt_totales->execute();
    $totales = $stmt_totales->get_result()->fetch_assoc();
    $total_alumnos = $totales['total_alumnos'];
    $total_cursos = $totales['total_cursos'];
    $stmt_totales->close();
}

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

        <!-- Tarjetas de Resumen (Totales) -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card bg-primary text-white p-3 shadow-sm border-0">
                    <h5 class="card-title">ALUMNOS REGISTRADOS</h5>
                    <p class="display-4 fw-bold mb-0"><?= $total_alumnos; ?></p>
                </div>
            </div>
            <?php if ($es_gestor): ?>
            <div class="col-md-6 mb-3">
                <div class="card bg-success text-white p-3 shadow-sm border-0">
                    <h5 class="card-title">CURSOS Y DIVISIONES</h5>
                    <p class="display-4 fw-bold mb-0"><?= $total_cursos; ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Opciones de Gestión -->
        <div class="row">
            <!-- Módulo Alumnos -->
            <div class="col-md-6 mb-3">
                <div class="card p-4 shadow-sm text-center h-100 border-0">
                    <h3>👥 Alumnos</h3>
                    <p class="text-muted">Gestión de matrículas, carga de datos personales, ficha médica, tutor y legajo imprimible.</p>
                    <div class="d-flex justify-content-center gap-2 mt-auto">
                        <a href="alumnos_listado.php" class="btn btn-primary">Ver Lista de Alumnos</a>
                        <a href="alumno_nuevo.php" class="btn btn-outline-primary">+ Registrar Alumno</a>
                    </div>
                </div>
            </div>

            <!-- Módulo Cursos -->
            <?php if ($es_gestor): ?>
            <div class="col-md-6 mb-3">
                <div class="card p-4 shadow-sm text-center h-100 border-0">
                    <h3>🏫 Cursos y Divisiones</h3>
                    <p class="text-muted">Administración de años (1° a 7°), divisiones y especialidades técnicas de la escuela.</p>
                    <div class="d-flex justify-content-center gap-2 mt-auto">
                        <a href="cursos_listado.php" class="btn btn-success">Ver Cursos</a>
                        <a href="curso_nuevo.php" class="btn btn-outline-success">+ Crear Curso</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Fila de Accesos Especiales -->
        <?php if ($es_gestor): ?>
            <div class="row mt-3">
                <div class="col-md-12">
                    <div class="card p-3 shadow-sm border-0 bg-white d-flex flex-row align-items-center justify-content-between">
                        <div>
                            <h5 class="m-0 text-primary font-weight-bold">👤 Gestión de usuarios y preceptores</h5>
                            <p class="text-muted small mb-0">Administrar roles, asignar cursos y registrar nuevas cuentas.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="usuarios_listado.php" class="btn btn-outline-primary font-weight-bold">Gestionar usuarios</a>
                            <a href="crear_usuario.php" class="btn btn-warning font-weight-bold">+ Registrar usuario</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>