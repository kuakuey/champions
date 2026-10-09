<?php
/**
 * Funciones comunes del sitio: sesión, CSRF, consultas, tabla,
 * imágenes WebP y el calendario round-robin.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'funciones.php') {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once dirname(__DIR__) . '/config/db.php';

date_default_timezone_set('America/Bogota');

const LIGA_NOMBRE = 'Champions';
const LIGA_COLOR = '#198754';
const LIGA_AMARILLAS = 3;
const LIGA_PUNTOS_VICTORIA = 3;
const LIGA_PUNTOS_EMPATE = 1;
const LIGA_IMAGEN_MAX = 2000000;
const GRUPO_SIN_ASIGNAR = 'Sin asignación';

/**
 * Escapa un valor para insertarlo en HTML.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Arranca la sesión con cookie HttpOnly, SameSite y modo estricto.
 */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
    $ruta = url_raiz();

    session_name('LIGAMUNICIPAL');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $ruta === '' ? '/' : $ruta . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function token_csrf(): string
{
    iniciar_sesion();
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(token_csrf()) . '">';
}

/**
 * Corta la petición si el formulario no trae el token de la sesión.
 */
function verificar_csrf(): void
{
    iniciar_sesion();
    $enviado = $_POST['csrf'] ?? '';
    $guardado = $_SESSION['csrf'] ?? '';
    if (!is_string($enviado) || !is_string($guardado) || $guardado === '' || !hash_equals($guardado, $enviado)) {
        http_response_code(419);
        exit('La sesión del formulario caducó. Vuelve atrás e inténtalo de nuevo.');
    }
}

function usuario_actual(): ?array
{
    iniciar_sesion();
    $usuario = $_SESSION['usuario'] ?? null;
    if (!is_array($usuario) || empty($usuario['id']) || empty($usuario['rol'])) {
        return null;
    }

    return $usuario;
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'admin';
}

function es_planillero(): bool
{
    $rol = usuario_actual()['rol'] ?? '';

    return $rol === 'admin' || $rol === 'planillero';
}

/**
 * Guarda al usuario en sesión y cambia el id para evitar fijación de sesión.
 */
function iniciar_login(array $usuario): void
{
    iniciar_sesion();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int) $usuario['id'],
        'nombre' => (string) $usuario['nombre'],
        'email' => (string) $usuario['email'],
        'rol' => (string) $usuario['rol'],
    ];
    unset($_SESSION['csrf']);
}

function cerrar_sesion(): void
{
    iniciar_sesion();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            (bool) $parametros['secure'],
            (bool) $parametros['httponly']
        );
    }
    session_destroy();
}

function exigir_login(): void
{
    if (usuario_actual() === null) {
        poner_aviso('warning', 'Inicia sesión para continuar.');
        redirigir(url_admin('login.php'));
    }
}

/**
 * Debe llamarse antes de incluir header.php.
 */
function exigir_admin(): void
{
    exigir_login();
    if (es_admin()) {
        return;
    }

    http_response_code(403);
    if (empty($GLOBALS['liga_cabecera_enviada'])) {
        $titulo = 'Acceso restringido';
        $plantilla = 'simple';
        require __DIR__ . '/header.php';
    }
    echo '<div class="alert alert-danger">Esta sección es solo para administradores.</div>';
    require __DIR__ . '/footer.php';
    exit;
}

function poner_aviso(string $tipo, string $mensaje): void
{
    iniciar_sesion();
    $_SESSION['aviso'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_aviso(): ?array
{
    iniciar_sesion();
    if (empty($_SESSION['aviso']) || !is_array($_SESSION['aviso'])) {
        return null;
    }
    $aviso = $_SESSION['aviso'];
    unset($_SESSION['aviso']);

    return $aviso;
}

function avisos_html(): string
{
    $aviso = tomar_aviso();
    if ($aviso === null) {
        return '';
    }
    $permitidos = ['success', 'danger', 'warning', 'info'];
    $tipo = in_array($aviso['tipo'] ?? '', $permitidos, true) ? $aviso['tipo'] : 'info';

    return '<div class="alert alert-' . $tipo . ' alert-dismissible fade show" role="alert">'
        . e($aviso['mensaje'] ?? '')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
}

function url_raiz(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $directorio = rtrim(dirname($script), '/');
    if (preg_match('#/(public|admin)$#', $directorio) === 1) {
        $directorio = dirname($directorio);
    }
    if ($directorio === '/' || $directorio === '.' || $directorio === '') {
        return '';
    }

    return $directorio;
}

function url_public(string $archivo = 'index.php'): string
{
    return url_raiz() . '/' . ltrim($archivo, '/');
}

function url_admin(string $archivo = 'index.php'): string
{
    return url_raiz() . '/admin/' . ltrim($archivo, '/');
}

function url_asset(string $ruta): string
{
    return url_raiz() . '/assets/' . ltrim($ruta, '/');
}

function redirigir(string $url): never
{
    if (preg_match('#^(https?:)?//#i', $url) === 1) {
        $url = url_public('index.php');
    }
    header('Location: ' . $url);
    exit;
}

function abortar(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    if (empty($GLOBALS['liga_cabecera_enviada'])) {
        $titulo = 'Error';
        $pagina = '';
        $plantilla = 'simple';
        require __DIR__ . '/header.php';
    }
    echo '<div class="alert alert-warning">' . e($mensaje) . '</div>';
    echo '<p><a class="btn btn-success" href="' . e(url_public('index.php')) . '">Volver al inicio</a></p>';
    require __DIR__ . '/footer.php';
    exit;
}

function recortar(string $valor, int $maximo): string
{
    $valor = trim($valor);
    if ($maximo < 1) {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($valor) > $maximo) {
        return mb_substr($valor, 0, $maximo);
    }
    if (strlen($valor) > $maximo) {
        return substr($valor, 0, $maximo);
    }

    return $valor;
}

function texto_post(string $campo, int $maximo): string
{
    return recortar((string) ($_POST[$campo] ?? ''), $maximo);
}

function entero_post(string $campo, int $minimo, int $maximo): ?int
{
    if (!isset($_POST[$campo]) || $_POST[$campo] === '') {
        return null;
    }
    $valor = filter_var($_POST[$campo], FILTER_VALIDATE_INT);
    if ($valor === false || $valor < $minimo || $valor > $maximo) {
        return null;
    }

    return $valor;
}

function entero_get(string $campo): int
{
    $valor = filter_input(INPUT_GET, $campo, FILTER_VALIDATE_INT);
    if ($valor === false || $valor === null || $valor < 1) {
        return 0;
    }

    return $valor;
}

function fecha_valida(string $valor): ?string
{
    $valor = trim($valor);
    foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $formato) {
        $fecha = DateTime::createFromFormat('!' . $formato, $valor);
        $errores = DateTime::getLastErrors();
        if (!$fecha instanceof DateTime) {
            continue;
        }
        if (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0)) {
            continue;
        }

        return $fecha->format('Y-m-d H:i:s');
    }

    return null;
}

function fecha_dia(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
    $errores = DateTime::getLastErrors();
    if (!$fecha instanceof DateTime) {
        return null;
    }
    if (is_array($errores) && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0)) {
        return null;
    }

    return $fecha->format('Y-m-d');
}

function clave_orden(string $texto): string
{
    $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
    ];
    $texto = strtr($texto, $mapa);

    return function_exists('mb_strtolower') ? mb_strtolower($texto) : strtolower($texto);
}

function slugify(string $texto): string
{
    $texto = clave_orden($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    $texto = trim($texto, '-');

    return $texto !== '' ? $texto : 'nota';
}

function comparar_nombre(string $a, string $b): int
{
    return strcmp(clave_orden($a), clave_orden($b));
}

function fecha_hora(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $dt = new DateTime($fecha);
    $meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];

    return $dt->format('j') . ' ' . $meses[(int) $dt->format('n')] . ' ' . $dt->format('Y') . ', ' . $dt->format('H:i');
}

function fecha_corta(?string $fecha): string
{
    if ($fecha === null || $fecha === '') {
        return '';
    }
    $dt = new DateTime($fecha);
    $meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];

    return $dt->format('j') . ' ' . $meses[(int) $dt->format('n')] . ' ' . $dt->format('Y');
}

function etiqueta_posicion(string $posicion): string
{
    return match ($posicion) {
        'ARQ' => 'Arquero',
        'DEF' => 'Defensa',
        'MED' => 'Mediocampista',
        'DEL' => 'Delantero',
        default => $posicion,
    };
}

function posicion_valida(string $posicion): bool
{
    return in_array($posicion, ['ARQ', 'DEF', 'MED', 'DEL'], true);
}

function estado_valido(string $estado): bool
{
    return in_array($estado, ['programado', 'jugado', 'suspendido', 'aplazado'], true);
}

function etiqueta_estado(string $estado): string
{
    return match ($estado) {
        'programado' => 'Programado',
        'jugado' => 'Terminado',
        'suspendido' => 'Suspendido',
        'aplazado' => 'Aplazado',
        default => $estado,
    };
}

function html_estado(string $estado): string
{
    $color = match ($estado) {
        'jugado' => 'success',
        'suspendido' => 'danger',
        'aplazado' => 'warning',
        default => 'secondary',
    };

    return '<span class="badge text-bg-' . $color . '">' . e(etiqueta_estado($estado)) . '</span>';
}

