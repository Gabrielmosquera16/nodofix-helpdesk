<?php
/** Acciones sobre usuarios (POST + CSRF): activar/desactivar y eliminar. */
require dirname(__DIR__) . '/includes/init.php';
$yo = requiere_rol(ROL_ADMIN);
solo_post();

$id = entero($_POST['id'] ?? 0);
$accion = $_POST['accion'] ?? '';
$us = fila('SELECT id, email, estado, rol_id FROM usuarios WHERE id = ?', [$id]);

if (!$us) {
    flash('error', 'El usuario no existe.');
} elseif ($id === (int)$yo['id']) {
    flash('error', 'No puedes desactivar ni eliminar tu propia cuenta.');
} elseif ($accion === 'estado') {
    $nuevo = $us['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
    $activos = (int)valor("SELECT COUNT(*) FROM tickets WHERE tecnico_id = ? AND estado IN ('ASIGNADO','EN_PROCESO','EN_ESPERA')", [$id]);
    if ($nuevo === 'INACTIVO' && $activos > 0) {
        flash('error', "No se puede desactivar: el técnico tiene $activos ticket(s) activos. Reasígnalos primero.");
    } else {
        consulta('UPDATE usuarios SET estado = ? WHERE id = ?', [$nuevo, $id]);
        registrar_bitacora('USUARIO_ESTADO', "Cambió a $nuevo a {$us['email']}");
        flash('exito', "Usuario {$us['email']} ahora está $nuevo.");
    }
} elseif ($accion === 'eliminar') {
    $tickets = (int)valor('SELECT COUNT(*) FROM tickets WHERE cliente_id = ? OR tecnico_id = ?', [$id, $id]);
    if ($tickets > 0) {
        flash('error', 'El usuario tiene tickets asociados; para conservar el historial, desactívalo en lugar de eliminarlo.');
    } else {
        try {
            consulta('DELETE FROM usuarios WHERE id = ?', [$id]);
            registrar_bitacora('USUARIO_ELIMINADO', "Eliminó al usuario {$us['email']}");
            flash('exito', 'Usuario eliminado.');
        } catch (PDOException $e) {
            flash('error', 'No se puede eliminar: el usuario tiene actividad registrada en tickets. Desactívalo en su lugar.');
        }
    }
} else {
    flash('error', 'Acción no válida.');
}
redirigir('admin/usuarios.php');
