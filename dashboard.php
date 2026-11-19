<?php
require __DIR__ . '/includes/init.php';
$u = requiere_login();
$titulo = 'Inicio';
$seccion = 'inicio';
$uid = (int)$u['id'];

// Tarjetas y lista según el rol
if ($u['rol'] === ROL_ADMIN) {
    $k = fila("SELECT
        SUM(estado IN ('ABIERTO')) sin_asignar,
        SUM(estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA')) en_atencion,
        SUM(estado IN ('ABIERTO','ASIGNADO','EN_PROCESO','EN_ESPERA') AND fecha_limite < NOW()) vencidos,
        SUM(estado = 'RESUELTO') por_confirmar
        FROM tickets");
    $tarjetas = [
        ['Sin asignar', $k['sin_asignar'], 'tickets/index.php?estado=ABIERTO', 'azul'],
        ['En atención', $k['en_atencion'], 'tickets/index.php?vista=activos', 'teal'],
        ['SLA vencido', $k['vencidos'], 'tickets/index.php?vista=vencidos', 'rojo'],
        ['Por confirmar', $k['por_confirmar'], 'tickets/index.php?estado=RESUELTO', 'ambar'],
    ];
    $lista = filas("SELECT t.id, t.codigo, t.asunto, t.estado, t.fecha_limite, p.nombre prioridad, cl.nombre cliente, te.nombre tecnico
        FROM tickets t JOIN prioridades p ON p.id = t.prioridad_id JOIN usuarios cl ON cl.id = t.cliente_id
        LEFT JOIN usuarios te ON te.id = t.tecnico_id
        WHERE t.estado IN ('ABIERTO','ASIGNADO','EN_PROCESO','EN_ESPERA')
        ORDER BY (t.fecha_limite < NOW()) DESC, p.nivel DESC, t.fecha_limite LIMIT 8");
    $tituloLista = 'Tickets que requieren atención';
    $carga = filas("SELECT u.nombre, COUNT(t.id) activos FROM usuarios u
        LEFT JOIN tickets t ON t.tecnico_id = u.id AND t.estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA')
        WHERE u.rol_id = 2 AND u.estado = 'ACTIVO' GROUP BY u.id, u.nombre ORDER BY activos DESC");
} elseif ($u['rol'] === ROL_TECNICO) {
    $k = fila("SELECT
        SUM(tecnico_id = ? AND estado = 'ASIGNADO') asignados,
        SUM(tecnico_id = ? AND estado IN ('EN_PROCESO','EN_ESPERA')) en_curso,
        SUM(tecnico_id = ? AND estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA') AND fecha_limite < NOW()) vencidos,
        SUM(tecnico_id IS NULL AND estado = 'ABIERTO') cola
        FROM tickets", [$uid, $uid, $uid]);
    $tarjetas = [
        ['Nuevos asignados', $k['asignados'], 'tickets/index.php?estado=ASIGNADO', 'azul'],
        ['En curso', $k['en_curso'], 'tickets/index.php?vista=activos', 'teal'],
        ['SLA vencido', $k['vencidos'], 'tickets/index.php?vista=vencidos', 'rojo'],
        ['Cola sin asignar', $k['cola'], 'tickets/index.php?vista=cola', 'ambar'],
    ];
    $lista = filas("SELECT t.id, t.codigo, t.asunto, t.estado, t.fecha_limite, p.nombre prioridad, cl.nombre cliente, te.nombre tecnico
        FROM tickets t JOIN prioridades p ON p.id = t.prioridad_id JOIN usuarios cl ON cl.id = t.cliente_id
        LEFT JOIN usuarios te ON te.id = t.tecnico_id
        WHERE t.tecnico_id = ? AND t.estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA')
        ORDER BY (t.fecha_limite < NOW()) DESC, p.nivel DESC, t.fecha_limite LIMIT 8", [$uid]);
    $tituloLista = 'Mis tickets activos';
} else {
    $k = fila("SELECT
        SUM(estado IN ('ABIERTO','ASIGNADO','EN_PROCESO')) activos,
        SUM(estado = 'EN_ESPERA') espera,
        SUM(estado = 'RESUELTO') por_confirmar,
        SUM(estado = 'CERRADO') cerrados
        FROM tickets WHERE cliente_id = ?", [$uid]);
    $tarjetas = [
        ['En atención', $k['activos'], 'tickets/index.php?vista=activos', 'teal'],
        ['Esperan mi respuesta', $k['espera'], 'tickets/index.php?estado=EN_ESPERA', 'ambar'],
        ['Por confirmar', $k['por_confirmar'], 'tickets/index.php?estado=RESUELTO', 'azul'],
        ['Cerrados', $k['cerrados'], 'tickets/index.php?estado=CERRADO', 'gris'],
    ];
    $lista = filas("SELECT t.id, t.codigo, t.asunto, t.estado, t.fecha_limite, p.nombre prioridad, cl.nombre cliente, te.nombre tecnico
        FROM tickets t JOIN prioridades p ON p.id = t.prioridad_id JOIN usuarios cl ON cl.id = t.cliente_id
        LEFT JOIN usuarios te ON te.id = t.tecnico_id
        WHERE t.cliente_id = ? ORDER BY COALESCE(t.actualizado_en, t.creado_en) DESC LIMIT 8", [$uid]);
    $tituloLista = 'Mis tickets recientes';
}
$modTickets = is_file(__DIR__ . '/tickets/index.php');
require __DIR__ . '/includes/header.php';
?>
<div class="cabecera-pagina">
    <div>
        <h1>Hola, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
        <p class="sub"><?= e(['ADMIN' => 'Panel de administración del sistema de soporte.', 'TECNICO' => 'Estos son los tickets que tienes a cargo.', 'CLIENTE' => 'Aquí ves el estado de tus solicitudes de soporte.'][$u['rol']]) ?></p>
    </div>
    <?php if ($u['rol'] === ROL_CLIENTE && is_file(__DIR__ . '/tickets/crear.php')): ?>
        <a class="btn" href="<?= url('tickets/crear.php') ?>">+ Nuevo ticket</a>
    <?php endif; ?>
</div>

<div class="grid-4 kpis">
    <?php foreach ($tarjetas as [$txt, $n, $href, $color]): ?>
        <?php $tag = $modTickets ? 'a' : 'div'; ?>
        <<?= $tag ?> class="kpi kpi-<?= $color ?>"<?= $modTickets ? ' href="' . url($href) . '"' : '' ?>>
            <span class="kpi-n"><?= (int)$n ?></span>
            <span class="kpi-t"><?= e($txt) ?></span>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>

<div class="<?= isset($carga) ? 'grid-dash' : '' ?>">
    <section class="panel">
        <h2><?= e($tituloLista) ?></h2>
        <?php if (!$lista): ?>
            <p class="vacio">No hay tickets para mostrar.</p>
        <?php else: ?>
            <div class="tabla-resp"><table class="tabla">
                <thead><tr><th>Código</th><th>Asunto</th><th>Prioridad</th><th>Estado</th><th><?= $u['rol'] === ROL_CLIENTE ? 'Técnico' : 'Cliente' ?></th><th>SLA</th></tr></thead>
                <tbody>
                <?php foreach ($lista as $t): ?>
                    <tr>
                        <td><?= $modTickets ? '<a href="' . url('tickets/ver.php?id=' . (int)$t['id']) . '">' . e($t['codigo']) . '</a>' : e($t['codigo']) ?></td>
                        <td><?= e($t['asunto']) ?></td>
                        <td><?= badge_prioridad($t['prioridad']) ?></td>
                        <td><?= badge_estado($t['estado']) ?></td>
                        <td><?= e($u['rol'] === ROL_CLIENTE ? ($t['tecnico'] ?? 'Por asignar') : $t['cliente']) ?></td>
                        <td><?= badge_sla($t) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </section>
    <?php if (isset($carga)): ?>
        <section class="panel">
            <h2>Carga por técnico</h2>
            <ul class="barras">
                <?php foreach ($carga as $c): $pct = min(100, round($c['activos'] / MAX_TICKETS_TECNICO * 100)); ?>
                    <li><span class="b-lbl"><?= e($c['nombre']) ?></span>
                        <span class="b-bar"><span class="b-fill w-<?= (int)(round($pct / 5) * 5) ?><?= $pct >= 100 ? ' lleno' : '' ?>"></span></span>
                        <span class="b-val"><?= (int)$c['activos'] ?>/<?= MAX_TICKETS_TECNICO ?></span></li>
                <?php endforeach; ?>
            </ul>
            <p class="ayuda">Límite de <?= MAX_TICKETS_TECNICO ?> tickets activos por técnico.</p>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
