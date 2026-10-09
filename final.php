<?php
/**
 * Cuadro de la final: los dos primeros de cada grupo se arrastran a los cuartos.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

function responder_cuadro(bool $ok, string $mensaje): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $ajax = (string) ($_POST['ajax'] ?? '') === '1';
    try {
        $accion = (string) ($_POST['accion'] ?? '');
        if ($accion !== 'colocar') {
            throw new RuntimeException('Acción no reconocida.');
        }
        mover_equipo_cuadro(
            (int) ($_POST['equipo'] ?? 0),
            (int) ($_POST['puesto'] ?? 0)
        );
        if ($ajax) {
            responder_cuadro(true, 'Equipo colocado.');
        }
        poner_aviso('success', 'Equipo colocado.');
    } catch (PDOException $e) {
        error_log('Final: ' . $e->getMessage());
        if ($ajax) {
            responder_cuadro(false, 'No se pudo colocar el equipo.');
        }
        poner_aviso('danger', 'No se pudo colocar el equipo.');
    } catch (RuntimeException $e) {
        if ($ajax) {
            responder_cuadro(false, $e->getMessage());
        }
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
$clasificados = clasificados_por_grupo();
$fichas = [];
foreach ($clasificados as $filas) {
    foreach ($filas as $fila) {
        $fichas[(int) $fila['equipo_id']] = $fila;
    }
}
foreach ($puestos as $equipoId) {
    if ($equipoId === null || isset($fichas[$equipoId]) || !isset($porId[$equipoId])) {
        continue;
    }
    $equipo = $porId[$equipoId];
    $fichas[$equipoId] = [
        'equipo_id' => $equipoId,
        'nombre' => (string) $equipo['nombre'],
        'escudo' => $equipo['escudo'] ?? null,
        'grupo' => (string) ($equipo['grupo'] ?? ''),
        'posicion' => 0,
    ];
}
$ocupados = array_fill_keys(array_filter($puestos), true);

$titulo = 'Final';
$pagina = 'final';
$contenedor = 'fluid';
$descripcion = 'Los dos primeros de cada grupo juegan los cuartos de final.';

require __DIR__ . '/includes/header.php';

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

$ficha = static function (array $fila, bool $cerrado): void {
    $id = (int) $fila['equipo_id'];
    ?>
    <div
        class="list-group-item equipo-arrastrable d-flex align-items-center gap-2"
        draggable="<?= $cerrado ? 'false' : 'true' ?>"
        data-id="<?= $id ?>"
        data-grupo="<?= e((string) ($fila['grupo'] ?? '')) ?>"
    >
        <?php if (!$cerrado): ?>
            <button class="agarre" type="button" draggable="false" aria-label="Mover equipo">
                <i class="bi bi-grip-vertical" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
        <?php if ((int) ($fila['posicion'] ?? 0) > 0): ?>
            <span class="badge text-bg-success"><?= (int) $fila['posicion'] ?></span>
        <?php endif; ?>
        <?= html_escudo($fila['escudo'] ?? null, (string) $fila['nombre'], 28) ?>
        <span class="fw-semibold text-truncate"><?= e((string) $fila['nombre']) ?></span>
    </div>
    <?php
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Final</h1>
    <p class="text-secondary mb-0">Los 2 primeros de cada grupo</p>
</div>

<div class="sorteo-equipos mb-4">
    <section class="card">
        <div class="card-header fw-semibold">Clasificados</div>
        <div class="card-body d-flex flex-column gap-3">
            <?php if ($clasificados === []): ?>
                <p class="text-secondary mb-0">Todavía no hay grupos.</p>
            <?php endif; ?>
            <?php foreach ($clasificados as $nombreGrupo => $filas): ?>
                <div>
                    <h2 class="h6 text-secondary mb-2"><?= e($nombreGrupo) ?></h2>
                    <div class="list-group zona-grupo zona-cuadro zona-origen" data-puesto="0" data-grupo="<?= e($nombreGrupo) ?>">
                        <?php foreach ($filas as $fila): ?>
                            <?php if (isset($ocupados[(int) $fila['equipo_id']])) { continue; } ?>
                            <?php $ficha($fila, false); ?>
                        <?php endforeach; ?>
                        <div class="list-group-item text-secondary zona-vacia">Suelta aquí</div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2 class="h5 mb-3">Cuartos de final</h2>
        <div class="row g-3">
            <?php for ($cruce = 1; $cruce <= 4; $cruce++): ?>
                <?php
                $partido = $partidos[$cruce] ?? null;
                $cerrado = is_array($partido) && (string) $partido['estado'] === 'jugado';
                $ganadorId = is_array($partido) ? ganador_partido($partido) : null;
                $ganador = $ganadorId !== null && isset($porId[$ganadorId]) ? (string) $porId[$ganadorId]['nombre'] : '';
                $lados = [($cruce * 2) - 1 => 'Local', $cruce * 2 => 'Visita'];
                ?>
                <div class="col-12 col-lg-6">
                    <div class="card h-100<?= $cerrado ? ' partido-jugado' : '' ?>">
                        <div class="card-header fw-semibold"><?= e($nombreLlave[$cruce]) ?></div>
                        <div class="card-body d-flex flex-column gap-2">
                            <?php foreach ($lados as $puesto => $etiqueta): ?>
                                <?php $equipoId = $puestos[$puesto] ?? null; ?>
                                <div>
                                    <div class="small text-secondary mb-1"><?= e($etiqueta) ?></div>
                                    <div class="list-group zona-grupo zona-cuadro zona-puesto" data-puesto="<?= (int) $puesto ?>" <?= $cerrado ? 'data-cerrado="1"' : '' ?>>
                                        <?php if ($equipoId !== null && isset($fichas[$equipoId])): ?>
                                            <?php $ficha($fichas[$equipoId], $cerrado); ?>
                                        <?php endif; ?>
                                        <div class="list-group-item text-secondary zona-vacia">Suelta un equipo</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($ganador !== ''): ?>
                                <p class="small text-success mb-0">Pasa <?= e($ganador) ?></p>
                            <?php elseif ($cerrado): ?>
                                <p class="small text-secondary mb-0">Empate. Todavía no hay ganador.</p>
                            <?php endif; ?>
                        </div>
                        <?php if (is_array($partido)): ?>
                            <div class="card-footer bg-transparent">
                                <a class="btn btn-success w-100" href="<?= e(url_public('partido.php?id=' . (int) $partido['id'])) ?>">Abrir partido</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </section>
</div>

<?php foreach (['Semifinal' => [5, 6], 'Final' => [7]] as $tituloRonda => $llaves): ?>
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
        var token = document.querySelector('input[name="csrf"]');
        if (!token) {
            var campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = 'csrf';
            campo.value = <?= json_encode(token_csrf(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
            document.body.appendChild(campo);
            token = campo;
        }

        function pintarVacios() {
            document.querySelectorAll('.zona-cuadro').forEach(function (zona) {
                var vacio = zona.querySelector('.zona-vacia');
                if (!vacio) {
                    return;
                }
                vacio.classList.toggle('d-none', zona.querySelector('.equipo-arrastrable') !== null);
            });
        }

        function devolver(fila) {
            var grupo = fila.getAttribute('data-grupo') || '';
            var lista = document.querySelector('.zona-origen[data-grupo="' + CSS.escape(grupo) + '"]');
            if (!lista) {
                return;
            }
            var vacio = lista.querySelector('.zona-vacia');
            if (vacio) {
                lista.insertBefore(fila, vacio);
            } else {
                lista.appendChild(fila);
            }
        }

        function soltar(fila, zona) {
            if (!zona || zona.getAttribute('data-cerrado') === '1') {
                return false;
            }
            var destino = zona.getAttribute('data-puesto') || '0';
            if (destino === '0') {
                devolver(fila);
                return true;
            }
            var otro = zona.querySelector('.equipo-arrastrable');
            if (otro && otro !== fila) {
                devolver(otro);
            }
            var vacio = zona.querySelector('.zona-vacia');
            if (vacio) {
                zona.insertBefore(fila, vacio);
            } else {
                zona.appendChild(fila);
            }
            return true;
        }

        function guardar(fila, zona) {
            var cuerpo = new URLSearchParams();
            cuerpo.set('csrf', token.value);
            cuerpo.set('accion', 'colocar');
            cuerpo.set('ajax', '1');
            cuerpo.set('equipo', fila.getAttribute('data-id') || '');
            cuerpo.set('puesto', zona.classList.contains('zona-origen') ? '0' : (zona.getAttribute('data-puesto') || '0'));
            fetch('final.php', { method: 'POST', body: cuerpo, headers: { 'Accept': 'application/json' } })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (dato) {
                    if (!dato.ok) {
                        window.alert(dato.mensaje || 'No se pudo colocar.');
                    }
                    window.location.reload();
                })
                .catch(function () {
                    window.alert('No se pudo colocar.');
                    window.location.reload();
                });
        }

        document.querySelectorAll('.equipo-arrastrable').forEach(function (fila) {
            fila.addEventListener('dragstart', function (evento) {
                if (fila.getAttribute('draggable') !== 'true' || evento.target.closest('button')) {
                    evento.preventDefault();
                    return;
                }
                evento.dataTransfer.setData('text/plain', fila.getAttribute('data-id') || '');
                evento.dataTransfer.effectAllowed = 'move';
                fila.classList.add('arrastrando');
            });
            fila.addEventListener('dragend', function () {
                fila.classList.remove('arrastrando');
                document.querySelectorAll('.zona-cuadro.soltando').forEach(function (zona) {
                    zona.classList.remove('soltando');
                });
            });
        });

        document.querySelectorAll('.zona-cuadro').forEach(function (zona) {
            zona.addEventListener('dragover', function (evento) {
                if (zona.getAttribute('data-cerrado') === '1') {
                    return;
                }
                evento.preventDefault();
                zona.classList.add('soltando');
            });
            zona.addEventListener('dragleave', function (evento) {
                if (!zona.contains(evento.relatedTarget)) {
                    zona.classList.remove('soltando');
                }
            });
            zona.addEventListener('drop', function (evento) {
                evento.preventDefault();
                zona.classList.remove('soltando');
                var id = evento.dataTransfer.getData('text/plain');
                var fila = document.querySelector('.equipo-arrastrable[data-id="' + id + '"]');
                if (!fila) {
                    return;
                }
                var origen = fila.parentElement;
                var puestoOrigen = origen && origen.classList.contains('zona-origen') ? '0' : (origen ? origen.getAttribute('data-puesto') || '0' : '0');
                var puestoDestino = zona.classList.contains('zona-origen') ? '0' : (zona.getAttribute('data-puesto') || '0');
                if (puestoOrigen === '0' && puestoDestino === '0') {
                    devolver(fila);
                    pintarVacios();
                    return;
                }
                if (origen === zona) {
                    return;
                }
                if (!soltar(fila, zona)) {
                    return;
                }
                pintarVacios();
                guardar(fila, zona);
            });
        });

        var arrastre = null;
        document.querySelectorAll('.equipo-arrastrable .agarre').forEach(function (agarre) {
            agarre.addEventListener('pointerdown', function (evento) {
                if (evento.button !== 0) {
                    return;
                }
                var fila = agarre.closest('.equipo-arrastrable');
                if (!fila) {
                    return;
                }
                evento.preventDefault();
                if (evento.isTrusted) {
                    try {
                        agarre.setPointerCapture(evento.pointerId);
                    } catch (error) {}
                }
                arrastre = {
                    fila: fila,
                    origen: fila.parentElement,
                    siguiente: fila.nextSibling,
                    pointerId: evento.pointerId,
                    movio: false
                };
                fila.classList.add('arrastrando');
                fila.style.pointerEvents = 'none';
            });
            agarre.addEventListener('pointermove', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                arrastre.movio = true;
                if (evento.clientY < 72) {
                    window.scrollBy(0, -16);
                } else if (evento.clientY > window.innerHeight - 120) {
                    window.scrollBy(0, 16);
                }
                var bajo = document.elementFromPoint(evento.clientX, evento.clientY);
                document.querySelectorAll('.zona-cuadro.soltando').forEach(function (zona) {
                    zona.classList.remove('soltando');
                });
                if (!bajo) {
                    return;
                }
                var zona = bajo.closest('.zona-cuadro');
                if (!zona || zona.getAttribute('data-cerrado') === '1') {
                    return;
                }
                zona.classList.add('soltando');
            });
            agarre.addEventListener('pointerup', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                var fila = arrastre.fila;
                var origen = arrastre.origen;
                var siguiente = arrastre.siguiente;
                fila.style.pointerEvents = '';
                fila.classList.remove('arrastrando');
                var bajo = document.elementFromPoint(evento.clientX, evento.clientY);
                var zona = bajo ? bajo.closest('.zona-cuadro') : null;
                document.querySelectorAll('.zona-cuadro.soltando').forEach(function (item) {
                    item.classList.remove('soltando');
                });
                arrastre = null;
                var puestoOrigen = origen && origen.classList.contains('zona-origen') ? '0' : (origen ? origen.getAttribute('data-puesto') || '0' : '0');
                var puestoDestino = zona && zona.classList.contains('zona-origen') ? '0' : (zona ? zona.getAttribute('data-puesto') || '0' : '');
                if (!zona || zona === origen || (puestoOrigen === '0' && puestoDestino === '0')) {
                    if (origen) {
                        origen.insertBefore(fila, siguiente);
                    }
                    pintarVacios();
                    return;
                }
                if (!soltar(fila, zona)) {
                    if (origen) {
                        origen.insertBefore(fila, siguiente);
                    }
                    pintarVacios();
                    return;
                }
                pintarVacios();
                guardar(fila, zona);
            });
            agarre.addEventListener('pointercancel', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                var fila = arrastre.fila;
                fila.style.pointerEvents = '';
                fila.classList.remove('arrastrando');
                if (arrastre.origen) {
                    arrastre.origen.insertBefore(fila, arrastre.siguiente);
                }
                arrastre = null;
                pintarVacios();
            });
        });

        pintarVacios();
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
