<?php

declare(strict_types=1);

require __DIR__ . '/includes/funciones.php';

$equipo = entero_get('equipo');
$destino = $equipo > 0 ? 'equipos.php?id=' . $equipo : 'equipos.php';
redirigir(url_public($destino));
