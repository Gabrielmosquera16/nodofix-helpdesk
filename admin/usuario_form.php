<?php
/** Crear / editar usuario (solo ADMIN). */
require dirname(__DIR__) . '/includes/init.php';
$yo = requiere_rol(ROL_ADMIN);
$seccion = 'usuarios';

$id = entero($_GET['id'] ?? $_POST['id'] ?? 0);
$roles = filas('SELECT id, nombre, descripcion FROM roles ORDER BY id');
$d = ['nombre' => '', 'email' => '', 'telefono' => '', 'empresa' => '', 'rol_id' => 3, 'estado' => 'ACTIVO'];
if ($id) {
    $d = fila('SELECT id, nombre, email, telefono, empresa, rol_id, estado FROM usuarios WHERE id = ?', [$id]);
    if (!$d) {
        http_response_code(404);
        $titulo = 'No encontrado';
        $mensaje = 'El usuario no existe.';
        require ROOT . '/includes/error.php';
        exit;
    }
}
$esYo = $id && $id === (int)$yo['id'];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $d['nombre']   = texto($_POST, 'nombre', 80);
    $d['email']    = mb_strtolower(texto($_POST, 'email', 120));
    $d['telefono'] = texto($_POST, 'telefono', 20);
    $d['empresa']  = texto($_POST, 'empresa', 100);
    $d['rol_id']   = entero($_POST['rol_id'] ?? 0);
    $d['estado']   = ($_POST['estado'] ?? '') === 'INACTIVO' ? 'INACTIVO' : 'ACTIVO';
    $pass = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if ($esYo) {                       // el admin no puede quitarse su propio rol ni desactivarse
        $d['rol_id'] = 1;
        $d['estado'] = 'ACTIVO';
    }
    if (mb_strlen($d['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electrónico no es válido.';
    if ($d['telefono'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $d['telefono'])) $errores[] = 'Teléfono no válido.';
    if (!in_array($d['rol_id'], array_map('intval', array_column($roles, 'id')), true)) $errores[] = 'Seleccione un rol válido.';
    if (!$id || $pass !== '') {
        $reglas = validar_password($pass);
        if ($reglas) $errores[] = 'La contraseña debe tener: ' . implode(', ', $reglas) . '.';
    }
    if (valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?', [$d['email'], $id])) {
        $errores[] = 'Ya existe otro usuario con ese correo.';
    }
    // Un técnico con tickets activos no puede cambiar de rol sin reasignarlos antes
    if ($id && !$errores) {
        $rolAntes = (int)valor('SELECT rol_id FROM usuarios WHERE id = ?', [$id]);
        $activos = (int)valor("SELECT COUNT(*) FROM tickets WHERE tecnico_id = ? AND estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA')", [$id]);
        if ($rolAntes === 2 && $d['rol_id'] !== 2 && $activos > 0) {
            $errores[] = "Este técnico tiene $activos ticket(s) activos. Reasígnalos antes de cambiarle el rol.";
        }
    }

    if (!$errores) {
        if ($id) {
            consulta(
                'UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, empresa = ?, rol_id = ?, estado = ? WHERE id = ?',
                [$d['nombre'], $d['email'], $d['telefono'] ?: null, $d['empresa'] ?: null, $d['rol_id'], $d['estado'], $id]
            );
            if ($pass !== '') {
                consulta('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
            }
            registrar_bitacora('USUARIO_EDITADO', 'Editó al usuario ' . $d['email']);
            flash('exito', 'Usuario actualizado correctamente.');
        } else {
            consulta(
                'INSERT INTO usuarios (rol_id, nombre, email, password_hash, telefono, empresa, estado) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$d['rol_id'], $d['nombre'], $d['email'], password_hash($pass, PASSWORD_DEFAULT), $d['telefono'] ?: null, $d['empresa'] ?: null, $d['estado']]
            );
            registrar_bitacora('USUARIO_CREADO', 'Creó al usuario ' . $d['email']);
            flash('exito', 'Usuario creado correctamente.');
        }
        redirigir('admin/usuarios.php');
    }
}
$titulo = $id ? 'Editar usuario' : 'Nuevo usuario';
require ROOT . '/includes/header.php';
?>
<div class="cabecera-pagina">
    <div><a class="volver" href="<?= url('admin/usuarios.php') ?>">← Usuarios</a><h1><?= e($titulo) ?></h1></div>
</div>
<section class="panel form-panel">
    <?php if ($errores): ?>
        <div class="alerta alerta-error" role="alert"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" novalidate data-validar>
        <?= campo_csrf() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <div class="grid-2">
            <label>Nombre completo *<input name="nombre" value="<?= e($d['nombre']) ?>" required minlength="3" maxlength="80"></label>
            <label>Correo electrónico *<input type="email" name="email" value="<?= e($d['email']) ?>" required maxlength="120"></label>
            <label>Teléfono<input name="telefono" value="<?= e($d['telefono']) ?>" maxlength="20" pattern="[0-9+\-\s]{7,20}"></label>
            <label>Empresa<input name="empresa" value="<?= e($d['empresa']) ?>" maxlength="100"></label>
            <label>Rol *
                <select name="rol_id" required <?= $esYo ? 'disabled' : '' ?>>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= (int)$d['rol_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?> — <?= e($r['descripcion']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado
                <select name="estado" <?= $esYo ? 'disabled' : '' ?>>
                    <option value="ACTIVO" <?= $d['estado'] === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                    <option value="INACTIVO" <?= $d['estado'] === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </label>
            <label><?= $id ? 'Nueva contraseña (dejar vacío para no cambiarla)' : 'Contraseña *' ?>
                <input type="password" name="password" <?= $id ? '' : 'required' ?> minlength="8" maxlength="72" autocomplete="new-password" data-fuerza>
            </label>
        </div>
        <ul class="reglas-pass" data-reglas>
            <li data-r="len">8 caracteres o más</li><li data-r="may">Una mayúscula</li><li data-r="min">Una minúscula</li><li data-r="num">Un número</li><li data-r="esp">Un carácter especial</li>
        </ul>
        <?php if ($esYo): ?><p class="ayuda">No puedes cambiar tu propio rol ni desactivar tu cuenta.</p><?php endif; ?>
        <div class="form-acciones">
            <a class="btn btn-link" href="<?= url('admin/usuarios.php') ?>">Cancelar</a>
            <button class="btn" type="submit"><?= $id ? 'Guardar cambios' : 'Crear usuario' ?></button>
        </div>
    </form>
</section>
<?php require ROOT . '/includes/footer.php'; ?>
