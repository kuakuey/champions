<?php
/**
 * Inicio a pantalla completa: partidos a la izquierda,
 * tabla arriba y goleadores abajo a la derecha.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

$partidos = listar_partidos(['orden' => 'asc']);
$tabla = tabla_posiciones();
$goleadores = goleadores();

$titulo = LIGA_NOMBRE;
$pagina = 'inicio';
$cuerpo = 'pantalla-fija';
$contenedor = 'fluid';
$descripcion = 'Orden de partidos, tabla de puntajes y goleadores de Champions.';

require __DIR__ . '/includes/header.php';
?>
<div class="rejilla-inicio">
    <section class="panel" aria-labelledby="titulo-partidos">
        <div class="panel-titulo">
            <h1 id="titulo-partidos">Orden de partidos</h1>
            <div class="form-check form-switch mb-0 flex-shrink-0 interruptor-refresh">
                <input class="form-check-input" type="checkbox" role="switch" id="refresh">
                <label class="form-check-label" for="refresh">refresh</label>
            </div>
        </div>
        <div class="panel-cuerpo">
            <?php if ($partidos === []): ?>
                <p class="text-secondary p-3 mb-0">Todavía no hay partidos en el calendario.</p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($partidos as $partido): ?>
                        <a class="list-group-item list-group-item-action<?= (string) $partido['estado'] === 'jugado' ? ' partido-jugado' : '' ?>" href="<?= e(url_public('partido.php?id=' . (int) $partido['id'])) ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <span class="badge <?= $partido['tipo'] === 'eliminatoria' ? 'text-bg-dark' : 'text-bg-success' ?>">
                                    <?= $partido['tipo'] === 'eliminatoria' ? 'Eliminatoria' : 'Clasificatoria' ?>
                                </span>
                                <?= html_estado((string) $partido['estado']) ?>
                            </div>
                            <div class="fila-partido d-flex align-items-center justify-content-between gap-2">
                                <span class="lado d-inline-flex align-items-center gap-2">
                                    <?= html_escudo($partido['local_escudo'], (string) $partido['local_nombre'], 28) ?>
                                    <span><?= e($partido['local_nombre']) ?></span>
                                </span>
                                <span class="fw-bold flex-shrink-0"><?= e(marcador_texto($partido)) ?></span>
                                <span class="lado d-inline-flex align-items-center justify-content-end gap-2 text-end">
                                    <span><?= e($partido['visita_nombre']) ?></span>
                                    <?= html_escudo($partido['visita_escudo'], (string) $partido['visita_nombre'], 28) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="columna-derecha">
        <section class="panel" data-panel="tabla" aria-labelledby="titulo-tabla">
            <div class="panel-titulo">
                <h2 id="titulo-tabla">Tabla de puntajes</h2>
                <button class="panel-contraer" type="button" aria-expanded="true" aria-controls="cuerpo-tabla" data-nombre="tabla de puntajes" aria-label="Contraer tabla de puntajes">
                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </button>
            </div>
            <div class="panel-cuerpo" id="cuerpo-tabla">
                <?php
                $bloquesTabla = [];
                foreach ($tabla as $fila) {
                    if ((string) ($fila['grupo'] ?? '') === GRUPO_SIN_ASIGNAR) {
                        continue;
                    }
                    $bloquesTabla[(string) ($fila['grupo'] ?? '')][] = $fila;
                }
                ?>
                <?php if ($bloquesTabla === []): ?>
                    <p class="text-secondary p-3 mb-0">Todavía no hay equipos en la tabla.</p>
                <?php endif; ?>
                <?php foreach ($bloquesTabla as $nombreGrupo => $filasGrupo): ?>
                    <div class="grupo-tabla">
                        <?php if ($nombreGrupo !== ''): ?>
                            <h3 class="h6 text-secondary px-3 pt-3 mb-2"><?= e($nombreGrupo) ?></h3>
                        <?php endif; ?>
                        <table class="table table-striped table-hover tabla-liga tabla-puntajes align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Equipo</th>
                                    <th title="Partidos jugados">PJ</th>
                                    <th title="Partidos ganados">PG</th>
                                    <th title="Partidos empatados">PE</th>
                                    <th title="Partidos perdidos">PP</th>
                                    <th title="Goles a favor">GF</th>
                                    <th title="Goles en contra">GC</th>
                                    <th title="Diferencia de gol">DG</th>
                                    <th title="Puntos">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filasGrupo as $fila): ?>
                                    <tr>
                                        <td class="<?= $fila['posicion'] <= 3 ? 'posicion-top' : '' ?>"><?= (int) $fila['posicion'] ?></td>
                                        <td>
                                            <span class="d-inline-flex align-items-center gap-2">
                                                <?= html_escudo($fila['escudo'], (string) $fila['nombre'], 28) ?>
                                                <span><?= e($fila['nombre_corto']) ?></span>
                                            </span>
                                        </td>
                                        <td><?= (int) $fila['pj'] ?></td>
                                        <td><?= (int) $fila['pg'] ?></td>
                                        <td><?= (int) $fila['pe'] ?></td>
                                        <td><?= (int) $fila['pp'] ?></td>
                                        <td><?= (int) $fila['gf'] ?></td>
                                        <td><?= (int) $fila['gc'] ?></td>
                                        <td><?= $fila['dg'] > 0 ? '+' . (int) $fila['dg'] : (int) $fila['dg'] ?></td>
                                        <td class="fw-bold"><?= (int) $fila['pts'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="panel" data-panel="goleadores" aria-labelledby="titulo-goleadores">
            <div class="panel-titulo">
                <h2 id="titulo-goleadores">Goleadores</h2>
                <button class="panel-contraer" type="button" aria-expanded="true" aria-controls="cuerpo-goleadores" data-nombre="goleadores" aria-label="Contraer goleadores">
                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </button>
            </div>
            <div class="panel-cuerpo" id="cuerpo-goleadores">
                <?php if ($goleadores === []): ?>
                    <p class="text-secondary p-3 mb-0">Todavía no hay goles cargados.</p>
                <?php else: ?>
                    <div class="tabla-movil">
                    <table class="table table-hover tabla-liga align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Jugador</th>
                                <th>Equipo</th>
                                <th>Goles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($goleadores as $indice => $jugador): ?>
                                <tr>
                                    <td class="<?= $indice < 3 ? 'posicion-top' : '' ?>"><?= $indice + 1 ?></td>
                                    <td><?= e(nombre_persona($jugador)) ?></td>
                                    <td><?= e($jugador['nombre_corto']) ?></td>
                                    <td class="fw-bold"><?= (int) $jugador['total'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<script>
    (function () {
        document.querySelectorAll('[data-panel]').forEach(function (panel) {
            var clave = 'panel-' + panel.getAttribute('data-panel');
            var boton = panel.querySelector('.panel-contraer');
            var icono = boton.querySelector('i');
            var nombre = boton.getAttribute('data-nombre') || '';

            function aplicar(contraido) {
                panel.classList.toggle('contraido', contraido);
                boton.setAttribute('aria-expanded', contraido ? 'false' : 'true');
                boton.setAttribute('aria-label', (contraido ? 'Mostrar ' : 'Contraer ') + nombre);
                icono.className = contraido ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
            }

            var guardado = false;
            try {
                guardado = localStorage.getItem(clave) === '1';
            } catch (error) {}
            aplicar(guardado);

            boton.addEventListener('click', function () {
                var contraido = !panel.classList.contains('contraido');
                aplicar(contraido);
                try {
                    localStorage.setItem(clave, contraido ? '1' : '0');
                } catch (error) {}
            });
        });
    })();
    (function () {
        var interruptor = document.getElementById('refresh');
        if (!interruptor) {
            return;
        }
        var espera = null;

        function programar() {
            if (espera !== null) {
                clearTimeout(espera);
                espera = null;
            }
            if (!interruptor.checked) {
                return;
            }
            espera = setTimeout(function () {
                window.location.reload();
            }, 30000);
        }

        var activo = false;
        try {
            activo = localStorage.getItem('refresh') === '1';
        } catch (error) {}
        interruptor.checked = activo;
        programar();
        interruptor.addEventListener('change', function () {
            try {
                localStorage.setItem('refresh', interruptor.checked ? '1' : '0');
            } catch (error) {}
            programar();
        });
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
