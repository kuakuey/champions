<?php
/**
 * Cabecera común. La página pública solo muestra la marca y el tema.
 * $titulo, $cuerpo y $contenedor se definen antes de incluir este archivo.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'header.php') {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once __DIR__ . '/funciones.php';

$titulo = $titulo ?? LIGA_NOMBRE;
$pagina = $pagina ?? '';
$plantilla = $plantilla ?? 'public';
$descripcion = $descripcion ?? 'Tabla, fixture y resultados de Champions.';
if (!in_array($plantilla, ['public', 'admin', 'simple'], true)) {
    $plantilla = 'public';
}
$GLOBALS['pagina'] = $pagina;
$GLOBALS['liga_cabecera_enviada'] = true;

iniciar_sesion();
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

$tituloCompleto = $titulo === LIGA_NOMBRE ? LIGA_NOMBRE : $titulo . ' · ' . LIGA_NOMBRE;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#198754">
    <meta name="description" content="<?= e($descripcion) ?>">
    <?php if ($plantilla === 'admin'): ?>
        <meta name="robots" content="noindex">
    <?php endif; ?>
    <title><?= e($tituloCompleto) ?></title>
    <script>
        (function () {
            var tema = 'light';
            try {
                var guardado = localStorage.getItem('tema');
                if (guardado === 'dark' || guardado === 'light') {
                    tema = guardado;
                }
            } catch (error) {}
            document.documentElement.setAttribute('data-bs-theme', tema);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        html[data-bs-theme="light"] { color-scheme: light; }
        html[data-bs-theme="dark"] { color-scheme: dark; }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main { flex: 1 0 auto; }
        .bg-liga { background-color: #198754 !important; }
        .navbar-dark .nav-link.active {
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: .35rem;
        }
        .escudo {
            object-fit: contain;
            background: #fff;
            border-radius: 50%;
            border: 1px solid rgba(0, 0, 0, .08);
        }
        [data-bs-theme="dark"] .escudo {
            background: #212529;
            border-color: rgba(255, 255, 255, .15);
        }
        .escudo-vacio {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(25, 135, 84, .12);
            color: #198754;
            vertical-align: middle;
        }
        .badge-forma {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            font-size: .72rem;
            font-weight: 700;
            color: #fff;
            margin-right: .15rem;
        }
        .forma-v { background: #198754; }
        .forma-e { background: #6c757d; }
        .forma-d { background: #dc3545; }
        .marcador {
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            font-size: 1.35rem;
            letter-spacing: .04em;
            min-width: 3.2rem;
            text-align: center;
            flex: 0 0 auto;
        }
        .partido-card .enfrentamiento {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }
        .partido-card .equipo-linea {
            display: flex;
            align-items: center;
            gap: .45rem;
            flex: 1 1 0;
            min-width: 0;
            font-weight: 600;
            line-height: 1.2;
        }
        .partido-card .equipo-linea span {
            min-width: 0;
        }
        .partido-card .escudo,
        .partido-card .escudo-vacio {
            flex: 0 0 auto;
        }
        .tabla-liga th {
            white-space: nowrap;
            font-size: .75rem;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .tabla-liga td { vertical-align: middle; }
        .posicion-top { font-weight: 700; color: #198754; }
        body.pantalla-fija {
            height: 100dvh;
            max-height: 100dvh;
            overflow: hidden;
        }
        body.pantalla-fija main {
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .rejilla-inicio {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: .75rem;
        }
        .columna-derecha {
            min-height: 0;
            min-width: 0;
            display: grid;
            grid-template-rows: minmax(0, 1fr) minmax(0, 1fr);
            gap: .75rem;
        }
        .columna-derecha:has(> .panel:first-child.contraido) {
            grid-template-rows: auto minmax(0, 1fr);
        }
        .columna-derecha:has(> .panel:last-child.contraido) {
            grid-template-rows: minmax(0, 1fr) auto;
        }
        .columna-derecha:has(> .panel:first-child.contraido):has(> .panel:last-child.contraido) {
            grid-template-rows: auto auto;
            align-content: start;
        }
        .panel {
            min-height: 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--bs-border-color);
            border-radius: .75rem;
            overflow: hidden;
        }
        .panel-titulo {
            flex: 0 0 auto;
            margin: 0;
            padding: .7rem 1rem;
            border-bottom: 1px solid var(--bs-border-color);
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }
        .panel-titulo h1,
        .panel-titulo h2 {
            margin: 0;
            font-size: inherit;
        }
        .interruptor-refresh .form-check-input {
            cursor: pointer;
        }
        .interruptor-refresh .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }
        .interruptor-refresh .form-check-label {
            cursor: pointer;
            font-size: .95rem;
            font-weight: 600;
        }
        .panel-contraer {
            border: 0;
            background: transparent;
            color: inherit;
            line-height: 1;
            padding: .15rem .35rem;
        }
        .panel.contraido .panel-cuerpo {
            display: none;
        }
        .panel.contraido .panel-titulo {
            border-bottom: 0;
        }
        .panel-cuerpo {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
        }
        .panel-cuerpo thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: var(--bs-body-bg);
        }
        .grupo-tabla + .grupo-tabla {
            border-top: 1px solid var(--bs-border-color);
        }
        .partido-jugado {
            opacity: .5;
        }
        .fila-partido .lado {
            flex: 1 1 0;
            min-width: 0;
        }
        .fila-partido .lado > span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .agarre {
            touch-action: none;
            flex: 0 0 auto;
            border: 0;
            background: transparent;
            color: var(--bs-secondary-color);
            padding: .35rem .15rem;
            font-size: 1.35rem;
            line-height: 1;
            cursor: grab;
        }
        .partido-card .fw-semibold {
            overflow-wrap: anywhere;
        }
        .barra-movil {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1030;
            display: none;
            background: #198754;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .barra-movil a {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: .1rem;
            min-height: 3.25rem;
            color: rgba(255, 255, 255, .78);
            text-decoration: none;
            font-size: .72rem;
            font-weight: 600;
        }
        .barra-movil a.activo {
            color: #fff;
        }
        .barra-movil i {
            font-size: 1.2rem;
        }
        .equipo-arrastrable,
        .partido-arrastrable {
            cursor: grab;
        }
        .equipo-arrastrable.arrastrando,
        .partido-arrastrable.arrastrando {
            opacity: .45;
        }
        .zona-grupo {
            min-height: 3.25rem;
        }
        .zona-grupo.soltando,
        .zona-cuadro.soltando {
            background: rgba(25, 135, 84, .12);
        }
        .zona-puesto {
            min-height: 3.25rem;
            border: 1px dashed rgba(25, 135, 84, .45);
            border-radius: .5rem;
        }
        .sorteo-equipos {
            display: grid;
            grid-template-columns: minmax(0, 3fr) minmax(0, 7fr);
            gap: 1rem;
            align-items: start;
        }
        .sorteo-equipos > * {
            min-width: 0;
        }
        .sorteo-equipos .card,
        .sorteo-equipos .list-group-item {
            min-width: 0;
        }
        @media (max-width: 767.98px) {
            .navbar {
                padding-top: calc(.4rem + env(safe-area-inset-top));
            }
            main.container,
            main.container-fluid {
                padding-top: 1rem !important;
                padding-bottom: calc(4.5rem + env(safe-area-inset-bottom)) !important;
            }
            .barra-movil {
                display: flex;
            }
            .form-control,
            .form-select {
                font-size: 16px;
            }
            body.pantalla-fija {
                height: auto;
                max-height: none;
                overflow: auto;
            }
            body.pantalla-fija main {
                overflow: visible;
                display: block;
            }
            .rejilla-inicio,
            .columna-derecha {
                display: flex;
                flex-direction: column;
                min-height: auto;
            }
            .panel {
                min-height: auto;
            }
            .panel-cuerpo {
                overflow: visible;
            }
            .grupo-tabla,
            .tabla-movil {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            .tabla-liga th,
            .tabla-liga td {
                padding: .4rem .45rem;
            }
            .tabla-puntajes {
                min-width: 34rem;
            }
            .tabla-puntajes th:nth-child(1),
            .tabla-puntajes td:nth-child(1),
            .tabla-puntajes th:nth-child(2),
            .tabla-puntajes td:nth-child(2) {
                position: sticky;
                z-index: 2;
                background: var(--bs-body-bg);
            }
            .tabla-puntajes th:nth-child(1),
            .tabla-puntajes td:nth-child(1) {
                left: 0;
            }
            .tabla-puntajes th:nth-child(2),
            .tabla-puntajes td:nth-child(2) {
                left: 1.8rem;
                box-shadow: 4px 0 6px rgba(0, 0, 0, .06);
            }
            .agarre {
                min-width: 2.75rem;
                min-height: 2.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .sorteo-equipos {
                grid-template-columns: minmax(0, 1fr);
            }
            .equipo-arrastrable .btn,
            .partido-arrastrable .btn,
            .list-group-item .btn {
                min-width: 2.75rem;
                min-height: 2.75rem;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
            .partido-card .escudo,
            .partido-card .escudo-vacio {
                width: 48px !important;
                height: 48px !important;
            }
            .partido-card .marcador {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body<?= ($cuerpo ?? '') === 'pantalla-fija' ? ' class="pantalla-fija"' : '' ?>>
<nav class="navbar navbar-dark bg-liga">
    <div class="<?= ($contenedor ?? '') === 'fluid' ? 'container-fluid' : 'container' ?> d-flex flex-wrap gap-2">
        <a class="navbar-brand fw-semibold" href="<?= e(url_public('index.php')) ?>">
            <i class="bi bi-trophy-fill me-1" aria-hidden="true"></i><?= e(LIGA_NOMBRE) ?>
        </a>
        <div class="d-none d-md-flex align-items-center gap-2 ms-auto">
            <a class="btn btn-sm <?= ($pagina ?? '') === 'equipos' ? 'btn-light' : 'btn-outline-light' ?>" href="<?= e(url_public('equipos.php')) ?>">Equipos</a>
            <a class="btn btn-sm <?= ($pagina ?? '') === 'partidos' ? 'btn-light' : 'btn-outline-light' ?>" href="<?= e(url_public('partidos.php')) ?>">Partidos</a>
            <a class="btn btn-sm <?= ($pagina ?? '') === 'final' ? 'btn-light' : 'btn-outline-light' ?>" href="<?= e(url_public('final.php')) ?>">Final</a>
            <button class="btn btn-outline-light btn-sm" type="button" data-tema aria-label="Cambiar tema claro u oscuro">
                <i class="bi bi-moon-fill icono-tema" aria-hidden="true"></i>
            </button>
        </div>
        <button class="btn btn-outline-light btn-sm d-md-none ms-auto" type="button" data-tema aria-label="Cambiar tema claro u oscuro">
            <i class="bi bi-moon-fill icono-tema" aria-hidden="true"></i>
        </button>
    </div>
</nav>
<nav class="barra-movil" aria-label="Secciones">
    <a class="<?= ($pagina ?? '') === 'inicio' ? 'activo' : '' ?>" href="<?= e(url_public('index.php')) ?>">
        <i class="bi bi-house-fill" aria-hidden="true"></i>
        <span>Inicio</span>
    </a>
    <a class="<?= ($pagina ?? '') === 'equipos' ? 'activo' : '' ?>" href="<?= e(url_public('equipos.php')) ?>">
        <i class="bi bi-people-fill" aria-hidden="true"></i>
        <span>Equipos</span>
    </a>
    <a class="<?= ($pagina ?? '') === 'partidos' ? 'activo' : '' ?>" href="<?= e(url_public('partidos.php')) ?>">
        <i class="bi bi-calendar-event" aria-hidden="true"></i>
        <span>Partidos</span>
    </a>
    <a class="<?= ($pagina ?? '') === 'final' ? 'activo' : '' ?>" href="<?= e(url_public('final.php')) ?>">
        <i class="bi bi-trophy" aria-hidden="true"></i>
        <span>Final</span>
    </a>
</nav>
<main class="<?= ($contenedor ?? '') === 'fluid' ? 'container-fluid' : 'container' ?> py-4">
<?= avisos_html() ?>
