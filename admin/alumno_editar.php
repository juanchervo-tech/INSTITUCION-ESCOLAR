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
    exit('No tenés permisos para modificar este alumno.');
}
$mensaje = "";
$error = "";
$csrf_token = admin_csrf_token();
$es_preceptor = strtolower($_SESSION['rol'] ?? '') === 'preceptor';
if ($es_preceptor) {
    $cursos_stmt = $conexion->prepare(
        "SELECT c.* FROM cursos c
         INNER JOIN preceptor_curso pc ON pc.id_curso = c.id_curso
         WHERE pc.id_usuario = ?
         ORDER BY c.anio, c.division ASC"
    );
    $cursos_stmt->bind_param("i", $_SESSION['id_usuario']);
    $cursos_stmt->execute();
    $cursos_query = $cursos_stmt->get_result();
} else {
    $cursos_query = $conexion->query("SELECT * FROM cursos ORDER BY anio, division ASC");
}
// Procesar formulario de edición
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!admin_validar_csrf($_POST['csrf_token'] ?? null)) {
        $error = "La solicitud venció o no es válida. Actualizá la página e intentá nuevamente.";
    }
    $legajo = trim($_POST['legajo'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nac = $_POST['fecha_nacimiento'] ?? '';
    $domicilio = trim($_POST['domicilio'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $obra_social = trim($_POST['obra_social'] ?? '');
    $alergias = trim($_POST['alergias'] ?? '');
    $grupo_sanguineo = trim($_POST['grupo_sanguineo'] ?? '');
    $id_curso = !empty($_POST['id_curso'])
        ? intval($_POST['id_curso']) : null;
    $tutor_nombre = trim($_POST['tutor_nombre'] ?? '');
    $tutor_dni = trim($_POST['tutor_dni'] ?? '');
    $tutor_telefono = trim($_POST['tutor_telefono'] ?? '');
    $tutor_parentesco = trim($_POST['tutor_parentesco'] ?? '');
    $auth_imagen = $_POST['auth_imagen'] ?? 'No';
    $auth_salidas = $_POST['auth_salidas'] ?? 'No';
    $auth_convivencia = $_POST['auth_convivencia'] ?? 'No';
    $fecha_convivencia = !empty($_POST['fecha_convivencia']) ? $_POST['fecha_convivencia'] : null;

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
        $stmt_curso->bind_param("ii", $_SESSION['id_usuario'], $id_curso);
        $stmt_curso->execute();
        if ($stmt_curso->get_result()->num_rows !== 1) {
            $error = "Solo podés asignar alumnos a tus cursos autorizados.";
        }
        $stmt_curso->close();
    }
    
    // Recuperar la fotografía actual desde la base de datos.
    $stmt_foto = $conexion->prepare(
        "SELECT foto FROM alumnos WHERE id_alumno = ?"
    );
    $stmt_foto->bind_param("i", $id_alumno);
    $stmt_foto->execute();
    $resultado_foto = $stmt_foto->get_result()->fetch_assoc();
    $stmt_foto->close();
    if (!$resultado_foto) {
        $error = "No se encontró el alumno.";
    }
    $foto_actual = $resultado_foto['foto'] ?? 'alumno_sin_foto.svg';
    $foto_nombre = $foto_actual;
    $foto_nueva_subida = false;
    
    // Validar y procesar una nueva fotografía
    $max_size = 2 * 1024 * 1024; // 2 MB
    if (
        empty($error) &&
        isset($_FILES['foto']) &&
        $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            $error = "Error al subir la fotografía.";
        } elseif (
            $_FILES['foto']['size'] <= 0 ||
            $_FILES['foto']['size'] > $max_size
        ) {
            $error = "La imagen debe pesar más de 0 bytes y no superar los 2 MB.";
        } elseif (!is_uploaded_file($_FILES['foto']['tmp_name'])) {
            $error = "El archivo recibido no es una carga válida.";
        } else {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = $file_info
                ? finfo_file($file_info, $_FILES['foto']['tmp_name'])
                : false;
            if ($file_info) {
                finfo_close($file_info);
            }
            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png'
            ];
            if (!isset($allowed_mimes[$mime_type])) {
                $error = "Solo se permiten imágenes JPG o PNG.";
            } elseif (
                @getimagesize($_FILES['foto']['tmp_name']) === false
            ) {
                $error = "El archivo no es una imagen válida.";
            } else {
                $ext = $allowed_mimes[$mime_type];
                $dir_destino = __DIR__ . '/../uploads/';
                if (
                    !is_dir($dir_destino) &&
                    !mkdir($dir_destino, 0755, true)
                ) {
                    $error = "No se pudo crear la carpeta de imágenes.";
                } else {
                    $nuevo_nombre = 'alumno_' .
                        bin2hex(random_bytes(8)) . '.' . $ext;
                    if (move_uploaded_file(
                        $_FILES['foto']['tmp_name'],
                        $dir_destino . $nuevo_nombre
                    )) {
                        $foto_nombre = $nuevo_nombre;
                        $foto_nueva_subida = true;
                    } else {
                        $error = "No se pudo guardar la fotografía.";
                    }
                }
            }
        }
    }
    
    // Actualizar únicamente si no hubo errores
    if (empty($error)) {
        try {
            $stmt_update = $conexion->prepare(
                "UPDATE alumnos SET
                    legajo = ?,
                    apellido = ?,
                    nombre = ?,
                    dni = ?,
                    fecha_nacimiento = ?,
                    domicilio = ?,
                    telefono = ?,
                    email = ?,
                    obra_social = ?,
                    alergias = ?,
                    grupo_sanguineo = ?,
                    id_curso = ?,
                    tutor_nombre = ?,
                    tutor_dni = ?,
                    tutor_telefono = ?,
                    tutor_parentesco = ?,
                    auth_imagen = ?,
                    auth_salidas = ?,
                    auth_convivencia = ?,
                    fecha_convivencia = ?,
                    foto = ?
                WHERE id_alumno = ?"
            );
            $stmt_update->bind_param(
                "sssssssssssisssssssssi",
                $legajo,
                $apellido,
                $nombre,
                $dni,
                $fecha_nac,
                $domicilio,
                $telefono,
                $email,
                $obra_social,
                $alergias,
                $grupo_sanguineo,
                $id_curso,
                $tutor_nombre,
                $tutor_dni,
                $tutor_telefono,
                $tutor_parentesco,
                $auth_imagen,
                $auth_salidas,
                $auth_convivencia,
                $fecha_convivencia,
                $foto_nombre,
                $id_alumno
            );
            if ($stmt_update->execute()) {
                $mensaje = "¡Datos del alumno actualizados correctamente!";
            } else {
                $error = "Error al actualizar los datos.";
            }
            $stmt_update->close();
        } catch (mysqli_sql_exception $e) {
            if ($foto_nueva_subida) {
                unlink(__DIR__ . '/../uploads/' . $foto_nombre);
            }
            if ($e->getCode() == 1062) {
                $error = "El legajo o DNI ingresado ya le pertenece a otro alumno.";
            } else {
                error_log($e->getMessage());
                $error = "Error en la base de datos al actualizar el alumno.";
            }
        }
    }
}

