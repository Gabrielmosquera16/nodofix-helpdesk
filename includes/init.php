<?php
/**
 * Punto de arranque: cada página incluye este archivo primero.
 * Carga configuración, cabeceras de seguridad, sesión segura y utilidades.
 */
define('ROOT', dirname(__DIR__));

require ROOT . '/config/config.php';
require ROOT . '/includes/db.php';
require ROOT . '/includes/seguridad.php';
require ROOT . '/includes/auth.php';
require ROOT . '/includes/funciones.php';

// URL base (funciona en http://localhost/nodofix-helpdesk/ o en la raíz)
$rel = str_replace('\\', '/', substr(realpath($_SERVER['SCRIPT_FILENAME']), strlen(realpath(ROOT))));
define('BASE_URL', rtrim(substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - strlen($rel)), '/') . '/');

enviar_cabeceras_seguridad();
iniciar_sesion_segura();
