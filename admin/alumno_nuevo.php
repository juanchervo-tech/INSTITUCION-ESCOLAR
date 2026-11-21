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
$mensaje = "";
$error = "";
$csrf_token = admin_csrf_token();
$id_usuario_actual = (int) $_SESSION['id_usuario'];
$es_preceptor = strtolower($_SESSION['rol'] ?? '') === 'preceptor';
if ($es_preceptor) {
    $cursos_stmt = $conexion->prepare(
        "SELECT c.* FROM cursos c
         INNER JOIN preceptor_curso pc ON pc.id_curso = c.id_curso
         WHERE pc.id_usuario = ?
         ORDER BY c.anio, c.division ASC"
    );
    $cursos_stmt->bind_param("i", $id_usuario_actual);
    $cursos_stmt->execute();
    $cursos_query = $cursos_stmt->get_result();
} else {
    $cursos_query = $conexion->query("SELECT * FROM cursos ORDER BY anio, division ASC");
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualizá la página e intentá nuevamente.";
    }
    // Recibir campos personales y familiares
    $legajo              = trim($_POST['legajo'] ?? '');
    $apellido            = trim($_POST['apellido'] ?? '');
    $nombre              = trim($_POST['nombre'] ?? '');
    $dni                 = trim($_POST['dni'] ?? '');
    $nacionalidad        = trim($_POST['nacionalidad'] ?? '');
    $lugar_nacimiento    = trim($_POST['lugar_nacimiento'] ?? '');
    $fecha_nac           = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
    $domicilio           = trim($_POST['domicilio'] ?? '');
    $localidad           = trim($_POST['localidad'] ?? '');
    $provincia           = trim($_POST['provincia'] ?? '');
    $telefono            = trim($_POST['telefono'] ?? '');
    $email               = trim($_POST['email'] ?? '');
    
    // Padres
    $padre_nombre        = trim($_POST['padre_nombre'] ?? '');
    $padre_dni           = trim($_POST['padre_dni'] ?? '');
    $madre_nombre        = trim($_POST['madre_nombre'] ?? '');
    $madre_dni           = trim($_POST['madre_dni'] ?? '');
    // Tutor
    $tutor_nombre        = trim($_POST['tutor_nombre'] ?? '');
    $tutor_dni           = trim($_POST['tutor_dni'] ?? '');
    $tutor_telefono      = trim($_POST['tutor_telefono'] ?? '');
    $tutor_parentesco    = trim($_POST['tutor_parentesco'] ?? '');
    // Académicos
    $id_curso            = !empty($_POST['id_curso']) ? intval($_POST['id_curso']) : null;
    $materias_adeuda     = trim($_POST['materias_adeuda'] ?? 'Ninguna');
    // Ficha Médica y Datos Sanitarios
    $obra_social         = trim($_POST['cobertura_medica'] ?? '');
    $nro_afiliado        = trim($_POST['nro_afiliado'] ?? '');
    $grupo_sanguineo     = trim($_POST['grupo_sanguineo'] ?? '');
    $peso                = trim($_POST['peso'] ?? '');
    $altura              = trim($_POST['altura'] ?? '');
    $alergias            = trim($_POST['alergias'] ?? 'Ninguna');
    $observaciones_medicas = trim($_POST['observaciones_medicas'] ?? '');
    $apto_fisico         = $_POST['apto_fisico'] ?? 'Sí';
    // Autorizaciones
    $auth_imagen         = $_POST['auth_imagen'] ?? 'No';
    $auth_salidas        = $_POST['auth_salidas'] ?? 'No';
    $auth_convivencia    = $_POST['auth_convivencia'] ?? 'No';
    $fecha_convivencia   = !empty($_POST['fecha_convivencia']) ? $_POST['fecha_convivencia'] : null;

    if (!in_array($auth_imagen, ['Sí', 'No'], true)
        || !in_array($auth_salidas, ['Sí', 'No'], true)
        || !in_array($auth_convivencia, ['Sí', 'No'], true)) {
        $error = "Una de las autorizaciones no es válida.";
    } elseif ($auth_convivencia === 'Sí' && $fecha_convivencia === null) {
        $error = "Indicá la fecha en que se firmó el acuerdo de convivencia.";
    } elseif ($auth_convivencia === 'No') {
        $fecha_convivencia = null;
    }

    if (empty($error) && $es_preceptor) {
        $stmt_curso = $conexion->prepare(
            "SELECT 1 FROM preceptor_curso WHERE id_usuario = ? AND id_curso = ?"
        );
        $stmt_curso->bind_param("ii", $id_usuario_actual, $id_curso);
        $stmt_curso->execute();
        if ($stmt_curso->get_result()->num_rows !== 1) {
            $error = "Solo podés registrar alumnos en tus cursos asignados.";
        }
        $stmt_curso->close();
    }
    
    $foto_nombre = null;
    $foto_subida = false;
    if (empty($error) && isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $dir_destino = __DIR__ . '/../uploads/';
        $max_size = 2 * 1024 * 1024; // 2MB
        $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $error = "No se pudo recibir la fotografía.";
        } elseif ($_FILES['foto']['size'] <= 0 || $_FILES['foto']['size'] > $max_size) {
            $error = "La imagen debe pesar más de 0 bytes y no superar los 2 MB.";
        } elseif (!is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $error = "El archivo recibido no es una carga válida.";
        } else {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = $file_info ? finfo_file($file_info, $_FILES['foto']['tmp_name']) : false;
            if ($file_info) {
                finfo_close($file_info);
            }
            if (!isset($allowed_mimes[$mime_type]) || @getimagesize($_FILES['foto']['tmp_name']) === false) {
                $error = "El archivo debe ser una imagen JPG o PNG válida.";
            } elseif (!is_dir($dir_destino) && !mkdir($dir_destino, 0755, true)) {
                $error = "No se pudo preparar la carpeta de fotografías.";
            } else {
                $foto_nombre = 'alumno_' . bin2hex(random_bytes(12)) . '.' . $allowed_mimes[$mime_type];
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $dir_destino . $foto_nombre)) {
                    $foto_subida = true;
                } else {
                    $error = "No se pudo guardar la fotografía.";
                }
            }
        }
    }
    if ($foto_nombre === null) {
        $foto_nombre = 'alumno_sin_foto.svg';
    }
    if (empty($error)) {
        try {
            $stmt = $conexion->prepare("INSERT INTO alumnos (
                legajo, apellido, nombre, dni, nacionalidad, lugar_nacimiento, fecha_nacimiento, domicilio, localidad, provincia, telefono, email,
                padre_nombre, padre_dni, madre_nombre, madre_dni, tutor_nombre, tutor_dni, tutor_telefono, tutor_parentesco,
                id_curso, materias_adeuda, obra_social, nro_afiliado, grupo_sanguineo, peso, altura, alergias, observaciones_medicas, apto_fisico,
                auth_imagen, auth_salidas, auth_convivencia, fecha_convivencia, foto
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param(
                str_repeat("s", 20) . "i" . str_repeat("s", 14),
                $legajo, $apellido, $nombre, $dni, $nacionalidad, $lugar_nacimiento, $fecha_nac, $domicilio, $localidad, $provincia, $telefono, $email,
                $padre_nombre, $padre_dni, $madre_nombre, $madre_dni, $tutor_nombre, $tutor_dni, $tutor_telefono, $tutor_parentesco,
                $id_curso, $materias_adeuda, $obra_social, $nro_afiliado, $grupo_sanguineo, $peso, $altura, $alergias, $observaciones_medicas, $apto_fisico,
                $auth_imagen, $auth_salidas, $auth_convivencia, $fecha_convivencia, $foto_nombre
            );
            $stmt->execute();
            $mensaje = "¡Ficha de matrícula y médica registrada exitosamente!";
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($foto_subida) {
                unlink(__DIR__ . '/../uploads/' . $foto_nombre);
            }
            error_log("Error al registrar matrícula de alumno: " . $e->getMessage());
            $error = "No se pudo registrar la matrícula. Verifique que la migración de la tabla de alumnos esté aplicada.";
        }
    }
}

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

        <div class="card shadow border-0 p-4">
            <div class="text-center mb-4 border-bottom pb-3">
                <img src="../img/logoescuela.jpg" alt="Escuela Técnica Pedro Ferré" width="70" class="mb-2">
                <h2 class="h4 font-weight-bold text-primary">Escuela Técnica "Pedro Ferré"</h2>
                <p class="text-muted mb-0">Sistema de Gestión Institucional - Ficha Única de Matrícula y Legajo</p>
            </div>
            
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-success"><?= $mensaje; ?> <a href="alumnos_listado.php">Ir al listado de alumnos</a></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?= $error; ?></div>
            <?php endif; ?>
            <form action="alumno_nuevo.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                
                <!-- 1. Datos Personales -->
                <h5 class="text-secondary border-bottom pb-2 mb-3">1. Ficha Individual de Matrícula (Datos Personales)</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><label class="form-label">Legajo (*)</label><input type="text" name="legajo" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">DNI (*)</label><input type="text" name="dni" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Apellido (*)</label><input type="text" name="apellido" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Nombre (*)</label><input type="text" name="nombre" class="form-control" required></div>
                    
                    <div class="col-md-3"><label class="form-label">Nacionalidad</label><input type="text" name="nacionalidad" class="form-control" value="Argentina"></div>
                    <div class="col-md-3"><label class="form-label">Lugar de Nacimiento</label><input type="text" name="lugar_nacimiento" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Fecha Nacimiento (*)</label><input type="date" name="fecha_nacimiento" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Teléfono (*)</label><input type="text" name="telefono" class="form-control" required></div>
                    
                    <div class="col-md-4"><label class="form-label">Domicilio (*)</label><input type="text" name="domicilio" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Localidad</label><input type="text" name="localidad" class="form-control" value="Monte Caseros"></div>
                    <div class="col-md-3"><label class="form-label">Provincia</label><input type="text" name="provincia" class="form-control" value="Corrientes"></div>
                    <div class="col-md-2"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                </div>
                <!-- 2. Grupo Familiar y Tutor -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">2. Grupo Familiar y Tutor Responsable</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-6"><label class="form-label">Nombre del Padre</label><input type="text" name="padre_nombre" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">DNI del Padre</label><input type="text" name="padre_dni" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Nombre de la Madre</label><input type="text" name="madre_nombre" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">DNI de la Madre</label><input type="text" name="madre_dni" class="form-control"></div>
                    
                    <div class="col-md-3"><label class="form-label">Nombre del Tutor (*)</label><input type="text" name="tutor_nombre" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">DNI Tutor (*)</label><input type="text" name="tutor_dni" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Teléfono Tutor (*)</label><input type="text" name="tutor_telefono" class="form-control" required></div>
                    <div class="col-md-3"><label class="form-label">Parentesco (*)</label><input type="text" name="tutor_parentesco" class="form-control" placeholder="Padre/Madre/Tutor" required></div>
                </div>
                <!-- 3. Datos Académicos y Foto -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">3. Datos Académicos</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Curso / Especialidad (*)</label>
                        <select name="id_curso" class="form-select" required>
                            <option value="">-- Seleccionar Curso --</option>
                            <?php while($c = $cursos_query->fetch_assoc()): ?>
                                <option value="<?= $c['id_curso']; ?>">
                                    <?= $c['anio'] . " " . $c['division'] . " - " . $c['especialidad'] . " (" . $c['turno'] . ")"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Materias que Adeuda</label><input type="text" name="materias_adeuda" class="form-control" value="Ninguna"></div>
                    <div class="col-md-6"><label class="form-label">Fotografía del Alumno</label><input type="file" name="foto" class="form-control" accept="image/*"></div>
                </div>
                <!-- 4. Ficha Médica -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">4. Ficha Médica del Alumno</h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label">Cobertura Médica / Obra Social</label><input type="text" name="cobertura_medica" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">N° de Afiliado</label><input type="text" name="nro_afiliado" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label">Grupo Sanguíneo</label><input type="text" name="grupo_sanguineo" class="form-control" placeholder="Ej: A+"></div>
                    <div class="col-md-1"><label class="form-label">Peso</label><input type="text" name="peso" class="form-control" placeholder="kg"></div>
                    <div class="col-md-2"><label class="form-label">Altura</label><input type="text" name="altura" class="form-control" placeholder="cm"></div>
                    
                    <div class="col-md-6"><label class="form-label">Alergias o Enfermedades Declaradas</label><input type="text" name="alergias" class="form-control" placeholder="Ninguna"></div>
                    <div class="col-md-6"><label class="form-label">Observaciones / Tratamientos</label><input type="text" name="observaciones_medicas" class="form-control"></div>
                    <div class="col-md-4">
                        <label class="form-label">¿Apto para Educación Física?</label>
                        <select name="apto_fisico" class="form-select">
                            <option value="Sí" selected>Sí</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
                <!-- 5. Autorizaciones y Convivencia -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">5. Autorizaciones y Acuerdo de Convivencia</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">¿Autoriza uso de imagen?</label>
                        <select name="auth_imagen" class="form-select">
                            <option value="Sí">Sí</option>
                            <option value="No" selected>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">¿Autoriza salidas escolares?</label>
                        <select name="auth_salidas" class="form-select">
                            <option value="Sí">Sí</option>
                            <option value="No" selected>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">¿Acuerdo de convivencia firmado?</label>
                        <select name="auth_convivencia" class="form-select">
                            <option value="Sí">Sí</option>
                            <option value="No" selected>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de Convivencia / Firma</label>
                        <input type="date" name="fecha_convivencia" class="form-control">
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-5">Guardar Matrícula Completa</button>
                </div>
            </form>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>