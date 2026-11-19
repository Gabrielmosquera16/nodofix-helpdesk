<?php
/** Cierre de sesión por POST con token CSRF (un enlace externo no puede cerrar la sesión). */
require __DIR__ . '/includes/init.php';
solo_post();
registrar_bitacora('LOGOUT', 'Cierre de sesión');
cerrar_sesion();
session_start();
flash('info', 'Cerraste sesión correctamente.');
redirigir('login.php');
