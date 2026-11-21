<?php
// conexion.php - Conexión a la Base de Datos en Laragon
$host     = "localhost";
$usuario  = "root";
$password = "";
$bd       = "gestion_escuela";

$conexion = new mysqli($host, $usuario, $password, $bd);

// Verificar la conexión
if ($conexion->connect_error) {
    die("Error crítico de conexión a la Base de Datos: " . $conexion->connect_error);
}

// Establecer conjunto de caracteres a UTF-8 para tildes y ñ
$conexion->set_charset("utf8mb4");
?>