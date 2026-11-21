<?php
session_start();
if (!isset($_SESSION['id_usuario']) || !in_array(strtolower($_SESSION['rol'] ?? ''), ['administrador', 'directivo'], true)) {
    header("Location: ../login.php");
    exit();
}

require __DIR__ . "/../conexion.php";
require __DIR__ . "/TEMPLATE/seguridad.php";
admin_validar_sesion($conexion);
admin_requerir_roles(['administrador', 'directivo']);
$resultado = $conexion->query("SELECT id_mensaje, nombre, email, comentarios, fecha_envio FROM mensajes_contacto ORDER BY fecha_envio DESC, id_mensaje DESC");
include 'TEMPLATE/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 text-primary mb-1">Mensajes de contacto</h1>
        <p class="text-muted mb-0">Consultas recibidas desde el sitio público.</p>
    </div>
    <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Volver al panel</a>
</div>

<div class="card shadow-sm border-0 p-3">
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Mensaje</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado->num_rows > 0): ?>
                    <?php while ($mensaje = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($mensaje['fecha_envio'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($mensaje['nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><a href="mailto:<?= htmlspecialchars($mensaje['email'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($mensaje['email'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td class="text-break"><?= nl2br(htmlspecialchars($mensaje['comentarios'], ENT_QUOTES, 'UTF-8')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Todavía no hay consultas recibidas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'TEMPLATE/footer.php'; ?>
