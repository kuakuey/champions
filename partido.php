<?php
/**
 * Detalle de un partido y carga pública de goles y tarjetas.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

$partidoId = entero_get('id');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $partidoId = entero_post('partido_id', 1, 1000000) ?? $partidoId;
    try {
        $accion = (string) ($_POST['accion'] ?? '');
        if ($accion === 'quitar') {
            $mensaje = quitar_evento(entero_post('evento_id', 1, 1000000) ?? 0, $partidoId);
        } elseif ($accion === 'agregar') {
            $mensaje = agregar_evento_partido(
                $partidoId,
                entero_post('jugador_id', 1, 1000000) ?? 0,
                (string) ($_POST['tipo'] ?? '')
            );
        } else {
            throw new RuntimeException('Acción no reconocida.');
        }
        poner_aviso('success', $mensaje);
    } catch (RuntimeException $e) {
        poner_aviso('danger', $e->getMessage());
    } catch (PDOException $e) {
        error_log('Evento de partido: ' . $e->getMessage());
        poner_aviso('danger', 'No se pudo guardar el evento.');
    }
    redirigir(url_public('partido.php?id=' . $partidoId));
}

$partido = obtener_partido($partidoId);
if ($partido === null) {
    abortar(404, 'No encontramos ese partido.');
}

$eventos = array_values(array_filter(
    eventos_partido($partidoId),
    static fn (array $evento): bool => (string) $evento['tipo'] !== 'asistencia'
));
$goles = [];
$tarjetas = [];
foreach ($eventos as $evento) {
    if ($evento['tipo'] === 'gol' || $evento['tipo'] === 'autogol') {
        $goles[] = $evento;
    } elseif ($evento['tipo'] === 'amarilla' || $evento['tipo'] === 'roja') {
        $tarjetas[] = $evento;
    }
}
$locales = listar_jugadores((int) $partido['local_id'], true);
$visitas = listar_jugadores((int) $partido['visitante_id'], true);

$titulo = $partido['local_nombre'] . ' vs ' . $partido['visita_nombre'];
$pagina = 'inicio';
$descripcion = texto_partido($partido);

require __DIR__ . '/includes/header.php';

$nombreEvento = static function (array $evento): string {
    $nombre = nombre_persona($evento);

    return $nombre !== '' ? $nombre : 'Sin jugador';
};
?>
<p class="mb-2"><a href="<?= e(url_public('index.php')) ?>">Volver</a></p>
<div class="card partido-card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge <?= ($partido['tipo'] ?? '') === 'eliminatoria' ? 'text-bg-dark' : 'text-bg-success' ?>">
                <?= ($partido['tipo'] ?? '') === 'eliminatoria' ? 'Eliminatoria' : 'Clasificatoria' ?>
            </span>
            <?= html_estado((string) $partido['estado']) ?>
        </div>
        <div class="row align-items-center g-3 text-center">
            <div class="col-4">
                <div>
                    <div class="mb-2"><?= html_escudo($partido['local_escudo'], (string) $partido['local_nombre'], 72) ?></div>
                    <div class="fw-semibold"><?= e($partido['local_nombre']) ?></div>
                </div>
            </div>
            <div class="col-4">
                <div class="marcador display-6"><?= e(marcador_texto($partido)) ?></div>
            </div>
            <div class="col-4">
                <div>
                    <div class="mb-2"><?= html_escudo($partido['visita_escudo'], (string) $partido['visita_nombre'], 72) ?></div>
                    <div class="fw-semibold"><?= e($partido['visita_nombre']) ?></div>
                </div>
            </div>
        </div>
        <ul class="list-unstyled small text-secondary mt-4 mb-0">
            <li><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e((string) ($partido['cancha'] ?? 'Cancha por confirmar')) ?></li>
            <li><i class="bi bi-person-badge me-1" aria-hidden="true"></i>Árbitro: <?= e((string) ($partido['arbitro'] ?? 'Por designar')) ?></li>
        </ul>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <form method="post" class="card">
            <div class="card-body">
                <h2 class="h5">Añadir gol o tarjeta</h2>
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="agregar">
                <input type="hidden" name="partido_id" value="<?= (int) $partido['id'] ?>">
                <div class="mb-3">
                    <label class="form-label" for="tipo">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="gol">Gol</option>
                        <option value="amarilla">Tarjeta amarilla</option>
                        <option value="roja">Tarjeta roja</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="jugador_id">Persona</label>
                    <select class="form-select" id="jugador_id" name="jugador_id" required>
                        <option value="">Elige</option>
                        <optgroup label="<?= e((string) $partido['local_nombre']) ?>">
                            <?php foreach ($locales as $jugador): ?>
                                <option value="<?= (int) $jugador['id'] ?>">
                                    <?= $jugador['dorsal'] === null ? '' : (int) $jugador['dorsal'] . ' · ' ?>
                                    <?= e(nombre_persona($jugador)) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="<?= e((string) $partido['visita_nombre']) ?>">
                            <?php foreach ($visitas as $jugador): ?>
                                <option value="<?= (int) $jugador['id'] ?>">
                                    <?= $jugador['dorsal'] === null ? '' : (int) $jugador['dorsal'] . ' · ' ?>
                                    <?= e(nombre_persona($jugador)) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <button class="btn btn-success" type="submit">Añadir</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <h2 class="h5">Goles</h2>
        <?php if ($goles === []): ?>
            <p class="text-secondary">Sin goles.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($goles as $evento): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span>
                            <?= e($nombreEvento($evento)) ?>
                            <span class="d-block small text-secondary"><?= e((string) $evento['nombre_corto']) ?></span>
                        </span>
                        <form method="post" onsubmit="return confirm('¿Quitar este gol?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="partido_id" value="<?= (int) $partido['id'] ?>">
                            <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <h2 class="h5">Tarjetas</h2>
        <?php if ($tarjetas === []): ?>
            <p class="text-secondary">Sin tarjetas.</p>
        <?php else: ?>
            <ul class="list-group">
                <?php foreach ($tarjetas as $evento): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <span>
                            <?= e($nombreEvento($evento)) ?>
                            <span class="d-block small text-secondary"><?= $evento['tipo'] === 'roja' ? 'Roja' : 'Amarilla' ?> · <?= e((string) $evento['nombre_corto']) ?></span>
                        </span>
                        <form method="post" onsubmit="return confirm('¿Quitar esta tarjeta?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="partido_id" value="<?= (int) $partido['id'] ?>">
                            <input type="hidden" name="evento_id" value="<?= (int) $evento['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
