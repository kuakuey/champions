<?php
/**
 * Conexión PDO a MySQL/MariaDB.
 *
 * En este XAMPP la clave de root es "root", la misma de phpMyAdmin.
 * Si el tuyo no tiene clave, deja DB_PASS vacío.
 * Hosting compartido: cambia DB_HOST, DB_NAME, DB_USER y DB_PASS.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'db.php') {
    http_response_code(403);
    exit('Acceso denegado.');
}

const DB_HOST = '127.0.0.1';
const DB_NAME = 'campeonato';
const DB_USER = 'root';
const DB_PASS = 'root';
const DB_CHARSET = 'utf8mb4';

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
