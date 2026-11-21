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

$rol_usuario = $_SESSION['rol'] ?? '';

// 1. Consulta: Total de alumnos por Especialidad y Turno (COUNT y GROUP BY solicitados por la profesora)[cite: 3]
$sql_especialidad = "SELECT c.especialidad, c.turno, COUNT(a.id_alumno) AS total_alumnos 
                     FROM cursos c 
                     LEFT JOIN alumnos a ON c.id_curso = a.id_curso 
                     GROUP BY c.especialidad, c.turno 
                     ORDER BY c.especialidad ASC";
$resultado_especialidad = $conexion->query($sql_especialidad);

// 2. Consulta: Total de alumnos por Año / División
$sql_cursos = "SELECT c.anio, c.division, c.especialidad, c.turno, COUNT(a.id_alumno) AS total_alumnos 
               FROM cursos c 
               LEFT JOIN alumnos a ON c.id_curso = a.id_curso 
               GROUP BY c.id_curso 
               ORDER BY c.anio ASC, c.division ASC";
$resultado_cursos = $conexion->query($sql_cursos);

$sql_autorizaciones = "SELECT a.legajo, a.apellido, a.nombre, a.dni,
                              a.auth_imagen, a.auth_salidas,
                              c.anio, c.division, c.especialidad, c.turno
                       FROM alumnos a
                       LEFT JOIN cursos c ON c.id_curso = a.id_curso
                       WHERE COALESCE(a.auth_imagen, 'No') <> 'Sí'
                          OR COALESCE(a.auth_salidas, 'No') <> 'Sí'
                       ORDER BY a.apellido, a.nombre";
$resultado_autorizaciones = $conexion->query($sql_autorizaciones);

// 3. Totales generales rápidos
$total_gral = $conexion->query("SELECT COUNT(*) AS total FROM alumnos")->fetch_assoc()['total'];
$total_cursos_gral = $conexion->query("SELECT COUNT(*) AS total FROM cursos")->fetch_assoc()['total'];

