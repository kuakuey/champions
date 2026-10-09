<?php
/**
 * Equipos y, dentro de cada uno, sus jugadores.
 */

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

function responder_movimiento(bool $ok, string $mensaje): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function fila_equipo(array $club): void
{
    $grupo = (string) ($club['grupo'] ?? '');
    ?>
    <div
        class="list-group-item equipo-arrastrable d-flex justify-content-between align-items-center gap-3"
        draggable="true"
        data-id="<?= (int) $club['id'] ?>"
        data-grupo="<?= e($grupo) ?>"
    >
        <button class="agarre" type="button" draggable="false" aria-label="Mover equipo">
            <i class="bi bi-grip-vertical" aria-hidden="true"></i>
        </button>
        <div class="min-w-0 flex-grow-1 overflow-hidden">
            <div class="fw-semibold text-truncate"><?= e((string) $club['nombre']) ?></div>
            <div class="small text-secondary"><?= e((string) $club['nombre_corto']) ?></div>
        </div>
        <div class="d-flex gap-2 flex-shrink-0">
            <button
                class="btn btn-sm btn-outline-success"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modal-editar-equipo"
                data-id="<?= (int) $club['id'] ?>"
                data-nombre="<?= e((string) $club['nombre']) ?>"
                data-sigla="<?= e((string) $club['nombre_corto']) ?>"
                data-grupo="<?= e($grupo) ?>"
                aria-label="Editar"
                title="Editar"
            ><i class="bi bi-pencil" aria-hidden="true"></i></button>
            <a
                class="btn btn-sm btn-success"
                href="<?= e(url_public('equipos.php?id=' . (int) $club['id'])) ?>"
                aria-label="Jugadores"
                title="Jugadores"
                draggable="false"
            ><i class="bi bi-people" aria-hidden="true"></i></a>
        </div>
    </div>
    <?php
}

$equipoId = entero_get('id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $volver = entero_post('equipo_id', 1, 1000000) ?? $equipoId;
    $ajax = (string) ($_POST['ajax'] ?? '') === '1';
    if ($accion === 'guardar-equipo' || $accion === 'mover-grupo') {
        $volver = 0;
    }
    try {
        if ($accion === 'vaciar') {
            vaciar_campeonato();
            poner_aviso('success', 'Se vaciaron equipos, personas, partidos, goles y tarjetas.');
            redirigir(url_public('equipos.php'));
        }
        if ($accion === 'crear') {
            $nuevo = crear_equipo(
                (string) ($_POST['nombre'] ?? ''),
                (string) ($_POST['nombre_corto'] ?? ''),
                (string) ($_POST['grupo'] ?? '')
            );
            poner_aviso('success', 'Equipo creado. Ahora agrega sus jugadores.');
            redirigir(url_public('equipos.php?id=' . $nuevo));
        }
        if ($accion === 'guardar-equipo') {
            actualizar_equipo(
                entero_post('equipo_id', 1, 1000000) ?? 0,
                (string) ($_POST['nombre'] ?? ''),
                (string) ($_POST['nombre_corto'] ?? ''),
                (string) ($_POST['grupo'] ?? '')
            );
            poner_aviso('success', 'Equipo actualizado.');
            redirigir(url_public('equipos.php'));
        }
        if ($accion === 'mover-grupo') {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))), static fn (int $id): bool => $id > 0));
            if ($ids === []) {
                throw new RuntimeException('No encontramos ese equipo.');
            }
            guardar_orden_grupo((string) ($_POST['grupo'] ?? ''), $ids);
            $grupoOrigen = (string) ($_POST['grupo_origen'] ?? '');
            $idsOrigen = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['ids_origen'] ?? ''))), static fn (int $id): bool => $id > 0));
            if ($idsOrigen !== [] && $grupoOrigen !== '' && $grupoOrigen !== (string) ($_POST['grupo'] ?? '')) {
                guardar_orden_grupo($grupoOrigen, $idsOrigen);
            }
            if ($ajax) {
                responder_movimiento(true, 'Equipo movido.');
            }
            poner_aviso('success', 'Equipo movido.');
            redirigir(url_public('equipos.php'));
        }
        if ($accion === 'jugador' || $accion === 'editar' || $accion === 'quitar') {
            if ($volver < 1) {
                throw new RuntimeException('Elige un equipo.');
            }
            $jugadorId = entero_post('jugador_id', 1, 1000000) ?? 0;
            if ($accion === 'quitar') {
                quitar_jugador($jugadorId, $volver);
                poner_aviso('success', 'Jugador quitado.');
                redirigir(url_public('equipos.php?id=' . $volver));
            }
            $dorsal = null;
            $dorsalTexto = trim((string) ($_POST['dorsal'] ?? ''));
            if ($dorsalTexto !== '') {
                $dorsal = entero_post('dorsal', 1, 99);
                if ($dorsal === null) {
                    throw new RuntimeException('El dorsal debe estar entre 1 y 99.');
                }
            }
            if ($accion === 'editar') {
                actualizar_jugador(
                    $jugadorId,
                    $volver,
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['apellido'] ?? ''),
                    $dorsal
                );
                poner_aviso('success', 'Jugador actualizado.');
            } else {
                crear_jugador(
                    $volver,
                    (string) ($_POST['nombre'] ?? ''),
                    (string) ($_POST['apellido'] ?? ''),
                    $dorsal
                );
                poner_aviso('success', 'Jugador agregado.');
            }
            redirigir(url_public('equipos.php?id=' . $volver));
        }
        throw new RuntimeException('Acción no reconocida.');
    } catch (PDOException $e) {
        error_log('Equipos: ' . $e->getMessage());
        if ($ajax) {
            responder_movimiento(false, 'No se pudo guardar.');
        }
        poner_aviso('danger', 'No se pudo guardar.');
    } catch (RuntimeException $e) {
        if ($ajax) {
            responder_movimiento(false, $e->getMessage());
        }
        poner_aviso('danger', $e->getMessage());
    } catch (Throwable $e) {
        error_log('Equipos: ' . $e->getMessage());
        if ($ajax) {
            responder_movimiento(false, 'No se pudo guardar.');
        }
        poner_aviso('danger', 'No se pudo guardar.');
    }
    $destino = $volver > 0 ? 'equipos.php?id=' . $volver : 'equipos.php';
    redirigir(url_public($destino));
}

