<?php
session_start();

// El acceso de gestión se limita a directivos y administradores.
if (!isset($_SESSION['id_usuario']) || !in_array(strtolower($_SESSION['rol'] ?? ''), ['administrador', 'directivo'], true)) {
    header("Location: admin_dashboard.php?error=acceso_restringido");
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
    $usuario       = trim($_POST['usuario'] ?? '');
    $clave_plana   = $_POST['clave'] ?? '';
    $nombre        = trim($_POST['nombre'] ?? '');
    $apellido      = trim($_POST['apellido'] ?? '');
    $rol           = $_POST['rol'] ?? 'Preceptor';

    if (empty($error) && !in_array($rol, ['Preceptor', 'Administrador', 'Directivo'], true)) {
        $error = "El rol seleccionado no es válido.";
    } elseif (empty($error) &&
        !empty($usuario)
        && !empty($clave_plana)
        && strlen($clave_plana) >= 8
        && !empty($nombre)
        && !empty($apellido)
    ) {
        // Encriptar la contraseña de forma segura con hash
        $clave_hash = password_hash($clave_plana, PASSWORD_DEFAULT);

        try {
            $stmt = $conexion->prepare("INSERT INTO usuarios (usuario, clave, nombre, apellido, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $usuario, $clave_hash, $nombre, $apellido, $rol);
            
            if ($stmt->execute()) {
                $mensaje = "¡Usuario '$usuario' (Rol: $rol) creado exitosamente!";
            } else {
                $error = "Error al crear el usuario: " . $conexion->error;
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = "El nombre de usuario ya se encuentra registrado en el sistema.";
            } else {
                $error = "Error en la base de datos: " . $e->getMessage();
            }
        }
    } else {
        $error = "Complete todos los campos obligatorios y use una contraseña de al menos 8 caracteres.";
    }
}

// Incluir la cabecera modular desde la carpeta TEMPLATE
include 'TEMPLATE/header.php';
?>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold" href="admin_dashboard.php">⚙️ Panel Admin - Crear Usuarios</a>
            <div class="d-flex text-white ms-auto">
                <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">← Volver al Panel</a>
            </div>
        </div>
    </nav>

        <div class="card shadow border-0 p-4 mx-auto" style="max-width: 600px;">
            <h2 class="h4 font-weight-bold text-primary mb-3">Registrar Nuevo Usuario o Preceptor</h2>
            
            <?php if(!empty($mensaje)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form action="crear_usuario.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Nombre de Usuario (*)</label>
                    <input type="text" name="usuario" class="form-control" placeholder="Ej: preceptor_1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Contraseña (*)</label>
                    <input type="password" name="clave" class="form-control" placeholder="Contraseña de acceso" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Nombre (*)</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre" required>
                </div>
                <div class="mb-3">
                    <label class="form-label font-weight-bold">Apellido (*)</label>
                    <input type="text" name="apellido" class="form-control" placeholder="Apellido" required>
                </div>
                <div class="mb-4">
                    <label class="form-label font-weight-bold">Rol en la Institución (*)</label>
                    <select name="rol" class="form-select" required>
                        <option value="Preceptor">Preceptor</option>
                        <option value="Administrador">Administrador</option>
                        <option value="Directivo">Directivo</option>
                    </select>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4">Guardar Usuario</button>
                </div>
            </form>
        </div>

<?php 
// Incluir el pie de página modular desde la carpeta TEMPLATE
include 'TEMPLATE/footer.php'; 
?>