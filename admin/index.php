<?php

declare(strict_types=1);

$script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$destino = dirname(dirname($script));
if ($destino === '/' || $destino === '.' || $destino === '') {
    $destino = '';
}

header('Location: ' . $destino . '/', true, 302);
exit;
