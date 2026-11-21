<?php
require __DIR__ . "/conexion.php";
$resultado_busqueda = null;
$busqueda_realizada = false;
$termino = isset($_GET['buscar_publico']) && is_string($_GET['buscar_publico'])
    ? trim($_GET['buscar_publico'])
    : '';

if ($termino !== '') {
    $busqueda_realizada = true;
    $stmt = $conexion->prepare(
        "SELECT a.nombre, a.apellido, c.anio, c.division, c.especialidad, c.turno
         FROM alumnos a
         LEFT JOIN cursos c ON c.id_curso = a.id_curso
         WHERE a.dni = ? OR a.legajo = ?"
    );
    $stmt->bind_param("ss", $termino, $termino);
    $stmt->execute();
    $resultado_busqueda = $stmt->get_result();
    $stmt->close();
}

require __DIR__ . "/template/cabecera.php"; 
?>

<main class="container my-4">
    <!-- Bloque Principal de Bienvenida -->
    <div class="p-5 mb-4 bg-light rounded-3 text-center shadow-sm">
        <h1 class="display-5 fw-bold">Nuestra Escuela</h1>
        <img src="img/escuela67.jpeg" alt="Edificio principal de la Escuela Técnica Pedro Ferré" width="800" height="300" class="img-fluid my-3 rounded" loading="lazy">  
        <p class="fs-4 text-muted">
            Formando profesionales técnicos con futuro e innovación.
        </p>

        <div class="row justify-content-center mt-4">
            <div class="col-md-8">
                <div class="card shadow-sm border-0 p-4 bg-white">
                    <h2 class="h5 fw-bold text-primary mb-3">Consulta de alumno</h2>
                    <p class="text-muted">Ingresá el DNI o número de legajo para consultar el curso asignado.</p>
                    <form action="index.php" method="GET" class="row g-3">
                        <div class="col-md-9">
                            <label class="visually-hidden" for="buscar_publico">DNI o legajo</label>
                            <input type="text" name="buscar_publico" id="buscar_publico"
                                   class="form-control" placeholder="DNI o número de legajo"
                                   value="<?= htmlspecialchars($termino, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">Consultar</button>
                        </div>
                    </form>

                    <?php if ($busqueda_realizada): ?>
                        <div class="mt-4">
                            <?php if ($resultado_busqueda->num_rows > 0): ?>
                                <?php while ($alumno = $resultado_busqueda->fetch_assoc()): ?>
                                    <div class="alert alert-success text-start mb-2" role="status">
                                        <strong><?= htmlspecialchars($alumno['apellido'] . ', ' . $alumno['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br>
                                        Curso:
                                        <?php if (!empty($alumno['anio'])): ?>
                                            <?= htmlspecialchars(
                                                $alumno['anio'] . '° ' . $alumno['division'] . ' - '
                                                . $alumno['especialidad'] . ' (' . $alumno['turno'] . ')',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sin curso asignado</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="alert alert-warning" role="status">
                                    No se encontró un alumno con ese DNI o número de legajo.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sección de Novedades y Accesos Rápidos -->
    <div class="row">
        <!-- Tarjeta 1: Especialidades -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="card-body">
                    <h3 class="h5 card-title fw-bold">Nuestras especialidades</h3>
                    <p class="card-text text-muted small">Conocé nuestras orientaciones en Informática, Construcción y Electromecánica.</p>
                    <a href="especialidades.php" class="btn btn-outline-primary btn-sm mt-2">Ver Especialidades</a>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2: Institucional -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="card-body">
                    <h3 class="h5 card-title fw-bold">Sobre Nosotros</h3>
                    <p class="card-text text-muted small">Descubrí nuestra historia, misión y visión como institución técnica referente.</p>
                    <a href="nosotros.php" class="btn btn-outline-primary btn-sm mt-2">Conocer Más</a>
                </div>
            </div>
        </div>

        <!-- Tarjeta 3: Contacto -->
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0 text-center p-3">
                <div class="card-body">
                    <h3 class="h5 card-title fw-bold">Atención</h3>
                    <p class="card-text text-muted small">Consultá nuestros teléfonos, correos oficiales y horarios de atención al público.</p>
                    <a href="contacto.php" class="btn btn-outline-primary btn-sm mt-2">Ver Contacto</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . "/template/footer.php"; ?>