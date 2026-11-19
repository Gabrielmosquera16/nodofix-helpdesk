<?php
/**
 * Autenticación y autorización.
 * - password_verify() contra hashes de password_hash()
 * - regeneración del ID de sesión al entrar y al salir
 * - bloqueo temporal por intentos fallidos (correo e IP)
 * - el rol se vuelve a leer de la BD en cada petición (un cambio de rol o una
 *   desactivación aplica de inmediato, sin esperar a que el usuario salga)
 */

const ROL_ADMIN = 'ADMIN';
const ROL_TECNICO = 'TECNICO';
const ROL_CLIENTE = 'CLIENTE';

/** Minutos de bloqueo restantes para ese correo/IP; 0 si puede intentar. */
function minutos_bloqueo(string $email): int
{
    $ventana = LOGIN_VENTANA_MIN;
    $porEmail = fila(
        "SELECT COUNT(*) n, MIN(creado_en) primero FROM intentos_login
         WHERE email = ? AND exitoso = 0 AND creado_en > NOW() - INTERVAL $ventana MINUTE",
        [$email]
    );
    $porIp = fila(
        "SELECT COUNT(*) n, MIN(creado_en) primero FROM intentos_login
         WHERE ip = ? AND exitoso = 0 AND creado_en > NOW() - INTERVAL $ventana MINUTE",
        [ip_cliente()]
    );
    foreach ([[$porEmail, LOGIN_MAX_INTENTOS], [$porIp, LOGIN_MAX_INTENTOS_IP]] as [$r, $max]) {
        if ((int)$r['n'] >= $max) {
            $libre = strtotime($r['primero']) + $ventana * 60;
            return max(1, (int)ceil(($libre - time()) / 60));
        }
    }
    return 0;
}

/**
 * Intenta iniciar sesión. Devuelve null si tuvo éxito o el mensaje de error.
 * El mensaje es genérico para no revelar si el correo existe.
 */
function intentar_login(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    $bloqueo = minutos_bloqueo($email);
    if ($bloqueo > 0) {
        registrar_bitacora('LOGIN_BLOQUEADO', "Intento durante bloqueo para $email");
        return "Demasiados intentos fallidos. Por seguridad el acceso está bloqueado. Intenta de nuevo en $bloqueo minuto(s).";
    }

    $u = fila(
        'SELECT u.id, u.password_hash, u.estado, r.nombre rol
         FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.email = ?',
        [$email]
    );
    // Se ejecuta password_verify aunque el usuario no exista para que el tiempo
    // de respuesta no delate qué correos están registrados.
    $hashFalso = '$2y$10$CO40pUuv9DY5F4XtHAWkaut9S5.jtADlOYQSpxB2m9cFM5h6mHutG';
    $ok = password_verify($password, $u['password_hash'] ?? $hashFalso) && $u !== null;

    consulta('INSERT INTO intentos_login (email, ip, exitoso) VALUES (?, ?, ?)', [$email, ip_cliente(), $ok && $u['estado'] === 'ACTIVO' ? 1 : 0]);

    if (!$ok) {
        $restantes = LOGIN_MAX_INTENTOS - (int)valor(
            'SELECT COUNT(*) FROM intentos_login WHERE email = ? AND exitoso = 0 AND creado_en > NOW() - INTERVAL ' . LOGIN_VENTANA_MIN . ' MINUTE',
            [$email]
        );
        registrar_bitacora('LOGIN_FALLIDO', "Credenciales incorrectas para $email");
        if ($restantes <= 0) {
            return 'Demasiados intentos fallidos. Por seguridad el acceso está bloqueado por ' . LOGIN_VENTANA_MIN . ' minutos.';
        }
        return "Correo o contraseña incorrectos. Te quedan $restantes intento(s).";
    }
    if ($u['estado'] !== 'ACTIVO') {
        return 'Tu cuenta está inactiva. Comunícate con el administrador.';
    }

    // Rehash transparente si cambia el algoritmo/costo por defecto
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        consulta('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }

    session_regenerate_id(true);           // evita fijación de sesión
    $_SESSION['usuario_id'] = (int)$u['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    consulta('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$u['id']]);
    registrar_bitacora('LOGIN', 'Inicio de sesión correcto');
    return null;
}

function cerrar_sesion(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    session_regenerate_id(true);           // invalida el ID anterior
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    session_destroy();
}

/** Usuario autenticado (leído de la BD en cada petición) o null. */
function usuario_actual(): ?array
{
    static $cache = [];               // una consulta por petición y por usuario de sesión
    $sid = (int)($_SESSION['usuario_id'] ?? 0);
    if (array_key_exists($sid, $cache)) {
        return $cache[$sid];
    }
    $usuario = null;
    if ($sid) {
        $u = fila(
            'SELECT u.id, u.nombre, u.email, u.telefono, u.empresa, u.estado, r.nombre rol
             FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?',
            [$_SESSION['usuario_id']]
        );
        if ($u && $u['estado'] === 'ACTIVO') {
            $usuario = $u;
        } else {
            cerrar_sesion();
            session_start();
        }
    }
    return $cache[$sid] = $usuario;
}

function es_rol(string ...$roles): bool
{
    $u = usuario_actual();
    return $u !== null && in_array($u['rol'], $roles, true);
}

/** Exige sesión iniciada. */
function requiere_login(): array
{
    $u = usuario_actual();
    if ($u === null) {
        flash('info', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }
    return $u;
}

/** Exige sesión y uno de los roles indicados; si no, responde 403. */
function requiere_rol(string ...$roles): array
{
    $u = requiere_login();
    if (!in_array($u['rol'], $roles, true)) {
        acceso_denegado('Tu rol no tiene permiso para acceder a esta sección.');
    }
    return $u;
}

function acceso_denegado(string $mensaje = 'No tienes permiso para realizar esta acción.'): void
{
    registrar_bitacora('ACCESO_DENEGADO', ($_SERVER['REQUEST_URI'] ?? '') . ' - ' . $mensaje);
    http_response_code(403);
    $titulo = 'Acceso denegado';
    require ROOT . '/includes/error.php';
    exit;
}
