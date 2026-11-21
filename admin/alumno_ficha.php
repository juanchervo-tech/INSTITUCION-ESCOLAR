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
$id_alumno = intval($_GET['id'] ?? 0);
if ($id_alumno <= 0) {
    header("Location: alumnos_listado.php");
    exit();
}
if (strtolower($_SESSION['rol'] ?? '') === 'preceptor'
    && !admin_usuario_puede_acceder_alumno($conexion, $id_alumno, (int) $_SESSION['id_usuario'])) {
    http_response_code(403);
    exit('No tenés permisos para consultar este alumno.');
}
// Consultar datos completados del alumno + curso
$stmt = $conexion->prepare("SELECT a.*, c.anio, c.division, c.especialidad, c.turno 
                            FROM alumnos a 
                            LEFT JOIN cursos c ON a.id_curso = c.id_curso 
                            WHERE a.id_alumno = ?");
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo "Alumno no encontrado.";
    exit();
}
$alumno = $result->fetch_assoc();
$stmt->close();

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff !important; }
            .card { border: none !important; box-shadow: none !important; }
        }
    </style>

    <!-- Navegación sin impresión -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4 no-print">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin</a>
            <div class="d-flex gap-2 ms-auto">
                <button onclick="window.print();" class="btn btn-warning btn-sm">🖨️ Imprimir / Guardar PDF</button>
                <a href="alumno_editar.php?id=<?= $alumno['id_alumno']; ?>" class="btn btn-light btn-sm">✏️ Editar</a>
                <a href="alumnos_listado.php" class="btn btn-outline-light btn-sm">← Volver al Listado</a>
            </div>
        </div>
    </nav>

        <div class="card shadow border-0 p-4 bg-white">
            
            <!-- Encabezado Ficha -->
            <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-4">
                <div>
                    <h3 class="h4 font-weight-bold text-primary mb-1">Escuela Técnica Pedro Ferré</h3>
                    <h4 class="h6 text-muted mb-0">Ficha Digital de Matrícula y Legajo Individual</h4>
                </div>
                <div class="text-end">
                    <span class="badge bg-primary fs-6">Legajo N°: <?= htmlspecialchars($alumno['legajo']); ?></span>
                </div>
            </div>
            <div class="row g-4">
                <!-- Foto de Perfil -->
                <div class="col-md-3 text-center border-end">
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
                    <img src="<?= $foto_path; ?>" alt="Foto Alumno" class="img-thumbnail rounded mb-3" style="max-width: 180px; height: 210px; object-fit: cover;">
                    <h5 class="font-weight-bold text-dark mb-0"><?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre']); ?></h5>
                    <p class="text-muted small">DNI: <?= htmlspecialchars($alumno['dni']); ?></p>
                </div>
                <div class="col-md-9">
                    <!-- Datos Personales -->
                    <h5 class="text-primary border-bottom pb-2 mb-3">1. Datos Personales y de Contacto</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><strong>Fecha Nacimiento:</strong> <?= htmlspecialchars($alumno['fecha_nacimiento']); ?></div>
                        <div class="col-md-4"><strong>Domicilio:</strong> <?= htmlspecialchars($alumno['domicilio']); ?></div>
                        <div class="col-md-4"><strong>Teléfono:</strong> <?= htmlspecialchars($alumno['telefono']); ?></div>
                        <div class="col-md-6"><strong>Email:</strong> <?= htmlspecialchars($alumno['email'] ?: 'No registrado'); ?></div>
                        <div class="col-md-6"><strong>Obra Social:</strong> <?= htmlspecialchars($alumno['obra_social'] ?: 'Ninguna'); ?></div>
                        <div class="col-md-6"><strong>Grupo Sanguíneo:</strong> <?= htmlspecialchars($alumno['grupo_sanguineo'] ?: 'No especificado'); ?></div>
                        <div class="col-md-6"><strong>Alergias / Salud:</strong> <?= htmlspecialchars($alumno['alergias'] ?: 'Ninguna'); ?></div>
                    </div>
                    <!-- Curso -->
                    <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">2. Ubicación Académica</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-12">
                            <strong>Curso Asignado:</strong> 
                            <?php if (!empty($alumno['anio'])): ?>
                                <?= htmlspecialchars($alumno['anio'] . '° ' . $alumno['division'] . ' - Especialidad: ' . $alumno['especialidad'] . ' (' . $alumno['turno'] . ')'); ?>
                            <?php else: ?>
                                <span class="text-danger">Sin Curso Asignado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Datos del Tutor -->
                    <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">3. Responsable / Tutor Legal</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><strong>Nombre Tutor:</strong> <?= htmlspecialchars($alumno['tutor_nombre']); ?></div>
                        <div class="col-md-6"><strong>DNI Tutor:</strong> <?= htmlspecialchars($alumno['tutor_dni']); ?></div>
                        <div class="col-md-6"><strong>Teléfono Tutor:</strong> <?= htmlspecialchars($alumno['tutor_telefono']); ?></div>
                        <div class="col-md-6"><strong>Parentesco:</strong> <?= htmlspecialchars($alumno['tutor_parentesco']); ?></div>
                    </div>
                    <!-- Autorizaciones -->
                    <h5 class="text-primary border-bottom pb-2 mb-3 mt-4">4. Autorizaciones Institucionales</h5>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4"><strong>Uso de Imagen:</strong> <span class="badge <?= $alumno['auth_imagen'] == 'Sí' ? 'bg-success' : 'bg-secondary'; ?>"><?= $alumno['auth_imagen']; ?></span></div>
                        <div class="col-md-4"><strong>Salidas Escolares:</strong> <span class="badge <?= $alumno['auth_salidas'] == 'Sí' ? 'bg-success' : 'bg-secondary'; ?>"><?= $alumno['auth_salidas']; ?></span></div>
                        <div class="col-md-4"><strong>Acuerdo Convivencia:</strong> <span class="badge <?= $alumno['auth_convivencia'] == 'Sí' ? 'bg-success' : 'bg-secondary'; ?>"><?= $alumno['auth_convivencia']; ?></span></div>
                    </div>
                </div>
            </div>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>