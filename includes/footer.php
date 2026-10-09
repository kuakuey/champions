<?php
/**
 * Cierra el contenido abierto por header.php y carga Bootstrap más el tema.
 */

declare(strict_types=1);

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'footer.php') {
    http_response_code(403);
    exit('Acceso denegado.');
}

require_once __DIR__ . '/funciones.php';
?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        var botones = document.querySelectorAll('[data-tema]');
        if (botones.length === 0) {
            return;
        }

        function pintar() {
            var oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            botones.forEach(function (boton) {
                var icono = boton.querySelector('.icono-tema');
                if (icono) {
                    icono.className = (oscuro ? 'bi bi-sun-fill' : 'bi bi-moon-fill') + ' icono-tema';
                }
                boton.setAttribute('aria-pressed', oscuro ? 'true' : 'false');
                boton.setAttribute('title', oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro');
            });
        }

        pintar();
        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                var oscuro = document.documentElement.getAttribute('data-bs-theme') === 'dark';
                var siguiente = oscuro ? 'light' : 'dark';
                document.documentElement.setAttribute('data-bs-theme', siguiente);
                try {
                    localStorage.setItem('tema', siguiente);
                } catch (error) {}
                pintar();
            });
        });
    })();
</script>
</body>
</html>