function html_forma(array $forma): string
{
    if ($forma === []) {
        return '<span class="text-secondary small">Sin partidos</span>';
    }
    $clases = ['V' => 'forma-v', 'E' => 'forma-e', 'D' => 'forma-d'];
    $titulos = ['V' => 'Victoria', 'E' => 'Empate', 'D' => 'Derrota'];
    $html = '<span class="forma">';
    foreach ($forma as $letra) {
        if (!isset($clases[$letra])) {
            continue;
        }
        $html .= '<span class="badge-forma ' . $clases[$letra] . '" title="' . $titulos[$letra] . '">' . $letra . '</span>';
    }
    $html .= '</span>';

    return $html;
}

function html_escudo(?string $ruta, string $alt, int $tam = 40): string
{
    $tam = max(16, min($tam, 240));
    if ($ruta !== null && $ruta !== '') {
        return '<img class="escudo" src="' . e(url_asset($ruta)) . '" alt="' . e($alt)
            . '" width="' . $tam . '" height="' . $tam . '" loading="lazy">';
    }

    return '<span class="escudo-vacio" style="width:' . $tam . 'px;height:' . $tam . 'px" aria-hidden="true"><i class="bi bi-shield-fill"></i></span>';
}

function html_foto(?string $ruta, string $alt, int $tam = 40): string
{
    $tam = max(16, min($tam, 240));
    if ($ruta !== null && $ruta !== '') {
        return '<img class="escudo" src="' . e(url_asset($ruta)) . '" alt="' . e($alt)
            . '" width="' . $tam . '" height="' . $tam . '" loading="lazy">';
    }

    return '<span class="escudo-vacio" style="width:' . $tam . 'px;height:' . $tam . 'px" aria-hidden="true"><i class="bi bi-person-fill"></i></span>';
}

function marcador_texto(array $partido): string
{
    if (($partido['estado'] ?? '') === 'jugado' && $partido['goles_local'] !== null && $partido['goles_visitante'] !== null) {
        return (int) $partido['goles_local'] . ' - ' . (int) $partido['goles_visitante'];
    }

    return 'vs';
}

function texto_partido(array $partido): string
{
    return LIGA_NOMBRE . ': ' . ($partido['local_nombre'] ?? '') . ' ' . marcador_texto($partido) . ' '
        . ($partido['visita_nombre'] ?? '') . ' (Jornada ' . (int) ($partido['jornada'] ?? 0) . ')';
}

function url_whatsapp(string $texto): string
{
    return 'https://wa.me/?text=' . rawurlencode($texto);
}

function html_whatsapp(array $partido): string
{
    $url = url_whatsapp(texto_partido($partido));

    return '<a class="btn btn-success btn-sm" href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
        . '<i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Compartir</a>';
}

function html_tarjeta_partido(array $partido): string
{
    $url = url_public('partido.php?id=' . (int) $partido['id']);
    $vuelta = (int) ($partido['vuelta'] ?? 0) === 1 ? ' · Vuelta' : '';
    $html = '<article class="card partido-card h-100">';
    $html .= '<div class="card-body d-flex flex-column gap-3">';
    $html .= '<div class="d-flex justify-content-between align-items-center gap-2">';
    $html .= '<span class="small text-secondary">Jornada ' . (int) $partido['jornada'] . $vuelta . '</span>';
    $html .= html_estado((string) $partido['estado']);
    $html .= '</div>';
    $html .= '<a class="text-decoration-none text-body" href="' . e($url) . '">';
    $html .= '<div class="enfrentamiento">';
    $html .= '<div class="equipo-linea">' . html_escudo($partido['local_escudo'] ?? null, (string) $partido['local_nombre'], 36);
    $html .= '<span>' . e($partido['local_nombre']) . '</span></div>';
    $html .= '<div class="marcador">' . e(marcador_texto($partido)) . '</div>';
    $html .= '<div class="equipo-linea justify-content-end text-end">';
    $html .= '<span>' . e($partido['visita_nombre']) . '</span>';
    $html .= html_escudo($partido['visita_escudo'] ?? null, (string) $partido['visita_nombre'], 36) . '</div>';
    $html .= '</div></a>';
    $html .= '<div class="small text-secondary">' . e(fecha_hora((string) $partido['fecha']));
    if (!empty($partido['cancha'])) {
        $html .= ' · ' . e($partido['cancha']);
    }
    $html .= '</div>';
    $html .= '<div class="d-flex flex-wrap gap-2 mt-auto">';
    $html .= '<a class="btn btn-outline-success btn-sm" href="' . e($url) . '">Ver partido</a>';
    $html .= html_whatsapp($partido);
    $html .= '</div></div></article>';

    return $html;
}

function html_texto(string $texto): string
{
    return nl2br(e($texto), false);
}

function item_nav(string $clave, string $href, string $texto, string $icono): void
{
    $pagina = (string) ($GLOBALS['pagina'] ?? '');
    $activo = $pagina === $clave ? ' active' : '';
    $actual = $pagina === $clave ? ' aria-current="page"' : '';
    echo '<li class="nav-item"><a class="nav-link' . $activo . '" href="' . e($href) . '"' . $actual . '>';
    echo '<i class="bi ' . e($icono) . ' me-1" aria-hidden="true"></i>' . e($texto);
    echo '</a></li>';
}

