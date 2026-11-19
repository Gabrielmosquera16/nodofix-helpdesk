<?php
require __DIR__ . '/includes/init.php';
if (usuario_actual()) {
    redirigir('dashboard.php');
}
$titulo = 'Soporte técnico sin vueltas';
$stats = fila("SELECT COUNT(*) total, SUM(estado IN ('RESUELTO','CERRADO')) resueltos FROM tickets");
$prom = valor('SELECT ROUND(AVG(puntuacion),1) FROM valoraciones');
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <div class="hero-txt">
        <span class="eyebrow">Mesa de ayuda para pequeñas empresas</span>
        <h1>Reporta tu problema técnico y síguelo hasta que quede resuelto.</h1>
        <p>En NodoFix HelpDesk cada solicitud se convierte en un ticket con prioridad, técnico responsable y tiempo de respuesta. Tú ves el avance en todo momento.</p>
        <div class="hero-acciones">
            <a class="btn btn-grande" href="<?= url('registro.php') ?>">Crear mi cuenta</a>
            <a class="btn btn-borde btn-grande" href="<?= url('login.php') ?>">Ya tengo cuenta</a>
        </div>
        <ul class="hero-datos">
            <li><b><?= (int)$stats['total'] ?></b><span>tickets atendidos</span></li>
            <li><b><?= (int)$stats['resueltos'] ?></b><span>resueltos</span></li>
            <li><b><?= e($prom ?: '—') ?> ★</b><span>satisfacción</span></li>
        </ul>
    </div>
    <img class="hero-img" src="<?= url('assets/img/hero.svg') ?>" alt="Técnico atendiendo tickets de soporte" width="520" height="420">
</section>

<section class="pasos">
    <h2>¿Cómo funciona?</h2>
    <div class="grid-3">
        <article class="paso"><span class="num">1</span><h3>Crea un ticket</h3><p>Describe el problema, elige la categoría y qué tan urgente es.</p></article>
        <article class="paso"><span class="num">2</span><h3>Un técnico lo toma</h3><p>Se asigna un responsable y el ticket recibe un tiempo límite según su prioridad.</p></article>
        <article class="paso"><span class="num">3</span><h3>Confirma la solución</h3><p>Conversa con el técnico, confirma cuando quede resuelto y califica la atención.</p></article>
    </div>
</section>

<section class="sla-tabla">
    <h2>Tiempos de respuesta</h2>
    <div class="grid-4">
        <?php foreach (filas('SELECT nombre, horas_sla FROM prioridades ORDER BY nivel DESC') as $p): ?>
            <div class="sla-card pri-borde-<?= strtolower(e($p['nombre'])) ?>">
                <?= badge_prioridad($p['nombre']) ?>
                <b><?= (int)$p['horas_sla'] ?> h</b>
                <span>tiempo máximo de resolución</span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