// Obtener datos actualizados del alumno
$stmt = $conexion->prepare(
    "SELECT * FROM alumnos WHERE id_alumno = ?"
);
$stmt->bind_param("i", $id_alumno);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$alumno) {
    echo "Alumno no encontrado.";
    exit();
}

// Incluir cabecera modular
include 'TEMPLATE/header.php';
?>

        <div class="card shadow border-0 p-4">
            <h2 class="h4 font-weight-bold text-primary mb-3">
                Editar Ficha de Alumno
            </h2>
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($mensaje); ?>
                    <a href="alumnos_listado.php">
                        Volver al listado
                    </a>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <form action="alumno_editar.php?id=<?= $id_alumno; ?>"
                  method="POST"
                  enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <!-- Datos personales -->
                <h5 class="text-secondary border-bottom pb-2 mb-3">
                    1. Datos Personales
                </h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Legajo (*)</label>
                        <input type="text" name="legajo"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['legajo']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">DNI (*)</label>
                        <input type="text" name="dni"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['dni']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Apellido (*)</label>
                        <input type="text" name="apellido"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['apellido']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nombre (*)</label>
                        <input type="text" name="nombre"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['nombre']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha Nacimiento (*)</label>
                        <input type="date" name="fecha_nacimiento"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['fecha_nacimiento']); ?>"
                               required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Domicilio (*)</label>
                        <input type="text" name="domicilio"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['domicilio']); ?>"
                               required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Teléfono (*)</label>
                        <input type="text" name="telefono"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['telefono']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Obra Social</label>
                        <input type="text" name="obra_social"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['obra_social'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Grupo Sanguíneo</label>
                        <input type="text" name="grupo_sanguineo"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['grupo_sanguineo'] ?? ''); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Alergias / Salud</label>
                        <input type="text" name="alergias"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['alergias'] ?? ''); ?>">
                    </div>
                </div>
                <!-- Datos académicos y fotografía -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">
                    2. Datos Académicos y Fotografía
                </h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Curso / Especialidad (*)
                        </label>
                        <select name="id_curso" class="form-select" required>
                            <option value="">-- Seleccionar Curso --</option>
                            <?php while ($c = $cursos_query->fetch_assoc()): ?>
                                <option
                                    value="<?= (int)$c['id_curso']; ?>"
                                    <?= ((int)$alumno['id_curso'] === (int)$c['id_curso'])
                                        ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars(
                                        $c['anio'] . " " .
                                        $c['division'] . " - " .
                                        $c['especialidad'] . " (" .
                                        $c['turno'] . ")"
                                    ); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">
                            Cambiar Fotografía (Opcional)
                        </label>
                        <input type="file" name="foto"
                               class="form-control"
                               accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        <small class="text-muted">
                            Formatos JPG o PNG. Tamaño máximo: 2 MB.
                        </small>
                    </div>
                </div>
                <!-- Datos del tutor -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">
                    3. Datos del Tutor
                </h5>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Nombre del Tutor (*)</label>
                        <input type="text" name="tutor_nombre"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['tutor_nombre']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">DNI Tutor (*)</label>
                        <input type="text" name="tutor_dni"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['tutor_dni']); ?>"
                               required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teléfono Tutor (*)</label>
                        <input type="text" name="tutor_telefono"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['tutor_telefono']); ?>"
                               required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Parentesco (*)</label>
                        <input type="text" name="tutor_parentesco"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['tutor_parentesco']); ?>"
                               required>
                    </div>
                </div>
                <!-- Autorizaciones -->
                <h5 class="text-secondary border-bottom pb-2 mb-3 mt-4">
                    4. Autorizaciones
                </h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">
                            ¿Autoriza uso de imagen?
                        </label>
                        <select name="auth_imagen" class="form-select">
                            <option value="Sí"
                                <?= ($alumno['auth_imagen'] == 'Sí')
                                    ? 'selected' : ''; ?>>
                                Sí
                            </option>
                            <option value="No"
                                <?= ($alumno['auth_imagen'] == 'No')
                                    ? 'selected' : ''; ?>>
                                No
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            ¿Autoriza salidas escolares?
                        </label>
                        <select name="auth_salidas" class="form-select">
                            <option value="Sí"
                                <?= ($alumno['auth_salidas'] == 'Sí')
                                    ? 'selected' : ''; ?>>
                                Sí
                            </option>
                            <option value="No"
                                <?= ($alumno['auth_salidas'] == 'No')
                                    ? 'selected' : ''; ?>>
                                No
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">
                            ¿Acuerdo de convivencia firmado?
                        </label>
                        <select name="auth_convivencia" class="form-select">
                            <option value="Sí"
                                <?= ($alumno['auth_convivencia'] == 'Sí')
                                    ? 'selected' : ''; ?>>
                                Sí
                            </option>
                            <option value="No"
                                <?= ($alumno['auth_convivencia'] == 'No')
                                    ? 'selected' : ''; ?>>
                                No
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha_convivencia">
                            Fecha del acuerdo
                        </label>
                        <input type="date" id="fecha_convivencia" name="fecha_convivencia"
                               class="form-control"
                               value="<?= htmlspecialchars($alumno['fecha_convivencia'] ?? ''); ?>">
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-warning px-5">
                        Guardar Cambios
                    </button>
                </div>
            </form>
        </div>

<?php 
// Incluir pie de página modular
include 'TEMPLATE/footer.php'; 
?>