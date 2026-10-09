<?php
/**
 * Comprueba la conexión y permite crear la base, las tablas,
 * las vistas y los datos de ejemplo, uno por uno.
 * No borra nada que ya exista.
 */

declare(strict_types=1);

require __DIR__ . '/config/db.php';

session_name('INSTALACION');
session_start();

if (empty($_SESSION['csrf_instalacion'])) {
    $_SESSION['csrf_instalacion'] = bin2hex(random_bytes(32));
}

/**
 * @return array<int, array{id: string, grupo: string, nombre: string, detalle: string, requiere: array<int, string>}>
 */
function catalogo_instalacion(): array
{
    return [
        [
            'id' => 'base',
            'grupo' => 'Base de datos',
            'nombre' => DB_NAME,
            'detalle' => 'Base utf8mb4',
            'requiere' => [],
        ],
        [
            'id' => 'tabla:grupos',
            'grupo' => 'Tablas',
            'nombre' => 'grupos',
            'detalle' => 'Grupos del campeonato',
            'requiere' => ['base'],
        ],
        [
            'id' => 'tabla:equipos',
            'grupo' => 'Tablas',
            'nombre' => 'equipos',
            'detalle' => 'Clubes del campeonato',
            'requiere' => ['tabla:grupos'],
        ],
        [
            'id' => 'tabla:jugadores',
            'grupo' => 'Tablas',
            'nombre' => 'jugadores',
            'detalle' => 'Plantel de cada club',
            'requiere' => ['tabla:equipos'],
        ],
        [
            'id' => 'tabla:partidos',
            'grupo' => 'Tablas',
            'nombre' => 'partidos',
            'detalle' => 'Calendario y marcador',
            'requiere' => ['tabla:equipos'],
        ],
        [
            'id' => 'tabla:eventos',
            'grupo' => 'Tablas',
            'nombre' => 'eventos',
            'detalle' => 'Goles, asistencias y tarjetas',
            'requiere' => ['tabla:partidos', 'tabla:jugadores'],
        ],
        [
            'id' => 'tabla:sanciones',
            'grupo' => 'Tablas',
            'nombre' => 'sanciones',
            'detalle' => 'Suspensiones',
            'requiere' => ['tabla:jugadores', 'tabla:partidos'],
        ],
        [
            'id' => 'tabla:noticias',
            'grupo' => 'Tablas',
            'nombre' => 'noticias',
            'detalle' => 'Avisos de la liga',
            'requiere' => ['base'],
        ],
        [
            'id' => 'tabla:usuarios',
            'grupo' => 'Tablas',
            'nombre' => 'usuarios',
            'detalle' => 'Acceso de administración',
            'requiere' => ['base'],
        ],
        [
            'id' => 'vista:v_tabla',
            'grupo' => 'Vistas',
            'nombre' => 'v_tabla',
            'detalle' => 'Posiciones calculadas',
            'requiere' => ['tabla:equipos', 'tabla:partidos'],
        ],
        [
            'id' => 'vista:v_goleadores',
            'grupo' => 'Vistas',
            'nombre' => 'v_goleadores',
            'detalle' => 'Ranking de goles',
            'requiere' => ['tabla:eventos', 'tabla:jugadores', 'tabla:equipos'],
        ],
        [
            'id' => 'vista:v_asistidores',
            'grupo' => 'Vistas',
            'nombre' => 'v_asistidores',
            'detalle' => 'Ranking de asistencias',
            'requiere' => ['tabla:eventos', 'tabla:jugadores', 'tabla:equipos'],
        ],
        [
            'id' => 'datos:equipos',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'equipos',
            'detalle' => '8 clubes',
            'requiere' => ['tabla:equipos'],
        ],
        [
            'id' => 'datos:jugadores',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'jugadores',
            'detalle' => '6 jugadores por club',
            'requiere' => ['tabla:jugadores', 'datos:equipos'],
        ],
        [
            'id' => 'datos:partidos',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'partidos',
            'detalle' => 'Jornadas 1 a 5',
            'requiere' => ['tabla:partidos', 'datos:equipos'],
        ],
        [
            'id' => 'datos:eventos',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'eventos',
            'detalle' => 'Goles, asistencias y tarjetas',
            'requiere' => ['tabla:eventos', 'datos:partidos', 'datos:jugadores'],
        ],
        [
            'id' => 'datos:sanciones',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'sanciones',
            'detalle' => 'Roja y acumulación de amarillas',
            'requiere' => ['tabla:sanciones', 'datos:jugadores', 'datos:partidos'],
        ],
        [
            'id' => 'datos:noticias',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'noticias',
            'detalle' => 'Tres noticias',
            'requiere' => ['tabla:noticias'],
        ],
        [
            'id' => 'datos:usuarios',
            'grupo' => 'Datos de ejemplo',
            'nombre' => 'usuarios',
            'detalle' => 'Admin y planillero',
            'requiere' => ['tabla:usuarios'],
        ],
    ];
}

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

