<?php
require __DIR__ . '/includes/init.php';
if (usuario_actual()) {
    redirigir('dashboard.php');
}

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $email = texto($_POST, 'email', 120);
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if ($email === '' || $password === '') {
        $error = 'Ingresa tu correo y tu contraseña.';
    } else {
        $error = intentar_login($email, $password);
        if ($error === null) {
            flash('exito', 'Bienvenido(a), ' . usuario_actual()['nombre'] . '.');
            redirigir('dashboard.php');
        }
    }
}
$titulo = 'Iniciar sesión';
require __DIR__ . '/includes/header.php';
?>
<section class="auth">
    <div class="auth-card">
        <img src="<?= url('assets/img/logo.svg') ?>" alt="" width="56" height="56" class="auth-logo">
        <h1>Iniciar sesión</h1>
        <p class="sub">Accede para crear y dar seguimiento a tus tickets.</p>
        <?php if ($error): ?><div class="alerta alerta-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate data-validar>
            <?= campo_csrf() ?>
            <label>Correo electrónico
                <input type="email" name="email" value="<?= e($email) ?>" required maxlength="120" autocomplete="username" autofocus>
            </label>
            <label>Contraseña
                <input type="password" name="password" required maxlength="72" autocomplete="current-password">
            </label>
            <button class="btn btn-bloque" type="submit">Entrar</button>
        </form>
        <p class="auth-pie">¿No tienes cuenta? <a href="<?= url('registro.php') ?>">Regístrate</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
