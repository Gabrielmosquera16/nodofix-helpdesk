<?php
/**
 * Utilidades generales: URLs, mensajes flash, bitácora, notificaciones,
 * formatos y reglas del flujo de estados de los tickets.
 */

function url(string $ruta = ''): string
{
    return BASE_URL . ltrim($ruta, '/');
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function obtener_flash(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

/** Guarda una acción relevante en la bitácora de auditoría. */
function registrar_bitacora(string $accion, string $detalle = ''): void
{
    try {
        consulta(
            'INSERT INTO bitacora (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)',
            [$_SESSION['usuario_id'] ?? null, $accion, mb_substr($detalle, 0, 255), ip_cliente()]
        );
    } catch (Throwable $e) {
        error_log('Bitácora: ' . $e->getMessage());
    }
}

function notificar(int $usuarioId, ?int $ticketId, string $mensaje): void
{
    consulta('INSERT INTO notificaciones (usuario_id, ticket_id, mensaje) VALUES (?, ?, ?)', [$usuarioId, $ticketId, $mensaje]);
}

function notificaciones_sin_leer(int $usuarioId): int
{
    return (int)valor('SELECT COUNT(*) FROM notificaciones WHERE usuario_id = ? AND leida = 0', [$usuarioId]);
}

/** Lee un entero positivo de GET/POST; 0 si no es válido. */
function entero($v): int
{
    return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}

/** Texto recortado de un arreglo de entrada (POST/GET). */
function texto(array $origen, string $clave, int $max = 255): string
{
    $v = $origen[$clave] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function fecha($f, bool $hora = true): string
{
    if (!$f) return '—';
    return date($hora ? 'd/m/Y h:i a' : 'd/m/Y', strtotime($f));
}

function hace(string $f): string
{
    $s = time() - strtotime($f);
    if ($s < 60) return 'hace un momento';
    if ($s < 3600) return 'hace ' . intdiv($s, 60) . ' min';
    if ($s < 86400) return 'hace ' . intdiv($s, 3600) . ' h';
    if ($s < 86400 * 30) return 'hace ' . intdiv($s, 86400) . ' d';
    return fecha($f, false);
}

// ---------------------------------------------------------------- Tickets
const ESTADOS = [
    'ABIERTO'    => 'Abierto',
    'ASIGNADO'   => 'Asignado',
    'EN_PROCESO' => 'En proceso',
    'EN_ESPERA'  => 'En espera',
    'RESUELTO'   => 'Resuelto',
    'CERRADO'    => 'Cerrado',
    'CANCELADO'  => 'Cancelado',
];
const ESTADOS_ACTIVOS = ['ABIERTO', 'ASIGNADO', 'EN_PROCESO', 'EN_ESPERA'];

/**
 * Transiciones permitidas: estado actual => [estado destino => roles que pueden hacerlo].
 * Además de esto, el técnico solo actúa sobre sus tickets y el cliente sobre los suyos.
 */
const TRANSICIONES = [
    'ABIERTO'    => ['ASIGNADO' => ['ADMIN', 'TECNICO'], 'CANCELADO' => ['ADMIN', 'CLIENTE']],
    'ASIGNADO'   => ['EN_PROCESO' => ['ADMIN', 'TECNICO'], 'ABIERTO' => ['ADMIN'], 'CANCELADO' => ['ADMIN']],
    'EN_PROCESO' => ['EN_ESPERA' => ['ADMIN', 'TECNICO'], 'RESUELTO' => ['ADMIN', 'TECNICO'], 'CANCELADO' => ['ADMIN']],
    'EN_ESPERA'  => ['EN_PROCESO' => ['ADMIN', 'TECNICO', 'CLIENTE'], 'CANCELADO' => ['ADMIN']],
    'RESUELTO'   => ['CERRADO' => ['ADMIN', 'CLIENTE'], 'EN_PROCESO' => ['ADMIN', 'CLIENTE']],
    'CERRADO'    => [],
    'CANCELADO'  => [],
];

/** Texto del botón para cada transición según quién la ejecuta. */
function etiqueta_transicion(string $desde, string $hacia, string $rol): string
{
    if ($rol === 'CLIENTE') {
        return [
            'CANCELADO'  => 'Cancelar ticket',
            'CERRADO'    => 'Confirmar solución',
            'EN_PROCESO' => $desde === 'RESUELTO' ? 'No se resolvió, reabrir' : 'Ya respondí, continuar',
        ][$hacia] ?? ESTADOS[$hacia];
    }
    return [
        'ASIGNADO'   => 'Asignar',
        'EN_PROCESO' => $desde === 'RESUELTO' ? 'Reabrir' : 'Iniciar / continuar atención',
        'EN_ESPERA'  => 'Poner en espera del cliente',
        'RESUELTO'   => 'Marcar como resuelto',
        'CERRADO'    => 'Cerrar ticket',
        'CANCELADO'  => 'Cancelar ticket',
        'ABIERTO'    => 'Devolver a la cola',
    ][$hacia];
}

function badge_estado(string $estado): string
{
    return '<span class="badge est-' . strtolower(e($estado)) . '">' . e(ESTADOS[$estado] ?? $estado) . '</span>';
}

function nombre_rol(string $r): string
{
    return ['ADMIN' => 'Administrador', 'TECNICO' => 'Técnico', 'CLIENTE' => 'Cliente'][$r] ?? $r;
}

function nombre_prioridad(string $p): string
{
    return ['BAJA' => 'Baja', 'MEDIA' => 'Media', 'ALTA' => 'Alta', 'CRITICA' => 'Crítica'][$p] ?? $p;
}

function badge_prioridad(string $p): string
{
    return '<span class="badge pri-' . strtolower(e($p)) . '">' . e(nombre_prioridad($p)) . '</span>';
}

/** Indicador de SLA: vencido, por vencer (< 4 h) o a tiempo. */
function badge_sla(array $t): string
{
    if (!in_array($t['estado'], ESTADOS_ACTIVOS, true)) {
        return '';
    }
    $restante = strtotime($t['fecha_limite']) - time();
    if ($restante < 0) return '<span class="sla sla-vencido">SLA vencido</span>';
    if ($restante < 4 * 3600) return '<span class="sla sla-riesgo">Vence ' . e(date('h:i a', strtotime($t['fecha_limite']))) . '</span>';
    return '<span class="sla sla-ok">A tiempo</span>';
}

function estrellas(int $n): string
{
    return '<span class="estrellas" aria-label="' . $n . ' de 5">' . str_repeat('★', $n) . '<span class="off">' . str_repeat('★', 5 - $n) . '</span></span>';
}

/** Paginación simple: devuelve [limit, offset, paginas]. */
function paginar(int $total, int $porPagina = 10): array
{
    $paginas = max(1, (int)ceil($total / $porPagina));
    $pag = min($paginas, max(1, entero($_GET['pag'] ?? 1)));
    return [$porPagina, ($pag - 1) * $porPagina, $paginas, $pag];
}

function enlaces_paginacion(int $paginas, int $actual): string
{
    if ($paginas <= 1) return '';
    $html = '<nav class="paginacion" aria-label="Paginación">';
    for ($i = 1; $i <= $paginas; $i++) {
        $q = $_GET;
        $q['pag'] = $i;
        $cls = $i === $actual ? ' class="actual"' : '';
        $html .= '<a' . $cls . ' href="?' . e(http_build_query($q)) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Acceso a un ticket según rol y propiedad. Devuelve el ticket o null. */
function ticket_visible(int $id, array $u): ?array
{
    $t = fila(
        "SELECT t.*, c.nombre categoria, p.nombre prioridad, p.horas_sla,
                cl.nombre cliente, cl.email cliente_email, cl.empresa cliente_empresa, cl.telefono cliente_tel,
                te.nombre tecnico
         FROM tickets t
         JOIN categorias c   ON c.id = t.categoria_id
         JOIN prioridades p  ON p.id = t.prioridad_id
         JOIN usuarios cl    ON cl.id = t.cliente_id
         LEFT JOIN usuarios te ON te.id = t.tecnico_id
         WHERE t.id = ?",
        [$id]
    );
    if (!$t) return null;
    if ($u['rol'] === ROL_ADMIN) return $t;
    if ($u['rol'] === ROL_CLIENTE && (int)$t['cliente_id'] === (int)$u['id']) return $t;
    // El técnico ve los suyos y la cola de tickets abiertos sin asignar
    if ($u['rol'] === ROL_TECNICO && ((int)$t['tecnico_id'] === (int)$u['id'] || ($t['tecnico_id'] === null && $t['estado'] === 'ABIERTO'))) return $t;
    return null;
}

/** Calcula fecha límite según las horas de SLA de la prioridad. */
function fecha_limite_para(int $prioridadId, ?string $desde = null): string
{
    $horas = (int)valor('SELECT horas_sla FROM prioridades WHERE id = ?', [$prioridadId]);
    return date('Y-m-d H:i:s', strtotime(($desde ?? 'now') . " +$horas hours"));
}
