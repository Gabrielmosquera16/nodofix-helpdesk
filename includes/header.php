<?php
/** Encabezado y menú comunes. Requiere $titulo; $seccion marca el menú activo. */
$u = usuario_actual();
$menu = [];
if ($u) {
    $menu[] = ['dashboard.php', 'Inicio', 'inicio'];
    $menu[] = ['tickets/index.php', $u['rol'] === ROL_CLIENTE ? 'Mis tickets' : 'Tickets', 'tickets'];
    if ($u['rol'] === ROL_CLIENTE) $menu[] = ['tickets/crear.php', 'Nuevo ticket', 'nuevo'];
    if ($u['rol'] === ROL_ADMIN) {
        $menu[] = ['admin/usuarios.php', 'Usuarios', 'usuarios'];
        $menu[] = ['admin/categorias.php', 'Categorías', 'categorias'];
        $menu[] = ['admin/reportes.php', 'Reportes', 'reportes'];
        $menu[] = ['admin/bitacora.php', 'Bitácora', 'bitacora'];
    }
    if ($u['rol'] === ROL_TECNICO) $menu[] = ['admin/reportes.php', 'Mi desempeño', 'reportes'];
    // Solo se muestran los módulos que existen en esta versión del proyecto
    $menu = array_filter($menu, fn($m) => is_file(ROOT . '/' . $m[0]));
}
$tieneNotif = $u && is_file(ROOT . '/notificaciones.php');
$tienePerfil = $u && is_file(ROOT . '/perfil.php');
$seccion = $seccion ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" href="<?= url('assets/img/favicon.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <script src="<?= url('assets/js/app.js') ?>" defer></script>
</head>
<body class="<?= $u ? 'rol-' . strtolower(e($u['rol'])) : 'publico' ?>">
<header class="topbar">
    <div class="contenedor topbar-in">
        <a class="marca" href="<?= url($u ? 'dashboard.php' : 'index.php') ?>">
            <img src="<?= url('assets/img/logo.svg') ?>" alt="" width="34" height="34">
            <span>NodoFix <b>HelpDesk</b></span>
        </a>
        <?php if ($u): ?>
            <button class="menu-toggle" type="button" aria-label="Abrir menú" data-toggle="nav-principal">☰</button>
            <nav class="nav-principal" id="nav-principal">
                <?php foreach ($menu as [$href, $txt, $key]): ?>
                    <a href="<?= url($href) ?>" class="<?= $seccion === $key ? 'activo' : '' ?>"><?= e($txt) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="usuario-zona">
                <?php if ($tieneNotif): $n = notificaciones_sin_leer((int)$u['id']); ?>
                    <a class="campana" href="<?= url('notificaciones.php') ?>" title="Notificaciones">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3a1.5 1.5 0 0 0-3 0v1.16A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
                        <?php if ($n > 0): ?><span class="contador"><?= $n > 9 ? '9+' : $n ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
                <div class="usuario-menu">
                    <button type="button" class="usuario-btn" data-toggle="usuario-drop">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
                        <span class="usuario-txt"><?= e(explode(' ', $u['nombre'])[0]) ?><small><?= e(mb_strtoupper(nombre_rol($u['rol']))) ?></small></span>
                    </button>
                    <div class="usuario-drop" id="usuario-drop">
                        <?php if ($tienePerfil): ?><a href="<?= url('perfil.php') ?>">Mi perfil</a><?php endif; ?>
                        <form method="post" action="<?= url('logout.php') ?>">
                            <?= campo_csrf() ?>
                            <button type="submit">Cerrar sesión</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <nav class="nav-publico">
                <a href="<?= url('login.php') ?>">Iniciar sesión</a>
                <a class="btn btn-claro" href="<?= url('registro.php') ?>">Crear cuenta</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
<main class="contenedor contenido">
<?php foreach (obtener_flash() as $f): ?>
    <div class="alerta alerta-<?= e($f['tipo']) ?>" role="alert"><?= e($f['mensaje']) ?><button type="button" class="cerrar" aria-label="Cerrar">×</button></div>
<?php endforeach; ?>
