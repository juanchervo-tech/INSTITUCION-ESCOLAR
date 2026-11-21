<?php
// Aseguramos que la sesión esté iniciada y las rutas sean correctas
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrativo - Escuela Técnica Pedro Ferré</title>
    <!-- Rutas relativas corregidas desde la carpeta admin/ -->
    <link rel="stylesheet" href="../estilo/bootstrap.min.css">
    <link rel="stylesheet" href="../estilo/custom.css">
</head>
<body class="bg-light">

    <!-- Barra Superior con Navegación Bidireccional y Sesión Unificada -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm p-3 mb-4">
        <div class="container-fluid px-4">
            <a class="navbar-brand font-weight-bold d-flex align-items-center" href="admin_dashboard.php">
                <img src="../img/logoescuela.jpg" alt="Logo" width="30" height="30" class="me-2 rounded-circle bg-white p-1"> 
                ⚙️ Panel Admin
            </a>
            
            <div class="d-flex align-items-center ms-auto gap-3">
                <!-- Botón de navegación bidireccional exigido por la profesora -->
                <a href="../index.php" class="btn btn-outline-light btn-sm">🌐 Ir al Sitio Público</a>
                <?php if (in_array(strtolower($_SESSION['rol'] ?? ''), ['administrador', 'directivo'], true)): ?>
                    <a href="reportes.php" class="btn btn-info text-white btn-sm">📊 Ver Reportes</a>
                    <a href="mensajes_contacto.php" class="btn btn-outline-light btn-sm">Mensajes</a>
                    <a href="usuarios_listado.php" class="btn btn-outline-light btn-sm">Usuarios</a>
                <?php endif; ?>
                <span class="text-white">Usuario: <strong><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Administrador'); ?></strong></span>
                <a href="../logout.php" class="btn btn-danger btn-sm">Cerrar Sesión</a>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="container my-4">