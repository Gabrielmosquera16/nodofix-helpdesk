<?php
/** Módulo 1 - Gestión de usuarios (solo ADMIN): listado, búsqueda y filtros. */
require dirname(__DIR__) . '/includes/init.php';
$yo = requiere_rol(ROL_ADMIN);
$titulo = 'Usuarios';
$seccion = 'usuarios';

$q      = texto($_GET, 'q', 80);
$rol    = texto($_GET, 'rol', 10);
$estado = texto($_GET, 'estado', 10);

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(u.nombre LIKE ? OR u.email LIKE ? OR u.empresa LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if (in_array($rol, ['ADMIN', 'TECNICO', 'CLIENTE'], true)) {
    $where[] = 'r.nombre = ?';
    $params[] = $rol;
}
if (in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
    $where[] = 'u.estado = ?';
    $params[] = $estado;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)valor("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id $sqlWhere", $params);
[$lim, $off, $paginas, $pag] = paginar($total, 10);
$usuarios = filas(
    "SELECT u.id, u.nombre, u.email, u.empresa, u.estado, u.ultimo_login, r.nombre rol,
            (SELECT COUNT(*) FROM tickets t WHERE t.cliente_id = u.id OR t.tecnico_id = u.id) tickets
     FROM usuarios u JOIN roles r ON r.id = u.rol_id $sqlWhere
     ORDER BY r.id, u.nombre LIMIT $lim OFFSET $off",
    $params
);
require ROOT . '/includes/header.php';
?>
<div class="cabecera-pagina">
    <div><h1>Usuarios</h1><p class="sub"><?= $total ?> usuario(s) encontrados</p></div>
    <a class="btn" href="<?= url('admin/usuario_form.php') ?>">+ Nuevo usuario</a>
</div>

<form class="filtros" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre, correo o empresa" maxlength="80">
    <select name="rol">
        <option value="">Todos los roles</option>
        <?php foreach (['ADMIN', 'TECNICO', 'CLIENTE'] as $r): ?>
            <option value="<?= $r ?>" <?= $rol === $r ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
    </select>
    <select name="estado">
        <option value="">Todos los estados</option>
        <option value="ACTIVO" <?= $estado === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
        <option value="INACTIVO" <?= $estado === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
    </select>
    <button class="btn btn-sec" type="submit">Filtrar</button>
    <a class="btn btn-link" href="<?= url('admin/usuarios.php') ?>">Limpiar</a>
</form>

<section class="panel">
<?php if (!$usuarios): ?>
    <p class="vacio">No hay usuarios que coincidan con la búsqueda.</p>
<?php else: ?>
    <div class="tabla-resp"><table class="tabla">
        <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th class="der">Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $us): $esYo = (int)$us['id'] === (int)$yo['id']; ?>
            <tr>
                <td><strong><?= e($us['nombre']) ?></strong><?php if ($us['empresa']): ?><br><small class="gris"><?= e($us['empresa']) ?></small><?php endif; ?></td>
                <td><?= e($us['email']) ?></td>
                <td><span class="badge rol-<?= strtolower(e($us['rol'])) ?>"><?= e($us['rol']) ?></span></td>
                <td><span class="badge <?= $us['estado'] === 'ACTIVO' ? 'ok' : 'off' ?>"><?= e($us['estado']) ?></span></td>
                <td><?= $us['ultimo_login'] ? e(hace($us['ultimo_login'])) : '<span class="gris">Nunca</span>' ?></td>
                <td class="acciones">
                    <a class="btn btn-mini btn-sec" href="<?= url('admin/usuario_form.php?id=' . (int)$us['id']) ?>">Editar</a>
                    <?php if (!$esYo): ?>
                        <form method="post" action="<?= url('admin/usuario_accion.php') ?>">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                            <input type="hidden" name="accion" value="estado">
                            <button class="btn btn-mini btn-borde" type="submit"><?= $us['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                        <form method="post" action="<?= url('admin/usuario_accion.php') ?>" data-confirmar="¿Eliminar a <?= e($us['nombre']) ?>? Esta acción no se puede deshacer.">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="id" value="<?= (int)$us['id'] ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <button class="btn btn-mini btn-peligro" type="submit" <?= $us['tickets'] ? 'disabled title="Tiene tickets asociados: desactívelo en su lugar"' : '' ?>>Eliminar</button>
                        </form>
                    <?php else: ?>
                        <span class="gris">(tú)</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?= enlaces_paginacion($paginas, $pag) ?>
<?php endif; ?>
</section>
<?php require ROOT . '/includes/footer.php'; ?>