if (in_array($_GET['exportar'] ?? '', ['especialidad', 'cursos', 'autorizaciones'], true)) {
    $exportar = $_GET['exportar'];
    if ($exportar === 'especialidad') {
        $resultado = $resultado_especialidad;
        $nombre_archivo = 'reporte_por_especialidad_y_turno.csv';
        $encabezados = ['Especialidad', 'Turno', 'Total de alumnos'];
    } elseif ($exportar === 'cursos') {
        $resultado = $resultado_cursos;
        $nombre_archivo = 'reporte_por_curso.csv';
        $encabezados = ['Año', 'División', 'Especialidad', 'Turno', 'Total de alumnos'];
    } else {
        $resultado = $resultado_autorizaciones;
        $nombre_archivo = 'alumnos_sin_autorizacion.csv';
        $encabezados = ['Legajo', 'Apellido', 'Nombre', 'DNI', 'Curso', 'Autorización de imagen', 'Autorización de salidas'];
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    $salida = fopen('php://output', 'w');
    if ($salida === false) {
        throw new RuntimeException("No se pudo generar el archivo CSV.");
    }
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, $encabezados, ';', '"', '\\');
    while ($fila = $resultado->fetch_assoc()) {
        if ($exportar === 'especialidad') {
            $datos = [$fila['especialidad'], $fila['turno'], $fila['total_alumnos']];
        } elseif ($exportar === 'cursos') {
            $datos = [$fila['anio'], $fila['division'], $fila['especialidad'], $fila['turno'], $fila['total_alumnos']];
        } else {
            $curso = !empty($fila['anio'])
                ? $fila['anio'] . '° ' . $fila['division'] . ' - ' . $fila['especialidad'] . ' (' . $fila['turno'] . ')'
                : 'Sin curso asignado';
            $datos = [
                $fila['legajo'], $fila['apellido'], $fila['nombre'], $fila['dni'], $curso,
                $fila['auth_imagen'] ?: 'No', $fila['auth_salidas'] ?: 'No'
            ];
        }
        fputcsv($salida, array_map('admin_csv_celda', $datos), ';', '"', '\\');
    }
    fclose($salida);
    exit();
}

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

    <style>
        @media print {
            .no-print { display: none !important; }
            nav { display: none !important; }
            footer { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
            .container { max-width: 100% !important; }
        }
    </style>

    <!-- Navegación Superior -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4 no-print">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin - Reportes Estadísticos</a>
            <div class="d-flex text-white ms-auto gap-2">
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 font-weight-bold text-primary m-0">Informes y Estadísticas Institucionales</h2>
                <p class="text-muted small m-0">Datos consolidados de matrículas de la Escuela Técnica "Pedro Ferré"</p>
            </div>
            <div class="d-flex gap-2 no-print">
                <a href="reportes.php?exportar=especialidad" class="btn btn-outline-success btn-sm">CSV por especialidad</a>
                <a href="reportes.php?exportar=cursos" class="btn btn-outline-success btn-sm">CSV por curso</a>
                <a href="reportes.php?exportar=autorizaciones" class="btn btn-outline-danger btn-sm">CSV sin autorización</a>
                <button onclick="window.print();" class="btn btn-secondary btn-sm">Imprimir / Guardar PDF</button>
            </div>
        </div>

        <!-- Tarjetas de Resumen General -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 p-3 bg-white">
                    <h6 class="text-muted fw-bold">MATRÍCULA TOTAL GENERAL</h6>
                    <h3 class="text-primary fw-bold display-6 mb-0"><?= $total_gral; ?> alumnos</h3>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card shadow-sm border-0 p-3 bg-white">
                    <h6 class="text-muted fw-bold">TOTAL DE CURSOS ACTIVOS</h6>
                    <h3 class="text-success fw-bold display-6 mb-0"><?= $total_cursos_gral; ?> cursos</h3>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="h5 fw-bold text-primary mb-1">Alumnos sin todas las autorizaciones</h4>
                    <p class="text-muted small mb-0">Incluye alumnos sin autorización de imagen o de salidas escolares.</p>
                </div>
                <span class="badge bg-warning text-dark"><?= $resultado_autorizaciones->num_rows; ?> alumnos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Legajo</th>
                            <th>Apellido y nombre</th>
                            <th>Curso</th>
                            <th>Uso de imagen</th>
                            <th>Salidas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_autorizaciones->num_rows > 0): ?>
                            <?php while ($alumno = $resultado_autorizaciones->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($alumno['legajo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= !empty($alumno['anio'])
                                        ? htmlspecialchars($alumno['anio'] . '° ' . $alumno['division'] . ' - ' . $alumno['especialidad'], ENT_QUOTES, 'UTF-8')
                                        : 'Sin curso asignado'; ?></td>
                                    <td><?= htmlspecialchars($alumno['auth_imagen'] ?: 'No', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlspecialchars($alumno['auth_salidas'] ?: 'No', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center text-muted">Todos los alumnos tienen registradas ambas autorizaciones.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla 1: Alumnos por Especialidad y Turno -->
        <div class="card shadow-sm border-0 p-4 mb-4">
            <h4 class="h5 fw-bold text-primary mb-3">📊 Distribución de Alumnos por Especialidad y Turno</h4>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Especialidad Técnica</th>
                            <th>Turno</th>
                            <th class="text-center">Total de Alumnos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_especialidad->num_rows > 0): ?>
                            <?php while ($row = $resultado_especialidad->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($row['especialidad']); ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($row['turno']); ?></span></td>
                                    <td class="text-center fw-bold"><?= $row['total_alumnos']; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">No hay registros de especialidades cargados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla 2: Detalle por Curso y División -->
        <div class="card shadow-sm border-0 p-4">
            <h4 class="h5 fw-bold text-primary mb-3">🏫 Detalle de Matrícula por Curso y División</h4>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Año / Grado</th>
                            <th>División</th>
                            <th>Especialidad</th>
                            <th>Turno</th>
                            <th class="text-center">Alumnos Inscriptos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_cursos->num_rows > 0): ?>
                            <?php while ($curso = $resultado_cursos->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($curso['anio']); ?>° Año</td>
                                    <td><?= htmlspecialchars($curso['division']); ?></td>
                                    <td><?= htmlspecialchars($curso['especialidad']); ?></td>
                                    <td><?= htmlspecialchars($curso['turno']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary px-3 py-2"><?= $curso['total_alumnos']; ?> alumnos</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">No hay cursos registrados.</td>
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