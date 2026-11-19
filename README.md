# NodoFix HelpDesk — Entregable 2 – Prototipo funcional inicial (23 de noviembre)

Proyecto Final de **Desarrollo Web** · UTP · Grupo **1S3221** · Prof. Giovani A. Sánchez
Integrantes: Cristian Jiménez · Gabriel Mosquera · Joel Torres · Marc Buchanan

Sistema de soporte técnico (tickets, seguimientos y prioridades) en PHP 8 + PDO, MySQL, HTML, CSS y JavaScript.

## Incluido en esta entrega

- Base de datos completa (11 tablas) con datos de prueba
- Estructura ordenada: `config/`, `includes/`, `admin/`, `assets/`, `database/`
- Plantilla base: encabezado, menú según rol y pie de página
- Registro, login y logout (contraseñas con `password_hash()`, regeneración del ID de sesión, CSRF)
- Control de roles básico: `requiere_rol()`; un rol no autorizado recibe 403
- CRUD completo de usuarios (listado, búsqueda, filtros, crear, editar, eliminar, activar/desactivar)

**Pendiente para la siguiente entrega:** CRUD de categorías y de tickets, flujo de estados, evidencias de seguridad.

## Instalación en XAMPP

1. Copiar la carpeta `nodofix-helpdesk` dentro de `htdocs` (por ejemplo `C:\xampp\htdocs\nodofix-helpdesk`).
2. Iniciar **Apache** y **MySQL** desde el panel de XAMPP.
3. Abrir **phpMyAdmin** → pestaña **Importar** → seleccionar `database/nodofix_helpdesk.sql` → **Continuar**.
   El script crea la base `nodofix_helpdesk` con sus 11 tablas y los datos de prueba.
4. Si el usuario de MySQL no es `root` sin contraseña, editar `config/config.php` (`DB_USER`, `DB_PASS`).
5. Abrir `http://localhost/nodofix-helpdesk/`.

Requisitos: PHP 8.0 o superior con la extensión `pdo_mysql` (incluida en XAMPP).

## Usuarios de prueba

Todos usan la contraseña **`NodoFix#2026`**

| Rol | Correo | Notas |
|---|---|---|
| ADMIN | admin@nodofix.test | Cristian Jiménez |
| TÉCNICO | gabriel.tecnico@nodofix.test | también joel.tecnico@… y marc.tecnico@… |
| TÉCNICO (inactivo) | laura.tecnico@nodofix.test | no puede iniciar sesión |
| CLIENTE | ana@cliente.test | también luis@, maria@, carlos@, sofia@, valeria@cliente.test |
| CLIENTE (inactivo) | pedro@cliente.test | no puede iniciar sesión |

> Si se prueban intentos fallidos, la cuenta queda bloqueada 15 minutos. Para desbloquear antes: `DELETE FROM intentos_login;`

