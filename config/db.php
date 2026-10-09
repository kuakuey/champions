<?php
/**
 * Conexión PDO a MySQL.
 * En cPanel solo se cambian las cuatro credenciales de abajo.
 * Salen de cPanel → Bases de datos MySQL.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db.php') {
    http_response_code(403);
    exit('Acceso denegado.');
}

$servidor = strtolower((string) ($_SERVER['SERVER_NAME'] ?? ''));
$enLocal = $servidor === 'localhost' || $servidor === '127.0.0.1';

if ($enLocal) {
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'campeonato');
    define('DB_USER', 'root');
    define('DB_PASS', 'root');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'iglesiacasadeavi_champions');
    define('DB_USER', 'iglesiacasadeavi_kuakuey');
    define('DB_PASS', '');
}

define('DB_CHARSET', 'utf8mb4');

/**
 * Devuelve una única instancia de PDO para toda la petición.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Error de conexión: ' . $e->getMessage());
        http_response_code(500);
        exit('No se pudo conectar a la base de datos. Revisa config/db.php y que el esquema esté importado.');
    }

    return $pdo;
}
