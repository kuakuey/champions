<?php
/**
 * Alta de partidos y orden arrastrable del calendario.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

function responder_partidos(bool $ok, string $mensaje): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $ajax = (string) ($_POST['ajax'] ?? '') === '1';
    try {
        if ($accion === 'crear') {
            crear_partido(
                entero_post('local_id', 1, 1000000) ?? 0,
                entero_post('visitante_id', 1, 1000000) ?? 0,
                (string) ($_POST['tipo'] ?? '')
            );
            poner_aviso('success', 'Partido creado.');
            redirigir(url_public('partidos.php'));
        }
        if ($accion === 'quitar') {
            quitar_partido(entero_post('partido_id', 1, 1000000) ?? 0);
            poner_aviso('success', 'Partido quitado.');
            redirigir(url_public('partidos.php'));
        }
        if ($accion === 'ordenar') {
            $ids = array_values(array_filter(
                array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))),
                static fn (int $id): bool => $id > 0
            ));
            if ($ids === []) {
                throw new RuntimeException('No hay partidos para ordenar.');
            }
            guardar_orden_partidos($ids);
            if ($ajax) {
                responder_partidos(true, 'Orden guardado.');
            }
            redirigir(url_public('partidos.php'));
        }
        throw new RuntimeException('Acción no reconocida.');
    } catch (PDOException $e) {
        error_log('Partidos: ' . $e->getMessage());
        if ($ajax) {
            responder_partidos(false, 'No se pudo guardar.');
        }
        poner_aviso('danger', 'No se pudo guardar.');
    } catch (RuntimeException $e) {
        if ($ajax) {
            responder_partidos(false, $e->getMessage());
        }
        poner_aviso('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Partidos: ' . $e->getMessage());
        if ($ajax) {
            responder_partidos(false, 'No se pudo guardar.');
        }
        poner_aviso('danger', 'No se pudo guardar.');
    }
    redirigir(url_public('partidos.php'));
}

$partidos = listar_partidos();
$equipos = listar_equipos(false);
$titulo = 'Partidos';
$pagina = 'partidos';
$contenedor = 'fluid';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Partidos</h1>
    <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modal-partido" <?= $equipos === [] ? 'disabled' : '' ?>>Agregar partido</button>
</div>
<?php if ($equipos === []): ?>
    <p class="text-secondary">Primero crea equipos para armar el calendario.</p>
<?php elseif ($partidos === []): ?>
    <p class="text-secondary">Todavía no hay partidos. Arrástralos después para definir el orden.</p>
<?php else: ?>
    <div class="card">
        <div class="list-group list-group-flush" id="lista-partidos">
            <?php foreach ($partidos as $partido): ?>
                <div class="list-group-item partido-arrastrable d-flex justify-content-between align-items-center gap-2" draggable="true" data-id="<?= (int) $partido['id'] ?>">
                    <button class="agarre" type="button" draggable="false" aria-label="Mover partido">
                        <i class="bi bi-grip-vertical" aria-hidden="true"></i>
                    </button>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate"><?= e((string) $partido['local_nombre']) ?> vs <?= e((string) $partido['visita_nombre']) ?></div>
                        <div class="d-flex flex-wrap align-items-center gap-1 mt-1">
                            <span class="badge <?= $partido['tipo'] === 'eliminatoria' ? 'text-bg-dark' : 'text-bg-success' ?>">
                                <?= $partido['tipo'] === 'eliminatoria' ? 'Eliminatoria' : 'Clasificatoria' ?>
                            </span>
                            <?php if ((string) $partido['estado'] === 'jugado'): ?>
                                <span class="badge text-bg-success">Jugado</span>
                                <span class="small fw-semibold"><?= e(marcador_texto($partido)) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Por jugar</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <a class="btn btn-sm btn-success" href="<?= e(url_public('partido.php?id=' . (int) $partido['id'])) ?>" aria-label="Abrir partido" title="Abrir" draggable="false"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                        <form method="post" onsubmit="return confirm('¿Quitar este partido?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="partido_id" value="<?= (int) $partido['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Quitar" title="Quitar"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="modal-partido" tabindex="-1" aria-labelledby="modal-partido-titulo" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-partido-titulo">Nuevo partido</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="local_id">Local</label>
                    <select class="form-select" id="local_id" name="local_id" required>
                        <option value="">Elige un equipo</option>
                        <?php foreach ($equipos as $club): ?>
                            <option value="<?= (int) $club['id'] ?>"><?= e((string) $club['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="visitante_id">Visitante</label>
                    <select class="form-select" id="visitante_id" name="visitante_id" required>
                        <option value="">Elige un equipo</option>
                        <?php foreach ($equipos as $club): ?>
                            <option value="<?= (int) $club['id'] ?>"><?= e((string) $club['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label" for="tipo">Tipo de partido</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="clasificatoria">Clasificatoria</option>
                        <option value="eliminatoria">Eliminatoria</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" type="submit">Crear partido</button>
            </div>
        </form>
    </div>
</div>
<script>
    (function () {
        var lista = document.getElementById('lista-partidos');
        var token = document.querySelector('#modal-partido [name="csrf"]');
        if (!lista || !token) {
            return;
        }
        document.querySelectorAll('.partido-arrastrable').forEach(function (fila) {
            fila.addEventListener('dragstart', function (evento) {
                if (evento.target.closest('a, button, form')) {
                    evento.preventDefault();
                    return;
                }
                evento.dataTransfer.setData('text/plain', fila.getAttribute('data-id') || '');
                evento.dataTransfer.effectAllowed = 'move';
                fila.classList.add('arrastrando');
            });
            fila.addEventListener('dragend', function () {
                fila.classList.remove('arrastrando');
            });
        });
        lista.addEventListener('dragover', function (evento) {
            evento.preventDefault();
        });
        lista.addEventListener('drop', function (evento) {
            evento.preventDefault();
            var id = evento.dataTransfer.getData('text/plain');
            var fila = lista.querySelector('.partido-arrastrable[data-id="' + id + '"]');
            if (!fila) {
                return;
            }
            var referencia = evento.target.closest('.partido-arrastrable');
            if (!referencia || referencia === fila) {
                return;
            }
            var rect = referencia.getBoundingClientRect();
            if (evento.clientY > rect.top + rect.height / 2) {
                lista.insertBefore(fila, referencia.nextElementSibling);
            } else {
                lista.insertBefore(fila, referencia);
            }
            var ids = Array.prototype.map.call(lista.querySelectorAll('.partido-arrastrable'), function (item) {
                return item.getAttribute('data-id') || '';
            }).filter(Boolean).join(',');
            var cuerpo = new URLSearchParams();
            cuerpo.set('csrf', token.value);
            cuerpo.set('accion', 'ordenar');
            cuerpo.set('ajax', '1');
            cuerpo.set('ids', ids);
            fetch('partidos.php', { method: 'POST', body: cuerpo, headers: { 'Accept': 'application/json' } })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (dato) {
                    if (!dato.ok) {
                        window.alert(dato.mensaje || 'No se pudo guardar el orden.');
                        window.location.reload();
                    }
                })
                .catch(function () {
                    window.alert('No se pudo guardar el orden.');
                    window.location.reload();
                });
        });

        function guardarOrden() {
            var ids = Array.prototype.map.call(lista.querySelectorAll('.partido-arrastrable'), function (item) {
                return item.getAttribute('data-id') || '';
            }).filter(Boolean).join(',');
            var cuerpo = new URLSearchParams();
            cuerpo.set('csrf', token.value);
            cuerpo.set('accion', 'ordenar');
            cuerpo.set('ajax', '1');
            cuerpo.set('ids', ids);
            fetch('partidos.php', { method: 'POST', body: cuerpo, headers: { 'Accept': 'application/json' } })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (dato) {
                    if (!dato.ok) {
                        window.alert(dato.mensaje || 'No se pudo guardar el orden.');
                        window.location.reload();
                    }
                })
                .catch(function () {
                    window.alert('No se pudo guardar el orden.');
                    window.location.reload();
                });
        }

        var arrastre = null;
        lista.querySelectorAll('.agarre').forEach(function (agarre) {
            agarre.addEventListener('pointerdown', function (evento) {
                if (evento.button !== 0) {
                    return;
                }
                var fila = agarre.closest('.partido-arrastrable');
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
                    siguiente: fila.nextSibling,
                    pointerId: evento.pointerId
                };
                fila.classList.add('arrastrando');
                fila.style.pointerEvents = 'none';
            });
            agarre.addEventListener('pointermove', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                if (evento.clientY < 72) {
                    window.scrollBy(0, -16);
                } else if (evento.clientY > window.innerHeight - 120) {
                    window.scrollBy(0, 16);
                }
                var bajo = document.elementFromPoint(evento.clientX, evento.clientY);
                if (!bajo) {
                    return;
                }
                var referencia = bajo.closest('.partido-arrastrable');
                if (!referencia || referencia === arrastre.fila || referencia.parentElement !== lista) {
                    return;
                }
                var rect = referencia.getBoundingClientRect();
                if (evento.clientY > rect.top + rect.height / 2) {
                    lista.insertBefore(arrastre.fila, referencia.nextElementSibling);
                } else {
                    lista.insertBefore(arrastre.fila, referencia);
                }
            });
            agarre.addEventListener('pointerup', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                var fila = arrastre.fila;
                var siguiente = arrastre.siguiente;
                fila.style.pointerEvents = '';
                fila.classList.remove('arrastrando');
                arrastre = null;
                if (fila.nextSibling !== siguiente) {
                    guardarOrden();
                }
            });
            agarre.addEventListener('pointercancel', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                var fila = arrastre.fila;
                fila.style.pointerEvents = '';
                fila.classList.remove('arrastrando');
                lista.insertBefore(fila, arrastre.siguiente);
                arrastre = null;
            });
        });
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