function consultar(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function consultar_uno(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $fila = $stmt->fetch();

    return $fila === false ? null : $fila;
}

function consultar_columna(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function ejecutar(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

/**
 * Ejecuta el callback dentro de una transacción.
 * Si ya hay una abierta, no anida otra.
 */
function transaccion(callable $callback): mixed
{
    $pdo = db();
    $propia = !$pdo->inTransaction();
    if ($propia) {
        $pdo->beginTransaction();
    }
    try {
        $resultado = $callback($pdo);
        if ($propia) {
            $pdo->commit();
        }

        return $resultado;
    } catch (Throwable $e) {
        if ($propia && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function clausula_limite(int $limite, int $maximo = 500): string
{
    if ($limite < 1) {
        return '';
    }

    return ' LIMIT ' . min($limite, $maximo);
}

function sql_partidos(): string
{
    return 'SELECT p.id, p.jornada, p.vuelta, p.local_id, p.visitante_id, p.fecha, p.cancha,
            p.arbitro, p.goles_local, p.goles_visitante, p.estado, p.tipo, p.orden,
            loc.nombre AS local_nombre, loc.nombre_corto AS local_corto, loc.escudo AS local_escudo,
            vis.nombre AS visita_nombre, vis.nombre_corto AS visita_corto, vis.escudo AS visita_escudo
            FROM partidos p
            INNER JOIN equipos loc ON loc.id = p.local_id
            INNER JOIN equipos vis ON vis.id = p.visitante_id';
}

function normalizar_partido(array $partido): array
{
    $partido['id'] = (int) $partido['id'];
    $partido['jornada'] = (int) $partido['jornada'];
    $partido['vuelta'] = (int) $partido['vuelta'];
    $partido['local_id'] = (int) $partido['local_id'];
    $partido['visitante_id'] = (int) $partido['visitante_id'];
    $partido['goles_local'] = $partido['goles_local'] === null ? null : (int) $partido['goles_local'];
    $partido['goles_visitante'] = $partido['goles_visitante'] === null ? null : (int) $partido['goles_visitante'];
    $partido['orden'] = (int) ($partido['orden'] ?? 0);
    if (!isset($partido['tipo']) || !in_array($partido['tipo'], ['clasificatoria', 'eliminatoria'], true)) {
        $partido['tipo'] = 'clasificatoria';
    }

    return $partido;
}

function listar_partidos(array $filtros = []): array
{
    $sql = sql_partidos() . ' WHERE 1 = 1';
    $params = [];

    $jornada = (int) ($filtros['jornada'] ?? 0);
    if ($jornada > 0) {
        $sql .= ' AND p.jornada = :jornada';
        $params['jornada'] = $jornada;
    }

    $equipo = (int) ($filtros['equipo_id'] ?? 0);
    if ($equipo > 0) {
        $sql .= ' AND (p.local_id = :equipo_local OR p.visitante_id = :equipo_visita)';
        $params['equipo_local'] = $equipo;
        $params['equipo_visita'] = $equipo;
    }

    $estado = (string) ($filtros['estado'] ?? '');
    if ($estado !== '' && estado_valido($estado)) {
        $sql .= ' AND p.estado = :estado';
        $params['estado'] = $estado;
    }
    if (!empty($filtros['jugados'])) {
        $sql .= " AND p.estado = 'jugado'";
    }
    if (!empty($filtros['programados'])) {
        $sql .= " AND p.estado = 'programado'";
    }
    if (!empty($filtros['pendientes'])) {
        $sql .= " AND p.estado <> 'jugado'";
    }

    $orden = (($filtros['orden'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
    $sql .= ' ORDER BY p.orden ' . $orden . ', p.id ' . $orden;
    $sql .= clausula_limite((int) ($filtros['limite'] ?? 0));

    return array_map('normalizar_partido', consultar($sql, $params));
}

function obtener_partido(int $id): ?array
{
    if ($id < 1) {
        return null;
    }
    $fila = consultar_uno(sql_partidos() . ' WHERE p.id = :id', ['id' => $id]);

    return $fila === null ? null : normalizar_partido($fila);
}

function crear_partido(int $localId, int $visitaId, string $tipo): int
{
    if ($localId < 1 || $visitaId < 1 || $localId === $visitaId) {
        throw new RuntimeException('Elige dos equipos distintos.');
    }
    if (obtener_equipo($localId) === null || obtener_equipo($visitaId) === null) {
        throw new RuntimeException('Elige dos equipos.');
    }
    if (!in_array($tipo, ['clasificatoria', 'eliminatoria'], true)) {
        throw new RuntimeException('Elige el tipo de partido.');
    }
    $siguiente = consultar_uno('SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM partidos');

    ejecutar(
        'INSERT INTO partidos (jornada, local_id, visitante_id, fecha, estado, tipo, orden)
         VALUES (1, :local, :visita, NULL, \'programado\', :tipo, :orden)',
        [
            'local' => $localId,
            'visita' => $visitaId,
            'tipo' => $tipo,
            'orden' => (int) ($siguiente['siguiente'] ?? 1),
        ]
    );

    return (int) db()->lastInsertId();
}

function asegurar_cuadro(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS cuadro_puesto (
            puesto TINYINT UNSIGNED NOT NULL,
            equipo_id INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (puesto),
            CONSTRAINT fk_cuadro_puesto_equipo
                FOREIGN KEY (equipo_id) REFERENCES equipos (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    db()->exec(
        'CREATE TABLE IF NOT EXISTS cuadro_llave (
            llave TINYINT UNSIGNED NOT NULL,
            partido_id INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (llave),
            CONSTRAINT fk_cuadro_llave_partido
                FOREIGN KEY (partido_id) REFERENCES partidos (id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    for ($puesto = 1; $puesto <= 8; $puesto++) {
        ejecutar('INSERT IGNORE INTO cuadro_puesto (puesto) VALUES (:puesto)', ['puesto' => $puesto]);
    }
    for ($llave = 1; $llave <= 7; $llave++) {
        ejecutar('INSERT IGNORE INTO cuadro_llave (llave) VALUES (:llave)', ['llave' => $llave]);
    }
}

/**
 * @return array<int, int|null>
 */
function puestos_cuadro(): array
{
    asegurar_cuadro();
    $puestos = array_fill(1, 8, null);
    foreach (consultar('SELECT puesto, equipo_id FROM cuadro_puesto') as $fila) {
        $puestos[(int) $fila['puesto']] = $fila['equipo_id'] === null ? null : (int) $fila['equipo_id'];
    }

    return $puestos;
}

function ganador_partido(array $partido): ?int
{
    if ((string) ($partido['estado'] ?? '') !== 'jugado') {
        return null;
    }
    $local = $partido['goles_local'];
    $visita = $partido['goles_visitante'];
    if ($local === null || $visita === null || (int) $local === (int) $visita) {
        return null;
    }

    return (int) $local > (int) $visita ? (int) $partido['local_id'] : (int) $partido['visitante_id'];
}

/**
 * @return array<int, array<string, mixed>|null>
 */
function partidos_cuadro(): array
{
    asegurar_cuadro();
    $partidos = array_fill(1, 7, null);
    $filas = consultar(
        'SELECT llave, partido_id FROM cuadro_llave ORDER BY llave ASC'
    );
    foreach ($filas as $fila) {
        $id = $fila['partido_id'] === null ? 0 : (int) $fila['partido_id'];
        $partidos[(int) $fila['llave']] = $id > 0 ? obtener_partido($id) : null;
    }

    return $partidos;
}

/**
 * Los dos primeros de cada grupo, en el orden de la tabla.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function clasificados_por_grupo(): array
{
    $porGrupo = [];
    foreach (tabla_posiciones() as $fila) {
        $nombre = (string) ($fila['grupo'] ?? '');
        if ($nombre === '' || $nombre === GRUPO_SIN_ASIGNAR || (int) $fila['posicion'] > 2) {
            continue;
        }
        $porGrupo[$nombre][] = $fila;
    }
    $nombres = [];
    foreach (listar_grupos() as $grupo) {
        $nombre = (string) $grupo['nombre'];
        if ($nombre !== '' && $nombre !== GRUPO_SIN_ASIGNAR) {
            $nombres[] = $nombre;
        }
    }
    natcasesort($nombres);
    $salida = [];
    foreach ($nombres as $nombre) {
        $salida[$nombre] = $porGrupo[$nombre] ?? [];
    }

    return $salida;
}

function colocar_llave(int $llave, ?int $localId, ?int $visitaId): void
{
    $fila = consultar_uno('SELECT partido_id FROM cuadro_llave WHERE llave = :llave', ['llave' => $llave]);
    $partidoId = $fila === null || $fila['partido_id'] === null ? 0 : (int) $fila['partido_id'];
    $partido = $partidoId > 0 ? obtener_partido($partidoId) : null;
    $incompleto = $localId === null || $visitaId === null || $localId < 1 || $visitaId < 1 || $localId === $visitaId;
    if ($incompleto) {
        if ($partido !== null && (string) $partido['estado'] !== 'jugado') {
            ejecutar('UPDATE cuadro_llave SET partido_id = NULL WHERE llave = :llave', ['llave' => $llave]);
            ejecutar('DELETE FROM partidos WHERE id = :id AND estado <> \'jugado\'', ['id' => $partidoId]);
        }

        return;
    }
    if ($partido === null) {
        $nuevo = crear_partido($localId, $visitaId, 'eliminatoria');
        ejecutar(
            'UPDATE cuadro_llave SET partido_id = :partido WHERE llave = :llave',
            ['partido' => $nuevo, 'llave' => $llave]
        );

        return;
    }
    if ((string) $partido['estado'] === 'jugado') {
        return;
    }
    if ((int) $partido['local_id'] === $localId && (int) $partido['visitante_id'] === $visitaId) {
        return;
    }
    ejecutar(
        'UPDATE partidos SET local_id = :local, visitante_id = :visita WHERE id = :id',
        ['local' => $localId, 'visita' => $visitaId, 'id' => (int) $partido['id']]
    );
}

function sincronizar_cuadro(): void
{
    asegurar_cuadro();
    $puestos = puestos_cuadro();
    colocar_llave(1, $puestos[1], $puestos[2]);
    colocar_llave(2, $puestos[3], $puestos[4]);
    colocar_llave(3, $puestos[5], $puestos[6]);
    colocar_llave(4, $puestos[7], $puestos[8]);

    $partidos = partidos_cuadro();
    $ganador = static function (int $llave) use ($partidos): ?int {
        $partido = $partidos[$llave] ?? null;

        return is_array($partido) ? ganador_partido($partido) : null;
    };
    colocar_llave(5, $ganador(1), $ganador(2));
    colocar_llave(6, $ganador(3), $ganador(4));
    $partidos = partidos_cuadro();
    $semi = static function (int $llave) use ($partidos): ?int {
        $partido = $partidos[$llave] ?? null;

        return is_array($partido) ? ganador_partido($partido) : null;
    };
    colocar_llave(7, $semi(5), $semi(6));
}

/**
 * @param array<int, int> $puestos
 */
function guardar_puestos_cuadro(array $puestos): void
{
    asegurar_cuadro();
    if (count($puestos) !== 8) {
        throw new RuntimeException('La final es de 8 equipos.');
    }
    $ids = [];
    foreach ($puestos as $puesto => $equipoId) {
        if ($puesto < 1 || $puesto > 8 || $equipoId < 1 || obtener_equipo($equipoId) === null) {
            throw new RuntimeException('Elige los 8 equipos de la final.');
        }
        if (in_array($equipoId, $ids, true)) {
            throw new RuntimeException('Cada equipo entra una sola vez en la final.');
        }
        $ids[] = $equipoId;
    }

    $actuales = puestos_cuadro();
    $partidos = partidos_cuadro();
    $cruces = [[1, 1, 2], [2, 3, 4], [3, 5, 6], [4, 7, 8]];
    foreach ($cruces as [$llave, $izquierda, $derecha]) {
        $partido = $partidos[$llave] ?? null;
        if (!is_array($partido) || (string) $partido['estado'] !== 'jugado') {
            continue;
        }
        if ($actuales[$izquierda] !== $puestos[$izquierda] || $actuales[$derecha] !== $puestos[$derecha]) {
            throw new RuntimeException('Ese cruce ya se jugó.');
        }
    }

    foreach ($puestos as $puesto => $equipoId) {
        ejecutar(
            'UPDATE cuadro_puesto SET equipo_id = :equipo WHERE puesto = :puesto',
            ['equipo' => $equipoId, 'puesto' => $puesto]
        );
    }
    sincronizar_cuadro();
}

function mover_equipo_cuadro(int $equipoId, int $puesto): void
{
    asegurar_cuadro();
    if ($puesto < 0 || $puesto > 8 || obtener_equipo($equipoId) === null) {
        throw new RuntimeException('No encontramos ese equipo.');
    }
    $puestos = puestos_cuadro();
    $partidos = partidos_cuadro();
    $cerrada = static function (int $numero) use ($partidos): bool {
        if ($numero < 1) {
            return false;
        }
        $partido = $partidos[(int) ceil($numero / 2)] ?? null;

        return is_array($partido) && (string) $partido['estado'] === 'jugado';
    };
    $actual = 0;
    foreach ($puestos as $numero => $id) {
        if ($id === $equipoId) {
            $actual = (int) $numero;
        }
    }
    if ($actual === $puesto) {
        return;
    }
    if (($actual > 0 && $cerrada($actual)) || ($puesto > 0 && $cerrada($puesto))) {
        throw new RuntimeException('Ese cruce ya se jugó.');
    }
    if ($puesto > 0 && $actual === 0) {
        $permitido = false;
        foreach (clasificados_por_grupo() as $filas) {
            foreach ($filas as $fila) {
                if ((int) $fila['equipo_id'] === $equipoId) {
                    $permitido = true;
                }
            }
        }
        if (!$permitido) {
            throw new RuntimeException('Solo entran los dos primeros de cada grupo.');
        }
    }
    if ($actual > 0) {
        ejecutar('UPDATE cuadro_puesto SET equipo_id = NULL WHERE puesto = :puesto', ['puesto' => $actual]);
    }
    if ($puesto > 0) {
        ejecutar(
            'UPDATE cuadro_puesto SET equipo_id = :equipo WHERE puesto = :puesto',
            ['equipo' => $equipoId, 'puesto' => $puesto]
        );
    }
    sincronizar_cuadro();
}

function guardar_orden_partidos(array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    $orden = 1;
    foreach ($ids as $id) {
        if (obtener_partido($id) === null) {
            throw new RuntimeException('No encontramos ese partido.');
        }
        ejecutar(
            'UPDATE partidos SET orden = :orden WHERE id = :id',
            ['orden' => $orden, 'id' => $id]
        );
        $orden++;
    }
}

function quitar_partido(int $partidoId): void
{
    if (obtener_partido($partidoId) === null) {
        throw new RuntimeException('No encontramos ese partido.');
    }
    ejecutar('DELETE FROM partidos WHERE id = :id', ['id' => $partidoId]);
}

function eventos_partido(int $partidoId): array
{
    return consultar(
        'SELECT ev.id, ev.partido_id, ev.jugador_id, ev.equipo_id, ev.tipo, ev.minuto,
                j.nombre, j.apellido, j.dorsal, e.nombre AS equipo, e.nombre_corto
         FROM eventos ev
         LEFT JOIN jugadores j ON j.id = ev.jugador_id
         INNER JOIN equipos e ON e.id = ev.equipo_id
         WHERE ev.partido_id = :partido
         ORDER BY ev.minuto ASC, ev.id ASC',
        ['partido' => $partidoId]
    );
}

function proximos_partidos(int $limite = 4): array
{
    return listar_partidos([
        'programados' => true,
        'orden' => 'asc',
        'limite' => $limite,
    ]);
}

function ultimos_resultados(int $limite = 4): array
{
    return listar_partidos([
        'jugados' => true,
        'orden' => 'desc',
        'limite' => $limite,
    ]);
}

function partidos_equipo(int $equipoId): array
{
    return listar_partidos([
        'equipo_id' => $equipoId,
        'orden' => 'asc',
    ]);
}

function listar_jornadas(): array
{
    return array_map('intval', consultar_columna('SELECT DISTINCT jornada FROM partidos ORDER BY jornada ASC'));
}

function listar_equipos(bool $soloActivos = true): array
{
    $sql = 'SELECT e.id, e.nombre, e.nombre_corto, e.grupo_id, e.orden, g.nombre AS grupo,
                   e.escudo, e.dt, e.ciudad, e.fundacion, e.colores, e.activo
            FROM equipos e
            LEFT JOIN grupos g ON g.id = e.grupo_id';
    if ($soloActivos) {
        $sql .= ' WHERE e.activo = 1';
    }
    $sql .= ' ORDER BY g.nombre ASC, e.orden ASC, e.nombre ASC';

    return consultar($sql);
}

function obtener_equipo(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    return consultar_uno(
        'SELECT e.id, e.nombre, e.nombre_corto, e.grupo_id, e.orden, g.nombre AS grupo,
                e.escudo, e.dt, e.ciudad, e.fundacion, e.colores, e.activo
         FROM equipos e
         LEFT JOIN grupos g ON g.id = e.grupo_id
         WHERE e.id = :id',
        ['id' => $id]
    );
}

function obtener_jugador(int $id): ?array
{
    if ($id < 1) {
        return null;
    }

    return consultar_uno(
        'SELECT j.id, j.equipo_id, j.nombre, j.apellido, j.dorsal, j.posicion, j.foto,
                j.fecha_nacimiento, j.activo, e.nombre AS equipo, e.nombre_corto
         FROM jugadores j
         INNER JOIN equipos e ON e.id = j.equipo_id
         WHERE j.id = :id',
        ['id' => $id]
    );
}

function listar_jugadores(?int $equipoId = null, bool $soloActivos = true): array
{
    $sql = 'SELECT j.id, j.equipo_id, j.nombre, j.apellido, j.dorsal, j.posicion, j.foto,
                   j.fecha_nacimiento, j.activo, e.nombre AS equipo, e.nombre_corto, e.escudo
            FROM jugadores j
            INNER JOIN equipos e ON e.id = j.equipo_id
            WHERE 1 = 1';
    $params = [];
    if ($equipoId !== null && $equipoId > 0) {
        $sql .= ' AND j.equipo_id = :equipo';
        $params['equipo'] = $equipoId;
    }
    if ($soloActivos) {
        $sql .= ' AND j.activo = 1';
    }
    $sql .= ' ORDER BY e.nombre ASC, j.apellido ASC, j.nombre ASC, j.dorsal ASC';

    return consultar($sql, $params);
}

function plantel(int $equipoId, bool $soloActivos = true): array
{
    $sql = 'SELECT j.id, j.nombre, j.apellido, j.dorsal, j.posicion, j.foto, j.fecha_nacimiento, j.activo,
                   COALESCE(SUM(CASE WHEN ev.tipo = \'gol\' THEN 1 ELSE 0 END), 0) AS goles,
                   COALESCE(SUM(CASE WHEN ev.tipo = \'asistencia\' THEN 1 ELSE 0 END), 0) AS asistencias,
                   COALESCE(SUM(CASE WHEN ev.tipo = \'amarilla\' THEN 1 ELSE 0 END), 0) AS amarillas,
                   COALESCE(SUM(CASE WHEN ev.tipo = \'roja\' THEN 1 ELSE 0 END), 0) AS rojas
            FROM jugadores j
            LEFT JOIN eventos ev ON ev.jugador_id = j.id
            WHERE j.equipo_id = :equipo';
    if ($soloActivos) {
        $sql .= ' AND j.activo = 1';
    }
    $sql .= ' GROUP BY j.id, j.nombre, j.apellido, j.dorsal, j.posicion, j.foto, j.fecha_nacimiento, j.activo';
    $sql .= " ORDER BY FIELD(j.posicion, 'ARQ', 'DEF', 'MED', 'DEL'), j.dorsal ASC, j.apellido ASC";
    $filas = consultar($sql, ['equipo' => $equipoId]);
    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
        $fila['goles'] = (int) $fila['goles'];
        $fila['asistencias'] = (int) $fila['asistencias'];
        $fila['amarillas'] = (int) $fila['amarillas'];
        $fila['rojas'] = (int) $fila['rojas'];
        $fila['dorsal'] = $fila['dorsal'] === null ? null : (int) $fila['dorsal'];
    }
    unset($fila);

    return $filas;
}

function normalizar_fila_tabla(array $fila): array
{
    foreach (['equipo_id', 'pj', 'pg', 'pe', 'pp', 'gf', 'gc', 'dg', 'pts'] as $campo) {
        $fila[$campo] = (int) $fila[$campo];
    }
    if (array_key_exists('orden', $fila)) {
        $fila['orden'] = (int) $fila['orden'];
    }

    return $fila;
}

function forma_desde_partidos(array $partidos, int $equipoId, int $cantidad = 5): array
{
    $forma = [];
    foreach ($partidos as $partido) {
        $local = (int) $partido['local_id'];
        $visita = (int) $partido['visitante_id'];
        if ($local !== $equipoId && $visita !== $equipoId) {
            continue;
        }
        $golesLocal = (int) $partido['goles_local'];
        $golesVisita = (int) $partido['goles_visitante'];
        if ($local === $equipoId) {
            $forma[] = $golesLocal > $golesVisita ? 'V' : ($golesLocal === $golesVisita ? 'E' : 'D');
        } else {
            $forma[] = $golesVisita > $golesLocal ? 'V' : ($golesVisita === $golesLocal ? 'E' : 'D');
        }
    }

    return array_slice($forma, -$cantidad);
}

/**
 * Entre equipos iguales en puntos, diferencia y goles a favor,
 * manda el enfrentamiento directo (puntos, diferencia y goles de esos duelos).
 */
function ordenar_por_enfrentamiento(array $grupo, array $partidos): array
{
    $ids = [];
    foreach ($grupo as $fila) {
        $ids[(int) $fila['equipo_id']] = true;
    }
    $mini = [];
    foreach ($ids as $id => $_) {
        $mini[$id] = ['pts' => 0, 'dg' => 0, 'gf' => 0];
    }
    foreach ($partidos as $partido) {
        $local = (int) $partido['local_id'];
        $visita = (int) $partido['visitante_id'];
        if (!isset($ids[$local], $ids[$visita])) {
            continue;
        }
        $golesLocal = (int) $partido['goles_local'];
        $golesVisita = (int) $partido['goles_visitante'];
        $mini[$local]['gf'] += $golesLocal;
        $mini[$local]['dg'] += $golesLocal - $golesVisita;
        $mini[$visita]['gf'] += $golesVisita;
        $mini[$visita]['dg'] += $golesVisita - $golesLocal;
        if ($golesLocal > $golesVisita) {
            $mini[$local]['pts'] += LIGA_PUNTOS_VICTORIA;
        } elseif ($golesLocal < $golesVisita) {
            $mini[$visita]['pts'] += LIGA_PUNTOS_VICTORIA;
        } else {
            $mini[$local]['pts'] += LIGA_PUNTOS_EMPATE;
            $mini[$visita]['pts'] += LIGA_PUNTOS_EMPATE;
        }
    }

    usort($grupo, static function (array $a, array $b) use ($mini): int {
        $sa = $mini[(int) $a['equipo_id']];
        $sb = $mini[(int) $b['equipo_id']];
        foreach (['pts', 'dg', 'gf'] as $campo) {
            if ($sa[$campo] !== $sb[$campo]) {
                return $sb[$campo] <=> $sa[$campo];
            }
        }

        $porOrden = ((int) ($a['orden'] ?? 0)) <=> ((int) ($b['orden'] ?? 0));
        if ($porOrden !== 0) {
            return $porOrden;
        }

        return comparar_nombre((string) $a['nombre'], (string) $b['nombre']);
    });

    return $grupo;
}

function desempatar_tabla(array $filas, array $partidos): array
{
    $grupos = [];
    $actual = [];
    $clave = null;
    foreach ($filas as $fila) {
        $nueva = $fila['pts'] . '|' . $fila['dg'] . '|' . $fila['gf'];
        if ($clave !== null && $nueva !== $clave) {
            $grupos[] = $actual;
            $actual = [];
        }
        $clave = $nueva;
        $actual[] = $fila;
    }
    if ($actual !== []) {
        $grupos[] = $actual;
    }

    $salida = [];
    foreach ($grupos as $grupo) {
        if (count($grupo) > 1) {
            $grupo = ordenar_por_enfrentamiento($grupo, $partidos);
        }
        foreach ($grupo as $fila) {
            $salida[] = $fila;
        }
    }

    return $salida;
}

/**
 * Tabla calculada con la vista v_tabla. No se guarda en una tabla física.
 * Orden: puntos, diferencia de gol, goles a favor y, si siguen iguales, el orden guardado del grupo.
 */
function tabla_posiciones(): array
{
    $filas = consultar(
        'SELECT equipo_id, nombre, nombre_corto, escudo, grupo_id, grupo, orden, pj, pg, pe, pp, gf, gc, dg, pts
         FROM v_tabla
         ORDER BY grupo ASC, pts DESC, dg DESC, gf DESC, orden ASC, nombre ASC'
    );
    $partidos = consultar(
        "SELECT id, local_id, visitante_id, goles_local, goles_visitante, fecha
         FROM partidos
         WHERE estado = 'jugado' AND tipo = 'clasificatoria'
         ORDER BY fecha ASC, id ASC"
    );
    $porGrupo = [];
    foreach ($filas as $fila) {
        $fila = normalizar_fila_tabla($fila);
        $fila['forma'] = forma_desde_partidos($partidos, $fila['equipo_id']);
        $clave = (string) ($fila['grupo'] ?? '');
        $porGrupo[$clave][] = $fila;
    }
    ksort($porGrupo, SORT_NATURAL | SORT_FLAG_CASE);
    $salida = [];
    foreach ($porGrupo as $filasGrupo) {
        $ordenadas = desempatar_tabla($filasGrupo, $partidos);
        $posicion = 1;
        foreach ($ordenadas as $fila) {
            $fila['posicion'] = $posicion;
            $posicion++;
            $salida[] = $fila;
        }
    }

    return $salida;
}

function estadisticas_equipo(int $equipoId): ?array
{
    $fila = consultar_uno(
        'SELECT equipo_id, nombre, nombre_corto, escudo, pj, pg, pe, pp, gf, gc, dg, pts
         FROM v_tabla WHERE equipo_id = :id',
        ['id' => $equipoId]
    );
    if ($fila === null) {
        return null;
    }
    $fila = normalizar_fila_tabla($fila);
    $partidos = consultar(
        "SELECT local_id, visitante_id, goles_local, goles_visitante
         FROM partidos
         WHERE estado = 'jugado' AND tipo = 'clasificatoria' AND (local_id = :local OR visitante_id = :visita)
         ORDER BY fecha ASC, id ASC",
        ['local' => $equipoId, 'visita' => $equipoId]
    );
    $fila['forma'] = forma_desde_partidos($partidos, $equipoId);

    return $fila;
}

function ranking(string $vista, string $columna, int $limite = 0): array
{
    if (!in_array($vista, ['v_goleadores', 'v_asistidores'], true)) {
        return [];
    }
    if (!in_array($columna, ['goles', 'asistencias'], true)) {
        return [];
    }
    $sql = 'SELECT jugador_id, nombre, apellido, dorsal, equipo_id, equipo, nombre_corto, escudo, '
        . $columna . ' AS total FROM ' . $vista . ' ORDER BY ' . $columna . ' DESC, apellido ASC, nombre ASC'
        . clausula_limite($limite);
    $filas = consultar($sql);
    foreach ($filas as &$fila) {
        $fila['jugador_id'] = (int) $fila['jugador_id'];
        $fila['equipo_id'] = (int) $fila['equipo_id'];
        $fila['total'] = (int) $fila['total'];
        $fila['dorsal'] = $fila['dorsal'] === null ? null : (int) $fila['dorsal'];
    }
    unset($fila);

    return $filas;
}

function goleadores(int $limite = 0): array
{
    return ranking('v_goleadores', 'goles', $limite);
}

function asistidores(int $limite = 0): array
{
    return ranking('v_asistidores', 'asistencias', $limite);
}

/**
 * Menos puntos es mejor fair play: cada amarilla suma 1 y cada roja suma 3.
 */
function fairplay_equipos(): array
{
    $filas = consultar(
        "SELECT e.id AS equipo_id, e.nombre, e.nombre_corto, e.escudo,
                COALESCE(SUM(CASE WHEN ev.tipo = 'amarilla' THEN 1 ELSE 0 END), 0) AS amarillas,
                COALESCE(SUM(CASE WHEN ev.tipo = 'roja' THEN 1 ELSE 0 END), 0) AS rojas
         FROM equipos e
         LEFT JOIN eventos ev ON ev.equipo_id = e.id AND ev.tipo IN ('amarilla', 'roja')
         WHERE e.activo = 1
         GROUP BY e.id, e.nombre, e.nombre_corto, e.escudo"
    );
    foreach ($filas as &$fila) {
        $fila['equipo_id'] = (int) $fila['equipo_id'];
        $fila['amarillas'] = (int) $fila['amarillas'];
        $fila['rojas'] = (int) $fila['rojas'];
        $fila['puntos'] = $fila['amarillas'] + ($fila['rojas'] * 3);
    }
    unset($fila);
    usort($filas, static function (array $a, array $b): int {
        if ($a['puntos'] !== $b['puntos']) {
            return $a['puntos'] <=> $b['puntos'];
        }

        return comparar_nombre((string) $a['nombre'], (string) $b['nombre']);
    });

    return $filas;
}

function fairplay_jugadores(): array
{
    $filas = consultar(
        "SELECT j.id AS jugador_id, j.nombre, j.apellido, j.dorsal, e.nombre AS equipo, e.nombre_corto,
                SUM(CASE WHEN ev.tipo = 'amarilla' THEN 1 ELSE 0 END) AS amarillas,
                SUM(CASE WHEN ev.tipo = 'roja' THEN 1 ELSE 0 END) AS rojas
         FROM eventos ev
         INNER JOIN jugadores j ON j.id = ev.jugador_id
         INNER JOIN equipos e ON e.id = j.equipo_id
         WHERE ev.tipo IN ('amarilla', 'roja')
         GROUP BY j.id, j.nombre, j.apellido, j.dorsal, e.nombre, e.nombre_corto
         ORDER BY rojas DESC, amarillas DESC, j.apellido ASC"
    );
    foreach ($filas as &$fila) {
        $fila['jugador_id'] = (int) $fila['jugador_id'];
        $fila['amarillas'] = (int) $fila['amarillas'];
        $fila['rojas'] = (int) $fila['rojas'];
        $fila['dorsal'] = $fila['dorsal'] === null ? null : (int) $fila['dorsal'];
    }
    unset($fila);

    return $filas;
}

function suspendidos(): array
{
    return consultar(
        'SELECT s.id, s.tipo, s.partidos_suspension, s.partidos_cumplidos, s.motivo, s.creado_en,
                j.id AS jugador_id, j.nombre, j.apellido, j.dorsal,
                e.id AS equipo_id, e.nombre AS equipo, e.nombre_corto
         FROM sanciones s
         INNER JOIN jugadores j ON j.id = s.jugador_id
         INNER JOIN equipos e ON e.id = j.equipo_id
         WHERE s.activa = 1 AND s.partidos_cumplidos < s.partidos_suspension
         ORDER BY e.nombre ASC, j.apellido ASC'
    );
}

function listar_noticias(bool $soloPublicadas = true, int $limite = 0): array
{
    $sql = 'SELECT id, titulo, slug, resumen, cuerpo, imagen, publicada, creado_en FROM noticias';
    if ($soloPublicadas) {
        $sql .= ' WHERE publicada = 1';
    }
    $sql .= ' ORDER BY creado_en DESC, id DESC';
    $sql .= clausula_limite($limite);

    return consultar($sql);
}

function obtener_noticia(?int $id = null, ?string $slug = null, bool $soloPublicadas = true): ?array
{
    if ($id !== null && $id > 0) {
        $sql = 'SELECT id, titulo, slug, resumen, cuerpo, imagen, publicada, creado_en FROM noticias WHERE id = :id';
        $params = ['id' => $id];
    } elseif ($slug !== null && $slug !== '') {
        $sql = 'SELECT id, titulo, slug, resumen, cuerpo, imagen, publicada, creado_en FROM noticias WHERE slug = :slug';
        $params = ['slug' => $slug];
    } else {
        return null;
    }
    if ($soloPublicadas) {
        $sql .= ' AND publicada = 1';
    }

    return consultar_uno($sql, $params);
}

function buscar_usuario_email(string $email): ?array
{
    return consultar_uno(
        'SELECT id, nombre, email, password_hash, rol, activo FROM usuarios WHERE email = :email LIMIT 1',
        ['email' => $email]
    );
}

function total_amarillas(int $jugadorId): int
{
    $fila = consultar_uno(
        "SELECT COUNT(*) AS total FROM eventos WHERE jugador_id = :id AND tipo = 'amarilla'",
        ['id' => $jugadorId]
    );

    return (int) ($fila['total'] ?? 0);
}

function ciclo_amarillas(int $total): bool
{
    return $total > 0 && $total % LIGA_AMARILLAS === 0;
}

function borrar_sanciones_partido(int $partidoId): void
{
    ejecutar(
        "DELETE FROM sanciones WHERE partido_origen_id = :partido AND tipo IN ('amarillas', 'roja')",
        ['partido' => $partidoId]
    );
}

function registrar_sancion_automatica(int $jugadorId, int $partidoId, string $tipo): void
{
    if ($tipo !== 'amarillas' && $tipo !== 'roja') {
        return;
    }
    $existe = consultar_uno(
        'SELECT id FROM sanciones
         WHERE jugador_id = :jugador AND partido_origen_id = :partido AND tipo = :tipo
         LIMIT 1',
        ['jugador' => $jugadorId, 'partido' => $partidoId, 'tipo' => $tipo]
    );
    if ($existe !== null) {
        return;
    }
    $motivo = $tipo === 'roja'
        ? 'Roja directa'
        : 'Acumulación de ' . LIGA_AMARILLAS . ' tarjetas amarillas';
    ejecutar(
        'INSERT INTO sanciones (jugador_id, partido_origen_id, tipo, partidos_suspension, partidos_cumplidos, motivo, activa)
         VALUES (:jugador, :partido, :tipo, 1, 0, :motivo, 1)',
        [
            'jugador' => $jugadorId,
            'partido' => $partidoId,
            'tipo' => $tipo,
            'motivo' => $motivo,
        ]
    );
}

/**
 * Vuelve a contar cuántos partidos del equipo ya se jugaron después de la falta.
 * Se puede llamar varias veces sin duplicar el castigo.
 */
function recalcular_sanciones(): void
{
    transaccion(static function (): void {
        $sanciones = consultar(
            'SELECT s.id, s.partidos_suspension, s.partido_origen_id, p.fecha AS origen_fecha, j.equipo_id
             FROM sanciones s
             INNER JOIN jugadores j ON j.id = s.jugador_id
             LEFT JOIN partidos p ON p.id = s.partido_origen_id
             WHERE s.partido_origen_id IS NOT NULL'
        );
        foreach ($sanciones as $sancion) {
            if ($sancion['origen_fecha'] === null) {
                continue;
            }
            $fila = consultar_uno(
                "SELECT COUNT(*) AS total
                 FROM partidos
                 WHERE estado = 'jugado'
                   AND (local_id = :equipo_local OR visitante_id = :equipo_visita)
                   AND (fecha > :fecha OR (fecha = :fecha_misma AND id > :partido))",
                [
                    'equipo_local' => (int) $sancion['equipo_id'],
                    'equipo_visita' => (int) $sancion['equipo_id'],
                    'fecha' => $sancion['origen_fecha'],
                    'fecha_misma' => $sancion['origen_fecha'],
                    'partido' => (int) $sancion['partido_origen_id'],
                ]
            );
            $suspension = (int) $sancion['partidos_suspension'];
            $cumplidos = min($suspension, (int) ($fila['total'] ?? 0));
            ejecutar(
                'UPDATE sanciones SET partidos_cumplidos = :cumplidos, activa = :activa WHERE id = :id',
                [
                    'cumplidos' => $cumplidos,
                    'activa' => $cumplidos < $suspension ? 1 : 0,
                    'id' => (int) $sancion['id'],
                ]
            );
        }
    });
}

function texto_limpio(string $valor, int $maximo, string $etiqueta): string
{
    $valor = trim($valor);
    if ($valor === '' || recortar($valor, $maximo) !== $valor) {
        throw new RuntimeException($etiqueta . ' debe tener entre 1 y ' . $maximo . ' caracteres.');
    }

    return $valor;
}

function texto_opcional_limpio(?string $valor, int $maximo, string $etiqueta): ?string
{
    if ($valor === null) {
        return null;
    }
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }
    if (recortar($valor, $maximo) !== $valor) {
        throw new RuntimeException($etiqueta . ' admite como máximo ' . $maximo . ' caracteres.');
    }

    return $valor;
}

function asegurar_grupo(string $nombre): int
{
    $nombre = texto_limpio($nombre, 20, 'El grupo');
    $existente = consultar_uno(
        'SELECT id FROM grupos WHERE nombre = :nombre LIMIT 1',
        ['nombre' => $nombre]
    );
    if ($existente !== null) {
        return (int) $existente['id'];
    }

    ejecutar('INSERT INTO grupos (nombre) VALUES (:nombre)', ['nombre' => $nombre]);

    return (int) db()->lastInsertId();
}

function listar_grupos(): array
{
    return consultar('SELECT id, nombre FROM grupos ORDER BY nombre ASC');
}

/**
 * Deja listos Sin asignación y GRUPO 1 a GRUPO 4.
 */
function asegurar_grupos_base(int $cantidad = 4): void
{
    asegurar_grupo(GRUPO_SIN_ASIGNAR);
    $cantidad = max(1, min($cantidad, 24));
    for ($numero = 1; $numero <= $cantidad; $numero++) {
        asegurar_grupo('GRUPO ' . $numero);
    }
}

/**
 * Crea el siguiente GRUPO N y devuelve su nombre.
 */
function agregar_grupo(): string
{
    $maximo = 0;
    foreach (listar_grupos() as $fila) {
        if (preg_match('/^GRUPO\s+(\d+)$/i', (string) $fila['nombre'], $coincidencia) === 1) {
            $maximo = max($maximo, (int) $coincidencia[1]);
        }
    }
    $siguiente = $maximo + 1;
    if ($siguiente > 24) {
        throw new RuntimeException('Ya no se pueden agregar más grupos.');
    }
    $nombre = 'GRUPO ' . $siguiente;
    asegurar_grupo($nombre);

    return $nombre;
}

function crear_equipo(string $nombre, string $corto, string $grupo): int
{
    $nombre = texto_limpio($nombre, 120, 'El nombre del equipo');
    $corto = texto_limpio($corto, 10, 'La sigla');
    if (consultar_uno('SELECT id FROM equipos WHERE nombre = :nombre LIMIT 1', ['nombre' => $nombre]) !== null) {
        throw new RuntimeException('Ya hay un equipo con ese nombre.');
    }
    if (consultar_uno('SELECT id FROM equipos WHERE nombre_corto = :corto LIMIT 1', ['corto' => $corto]) !== null) {
        throw new RuntimeException('Ya hay un equipo con esa sigla.');
    }
    $grupoId = asegurar_grupo($grupo);
    $siguiente = consultar_uno(
        'SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM equipos WHERE grupo_id = :grupo',
        ['grupo' => $grupoId]
    );

    ejecutar(
        'INSERT INTO equipos (nombre, nombre_corto, grupo_id, orden) VALUES (:nombre, :corto, :grupo, :orden)',
        [
            'nombre' => $nombre,
            'corto' => $corto,
            'grupo' => $grupoId,
            'orden' => (int) ($siguiente['siguiente'] ?? 1),
        ]
    );

    return (int) db()->lastInsertId();
}

function actualizar_equipo(int $equipoId, string $nombre, string $corto, string $grupo): void
{
    $actual = obtener_equipo($equipoId);
    if ($actual === null) {
        throw new RuntimeException('No encontramos ese equipo.');
    }
    $nombre = texto_limpio($nombre, 120, 'El nombre del equipo');
    $corto = texto_limpio($corto, 10, 'La sigla');
    if (consultar_uno(
        'SELECT id FROM equipos WHERE nombre = :nombre AND id <> :id LIMIT 1',
        ['nombre' => $nombre, 'id' => $equipoId]
    ) !== null) {
        throw new RuntimeException('Ya hay un equipo con ese nombre.');
    }
    if (consultar_uno(
        'SELECT id FROM equipos WHERE nombre_corto = :corto AND id <> :id LIMIT 1',
        ['corto' => $corto, 'id' => $equipoId]
    ) !== null) {
        throw new RuntimeException('Ya hay un equipo con esa sigla.');
    }

    $grupoId = asegurar_grupo($grupo);
    $orden = (int) ($actual['orden'] ?? 0);
    if ((int) ($actual['grupo_id'] ?? 0) !== $grupoId) {
        $siguiente = consultar_uno(
            'SELECT COALESCE(MAX(orden), 0) + 1 AS siguiente FROM equipos WHERE grupo_id = :grupo',
            ['grupo' => $grupoId]
        );
        $orden = (int) ($siguiente['siguiente'] ?? 1);
    }

    ejecutar(
        'UPDATE equipos SET nombre = :nombre, nombre_corto = :corto, grupo_id = :grupo, orden = :orden WHERE id = :id',
        [
            'nombre' => $nombre,
            'corto' => $corto,
            'grupo' => $grupoId,
            'orden' => $orden,
            'id' => $equipoId,
        ]
    );
}

function guardar_orden_grupo(string $grupo, array $ids): void
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        return;
    }
    $grupoId = asegurar_grupo($grupo);
    $orden = 1;
    foreach ($ids as $id) {
        if (obtener_equipo($id) === null) {
            throw new RuntimeException('No encontramos ese equipo.');
        }
        ejecutar(
            'UPDATE equipos SET grupo_id = :grupo, orden = :orden WHERE id = :id',
            ['grupo' => $grupoId, 'orden' => $orden, 'id' => $id]
        );
        $orden++;
    }
}

function nombre_persona(array $fila): string
{
    return trim((string) ($fila['nombre'] ?? '') . ' ' . (string) ($fila['apellido'] ?? ''));
}

/**
 * @return array<int, array<string, mixed>>
 */
function contar_jugadores_equipos(): array
{
    return consultar(
        'SELECT e.id, e.nombre, e.nombre_corto, COUNT(j.id) AS total
         FROM equipos e
         LEFT JOIN jugadores j ON j.equipo_id = e.id
         GROUP BY e.id, e.nombre, e.nombre_corto
         ORDER BY e.nombre ASC'
    );
}

function crear_jugador(int $equipoId, string $nombre, ?int $dorsal): int
{
    if (obtener_equipo($equipoId) === null) {
        throw new RuntimeException('Elige un equipo.');
    }
    $nombre = texto_limpio($nombre, 120, 'El nombre');
    if ($dorsal !== null && ($dorsal < 1 || $dorsal > 99)) {
        throw new RuntimeException('El dorsal debe estar entre 1 y 99.');
    }
    if ($dorsal !== null && consultar_uno(
        'SELECT id FROM jugadores WHERE equipo_id = :equipo AND dorsal = :dorsal LIMIT 1',
        ['equipo' => $equipoId, 'dorsal' => $dorsal]
    ) !== null) {
        throw new RuntimeException('Ese equipo ya tiene el dorsal ' . $dorsal . '.');
    }

    ejecutar(
        'INSERT INTO jugadores (equipo_id, nombre, apellido, dorsal)
         VALUES (:equipo, :nombre, :apellido, :dorsal)',
        [
            'equipo' => $equipoId,
            'nombre' => $nombre,
            'apellido' => '',
            'dorsal' => $dorsal,
        ]
    );

    return (int) db()->lastInsertId();
}

function actualizar_jugador(int $jugadorId, int $equipoId, string $nombre, ?int $dorsal): void
{
    $jugador = obtener_jugador($jugadorId);
    if ($jugador === null || (int) $jugador['equipo_id'] !== $equipoId) {
        throw new RuntimeException('No encontramos ese jugador en el equipo.');
    }
    $nombre = texto_limpio($nombre, 120, 'El nombre');
    if ($dorsal !== null && ($dorsal < 1 || $dorsal > 99)) {
        throw new RuntimeException('El dorsal debe estar entre 1 y 99.');
    }
    if ($dorsal !== null) {
        $ocupado = consultar_uno(
            'SELECT id FROM jugadores WHERE equipo_id = :equipo AND dorsal = :dorsal AND id <> :id LIMIT 1',
            ['equipo' => $equipoId, 'dorsal' => $dorsal, 'id' => $jugadorId]
        );
        if ($ocupado !== null) {
            throw new RuntimeException('Ese equipo ya tiene el dorsal ' . $dorsal . '.');
        }
    }

    ejecutar(
        'UPDATE jugadores
         SET nombre = :nombre, apellido = :apellido, dorsal = :dorsal
         WHERE id = :id AND equipo_id = :equipo',
        [
            'nombre' => $nombre,
            'apellido' => '',
            'dorsal' => $dorsal,
            'id' => $jugadorId,
            'equipo' => $equipoId,
        ]
    );
}

function eliminar_equipo(int $equipoId): void
{
    if (obtener_equipo($equipoId) === null) {
        throw new RuntimeException('No encontramos ese equipo.');
    }

    transaccion(static function () use ($equipoId): void {
        ejecutar(
            'DELETE FROM partidos WHERE local_id = :local OR visitante_id = :visita',
            ['local' => $equipoId, 'visita' => $equipoId]
        );
        ejecutar('DELETE FROM eventos WHERE equipo_id = :equipo', ['equipo' => $equipoId]);
        ejecutar('DELETE FROM equipos WHERE id = :id', ['id' => $equipoId]);
    });
}

function quitar_jugador(int $jugadorId, int $equipoId): void
{
    $jugador = obtener_jugador($jugadorId);
    if ($jugador === null || (int) $jugador['equipo_id'] !== $equipoId) {
        throw new RuntimeException('No encontramos ese jugador en el equipo.');
    }

    ejecutar(
        'DELETE FROM jugadores WHERE id = :id AND equipo_id = :equipo',
        ['id' => $jugadorId, 'equipo' => $equipoId]
    );
}

function vaciar_campeonato(): void
{
    $tablas = ['eventos', 'sanciones', 'partidos', 'jugadores', 'noticias', 'equipos', 'grupos'];
    $pdo = db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($tablas as $tabla) {
            if (preg_match('/^[a-z_]+$/', $tabla) !== 1) {
                throw new RuntimeException('No se pudo vaciar el campeonato.');
            }
            $pdo->exec('DELETE FROM `' . $tabla . '`');
            $pdo->exec('ALTER TABLE `' . $tabla . '` AUTO_INCREMENT = 1');
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}

function sincronizar_tarjetas(int $jugadorId, int $partidoId, string $tipo): void
{
    if ($tipo === 'roja') {
        $quedan = consultar_uno(
            "SELECT COUNT(*) AS total
             FROM eventos
             WHERE jugador_id = :jugador AND partido_id = :partido AND tipo = 'roja'",
            ['jugador' => $jugadorId, 'partido' => $partidoId]
        );
        if ((int) ($quedan['total'] ?? 0) === 0) {
            ejecutar(
                "DELETE FROM sanciones
                 WHERE jugador_id = :jugador AND partido_origen_id = :partido AND tipo = 'roja'",
                ['jugador' => $jugadorId, 'partido' => $partidoId]
            );
        } else {
            registrar_sancion_automatica($jugadorId, $partidoId, 'roja');
        }
    }

    if ($tipo !== 'amarilla') {
        return;
    }

    $corresponden = intdiv(total_amarillas($jugadorId), LIGA_AMARILLAS);
    $sanciones = consultar(
        "SELECT id FROM sanciones WHERE jugador_id = :jugador AND tipo = 'amarillas' ORDER BY id ASC",
        ['jugador' => $jugadorId]
    );
    if (count($sanciones) > $corresponden) {
        foreach (array_slice($sanciones, $corresponden) as $sancion) {
            ejecutar('DELETE FROM sanciones WHERE id = :id', ['id' => (int) $sancion['id']]);
        }

        return;
    }
    if (count($sanciones) < $corresponden) {
        registrar_sancion_automatica($jugadorId, $partidoId, 'amarillas');
    }
}

function actualizar_marcador(int $partidoId, int $equipoGol, int $delta): void
{
    $partido = obtener_partido($partidoId);
    if ($partido === null) {
        throw new RuntimeException('No encontramos ese partido.');
    }
    $local = (int) $partido['local_id'];
    $visita = (int) $partido['visitante_id'];
    if ($equipoGol !== $local && $equipoGol !== $visita) {
        throw new RuntimeException('El gol no corresponde a ninguno de los dos equipos.');
    }
    $masLocal = $equipoGol === $local ? $delta : 0;
    $masVisita = $equipoGol === $visita ? $delta : 0;
    if ($delta < 0) {
        ejecutar(
            'UPDATE partidos
             SET goles_local = GREATEST(0, COALESCE(goles_local, 0) + :mas_local),
                 goles_visitante = GREATEST(0, COALESCE(goles_visitante, 0) + :mas_visita)
             WHERE id = :id',
            ['mas_local' => $masLocal, 'mas_visita' => $masVisita, 'id' => $partidoId]
        );

        return;
    }

    ejecutar(
        'UPDATE partidos
         SET goles_local = COALESCE(goles_local, 0) + :mas_local,
             goles_visitante = COALESCE(goles_visitante, 0) + :mas_visita
         WHERE id = :id',
        ['mas_local' => $masLocal, 'mas_visita' => $masVisita, 'id' => $partidoId]
    );
}

function terminar_partido(int $partidoId): void
{
    $partido = obtener_partido($partidoId);
    if ($partido === null) {
        throw new RuntimeException('No encontramos ese partido.');
    }
    if ((string) $partido['estado'] === 'jugado') {
        return;
    }
    if (in_array((string) $partido['estado'], ['suspendido', 'aplazado'], true)) {
        throw new RuntimeException('Ese partido está ' . etiqueta_estado((string) $partido['estado']) . ' y no se puede terminar.');
    }

    ejecutar(
        "UPDATE partidos
         SET estado = 'jugado',
             goles_local = COALESCE(goles_local, 0),
             goles_visitante = COALESCE(goles_visitante, 0)
         WHERE id = :id",
        ['id' => $partidoId]
    );
}

function agregar_evento_partido(int $partidoId, int $jugadorId, string $tipo): string
{
    if (!in_array($tipo, ['gol', 'amarilla', 'roja'], true)) {
        throw new RuntimeException('Elige gol, amarilla o roja.');
    }
    $partido = obtener_partido($partidoId);
    if ($partido === null) {
        throw new RuntimeException('No encontramos ese partido.');
    }
    if (in_array((string) $partido['estado'], ['suspendido', 'aplazado'], true)) {
        throw new RuntimeException('Ese partido está ' . etiqueta_estado((string) $partido['estado']) . ' y no admite eventos.');
    }
    $jugador = obtener_jugador($jugadorId);
    if ($jugador === null || (int) $jugador['activo'] !== 1) {
        throw new RuntimeException('Elige una persona del partido.');
    }
    $equipoId = (int) $jugador['equipo_id'];
    if ($equipoId !== (int) $partido['local_id'] && $equipoId !== (int) $partido['visitante_id']) {
        throw new RuntimeException('Esa persona no juega en ninguno de los dos equipos.');
    }

    $mensaje = transaccion(static function () use ($partidoId, $jugadorId, $equipoId, $tipo): string {
        ejecutar(
            'INSERT INTO eventos (partido_id, jugador_id, equipo_id, tipo, minuto)
             VALUES (:partido, :jugador, :equipo, :tipo, 0)',
            [
                'partido' => $partidoId,
                'jugador' => $jugadorId,
                'equipo' => $equipoId,
                'tipo' => $tipo,
            ]
        );
        if ($tipo === 'gol') {
            actualizar_marcador($partidoId, $equipoId, 1);
        } else {
            actualizar_marcador($partidoId, (int) obtener_partido($partidoId)['local_id'], 0);
            sincronizar_tarjetas($jugadorId, $partidoId, $tipo);
        }
        recalcular_sanciones();
        $actual = obtener_partido($partidoId);
        if ($tipo === 'gol') {
            return 'Gol añadido. Marcador ' . marcador_texto($actual ?? []) . '.';
        }
        if ($tipo === 'roja') {
            return 'Tarjeta roja añadida. El jugador queda suspendido un partido.';
        }
        if (ciclo_amarillas(total_amarillas($jugadorId))) {
            return 'Tarjeta amarilla añadida. Completó la acumulación y queda suspendido un partido.';
        }

        return 'Tarjeta amarilla añadida.';
    });

    return is_string($mensaje) ? $mensaje : 'Evento añadido.';
}

function quitar_evento(int $eventoId, int $partidoId): string
{
    if ($eventoId < 1 || $partidoId < 1) {
        throw new RuntimeException('No encontramos ese evento.');
    }
    $evento = consultar_uno(
        'SELECT id, partido_id, jugador_id, equipo_id, tipo
         FROM eventos
         WHERE id = :id AND partido_id = :partido',
        ['id' => $eventoId, 'partido' => $partidoId]
    );
    if ($evento === null) {
        throw new RuntimeException('Ese evento no pertenece al partido.');
    }

    transaccion(static function () use ($evento, $partidoId): void {
        $tipo = (string) $evento['tipo'];
        $jugadorId = $evento['jugador_id'] === null ? 0 : (int) $evento['jugador_id'];
        ejecutar('DELETE FROM eventos WHERE id = :id', ['id' => (int) $evento['id']]);
        if ($tipo === 'gol' || $tipo === 'autogol') {
            actualizar_marcador($partidoId, (int) $evento['equipo_id'], -1);
        }
        if ($jugadorId > 0 && ($tipo === 'amarilla' || $tipo === 'roja')) {
            sincronizar_tarjetas($jugadorId, $partidoId, $tipo);
        }
        recalcular_sanciones();
    });

    return 'Evento quitado.';
}

/**
 * Valida la subida, la redimensiona y la guarda como WebP.
 * Devuelve null si el formulario no trae archivo.
 * La ruta devuelta es relativa a assets, por ejemplo escudos/abc.webp.
 */
function guardar_imagen_webp(array $archivo, string $carpeta, int $ladoMax = 640): ?string
{
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }
    $peso = (int) ($archivo['size'] ?? 0);
    if ($peso < 1 || $peso > LIGA_IMAGEN_MAX) {
        throw new RuntimeException('La imagen supera el límite de 2 MB.');
    }
    $tmp = (string) ($archivo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('La subida no es válida.');
    }
    if (!class_exists('finfo')) {
        throw new RuntimeException('El servidor no tiene la extensión fileinfo.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $permitidos = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/gif' => IMAGETYPE_GIF,
        'image/webp' => IMAGETYPE_WEBP,
    ];
    if (!is_string($mime) || !isset($permitidos[$mime])) {
        throw new RuntimeException('El archivo debe ser JPG, PNG, WEBP o GIF.');
    }
    $info = getimagesize($tmp);
    if ($info === false || ($info[2] ?? null) !== $permitidos[$mime]) {
        throw new RuntimeException('El contenido no coincide con una imagen válida.');
    }
    $ancho = (int) $info[0];
    $alto = (int) $info[1];
    if ($ancho < 1 || $alto < 1 || ($ancho * $alto) > 24000000) {
        throw new RuntimeException('Las dimensiones de la imagen no son válidas.');
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('El servidor no tiene GD con soporte WebP.');
    }
    $origen = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($tmp),
        'image/png' => imagecreatefrompng($tmp),
        'image/gif' => imagecreatefromgif($tmp),
        'image/webp' => imagecreatefromwebp($tmp),
        default => false,
    };
    if ($origen === false) {
        throw new RuntimeException('No se pudo leer la imagen.');
    }

    $ladoMax = max(64, min($ladoMax, 1600));
    $escala = min(1, $ladoMax / max($ancho, $alto));
    $nuevoAncho = max(1, (int) round($ancho * $escala));
    $nuevoAlto = max(1, (int) round($alto * $escala));
    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    if ($destino === false) {
        imagedestroy($origen);
        throw new RuntimeException('No se pudo preparar la imagen.');
    }
    imagealphablending($destino, false);
    imagesavealpha($destino, true);
    $transparente = imagecolorallocatealpha($destino, 0, 0, 0, 127);
    imagefilledrectangle($destino, 0, 0, $nuevoAncho, $nuevoAlto, $transparente);
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
    imagedestroy($origen);

    if (preg_match('/^(escudos|fotos|noticias)$/', $carpeta) !== 1) {
        imagedestroy($destino);
        throw new RuntimeException('Carpeta de destino no permitida.');
    }
    $directorio = dirname(__DIR__) . '/assets/' . $carpeta;
    if (!is_dir($directorio) && !mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        imagedestroy($destino);
        throw new RuntimeException('No se pudo crear la carpeta de imágenes.');
    }
    $nombre = bin2hex(random_bytes(16)) . '.webp';
    $ok = imagewebp($destino, $directorio . '/' . $nombre, 82);
    imagedestroy($destino);
    if ($ok !== true) {
        throw new RuntimeException('No se pudo guardar la imagen WebP.');
    }

    return $carpeta . '/' . $nombre;
}

function borrar_asset(?string $relativo): void
{
    if ($relativo === null || preg_match('#^(escudos|fotos|noticias)/[a-f0-9]{32}\.webp$#', $relativo) !== 1) {
        return;
    }
    $ruta = dirname(__DIR__) . '/assets/' . $relativo;
    if (is_file($ruta)) {
        unlink($ruta);
    }
}

/**
 * Ida de un todos contra todos con el método del círculo.
 * El primer equipo es local en toda la ida; la vuelta invierte la localía.
 */
function rondas_round_robin(array $ids): array
{
    $limpios = [];
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $limpios[$id] = $id;
        }
    }
    $ids = array_values($limpios);
    $cantidad = count($ids);
    if ($cantidad < 2) {
        return [];
    }
    if ($cantidad % 2 === 1) {
        $ids[] = 0;
        $cantidad++;
    }

    $rotacion = $ids;
    $rondas = [];
    for ($ronda = 0; $ronda < $cantidad - 1; $ronda++) {
        $pares = [];
        for ($i = 0; $i < intdiv($cantidad, 2); $i++) {
            $local = $rotacion[$i];
            $visita = $rotacion[$cantidad - 1 - $i];
            if ($local === 0 || $visita === 0) {
                continue;
            }
            $pares[] = ['local' => $local, 'visitante' => $visita];
        }
        $rondas[] = $pares;
        $fijo = $rotacion[0];
        $resto = array_slice($rotacion, 1);
        $ultimo = array_pop($resto);
        array_unshift($resto, $ultimo);
        $rotacion = array_merge([$fijo], $resto);
    }

    return $rondas;
}

/**
 * Devuelve los partidos de la ida y, si se pide, de la vuelta.
 * Cada elemento trae jornada, vuelta, local_id y visitante_id.
 */
function calendario_round_robin(array $equipoIds, bool $idaYVuelta): array
{
    $rondas = rondas_round_robin($equipoIds);
    $partidos = [];
    $jornada = 1;
    foreach ($rondas as $pares) {
        foreach ($pares as $par) {
            $partidos[] = [
                'jornada' => $jornada,
                'vuelta' => 0,
                'local_id' => $par['local'],
                'visitante_id' => $par['visitante'],
            ];
        }
        $jornada++;
    }
    if (!$idaYVuelta) {
        return $partidos;
    }

    $totalIda = count($rondas);
    $primera = $partidos;
    foreach ($primera as $partido) {
        $partidos[] = [
            'jornada' => $partido['jornada'] + $totalIda,
            'vuelta' => 1,
            'local_id' => $partido['visitante_id'],
            'visitante_id' => $partido['local_id'],
        ];
    }

    return $partidos;
}
