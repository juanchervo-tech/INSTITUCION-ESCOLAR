<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sitio Institucional - Escuela Técnica Pedro Ferré</title>
    <!-- CSS de Bootstrap -->
    <link rel="stylesheet" href="estilo/bootstrap.min.css">
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="estilo/custom.css">
</head>
<body>

    <!-- Encabezado Institucional -->
    <header class="py-3 text-center">
        <h1 class="h3 m-0">Escuela Técnica "Pedro Ferré"</h1>
    </header>

    <!-- Barra de Navegación -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid px-4">
            
            <!-- 1. Logo a la izquierda -->
            <a class="navbar-brand d-flex align-items-center mr-0" href="index.php">
                <img width="70" height="70" src="img/logoescuela.jpg" alt="Logo oficial de la Escuela Técnica Pedro Ferré" loading="lazy">
            </a>

            <!-- Botón responsive para móviles -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Desplegar menú de navegación">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Contenedor del menú ajustado -->
            <div class="collapse navbar-collapse w-100" id="navMenu">
                
                <!-- 2. Enlaces centrados en el medio exacto -->
                <ul class="navbar-nav mx-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="especialidades.php">Especialidades</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contacto.php">Contacto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nosotros.php">Nosotros</a>
                    </li>
                </ul>

                <!-- 3. Botón de acceso al Admin (Tuerca) -->
<div class="d-flex align-items-center">
    <a href="login.php" class="btn btn-outline-secondary" title="Acceso al Panel Administrativo">
        <img src="img/tuerca.png" alt="Icono de configuración" width="25" height="25" loading="lazy">
    </a>
</div>

            </div>
        </div>
    </nav>
