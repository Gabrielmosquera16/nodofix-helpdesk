<?php
/** Registro público: solo crea cuentas con rol CLIENTE. */
require __DIR__ . '/includes/init.php';
if (usuario_actual()) {
    redirigir('dashboard.php');
}

$errores = [];
$d = ['nombre' => '', 'email' => '', 'telefono' => '', 'empresa' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $d['nombre']   = texto($_POST, 'nombre', 80);
    $d['email']    = mb_strtolower(texto($_POST, 'email', 120));
    $d['telefono'] = texto($_POST, 'telefono', 20);
    $d['empresa']  = texto($_POST, 'empresa', 100);
    $pass  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pass2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if (mb_strlen($d['nombre']) < 3) $errores[] = 'El nombre debe tener al menos 3 caracteres.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo electrónico no es válido.';
    if ($d['telefono'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $d['telefono'])) $errores[] = 'El teléfono solo puede tener números, espacios, + o -.';
    $reglas = validar_password($pass);
    if ($reglas) $errores[] = 'La contraseña debe tener: ' . implode(', ', $reglas) . '.';
    if ($pass !== $pass2) $errores[] = 'Las contraseñas no coinciden.';
    if (!$errores && valor('SELECT COUNT(*) FROM usuarios WHERE email = ?', [$d['email']])) {
        $errores[] = 'Ya existe una cuenta con ese correo.';
    }

    if (!$errores) {
        consulta(
            'INSERT INTO usuarios (rol_id, nombre, email, password_hash, telefono, empresa) VALUES (3, ?, ?, ?, ?, ?)',
            [$d['nombre'], $d['email'], password_hash($pass, PASSWORD_DEFAULT), $d['telefono'] ?: null, $d['empresa'] ?: null]
        );
        registrar_bitacora('REGISTRO', 'Nueva cuenta de cliente: ' . $d['email']);
        flash('exito', 'Cuenta creada. Ya puedes iniciar sesión.');
        redirigir('login.php');
    }
}
$titulo = 'Crear cuenta';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
    <div class="auth-card ancho">
        <h1>Crear cuenta de cliente</h1>
        <p class="sub">Regístrate para reportar problemas y seguir su avance.</p>
        <?php if ($errores): ?>
            <div class="alerta alerta-error" role="alert"><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" novalidate data-validar>
            <?= campo_csrf() ?>
            <div class="grid-2">
                <label>Nombre completo *<input name="nombre" value="<?= e($d['nombre']) ?>" required minlength="3" maxlength="80"></label>
                <label>Correo electrónico *<input type="email" name="email" value="<?= e($d['email']) ?>" required maxlength="120" autocomplete="username"></label>
                <label>Teléfono<input name="telefono" value="<?= e($d['telefono']) ?>" maxlength="20" pattern="[0-9+\-\s]{7,20}"></label>
                <label>Empresa<input name="empresa" value="<?= e($d['empresa']) ?>" maxlength="100"></label>
                <label>Contraseña *<input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password" data-fuerza></label>
                <label>Confirmar contraseña *<input type="password" name="password2" required minlength="8" maxlength="72" autocomplete="new-password" data-igual="password"></label>
            </div>
            <ul class="reglas-pass" data-reglas>
                <li data-r="len">8 caracteres o más</li><li data-r="may">Una mayúscula</li><li data-r="min">Una minúscula</li><li data-r="num">Un número</li><li data-r="esp">Un carácter especial</li>
            </ul>
            <button class="btn btn-bloque" type="submit">Crear cuenta</button>
        </form>
        <p class="auth-pie">¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