function etiqueta_paso(array $paso): string
{
    $id = (string) $paso['id'];
    $nombre = (string) $paso['nombre'];
    if ($id === 'base') {
        return 'la base de datos';
    }
    if (str_starts_with($id, 'datos:')) {
        return 'datos de ' . $nombre;
    }
    if (str_starts_with($id, 'vista:')) {
        return 'la vista ' . $nombre;
    }

    return 'la tabla ' . $nombre;
}

function nombre_sql(string $nombre): string
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $nombre) !== 1) {
        throw new RuntimeException('Nombre de base o tabla no válido.');
    }

    return '`' . $nombre . '`';
}

function conectar_servidor(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

/**
 * @return array<string, string>
 */
function sentencias_esquema(string $ruta): array
{
    if (!is_file($ruta)) {
        throw new RuntimeException('No se encontró sql/esquema.sql.');
    }

    $texto = file_get_contents($ruta);
    if ($texto === false) {
        throw new RuntimeException('No se pudo leer sql/esquema.sql.');
    }

    $limpias = [];
    foreach (preg_split('/\R/', $texto) ?: [] as $linea) {
        $sinComentario = preg_replace('/--.*$/', '', $linea);
        if (trim((string) $sinComentario) !== '') {
            $limpias[] = $sinComentario;
        }
    }

    $mapa = [];
    foreach (preg_split('/;\s*/', implode("\n", $limpias)) ?: [] as $bloque) {
        $sql = trim($bloque);
        if ($sql === '' || preg_match('/^(DROP|USE|SET)\b/i', $sql) === 1) {
            continue;
        }
        if (preg_match('/^CREATE\s+DATABASE\b/i', $sql) === 1) {
            $mapa['base'] = $sql;
        } elseif (preg_match('/^CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m) === 1) {
            $mapa['tabla:' . $m[1]] = preg_replace('/^CREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', $sql, 1) ?? $sql;
        } elseif (preg_match('/^CREATE\s+VIEW\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m) === 1) {
            $mapa['vista:' . $m[1]] = preg_replace('/^CREATE\s+VIEW\s+/i', 'CREATE OR REPLACE VIEW ', $sql, 1) ?? $sql;
        } elseif (preg_match('/^INSERT\s+INTO\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m) === 1) {
            $mapa['datos:' . $m[1]] = $sql;
        }
    }

    return $mapa;
}

/**
 * @return array<int, string>
 */
function columnas_esperadas(string $sql): array
{
    $inicio = strpos($sql, '(');
    $fin = strrpos($sql, ')');
    if ($inicio === false || $fin === false || $fin <= $inicio) {
        return [];
    }

    $columnas = [];
    $cuerpo = substr($sql, $inicio + 1, $fin - $inicio - 1);
    foreach (preg_split('/\R/', $cuerpo) ?: [] as $linea) {
        $linea = trim($linea);
        if ($linea === '' || preg_match('/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|CHECK|FOREIGN|ON|REFERENCES)\b/i', $linea) === 1) {
            continue;
        }
        if (preg_match('/^`?([A-Za-z_][A-Za-z0-9_]*)`?\s+(INT|TINYINT|SMALLINT|MEDIUMINT|BIGINT|VARCHAR|CHAR|TEXT|DATE|DATETIME|TIMESTAMP|ENUM|DECIMAL|FLOAT|DOUBLE|BOOLEAN|BOOL|JSON|BLOB)\b/i', $linea, $m) === 1) {
            $columnas[] = $m[1];
        }
    }

    return $columnas;
}

function base_existe(PDO $pdo): bool
{
    $consulta = $pdo->prepare(
        'SELECT SCHEMA_NAME
         FROM information_schema.SCHEMATA
         WHERE SCHEMA_NAME = ?'
    );
    $consulta->execute([DB_NAME]);

    return $consulta->fetch() !== false;
}

/**
 * @return array{charset: string, collation: string}
 */
function datos_base(PDO $pdo): array
{
    $consulta = $pdo->prepare(
        'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation
         FROM information_schema.SCHEMATA
         WHERE SCHEMA_NAME = ?'
    );
    $consulta->execute([DB_NAME]);
    $fila = $consulta->fetch();

    return [
        'charset' => (string) ($fila['charset'] ?? ''),
        'collation' => (string) ($fila['collation'] ?? ''),
    ];
}

/**
 * @return array<string, string> nombre => TABLE|VIEW
 */
function objetos_base(PDO $pdo): array
{
    $consulta = $pdo->prepare(
        'SELECT TABLE_NAME, TABLE_TYPE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = ?'
    );
    $consulta->execute([DB_NAME]);
    $objetos = [];
    foreach ($consulta->fetchAll() as $fila) {
        $objetos[(string) $fila['TABLE_NAME']] = (string) $fila['TABLE_TYPE'];
    }

    return $objetos;
}

/**
 * @return array<int, string>
 */
function columnas_reales(PDO $pdo, string $tabla): array
{
    $consulta = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION'
    );
    $consulta->execute([DB_NAME, $tabla]);

    return array_column($consulta->fetchAll(), 'COLUMN_NAME');
}

function contar_filas(PDO $pdo, string $tabla): int
{
    $sql = 'SELECT COUNT(*) FROM ' . nombre_sql(DB_NAME) . '.' . nombre_sql($tabla);
    return (int) $pdo->query($sql)->fetchColumn();
}

function usar_base(PDO $pdo): void
{
    $pdo->exec('USE ' . nombre_sql(DB_NAME));
}

/**
 * @param array<string, string> $sentencias
 * @return array{servidor: string, base: bool, charset: string, collation: string, pasos: array<int, array<string, mixed>>, pendientes: int}
 */
function diagnosticar(PDO $pdo, array $sentencias): array
{
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $existeBase = base_existe($pdo);
    $charset = '';
    $collation = '';
    $objetos = [];
    if ($existeBase) {
        $info = datos_base($pdo);
        $charset = $info['charset'];
        $collation = $info['collation'];
        $objetos = objetos_base($pdo);
    }

    $listos = [];
    $pasos = [];
    $etiquetas = [];
    foreach (catalogo_instalacion() as $paso) {
        $etiquetas[$paso['id']] = etiqueta_paso($paso);
    }
    foreach (catalogo_instalacion() as $paso) {
        $id = $paso['id'];
        $fila = $paso;
        $fila['filas'] = null;
        $fila['faltan'] = [];
        $fila['estado'] = 'bloqueado';
        $fila['puede'] = false;
        $fila['aviso'] = '';

        $bloqueos = [];
        foreach ($paso['requiere'] as $requisito) {
            if (!isset($listos[$requisito])) {
                $bloqueos[] = $requisito;
            }
        }

        if (!isset($sentencias[$id])) {
            $fila['estado'] = 'error';
            $fila['aviso'] = 'No está en sql/esquema.sql.';
            $pasos[] = $fila;
            continue;
        }

        if ($bloqueos !== []) {
            $nombres = [];
            foreach ($bloqueos as $requisito) {
                $nombres[] = $etiquetas[$requisito] ?? $requisito;
            }
            $fila['aviso'] = 'Antes importa: ' . implode(', ', $nombres) . '.';
            $pasos[] = $fila;
            continue;
        }

        if ($id === 'base') {
            if ($existeBase) {
                $fila['estado'] = 'listo';
                $fila['aviso'] = $charset . ' / ' . $collation;
                $listos[$id] = true;
            } else {
                $fila['estado'] = 'pendiente';
                $fila['puede'] = true;
            }
            $pasos[] = $fila;
            continue;
        }

        $nombre = $paso['nombre'];
        $esVista = str_starts_with($id, 'vista:');
        $esDatos = str_starts_with($id, 'datos:');
        $esTabla = str_starts_with($id, 'tabla:');
        $tipoReal = $objetos[$nombre] ?? '';

        if ($esTabla || $esVista) {
            $esperado = $esVista ? 'VIEW' : 'BASE TABLE';
            if ($tipoReal === '') {
                $fila['estado'] = 'pendiente';
                $fila['puede'] = true;
            } elseif ($tipoReal !== $esperado) {
                $fila['estado'] = 'error';
                $fila['aviso'] = 'Existe, pero no es del tipo esperado.';
            } else {
                try {
                    $fila['filas'] = contar_filas($pdo, $nombre);
                } catch (PDOException $e) {
                    $fila['estado'] = 'error';
                    $fila['aviso'] = 'No se pudo consultar: ' . $e->getMessage();
                    $pasos[] = $fila;
                    continue;
                }
                if ($esTabla) {
                    $reales = columnas_reales($pdo, $nombre);
                    $fila['faltan'] = array_values(array_diff(columnas_esperadas($sentencias[$id]), $reales));
                }
                if ($fila['faltan'] !== []) {
                    $fila['estado'] = 'incompleta';
                    $fila['aviso'] = 'Faltan columnas: ' . implode(', ', $fila['faltan']) . '.';
                } else {
                    $fila['estado'] = 'listo';
                    $listos[$id] = true;
                }
            }
            $pasos[] = $fila;
            continue;
        }

        if ($esDatos) {
            if ($tipoReal !== 'BASE TABLE') {
                $fila['aviso'] = 'Primero crea la tabla.';
            } else {
                $fila['filas'] = contar_filas($pdo, $nombre);
                if ($fila['filas'] > 0) {
                    $fila['estado'] = 'listo';
                    $listos[$id] = true;
                } else {
                    $fila['estado'] = 'pendiente';
                    $fila['puede'] = true;
                    $fila['aviso'] = 'La tabla está vacía.';
                }
            }
        }

        $pasos[] = $fila;
    }

    $pendientes = 0;
    foreach ($pasos as $paso) {
        if ($paso['puede'] === true) {
            $pendientes++;
        }
    }

    return [
        'servidor' => $version,
        'base' => $existeBase,
        'charset' => $charset,
        'collation' => $collation,
        'pasos' => $pasos,
        'pendientes' => $pendientes,
    ];
}

/**
 * @param array<string, mixed> $paso
 */
function importar_paso(PDO $pdo, array $paso, string $sql): string
{
    if ($paso['id'] === 'base') {
        $pdo->exec($sql);
        usar_base($pdo);

        return 'Base de datos creada.';
    }

    if (!base_existe($pdo)) {
        throw new RuntimeException('Primero crea la base de datos.');
    }
    usar_base($pdo);

    if (str_starts_with((string) $paso['id'], 'datos:')) {
        $filas = contar_filas($pdo, (string) $paso['nombre']);
        if ($filas > 0) {
            throw new RuntimeException('La tabla ' . $paso['nombre'] . ' ya tiene datos y no se volvió a importar.');
        }
    }

    $pdo->exec($sql);
    if (str_starts_with((string) $paso['id'], 'datos:')) {
        $filas = contar_filas($pdo, (string) $paso['nombre']);

        return 'Datos importados en ' . $paso['nombre'] . ' (' . $filas . ').';
    }

    return $paso['nombre'] . ' importado.';
}

function tomar_aviso(): ?array
{
    if (empty($_SESSION['aviso_instalacion']) || !is_array($_SESSION['aviso_instalacion'])) {
        return null;
    }
    $aviso = $_SESSION['aviso_instalacion'];
    unset($_SESSION['aviso_instalacion']);

    return $aviso;
}

$aviso = null;
$errorConexion = null;
$pdo = null;
$sentencias = [];
$diagnostico = null;

try {
    $sentencias = sentencias_esquema(__DIR__ . '/sql/esquema.sql');
    $pdo = conectar_servidor();
} catch (Throwable $e) {
    $errorConexion = $e->getMessage();
}

if ($pdo instanceof PDO && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    $accion = (string) ($_POST['accion'] ?? '');
    $pedido = (string) ($_POST['paso'] ?? '');
    $mensajes = [];
    $tipoAviso = 'success';

    try {
        if (!hash_equals((string) $_SESSION['csrf_instalacion'], $token)) {
            throw new RuntimeException('La sesión del formulario venció. Vuelve a intentar.');
        }

        if ($accion === 'pendientes') {
            for ($vuelta = 0; $vuelta < 30; $vuelta++) {
                $estado = diagnosticar($pdo, $sentencias);
                $siguiente = null;
                foreach ($estado['pasos'] as $paso) {
                    if ($paso['puede'] === true) {
                        $siguiente = $paso;
                        break;
                    }
                }
                if ($siguiente === null) {
                    break;
                }
                $mensajes[] = importar_paso($pdo, $siguiente, $sentencias[$siguiente['id']]);
            }
            if ($mensajes === []) {
                $mensajes[] = 'No hay nada pendiente.';
                $tipoAviso = 'info';
            }
        } elseif ($accion === 'paso') {
            $estado = diagnosticar($pdo, $sentencias);
            $elegido = null;
            foreach ($estado['pasos'] as $paso) {
                if ($paso['id'] === $pedido) {
                    $elegido = $paso;
                    break;
                }
            }
            if ($elegido === null) {
                throw new RuntimeException('Ese paso no existe.');
            }
            if ($elegido['puede'] !== true) {
                throw new RuntimeException($elegido['aviso'] !== '' ? (string) $elegido['aviso'] : 'Ese paso no se puede importar ahora.');
            }
            $mensajes[] = importar_paso($pdo, $elegido, $sentencias[$elegido['id']]);
        } else {
            throw new RuntimeException('Acción no reconocida.');
        }
    } catch (Throwable $e) {
        $tipoAviso = 'danger';
        $mensajes[] = $e->getMessage();
    }

    $_SESSION['aviso_instalacion'] = [
        'tipo' => $tipoAviso,
        'mensaje' => implode(' ', $mensajes),
    ];
    header('Location: instalacion.php');
    exit;
}

if ($pdo instanceof PDO && $errorConexion === null) {
    try {
        $diagnostico = diagnosticar($pdo, $sentencias);
    } catch (Throwable $e) {
        $errorConexion = $e->getMessage();
    }
}

$aviso = tomar_aviso();
$csrf = (string) $_SESSION['csrf_instalacion'];
$grupos = ['Base de datos', 'Tablas', 'Vistas', 'Datos de ejemplo'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Instalación · Champions</title>
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
        .bg-liga { background-color: #198754 !important; }
    </style>
</head>
<body>
<header class="navbar navbar-dark bg-liga">
    <div class="container">
        <span class="navbar-brand mb-0 fw-semibold"><i class="bi bi-database-check me-1"></i> Instalación</span>
        <a class="btn btn-outline-light btn-sm" href="index.php">Ir al campeonato</a>
    </div>
</header>
<main class="container py-4">
    <h1 class="h3">Verificación de la base</h1>
    <p class="text-secondary">Revisa la conexión e importa cada tabla cuando la anterior ya esté lista. Si algo ya existe, se deja como está.</p>

    <?php if (is_array($aviso)): ?>
        <?php $tipo = in_array($aviso['tipo'] ?? '', ['success', 'danger', 'warning', 'info'], true) ? $aviso['tipo'] : 'info'; ?>
        <div class="alert alert-<?= h($tipo) ?>"><?= h((string) ($aviso['mensaje'] ?? '')) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Conexión</div>
        <div class="card-body">
            <?php if ($errorConexion !== null): ?>
                <div class="alert alert-danger mb-3">No se pudo conectar. <?= h($errorConexion) ?></div>
            <?php else: ?>
                <p class="mb-2"><span class="badge text-bg-success">Conectado</span> <?= h((string) ($diagnostico['servidor'] ?? '')) ?></p>
            <?php endif; ?>
            <dl class="row mb-0">
                <dt class="col-sm-3">Servidor</dt>
                <dd class="col-sm-9"><?= h(DB_HOST) ?></dd>
                <dt class="col-sm-3">Base</dt>
                <dd class="col-sm-9"><?= h(DB_NAME) ?></dd>
                <dt class="col-sm-3">Usuario</dt>
                <dd class="col-sm-9"><?= h(DB_USER) ?></dd>
            </dl>
        </div>
    </div>

    <?php if (is_array($diagnostico)): ?>
        <form method="post" class="mb-4">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="accion" value="pendientes">
            <button class="btn btn-success" type="submit" <?= (int) $diagnostico['pendientes'] === 0 ? 'disabled' : '' ?>>
                Importar pendientes (<?= (int) $diagnostico['pendientes'] ?>)
            </button>
        </form>

        <?php foreach ($grupos as $grupo): ?>
            <div class="card mb-4">
                <div class="card-header"><?= h($grupo) ?></div>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Objeto</th>
                                <th>Detalle</th>
                                <th>Estado</th>
                                <th class="text-end">Registros</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnostico['pasos'] as $paso): ?>
                                <?php if ($paso['grupo'] !== $grupo) { continue; } ?>
                                <tr>
                                    <td class="fw-semibold"><?= h((string) $paso['nombre']) ?></td>
                                    <td><?= h((string) $paso['detalle']) ?></td>
                                    <td>
                                        <?php if ($paso['estado'] === 'listo'): ?>
                                            <span class="badge text-bg-success">Lista</span>
                                        <?php elseif ($paso['estado'] === 'pendiente'): ?>
                                            <span class="badge text-bg-warning">Pendiente</span>
                                        <?php elseif ($paso['estado'] === 'incompleta'): ?>
                                            <span class="badge text-bg-warning">Incompleta</span>
                                        <?php elseif ($paso['estado'] === 'error'): ?>
                                            <span class="badge text-bg-danger">Error</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">En espera</span>
                                        <?php endif; ?>
                                        <?php if ((string) $paso['aviso'] !== ''): ?>
                                            <div class="small text-secondary mt-1"><?= h((string) $paso['aviso']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= $paso['filas'] === null ? '—' : (int) $paso['filas'] ?></td>
                                    <td class="text-end">
                                        <?php if ($paso['puede'] === true): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="accion" value="paso">
                                                <input type="hidden" name="paso" value="<?= h((string) $paso['id']) ?>">
                                                <button class="btn btn-sm btn-success" type="submit">Importar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
