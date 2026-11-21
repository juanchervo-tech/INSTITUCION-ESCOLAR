<?php
session_start();
require __DIR__ . "/conexion.php";

$mensaje_contacto = "";
$clase_mensaje = "success";
$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? trim($_POST['nombre']) : '';
$email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';
$comentarios = isset($_POST['comentarios']) && is_string($_POST['comentarios']) ? trim($_POST['comentarios']) : '';
if (empty($_SESSION['contacto_csrf_token'])) {
    $_SESSION['contacto_csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['contacto_csrf_token'], $token)) {
        $clase_mensaje = "danger";
        $mensaje_contacto = "La solicitud venció o no es válida. Actualizá la página e intentá nuevamente.";
    } elseif (
        $nombre === ''
        || $email === ''
        || $comentarios === ''
        || strlen($nombre) > 120
        || strlen($email) > 254
        || strlen($comentarios) > 5000
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    ) {
        $clase_mensaje = "danger";
        $mensaje_contacto = "Completá los campos con datos válidos. El nombre admite hasta 120 caracteres, el correo 254 y el comentario 5000.";
    } else {
        try {
            $stmt = $conexion->prepare("INSERT INTO mensajes_contacto (nombre, email, comentarios) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $email, $comentarios);
            $stmt->execute();
            $stmt->close();
            $mensaje_contacto = "Gracias, " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ". Tu consulta fue guardada y la institución podrá revisarla desde el panel administrativo.";
            $nombre = $email = $comentarios = '';
        } catch (mysqli_sql_exception $e) {
            error_log("No se pudo guardar una consulta de contacto: " . $e->getMessage());
            $clase_mensaje = "danger";
            $mensaje_contacto = "No se pudo guardar el mensaje. Verificá que hayas ejecutado database/crear_tabla_mensajes_contacto.sql y volvé a intentarlo.";
        }
    }
}

require __DIR__ . "/template/cabecera.php";
?>

<main class="container my-5">
    
    <div class="p-5 mb-4 bg-light rounded-3 text-center">
        <h1 class="display-5 fw-bold mb-4">Contacto</h1>
        <p class="fs-5 text-muted mb-5">Podés comunicarte con la institución o visitarnos a través de los siguientes medios:</p>
         <div class="text-center">
            

        <form action="contacto.php" method="POST" class="container mt-4 col-md-6">
    <?php if ($mensaje_contacto !== ''): ?>
        <div class="alert alert-<?= $clase_mensaje; ?> mt-3" role="alert"><?= $mensaje_contacto; ?></div>
    <?php endif; ?>
    <div class="form-group mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="form-control" maxlength="120" value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ingrese su nombre" required>
    </div>

    <div class="form-group mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control" maxlength="254" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" placeholder="correo@ejemplo.com" required>
    </div>

    <div class="form-group mb-3">
        <label for="comentarios" class="form-label">Comentarios</label>
        <textarea name="comentarios" id="comentarios" class="form-control" rows="4" maxlength="5000" placeholder="Ingrese su comentario aquí..." required><?= htmlspecialchars($comentarios, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['contacto_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

    <button type="submit" class="btn btn-primary">Enviar</button>
</form>


    </div>
        <div class="row justify-content-center">
            <div class="col-md-8 bg-white p-4 rounded shadow-sm">
                <h3 class="mb-4 text-primary">Información Institucional</h3>
                
                <ul class="list-unstyled fs-5 lh-lg">
                    <li class="mb-3">
                        <strong>📍 Dirección:</strong> Monte Caseros, Corrientes, Argentina
                    </li>
                    <li class="mb-3">
                        <strong>📞 Teléfono:</strong> (3775) 425584
                    </li>
                    <li class="mb-3">
                        <strong>✉️ Correo Electrónico:</strong> etferre@yahoo.com.ar
                    </li>
                    <li class="mb-3">
                        <strong>⏰ Horarios de Atención:</strong> Lunes a Viernes de 07:30 a 12:30 y de 14:00 a 18:30 hs
                    </li>
                </ul>
            </div>
        </div>
    </div>
   
</main>


<?php require __DIR__ . "/template/footer.php"; ?>