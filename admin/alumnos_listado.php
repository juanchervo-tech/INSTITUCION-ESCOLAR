<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}
require __DIR__ . "/../conexion.php";
require __DIR__ . "/TEMPLATE/seguridad.php";
admin_validar_sesion($conexion);
admin_requerir_roles(['administrador', 'directivo', 'preceptor']);
$rol_usuario = $_SESSION['rol'] ?? '';
$id_usuario_actual = $_SESSION['id_usuario'] ?? 0;
$es_preceptor = strtolower($rol_usuario) === 'preceptor';
$csrf_token = admin_csrf_token();

// Manejo de eliminación mediante método POST (Seguridad exigida)
$mensaje = "";
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['eliminar_id'])) {
    $id_eliminar = filter_var($_POST['eliminar_id'], FILTER_VALIDATE_INT);
    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualice la página e inténtelo nuevamente.";
    } elseif ($id_eliminar === false || $id_eliminar <= 0) {
        $error = "El alumno seleccionado no es válido.";
    }

    if (empty($error) && $es_preceptor) {
        $error = "Solo un Administrador o Directivo puede eliminar alumnos.";
    }
    if (empty($error)) {
        $stmt_del = $conexion->prepare("DELETE FROM alumnos WHERE id_alumno = ?");
        $stmt_del->bind_param("i", $id_eliminar);
        
        if ($stmt_del->execute()) {
            $mensaje = "El alumno ha sido eliminado correctamente del sistema.";
        } else {
            $error = "No se pudo eliminar el alumno. Es posible que tenga registros asociados.";
        }
        $stmt_del->close();
    }
}

// Parámetros de búsqueda y filtrado
$buscar = isset($_GET['buscar']) && is_string($_GET['buscar']) ? trim($_GET['buscar']) : '';
$filtro_curso = isset($_GET['id_curso']) && is_string($_GET['id_curso'])
    ? filter_var($_GET['id_curso'], FILTER_VALIDATE_INT)
    : '';
if ($filtro_curso === false || $filtro_curso < 1) {
    $filtro_curso = '';
}

// Consulta base uniendo con la tabla de cursos
$sql = "SELECT DISTINCT a.id_alumno, a.foto, a.legajo, a.apellido, a.nombre, a.dni, a.telefono, a.id_curso,
               c.anio, c.division, c.especialidad, c.turno
        FROM alumnos a 
        LEFT JOIN cursos c ON a.id_curso = c.id_curso";
$params = [];
$types = "";

// 🔒 CONTROL DE ROL: Si es preceptor, unimos con preceptor_curso para filtrar solo sus cursos asignados
if ($es_preceptor) {
    $sql .= " JOIN preceptor_curso cp ON c.id_curso = cp.id_curso WHERE cp.id_usuario = ?";
    $params[] = $id_usuario_actual;
    $types .= "i";
} else {
    $sql .= " WHERE 1=1";
}

if (!empty($buscar)) {
    $sql .= " AND (a.nombre LIKE ? OR a.apellido LIKE ? OR a.dni LIKE ? OR a.legajo LIKE ?)";
    $term = "%" . $buscar . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= "ssss";
}

if (!empty($filtro_curso)) {
    $sql .= " AND a.id_curso = ?";
    $params[] = $filtro_curso;
    $types .= "i";
}

$sql .= " ORDER BY a.apellido ASC, a.nombre ASC";
$stmt = $conexion->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$alumnos_result = $stmt->get_result();