$equipo = $equipoId > 0 ? obtener_equipo($equipoId) : null;
if ($equipoId > 0 && $equipo === null) {
    abortar(404, 'No encontramos ese equipo.');
}

$titulo = $equipo === null ? 'Equipos' : (string) $equipo['nombre'];
$pagina = 'equipos';
$contenedor = 'fluid';
require __DIR__ . '/includes/header.php';

if ($equipo !== null) {
    $jugadores = listar_jugadores((int) $equipo['id'], false);
    ?>
    <p class="mb-2"><a href="<?= e(url_public('equipos.php')) ?>">Volver a equipos</a></p>
    <h1 class="h3 mb-1"><?= e((string) $equipo['nombre']) ?></h1>
    <p class="text-secondary mb-4"><?= e((string) $equipo['nombre_corto']) ?><?php if (!empty($equipo['grupo'])): ?> · <?= e((string) $equipo['grupo']) ?><?php endif; ?></p>
    <div class="row g-4">
        <div class="col-lg-4">
            <form method="post" class="card">
                <div class="card-body">
                    <h2 class="h5">Nuevo jugador</h2>
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="jugador">
                    <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input class="form-control" id="nombre" name="nombre" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="apellido">Apellido</label>
                        <input class="form-control" id="apellido" name="apellido" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dorsal">Dorsal</label>
                        <input class="form-control" id="dorsal" name="dorsal" inputmode="numeric" maxlength="2">
                    </div>
                    <button class="btn btn-success" type="submit">Agregar jugador</button>
                </div>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive card">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jugador</th>
                            <th>Dorsal</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($jugadores === []): ?>
                            <tr><td colspan="3" class="text-secondary">Este equipo todavía no tiene jugadores.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($jugadores as $jugador): ?>
                            <?php $fila = (int) $jugador['id']; ?>
                            <tr>
                                <td class="fw-semibold"><?= e((string) $jugador['nombre'] . ' ' . (string) $jugador['apellido']) ?></td>
                                <td><?= $jugador['dorsal'] === null ? '—' : (int) $jugador['dorsal'] ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <button
                                            class="btn btn-sm btn-outline-success"
                                            type="button"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modal-editar"
                                            data-id="<?= $fila ?>"
                                            data-nombre="<?= e((string) $jugador['nombre']) ?>"
                                            data-apellido="<?= e((string) $jugador['apellido']) ?>"
                                            data-dorsal="<?= $jugador['dorsal'] === null ? '' : (int) $jugador['dorsal'] ?>"
                                        >Editar</button>
                                        <form method="post" onsubmit="return confirm('¿Quitar a este jugador?');">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="accion" value="quitar">
                                            <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>">
                                            <input type="hidden" name="jugador_id" value="<?= $fila ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modal-editar" tabindex="-1" aria-labelledby="modal-editar-titulo" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
            <form method="post" class="modal-content">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="editar">
                <input type="hidden" name="equipo_id" value="<?= (int) $equipo['id'] ?>">
                <input type="hidden" name="jugador_id" id="editar-id" value="">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="modal-editar-titulo">Editar jugador</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="editar-nombre">Nombre</label>
                        <input class="form-control" id="editar-nombre" name="nombre" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="editar-apellido">Apellido</label>
                        <input class="form-control" id="editar-apellido" name="apellido" required maxlength="80">
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="editar-dorsal">Dorsal</label>
                        <input class="form-control" id="editar-dorsal" name="dorsal" inputmode="numeric" maxlength="2">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        document.getElementById('modal-editar').addEventListener('show.bs.modal', function (evento) {
            var boton = evento.relatedTarget;
            if (!boton) {
                return;
            }
            document.getElementById('editar-id').value = boton.getAttribute('data-id') || '';
            document.getElementById('editar-nombre').value = boton.getAttribute('data-nombre') || '';
            document.getElementById('editar-apellido').value = boton.getAttribute('data-apellido') || '';
            document.getElementById('editar-dorsal').value = boton.getAttribute('data-dorsal') || '';
        });
    </script>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

asegurar_grupo(GRUPO_SIN_ASIGNAR);
$equipos = listar_equipos(false);
$grupos = listar_grupos();
$sinAsignar = [];
$bloques = [];
foreach ($grupos as $grupo) {
    $nombre = (string) $grupo['nombre'];
    if ($nombre === GRUPO_SIN_ASIGNAR) {
        continue;
    }
    $bloques[$nombre] = [];
}
foreach ($equipos as $club) {
    $clave = trim((string) ($club['grupo'] ?? ''));
    if ($clave === '' || $clave === GRUPO_SIN_ASIGNAR) {
        $sinAsignar[] = $club;
        continue;
    }
    $bloques[$clave][] = $club;
}
uksort($bloques, static function (string $a, string $b): int {
    return strnatcasecmp($a, $b);
});
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Equipos</h1>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#modal-equipo">Agregar equipo</button>
        <?php if (isset($_GET['confirmar']) && (string) $_GET['confirmar'] === 'vaciar'): ?>
            <a class="btn btn-outline-secondary" href="<?= e(url_public('equipos.php')) ?>">Cancelar</a>
        <?php else: ?>
            <a class="btn btn-outline-danger" href="<?= e(url_public('equipos.php?confirmar=vaciar')) ?>">Vaciar todo</a>
        <?php endif; ?>
    </div>
</div>
<?php if (isset($_GET['confirmar']) && (string) $_GET['confirmar'] === 'vaciar'): ?>
    <div class="alert alert-warning">
        <p class="mb-3">Se borran equipos, jugadores, partidos, goles y tarjetas. No se puede deshacer.</p>
        <form method="post" class="d-flex flex-wrap gap-2">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="vaciar">
            <button class="btn btn-danger" type="submit">Sí, vaciar todo</button>
        </form>
    </div>
<?php endif; ?>
<?php if ($sinAsignar === [] && $bloques === []): ?>
    <p class="text-secondary">Todavía no hay equipos.</p>
<?php else: ?>
    <div class="sorteo-equipos">
        <div class="card">
            <div class="card-header fw-semibold"><?= e(GRUPO_SIN_ASIGNAR) ?></div>
            <div class="list-group list-group-flush zona-grupo" data-grupo="<?= e(GRUPO_SIN_ASIGNAR) ?>">
                <?php foreach ($sinAsignar as $club): ?>
                    <?php fila_equipo($club); ?>
                <?php endforeach; ?>
                <p class="text-secondary small px-3 py-3 mb-0 zona-vacia<?= $sinAsignar === [] ? '' : ' d-none' ?>">Suelta un equipo aquí.</p>
            </div>
        </div>
        <div>
            <?php if ($bloques !== []): ?>
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    <?php foreach ($bloques as $nombreGrupo => $clubes): ?>
                        <div class="col">
                            <div class="card h-100">
                                <div class="card-header fw-semibold"><?= e($nombreGrupo) ?></div>
                                <div class="list-group list-group-flush zona-grupo" data-grupo="<?= e($nombreGrupo) ?>">
                                    <?php foreach ($clubes as $club): ?>
                                        <?php fila_equipo($club); ?>
                                    <?php endforeach; ?>
                                    <p class="text-secondary small px-3 py-3 mb-0 zona-vacia<?= $clubes === [] ? '' : ' d-none' ?>">Suelta un equipo aquí.</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<div class="modal fade" id="modal-equipo" tabindex="-1" aria-labelledby="modal-equipo-titulo" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-equipo-titulo">Nuevo equipo</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input class="form-control" id="nombre" name="nombre" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="nombre_corto">Sigla</label>
                    <input class="form-control" id="nombre_corto" name="nombre_corto" required maxlength="10">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="grupo">Grupo</label>
                    <input class="form-control" id="grupo" name="grupo" required maxlength="20" value="<?= e(GRUPO_SIN_ASIGNAR) ?>" list="lista-grupos">
                    <datalist id="lista-grupos">
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= e((string) $grupo['nombre']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" type="submit">Crear equipo</button>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modal-editar-equipo" tabindex="-1" aria-labelledby="modal-editar-equipo-titulo" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-dialog-scrollable">
        <form method="post" class="modal-content">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar-equipo">
            <input type="hidden" name="equipo_id" id="editar-equipo-id" value="">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-editar-equipo-titulo">Editar equipo</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="editar-equipo-nombre">Nombre</label>
                    <input class="form-control" id="editar-equipo-nombre" name="nombre" required maxlength="120">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editar-equipo-sigla">Sigla</label>
                    <input class="form-control" id="editar-equipo-sigla" name="nombre_corto" required maxlength="10">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="editar-equipo-grupo">Grupo</label>
                    <input class="form-control" id="editar-equipo-grupo" name="grupo" required maxlength="20" list="lista-grupos-editar">
                    <datalist id="lista-grupos-editar">
                        <?php foreach ($grupos as $grupo): ?>
                            <option value="<?= e((string) $grupo['nombre']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.getElementById('modal-editar-equipo').addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;
        if (!boton) {
            return;
        }
        document.getElementById('editar-equipo-id').value = boton.getAttribute('data-id') || '';
        document.getElementById('editar-equipo-nombre').value = boton.getAttribute('data-nombre') || '';
        document.getElementById('editar-equipo-sigla').value = boton.getAttribute('data-sigla') || '';
        document.getElementById('editar-equipo-grupo').value = boton.getAttribute('data-grupo') || '';
    });

    (function () {
        var token = document.querySelector('#modal-equipo [name="csrf"]');
        if (!token) {
            return;
        }

        function pintarVacios() {
            document.querySelectorAll('.zona-grupo').forEach(function (zona) {
                var vacio = zona.querySelector('.zona-vacia');
                if (!vacio) {
                    return;
                }
                vacio.classList.toggle('d-none', zona.querySelector('.equipo-arrastrable') !== null);
            });
        }

        function idsDe(lista) {
            return Array.prototype.map.call(lista.querySelectorAll('.equipo-arrastrable'), function (item) {
                return item.getAttribute('data-id') || '';
            }).filter(Boolean).join(',');
        }

        function colocar(fila, zona, referencia, clientY) {
            if (referencia && referencia !== fila && referencia.parentElement === zona) {
                var rect = referencia.getBoundingClientRect();
                if (clientY > rect.top + rect.height / 2) {
                    zona.insertBefore(fila, referencia.nextElementSibling);
                } else {
                    zona.insertBefore(fila, referencia);
                }
                return true;
            }
            if (fila.parentElement !== zona) {
                var vacio = zona.querySelector('.zona-vacia');
                if (vacio) {
                    zona.insertBefore(fila, vacio);
                } else {
                    zona.appendChild(fila);
                }
                return true;
            }
            return false;
        }

        function guardarMovimiento(fila, origen, zona) {
            var grupo = zona.getAttribute('data-grupo') || '';
            var cuerpo = new URLSearchParams();
            cuerpo.set('csrf', token.value);
            cuerpo.set('accion', 'mover-grupo');
            cuerpo.set('ajax', '1');
            cuerpo.set('grupo', grupo);
            cuerpo.set('ids', idsDe(zona));
            if (origen !== zona) {
                cuerpo.set('grupo_origen', origen.getAttribute('data-grupo') || '');
                cuerpo.set('ids_origen', idsDe(origen));
            }
            fetch('equipos.php', { method: 'POST', body: cuerpo, headers: { 'Accept': 'application/json' } })
                .then(function (respuesta) { return respuesta.json(); })
                .then(function (dato) {
                    if (!dato.ok) {
                        window.alert(dato.mensaje || 'No se pudo mover.');
                        window.location.reload();
                        return;
                    }
                    fila.setAttribute('data-grupo', grupo);
                    var boton = fila.querySelector('[data-bs-target="#modal-editar-equipo"]');
                    if (boton) {
                        boton.setAttribute('data-grupo', grupo);
                    }
                    pintarVacios();
                })
                .catch(function () {
                    window.alert('No se pudo mover.');
                    window.location.reload();
                });
        }

        document.querySelectorAll('.equipo-arrastrable').forEach(function (fila) {
            fila.addEventListener('dragstart', function (evento) {
                if (evento.target.closest('a, button')) {
                    evento.preventDefault();
                    return;
                }
                evento.dataTransfer.setData('text/plain', fila.getAttribute('data-id') || '');
                evento.dataTransfer.effectAllowed = 'move';
                fila.classList.add('arrastrando');
            });
            fila.addEventListener('dragend', function () {
                fila.classList.remove('arrastrando');
                document.querySelectorAll('.zona-grupo.soltando').forEach(function (zona) {
                    zona.classList.remove('soltando');
                });
            });
        });

        document.querySelectorAll('.zona-grupo').forEach(function (zona) {
            zona.addEventListener('dragover', function (evento) {
                evento.preventDefault();
                evento.dataTransfer.dropEffect = 'move';
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
                var referencia = evento.target.closest('.equipo-arrastrable');
                if (!colocar(fila, zona, referencia, evento.clientY)) {
                    return;
                }
                guardarMovimiento(fila, origen, zona);
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
                document.querySelectorAll('.zona-grupo.soltando').forEach(function (zona) {
                    zona.classList.remove('soltando');
                });
                if (!bajo) {
                    return;
                }
                var zona = bajo.closest('.zona-grupo');
                if (!zona) {
                    return;
                }
                zona.classList.add('soltando');
                colocar(arrastre.fila, zona, bajo.closest('.equipo-arrastrable'), evento.clientY);
            });
            agarre.addEventListener('pointerup', function (evento) {
                if (!arrastre || arrastre.pointerId !== evento.pointerId) {
                    return;
                }
                var fila = arrastre.fila;
                var origen = arrastre.origen;
                var siguiente = arrastre.siguiente;
                var zona = fila.parentElement;
                fila.style.pointerEvents = '';
                fila.classList.remove('arrastrando');
                document.querySelectorAll('.zona-grupo.soltando').forEach(function (item) {
                    item.classList.remove('soltando');
                });
                arrastre = null;
                if (!zona || !zona.classList.contains('zona-grupo') || (zona === origen && fila.nextSibling === siguiente)) {
                    if (origen) {
                        origen.insertBefore(fila, siguiente);
                    }
                    return;
                }
                guardarMovimiento(fila, origen, zona);
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
            });
        });
    })();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
