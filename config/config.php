<?php
/**
 * Configuración general de NodoFix HelpDesk.
 * Ajustar DB_USER / DB_PASS según la instalación de XAMPP.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'nodofix_helpdesk');
define('DB_USER', getenv('NFX_DB_USER') ?: 'root');
define('DB_PASS', getenv('NFX_DB_PASS') !== false ? getenv('NFX_DB_PASS') : '');

define('APP_NAME', 'NodoFix HelpDesk');
define('APP_ZONA', 'America/Panama');

// Seguridad
define('SESION_INACTIVIDAD', 1800);     // 30 min sin actividad cierra la sesión
define('LOGIN_MAX_INTENTOS', 5);         // intentos fallidos permitidos por correo
define('LOGIN_MAX_INTENTOS_IP', 15);     // intentos fallidos permitidos por IP
define('LOGIN_VENTANA_MIN', 15);         // ventana de bloqueo en minutos

// Reglas de negocio
define('MAX_TICKETS_TECNICO', 6);        // tickets activos simultáneos por técnico
define('MAX_TICKETS_CLIENTE', 5);        // tickets activos simultáneos por cliente
define('DIAS_REAPERTURA', 7);            // días para reabrir un ticket resuelto

date_default_timezone_set(APP_ZONA);