if (($_GET['exportar'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="listado_alumnos.csv"');
    $salida = fopen('php://output', 'w');
    if ($salida === false) {
        throw new RuntimeException("No se pudo generar el archivo CSV.");
    }
    fwrite($salida, "\xEF\xBB\xBF");
    fputcsv($salida, ['Legajo', 'Apellido', 'Nombre', 'DNI', 'Curso', 'Teléfono'], ';', '"', '\\');
    while ($alumno = $alumnos_result->fetch_assoc()) {
        $curso = !empty($alumno['anio'])
            ? $alumno['anio'] . '° ' . $alumno['division'] . ' - ' . $alumno['especialidad']
            : 'Sin asignar';
        fputcsv($salida, array_map('admin_csv_celda', [
            $alumno['legajo'],
            $alumno['apellido'],
            $alumno['nombre'],
            $alumno['dni'],
            $curso,
            $alumno['telefono']
        ]), ';', '"', '\\');
    }
    fclose($salida);
    exit();
}

// Obtener cursos para el filtro según el rol
if ($es_preceptor) {
    $stmt_cursos = $conexion->prepare("SELECT c.* FROM cursos c JOIN preceptor_curso cp ON c.id_curso = cp.id_curso WHERE cp.id_usuario = ? ORDER BY c.anio, c.division ASC");
    $stmt_cursos->bind_param("i", $id_usuario_actual);
    $stmt_cursos->execute();
    $cursos_query = $stmt_cursos->get_result();
} else {
    $cursos_query = $conexion->query("SELECT * FROM cursos ORDER BY anio, division ASC");
}

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

    <!-- Navegación específica de alumnos -->
    <style>
        @media print {
            .no-print, nav { display: none !important; }
            footer { display: none !important; }
            .container { max-width: 100% !important; }
        }
    </style>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin (Rol: <?= ucfirst($rol_usuario); ?>)</a>
            <div class="d-flex text-white ms-auto gap-2">
                <a href="alumno_nuevo.php" class="btn btn-success btn-sm">+ Registrar Nuevo Alumno</a>
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 font-weight-bold text-primary m-0">Gestión de Alumnos Registrados</h2>
            <a href="alumno_nuevo.php" class="btn btn-primary btn-sm">+ Nuevo Alumno</a>
        </div>
        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $mensaje; ?>
            </div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error; ?>
            </div>
        <?php endif; ?>
        <!-- Tarjeta de Búsqueda y Filtros -->
        <div class="card shadow-sm border-0 mb-4 p-3">
            <form method="GET" action="alumnos_listado.php" class="row g-3 align-items-end no-print">
                <div class="col-md-5">
                    <label class="form-label font-weight-bold">Buscar Alumno</label>
                    <input type="text" name="buscar" class="form-control" placeholder="Nombre, Apellido, DNI o Legajo..." value="<?= htmlspecialchars($buscar); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label font-weight-bold">Filtrar por Curso</label>
                    <select name="id_curso" class="form-select">
                        <option value="">-- Todos los Cursos --</option>
                        <?php while($c = $cursos_query->fetch_assoc()): ?>
                            <option value="<?= $c['id_curso']; ?>" <?= ($filtro_curso == $c['id_curso']) ? 'selected' : ''; ?>>
                                <?= $c['anio'] . " " . $c['division'] . " - " . $c['especialidad'] . " (" . $c['turno'] . ")"; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
                    <?php if(!empty($buscar) || !empty($filtro_curso)): ?>
                        <a href="alumnos_listado.php" class="btn btn-secondary">Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <div class="d-flex justify-content-end gap-2 mb-3 no-print">
            <a class="btn btn-outline-success btn-sm" href="alumnos_listado.php?<?= htmlspecialchars(http_build_query(['buscar' => $buscar, 'id_curso' => $filtro_curso, 'exportar' => 'csv']), ENT_QUOTES, 'UTF-8'); ?>">Exportar CSV</a>
            <button type="button" onclick="window.print();" class="btn btn-outline-secondary btn-sm">Imprimir / Guardar PDF</button>
        </div>
        <!-- Tabla de Alumnos -->
        <div class="card shadow border-0 p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Foto</th>
                            <th>Legajo</th>
                            <th>Apellido y Nombre</th>
                            <th>DNI</th>
                            <th>Curso / División</th>
                            <th>Teléfono</th>
                            <th class="text-center no-print">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($alumnos_result->num_rows > 0): ?>
                            <?php while ($alumno = $alumnos_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php 
                                            $foto_nombre = $alumno['foto'] ?? '';
                                            $foto_real_path = __DIR__ . '/../uploads/' . basename($foto_nombre);
                                            if (
                                                $foto_nombre === ''
                                                || basename($foto_nombre) !== $foto_nombre
                                                || !in_array(strtolower(pathinfo($foto_nombre, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true)
                                                || !is_file($foto_real_path)
                                            ) {
                                                $foto_path = "../img/alumno_sin_foto.svg";
                                            } else {
                                                $foto_path = "../uploads/" . rawurlencode($foto_nombre);
                                            }
                                        ?>
                                        <img src="<?= $foto_path; ?>" alt="Foto" class="rounded-circle border" width="45" height="45" style="object-fit: cover;">
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($alumno['legajo']); ?></span></td>
                                    <td class="font-weight-bold">
                                        <?= htmlspecialchars($alumno['apellido'] . ", " . $alumno['nombre']); ?>
                                    </td>
                                    <td><?= htmlspecialchars($alumno['dni']); ?></td>
                                    <td>
                                        <?php if (!empty($alumno['anio'])): ?>
                                            <?= htmlspecialchars($alumno['anio'] . '° ' . $alumno['division'] . ' - ' . $alumno['especialidad']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin Asignar</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($alumno['telefono']); ?></td>
                                    <td class="text-center no-print">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="alumno_ficha.php?id=<?= $alumno['id_alumno']; ?>" class="btn btn-outline-info" title="Ver Ficha / Impresión">👁️ Ficha</a>
                                            <a href="alumno_editar.php?id=<?= $alumno['id_alumno']; ?>" class="btn btn-outline-warning" title="Editar">✏️ Editar</a>
                                            
                                            <?php if (!$es_preceptor): ?>
                                                <form action="alumnos_listado.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar al alumno <?= htmlspecialchars($alumno['apellido'] . ' ' . $alumno['nombre']); ?>?');">
                                                    <input type="hidden" name="eliminar_id" value="<?= $alumno['id_alumno']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar">🗑️</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No se encontraron alumnos registrados que coincidan con los permisos o criterios de búsqueda.
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