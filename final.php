<?php
/**
 * Cuadro de la final: 8 equipos, cuartos, semifinal y final.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    try {
        $accion = (string) ($_POST['accion'] ?? '');
        if ($accion !== 'guardar') {
            throw new RuntimeException('Acción no reconocida.');
        }
        $recibidos = $_POST['puesto'] ?? [];
        if (!is_array($recibidos)) {
            throw new RuntimeException('Elige los 8 equipos de la final.');
        }
        $puestos = [];
        for ($puesto = 1; $puesto <= 8; $puesto++) {
            $puestos[$puesto] = (int) ($recibidos[$puesto] ?? 0);
        }
        guardar_puestos_cuadro($puestos);
        poner_aviso('success', 'Cuadro de la final guardado.');
    } catch (PDOException $e) {
        error_log('Final: ' . $e->getMessage());
        poner_aviso('danger', 'No se pudo guardar el cuadro.');
    } catch (RuntimeException $e) {
        poner_aviso('danger', $e->getMessage());
    }
    redirigir(url_public('final.php'));
}

$equipos = listar_equipos(false);
$porId = [];
foreach ($equipos as $equipo) {
    $porId[(int) $equipo['id']] = $equipo;
}
sincronizar_cuadro();
$puestos = puestos_cuadro();
$partidos = partidos_cuadro();

$titulo = 'Final';
$pagina = 'final';
$contenedor = 'fluid';
$descripcion = 'Cuadro de la final de 8 equipos.';

require __DIR__ . '/includes/header.php';

$rondas = [
    'Cuartos de final' => [1, 2, 3, 4],
    'Semifinal' => [5, 6],
    'Final' => [7],
];
$nombreLlave = [
    1 => 'Cuartos 1',
    2 => 'Cuartos 2',
    3 => 'Cuartos 3',
    4 => 'Cuartos 4',
    5 => 'Semifinal 1',
    6 => 'Semifinal 2',
    7 => 'Final',
];
$espera = [
    5 => ['Ganador cuartos 1', 'Ganador cuartos 2'],
    6 => ['Ganador cuartos 3', 'Ganador cuartos 4'],
    7 => ['Ganador semifinal 1', 'Ganador semifinal 2'],
];

$lado = static function (?array $partido, string $cual, string $esperaTexto) use ($porId): array {
    if ($partido === null) {
        return ['nombre' => $esperaTexto, 'escudo' => null, 'goles' => null];
    }
    $id = (int) $partido[$cual . '_id'];
    $equipo = $porId[$id] ?? null;

    return [
        'nombre' => $equipo === null ? $esperaTexto : (string) $equipo['nombre'],
        'escudo' => $equipo['escudo'] ?? null,
        'goles' => (string) ($partido['estado'] ?? '') === 'jugado' ? (int) $partido['goles_' . ($cual === 'local' ? 'local' : 'visitante')] : null,
    ];
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Final</h1>
    <p class="text-secondary mb-0">8 equipos</p>
</div>

<div class="card mb-4">
    <div class="card-header fw-semibold">Los 8</div>
    <div class="card-body">
        <?php if (count($equipos) < 8): ?>
            <p class="text-secondary mb-0">Hacen falta 8 equipos para armar la final.</p>
        <?php else: ?>
            <form method="post">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="guardar">
                <div class="row g-3">
                    <?php for ($cruce = 1; $cruce <= 4; $cruce++): ?>
                        <?php $izquierda = ($cruce * 2) - 1; ?>
                        <?php $derecha = $cruce * 2; ?>
                        <div class="col-12 col-md-6">
                            <p class="fw-semibold mb-2">Cuartos <?= $cruce ?></p>
                            <div class="mb-2">
                                <label class="form-label" for="puesto-<?= $izquierda ?>">Local</label>
                                <select class="form-select puesto-final" id="puesto-<?= $izquierda ?>" name="puesto[<?= $izquierda ?>]" required>
                                    <option value="">Elige un equipo</option>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <option value="<?= (int) $equipo['id'] ?>" <?= (int) ($puestos[$izquierda] ?? 0) === (int) $equipo['id'] ? 'selected' : '' ?>><?= e((string) $equipo['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="puesto-<?= $derecha ?>">Visita</label>
                                <select class="form-select puesto-final" id="puesto-<?= $derecha ?>" name="puesto[<?= $derecha ?>]" required>
                                    <option value="">Elige un equipo</option>
                                    <?php foreach ($equipos as $equipo): ?>
                                        <option value="<?= (int) $equipo['id'] ?>" <?= (int) ($puestos[$derecha] ?? 0) === (int) $equipo['id'] ? 'selected' : '' ?>><?= e((string) $equipo['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <button class="btn btn-success mt-3" type="submit">Guardar cuadro</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php foreach ($rondas as $tituloRonda => $llaves): ?>
    <section class="mb-4">
        <h2 class="h5 mb-3"><?= e($tituloRonda) ?></h2>
        <div class="row g-3">
            <?php foreach ($llaves as $llave): ?>
                <?php
                $partido = $partidos[$llave] ?? null;
                $textos = $espera[$llave] ?? ['Por definir', 'Por definir'];
                $local = $lado(is_array($partido) ? $partido : null, 'local', $textos[0]);
                $visita = $lado(is_array($partido) ? $partido : null, 'visitante', $textos[1]);
                $ganadorId = is_array($partido) ? ganador_partido($partido) : null;
                $ganador = $ganadorId !== null && isset($porId[$ganadorId]) ? (string) $porId[$ganadorId]['nombre'] : '';
                ?>
                <div class="col-12 col-md-6">
                    <div class="card h-100<?= is_array($partido) && (string) $partido['estado'] === 'jugado' ? ' partido-jugado' : '' ?>">
                        <div class="card-header fw-semibold"><?= e($nombreLlave[$llave]) ?></div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <span class="d-inline-flex align-items-center gap-2 min-w-0">
                                    <?= html_escudo($local['escudo'], $local['nombre'], 28) ?>
                                    <span class="text-truncate"><?= e($local['nombre']) ?></span>
                                </span>
                                <span class="fw-bold"><?= $local['goles'] === null ? '' : (int) $local['goles'] ?></span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2">
                                <span class="d-inline-flex align-items-center gap-2 min-w-0">
                                    <?= html_escudo($visita['escudo'], $visita['nombre'], 28) ?>
                                    <span class="text-truncate"><?= e($visita['nombre']) ?></span>
                                </span>
                                <span class="fw-bold"><?= $visita['goles'] === null ? '' : (int) $visita['goles'] ?></span>
                            </div>
                            <?php if ($ganador !== ''): ?>
                                <p class="small text-success mb-0 mt-3">Pasa <?= e($ganador) ?></p>
                            <?php elseif (is_array($partido) && (string) $partido['estado'] === 'jugado'): ?>
                                <p class="small text-secondary mb-0 mt-3">Empate. Todavía no hay ganador.</p>
                            <?php endif; ?>
                        </div>
                        <?php if (is_array($partido)): ?>
                            <div class="card-footer bg-transparent">
                                <a class="btn btn-success w-100" href="<?= e(url_public('partido.php?id=' . (int) $partido['id'])) ?>">Abrir partido</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
<script>
    (function () {
        var listas = document.querySelectorAll('.puesto-final');
        function refrescar() {
            var usados = {};
            listas.forEach(function (lista) {
                if (lista.value) {
                    usados[lista.value] = true;
                }
            });
            listas.forEach(function (lista) {
                Array.prototype.forEach.call(lista.options, function (opcion) {
                    if (!opcion.value) {
                        return;
                    }
                    opcion.disabled = usados[opcion.value] === true && opcion.value !== lista.value;
                });
            });
        }
        listas.forEach(function (lista) {
            lista.addEventListener('change', refrescar);
        });
        refrescar();
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
