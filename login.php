<?php
session_start();
// Si ya inició sesión, lo redirige al panel admin
if (isset($_SESSION['id_usuario'])) {
    header("Location: admin/admin_dashboard.php");
    exit();
}

require __DIR__ . "/conexion.php";
$mensaje_error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user  = isset($_POST['usuario']) && is_string($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $clave = isset($_POST['clave']) && is_string($_POST['clave']) ? $_POST['clave'] : '';

    if (!empty($user) && !empty($clave)) {
        // Consulta preparada para evitar inyecciones SQL (Seguridad)
        $stmt = $conexion->prepare("SELECT id_usuario, usuario, clave, nombre, apellido, rol FROM usuarios WHERE usuario = ? AND activo = 1");
        $stmt->bind_param("s", $user);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {
            $usuario_data = $resultado->fetch_assoc();
            
            // Verificar la contraseña encriptada
            if (password_verify($clave, $usuario_data['clave'])) {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $usuario_data['id_usuario'];
                $_SESSION['nombre_completo'] = $usuario_data['nombre'] . " " . $usuario_data['apellido'];
                $_SESSION['rol'] = $usuario_data['rol'];

                header("Location: admin/admin_dashboard.php");
                exit();
            } else {
                $mensaje_error = "Usuario o contraseña incorrectos.";
            }
        } else {
            $mensaje_error = "Usuario o contraseña incorrectos.";
        }
        $stmt->close();
    } else {
        $mensaje_error = "Por favor, complete todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema - Escuela Técnica Pedro Ferré</title>
    <link rel="stylesheet" href="estilo/bootstrap.min.css">
    <link rel="stylesheet" href="estilo/custom.css">
</head>
<body class="bg-light d-flex align-items-center vh-100">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow border-0 rounded-lg">
                    <div class="card-header bg-primary text-white text-center py-3">
                        <h4 class="mb-0 font-weight-bold">Sistema de Gestión</h4>
                        <small>Escuela Técnica "Pedro Ferré"</small>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if(!empty($mensaje_error)): ?>
                            <div class="alert alert-danger text-center p-2" role="alert">
                                <?= $mensaje_error; ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <div class="form-group mb-3">
                                <label for="usuario" class="form-label font-weight-bold">Usuario</label>
                                <input type="text" name="usuario" id="usuario" class="form-control" placeholder="Ingrese su usuario" required autofocus>
                            </div>

                            <div class="form-group mb-4">
                                <label for="clave" class="form-label font-weight-bold">Contraseña</label>
                                <input type="password" name="clave" id="clave" class="form-control" placeholder="Ingrese su contraseña" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-block w-100 font-weight-bold py-2">
                                Iniciar Sesión
                            </button>
                        </form>

                    </div>
                    <div class="card-footer text-center py-2 bg-white border-0">
                        <a href="index.php" class="text-decoration-none text-muted small">← Volver al sitio público</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>