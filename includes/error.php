<?php
/** Página de error genérica (403/404). Usa $titulo y $mensaje. */
$titulo = $titulo ?? 'Error';
require ROOT . '/includes/header.php';
?>
<section class="error-pagina">
    <img src="<?= url('assets/img/candado.svg') ?>" alt="" width="120" height="120">
    <h1><?= e($titulo) ?></h1>
    <p><?= e($mensaje ?? 'Ocurrió un problema al procesar la solicitud.') ?></p>
    <a class="btn" href="<?= url(usuario_actual() ? 'dashboard.php' : 'index.php') ?>">Volver al inicio</a>
</section>
<?php require ROOT . '/includes/footer.php'; ?>
