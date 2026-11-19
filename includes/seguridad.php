<?php
/**
 * Controles de seguridad transversales:
 * cabeceras HTTP, sesión segura, escape de salida (XSS), token CSRF y política de contraseñas.
 */

function enviar_cabeceras_seguridad(): void
{
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header_remove('X-Powered-By');
}

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('NFXSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,       // JavaScript no puede leer la cookie
        'samesite' => 'Strict',   // la cookie no viaja en peticiones de otros sitios
    ]);
    session_start();

    // Cierre por inactividad
    if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > SESION_INACTIVIDAD) {
        cerrar_sesion();
        session_start();
        flash('info', 'Tu sesión expiró por inactividad. Inicia sesión de nuevo.');
    }
    $_SESSION['ultima_actividad'] = time();
}

/** Escapa cualquier dato antes de mostrarlo en HTML (protección XSS). */
function e($valor): string
{
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------- CSRF
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Detiene la petición si el token CSRF falta o no coincide. */
function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        registrar_bitacora('CSRF_RECHAZADO', 'Token CSRF inválido en ' . ($_SERVER['SCRIPT_NAME'] ?? ''));
        http_response_code(403);
        $titulo = 'Solicitud rechazada';
        $mensaje = 'El token de seguridad (CSRF) no es válido o expiró. Vuelve a la página anterior y recarga el formulario.';
        require ROOT . '/includes/error.php';
        exit;
    }
}

/** Exige método POST + CSRF para cualquier acción que cambie datos. */
function solo_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método no permitido');
    }
    verificar_csrf();
}

// ---------------------------------------------------------------- Contraseñas
/** Devuelve la lista de reglas incumplidas (vacía = contraseña válida). */
function validar_password(string $p): array
{
    $err = [];
    if (strlen($p) < 8)                      $err[] = 'al menos 8 caracteres';
    if (strlen($p) > 72)                     $err[] = 'máximo 72 caracteres';
    if (!preg_match('/[A-Z]/', $p))          $err[] = 'una letra mayúscula';
    if (!preg_match('/[a-z]/', $p))          $err[] = 'una letra minúscula';
    if (!preg_match('/\d/', $p))             $err[] = 'un número';
    if (!preg_match('/[^A-Za-z0-9]/', $p))   $err[] = 'un carácter especial';
    return $err;
}

function ip_cliente(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}
