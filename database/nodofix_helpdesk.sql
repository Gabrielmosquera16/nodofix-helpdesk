-- =====================================================================
--  NodoFix HelpDesk - Sistema de soporte técnico
--  Proyecto Final - Desarrollo Web (Grupo 1S3221) - UTP 2026
--  Motor: MySQL 8 / MariaDB 10.4+ (InnoDB, utf8mb4)
-- =====================================================================

DROP DATABASE IF EXISTS nodofix_helpdesk;
CREATE DATABASE nodofix_helpdesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nodofix_helpdesk;

-- ---------------------------------------------------------------------
-- 1. roles: catálogo de roles del sistema
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id          TINYINT UNSIGNED PRIMARY KEY,
    nombre      VARCHAR(20)  NOT NULL UNIQUE,
    descripcion VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. usuarios
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id        TINYINT UNSIGNED NOT NULL,
    nombre        VARCHAR(80)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono      VARCHAR(20)  NULL,
    empresa       VARCHAR(100) NULL,
    estado        ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_login  DATETIME NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id),
    INDEX idx_usuarios_nombre (nombre),
    INDEX idx_usuarios_rol_estado (rol_id, estado)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. categorias: tipo de problema reportado
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(60)  NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    estado      ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. prioridades: nivel y tiempo de respuesta comprometido (SLA)
-- ---------------------------------------------------------------------
CREATE TABLE prioridades (
    id        TINYINT UNSIGNED PRIMARY KEY,
    nombre    VARCHAR(20) NOT NULL UNIQUE,
    nivel     TINYINT UNSIGNED NOT NULL,          -- 1 = más baja
    horas_sla SMALLINT UNSIGNED NOT NULL,         -- horas para resolver
    CONSTRAINT chk_prioridad_sla CHECK (horas_sla > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. tickets: módulo principal
-- ---------------------------------------------------------------------
CREATE TABLE tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo         VARCHAR(20) NULL UNIQUE,
    cliente_id     INT UNSIGNED NOT NULL,
    tecnico_id     INT UNSIGNED NULL,
    categoria_id   INT UNSIGNED NOT NULL,
    prioridad_id   TINYINT UNSIGNED NOT NULL,
    asunto         VARCHAR(120) NOT NULL,
    descripcion    TEXT NOT NULL,
    equipo         VARCHAR(100) NULL,                -- equipo o servicio afectado (opcional)
    estado         ENUM('ABIERTO','ASIGNADO','EN_PROCESO','EN_ESPERA','RESUELTO','CERRADO','CANCELADO')
                   NOT NULL DEFAULT 'ABIERTO',
    solucion       TEXT NULL,
    fecha_limite   DATETIME NOT NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    resuelto_en    DATETIME NULL,
    cerrado_en     DATETIME NULL,
    CONSTRAINT fk_tickets_cliente   FOREIGN KEY (cliente_id)   REFERENCES usuarios(id),
    CONSTRAINT fk_tickets_tecnico   FOREIGN KEY (tecnico_id)   REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    CONSTRAINT fk_tickets_prioridad FOREIGN KEY (prioridad_id) REFERENCES prioridades(id),
    INDEX idx_tickets_estado (estado),
    INDEX idx_tickets_asunto (asunto),
    INDEX idx_tickets_cliente_estado (cliente_id, estado),
    INDEX idx_tickets_tecnico_estado (tecnico_id, estado),
    INDEX idx_tickets_fecha_limite (fecha_limite)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. seguimientos: conversación del ticket (notas internas opcionales)
-- ---------------------------------------------------------------------
CREATE TABLE seguimientos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id  INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    mensaje    TEXT NOT NULL,
    es_interno TINYINT(1) NOT NULL DEFAULT 0,       -- 1 = solo visible para staff
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_seg_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_seg_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_seg_ticket (ticket_id, creado_en)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. historial_estados: trazabilidad de cada cambio de estado
-- ---------------------------------------------------------------------
CREATE TABLE historial_estados (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NOT NULL,
    estado_anterior VARCHAR(15) NULL,
    estado_nuevo    VARCHAR(15) NOT NULL,
    nota            VARCHAR(255) NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_hist_ticket (ticket_id, creado_en)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. valoraciones: encuesta de satisfacción al cerrar el ticket
-- ---------------------------------------------------------------------
CREATE TABLE valoraciones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL UNIQUE,         -- una valoración por ticket
    cliente_id  INT UNSIGNED NOT NULL,
    puntuacion  TINYINT UNSIGNED NOT NULL,
    comentario  VARCHAR(500) NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_val_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_val_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
    CONSTRAINT chk_val_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. notificaciones: avisos internos para cada usuario
-- ---------------------------------------------------------------------
CREATE TABLE notificaciones (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    ticket_id  INT UNSIGNED NULL,
    mensaje    VARCHAR(255) NOT NULL,
    leida      TINYINT(1) NOT NULL DEFAULT 0,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_not_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_not_ticket  FOREIGN KEY (ticket_id)  REFERENCES tickets(id)  ON DELETE CASCADE,
    INDEX idx_not_usuario (usuario_id, leida)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. intentos_login: control de fuerza bruta
-- ---------------------------------------------------------------------
CREATE TABLE intentos_login (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(120) NOT NULL,
    ip        VARCHAR(45)  NOT NULL,
    exitoso   TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intentos_email (email, creado_en),
    INDEX idx_intentos_ip (ip, creado_en)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. bitacora: auditoría de acciones relevantes
-- ---------------------------------------------------------------------
CREATE TABLE bitacora (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    accion     VARCHAR(40)  NOT NULL,
    detalle    VARCHAR(255) NULL,
    ip         VARCHAR(45)  NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_bit_accion (accion, creado_en)
) ENGINE=InnoDB;
USE nodofix_helpdesk;
SET NAMES utf8mb4;

INSERT INTO roles (id,nombre,descripcion) VALUES
(1,'ADMIN','Administra usuarios, catálogos, reportes y todos los tickets'),
(2,'TECNICO','Atiende y resuelve los tickets asignados'),
(3,'CLIENTE','Registra solicitudes de soporte y da seguimiento');

INSERT INTO prioridades (id,nombre,nivel,horas_sla) VALUES
(1,'BAJA',1,72),(2,'MEDIA',2,48),(3,'ALTA',3,24),(4,'CRITICA',4,8);

INSERT INTO categorias (nombre,descripcion,estado) VALUES
('Hardware','Fallas físicas de equipos: PC, laptops, impresoras, periféricos','ACTIVO'),
('Software','Instalación, errores o licencias de programas','ACTIVO'),
('Redes e Internet','Conectividad, Wi-Fi, VPN, cableado','ACTIVO'),
('Correo y cuentas','Acceso a correo, contraseñas bloqueadas, cuentas de usuario','ACTIVO'),
('Seguridad','Malware, phishing, accesos sospechosos','ACTIVO'),
('Impresión','Impresoras, escáneres y multifuncionales','ACTIVO'),
('Otros','Solicitudes que no encajan en otra categoría','INACTIVO');

INSERT INTO usuarios (rol_id,nombre,email,password_hash,telefono,empresa,estado,creado_en) VALUES
(1,'Cristian Jiménez','admin@nodofix.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6000-1001',NULL,'ACTIVO',NOW() - INTERVAL 86400 MINUTE),
(2,'Gabriel Mosquera','gabriel.tecnico@nodofix.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6000-2001',NULL,'ACTIVO',NOW() - INTERVAL 84960 MINUTE),
(2,'Joel Torres','joel.tecnico@nodofix.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6000-2002',NULL,'ACTIVO',NOW() - INTERVAL 83520 MINUTE),
(2,'Marc Buchanan','marc.tecnico@nodofix.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6000-2003',NULL,'ACTIVO',NOW() - INTERVAL 82080 MINUTE),
(2,'Laura Pinzón','laura.tecnico@nodofix.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6000-2004',NULL,'INACTIVO',NOW() - INTERVAL 80640 MINUTE),
(3,'Ana Castillo','ana@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0001','Farmacia La Salud','ACTIVO',NOW() - INTERVAL 79200 MINUTE),
(3,'Luis Herrera','luis@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0002','Constructora Herrera','ACTIVO',NOW() - INTERVAL 77760 MINUTE),
(3,'María Rodríguez','maria@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0003','Estudio Contable MR','ACTIVO',NOW() - INTERVAL 76320 MINUTE),
(3,'Carlos Vega','carlos@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0004',NULL,'ACTIVO',NOW() - INTERVAL 74880 MINUTE),
(3,'Sofía Delgado','sofia@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0005','Colegio Nuevo Horizonte','ACTIVO',NOW() - INTERVAL 73440 MINUTE),
(3,'Pedro Ríos','pedro@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0006','Taller Ríos','INACTIVO',NOW() - INTERVAL 72000 MINUTE),
(3,'Valeria Núñez','valeria@cliente.test','$2y$10$BessHL5xA30c9S3z.wRbYuiZeSN8yKp4fYCrrWt5IzJM8CMuYdJra','6111-0007','Clínica Dental Sonrisa','ACTIVO',NOW() - INTERVAL 70560 MINUTE);

INSERT INTO tickets (id,codigo,cliente_id,tecnico_id,categoria_id,prioridad_id,asunto,descripcion,equipo,estado,solucion,fecha_limite,creado_en,resuelto_en,cerrado_en) VALUES
(1,'NFX-00001',6,2,1,2,'La laptop no enciende','Desde esta mañana la laptop no enciende, solo parpadea la luz de carga.','Laptop Dell Latitude 5420','RESUELTO','Se reemplazó el componente defectuoso y se probó el equipo.',NOW() - INTERVAL 164 MINUTE,NOW() - INTERVAL 3044 MINUTE,NOW() - INTERVAL 1892 MINUTE,NULL),
(2,'NFX-00002',7,3,1,2,'Pantalla con líneas verticales','El monitor muestra líneas verticales de colores al encender.','Monitor LG 24"','EN_PROCESO',NULL,NOW() - INTERVAL 25840 MINUTE,NOW() - INTERVAL 28720 MINUTE,NULL,NULL),
(3,'NFX-00003',8,4,2,2,'Error al abrir Excel','Excel se cierra solo al abrir archivos con macros.','Microsoft 365','EN_PROCESO',NULL,NOW() - INTERVAL 29527 MINUTE,NOW() - INTERVAL 32407 MINUTE,NULL,NULL),
(4,'NFX-00004',9,2,2,2,'Instalar software de facturación','Necesitamos instalar el sistema de facturación en dos equipos nuevos.',NULL,'EN_ESPERA',NULL,NOW() - INTERVAL 9651 MINUTE,NOW() - INTERVAL 12531 MINUTE,NULL,NULL),
(5,'NFX-00005',10,NULL,3,1,'Sin conexión a Internet en recepción','Los equipos de recepción no tienen Internet desde ayer.','Router oficina principal','ABIERTO',NULL,NOW() - INTERVAL -3031 MINUTE,NOW() - INTERVAL 1289 MINUTE,NULL,NULL),
(6,'NFX-00006',12,4,3,3,'Wi-Fi muy lento','La red inalámbrica está muy lenta en la sala de reuniones.','Access point sala B','CERRADO','Se reemplazó el componente defectuoso y se probó el equipo.',NOW() - INTERVAL 14838 MINUTE,NOW() - INTERVAL 16278 MINUTE,NOW() - INTERVAL 15230 MINUTE,NOW() - INTERVAL 14968 MINUTE),
(7,'NFX-00007',6,2,3,4,'Configurar VPN para teletrabajo','Requiero acceso por VPN a la red de la empresa desde casa.',NULL,'CERRADO','Se reinstaló el programa y se actualizaron los controladores.',NOW() - INTERVAL 7148 MINUTE,NOW() - INTERVAL 7628 MINUTE,NOW() - INTERVAL 7408 MINUTE,NOW() - INTERVAL 7353 MINUTE),
(8,'NFX-00008',7,NULL,4,2,'Contraseña de correo bloqueada','No puedo entrar al correo, dice que la cuenta está bloqueada.','Correo corporativo','CANCELADO',NULL,NOW() - INTERVAL 80 MINUTE,NOW() - INTERVAL 2960 MINUTE,NULL,NOW() - INTERVAL 0 MINUTE),
(9,'NFX-00009',8,4,4,2,'Crear cuenta para nuevo colaborador','Ingresa una nueva asistente el lunes y necesita correo y usuario.',NULL,'EN_ESPERA',NULL,NOW() - INTERVAL 32390 MINUTE,NOW() - INTERVAL 35270 MINUTE,NULL,NULL),
(10,'NFX-00010',9,2,5,1,'Posible correo de phishing','Recibí un correo pidiendo actualizar mis datos bancarios.','Correo corporativo','CERRADO','Se limpió el equipo y se ejecutó un análisis completo del antivirus.',NOW() - INTERVAL 6435 MINUTE,NOW() - INTERVAL 10755 MINUTE,NOW() - INTERVAL 6911 MINUTE,NOW() - INTERVAL 5950 MINUTE),
(11,'NFX-00011',10,3,5,3,'Antivirus detectó una amenaza','El antivirus muestra una alerta de troyano en la PC de contabilidad.','PC Contabilidad','EN_ESPERA',NULL,NOW() - INTERVAL 17190 MINUTE,NOW() - INTERVAL 18630 MINUTE,NULL,NULL),
(12,'NFX-00012',12,4,6,1,'Impresora atasca el papel','La impresora atasca el papel en cada impresión.','HP LaserJet M404','ASIGNADO',NULL,NOW() - INTERVAL -2612 MINUTE,NOW() - INTERVAL 1708 MINUTE,NULL,NULL),
(13,'NFX-00013',6,2,6,2,'Escáner no envía a correo','El multifuncional no envía los escaneos al correo.','Ricoh MP 3055','CERRADO','Se reconfiguró el router y se verificó la conexión.',NOW() - INTERVAL 23917 MINUTE,NOW() - INTERVAL 26797 MINUTE,NOW() - INTERVAL 24525 MINUTE,NOW() - INTERVAL 23957 MINUTE),
(14,'NFX-00014',7,3,2,4,'Actualizar Windows','El equipo pide actualizaciones pero fallan al instalarse.','PC Gerencia','ASIGNADO',NULL,NOW() - INTERVAL 84 MINUTE,NOW() - INTERVAL 564 MINUTE,NULL,NULL),
(15,'NFX-00015',8,NULL,1,3,'Teclado con teclas que no responden','Varias teclas del teclado no funcionan.','Teclado inalámbrico Logitech','ABIERTO',NULL,NOW() - INTERVAL -1380 MINUTE,NOW() - INTERVAL 60 MINUTE,NULL,NULL),
(16,'NFX-00016',9,2,1,2,'La laptop no enciende','Desde esta mañana la laptop no enciende, solo parpadea la luz de carga.','Laptop Dell Latitude 5420','RESUELTO','Se reemplazó el componente defectuoso y se probó el equipo.',NOW() - INTERVAL 1043 MINUTE,NOW() - INTERVAL 3923 MINUTE,NOW() - INTERVAL 843 MINUTE,NULL),
(17,'NFX-00017',10,NULL,1,2,'Pantalla con líneas verticales','El monitor muestra líneas verticales de colores al encender.','Monitor LG 24"','CANCELADO',NULL,NOW() - INTERVAL 28414 MINUTE,NOW() - INTERVAL 31294 MINUTE,NULL,NOW() - INTERVAL 28849 MINUTE),
(18,'NFX-00018',12,NULL,2,4,'Error al abrir Excel','Excel se cierra solo al abrir archivos con macros.','Microsoft 365','ABIERTO',NULL,NOW() - INTERVAL 148 MINUTE,NOW() - INTERVAL 628 MINUTE,NULL,NULL),
(19,'NFX-00019',6,2,2,4,'Instalar software de facturación','Necesitamos instalar el sistema de facturación en dos equipos nuevos.',NULL,'CERRADO','Se restableció la contraseña y se activó la verificación en dos pasos.',NOW() - INTERVAL 27595 MINUTE,NOW() - INTERVAL 28075 MINUTE,NOW() - INTERVAL 27727 MINUTE,NOW() - INTERVAL 27640 MINUTE),
(20,'NFX-00020',7,3,3,2,'Sin conexión a Internet en recepción','Los equipos de recepción no tienen Internet desde ayer.','Router oficina principal','CERRADO','Se limpió el equipo y se ejecutó un análisis completo del antivirus.',NOW() - INTERVAL 14214 MINUTE,NOW() - INTERVAL 17094 MINUTE,NOW() - INTERVAL 16118 MINUTE,NOW() - INTERVAL 15874 MINUTE),
(21,'NFX-00021',8,4,3,2,'Wi-Fi muy lento','La red inalámbrica está muy lenta en la sala de reuniones.','Access point sala B','ASIGNADO',NULL,NOW() - INTERVAL -2181 MINUTE,NOW() - INTERVAL 699 MINUTE,NULL,NULL),
(22,'NFX-00022',9,2,3,4,'Configurar VPN para teletrabajo','Requiero acceso por VPN a la red de la empresa desde casa.',NULL,'CERRADO','Se reinstaló el programa y se actualizaron los controladores.',NOW() - INTERVAL 6420 MINUTE,NOW() - INTERVAL 6900 MINUTE,NOW() - INTERVAL 6668 MINUTE,NOW() - INTERVAL 6610 MINUTE),
(23,'NFX-00023',10,3,4,2,'Contraseña de correo bloqueada','No puedo entrar al correo, dice que la cuenta está bloqueada.','Correo corporativo','CERRADO','Se reconfiguró el router y se verificó la conexión.',NOW() - INTERVAL 31004 MINUTE,NOW() - INTERVAL 33884 MINUTE,NOW() - INTERVAL 32044 MINUTE,NOW() - INTERVAL 31584 MINUTE),
(24,'NFX-00024',12,4,4,2,'Crear cuenta para nuevo colaborador','Ingresa una nueva asistente el lunes y necesita correo y usuario.',NULL,'RESUELTO','Se restableció la contraseña y se activó la verificación en dos pasos.',NOW() - INTERVAL 1285 MINUTE,NOW() - INTERVAL 4165 MINUTE,NOW() - INTERVAL 1977 MINUTE,NULL),
(25,'NFX-00025',6,2,5,4,'Posible correo de phishing','Recibí un correo pidiendo actualizar mis datos bancarios.','Correo corporativo','EN_PROCESO',NULL,NOW() - INTERVAL 30760 MINUTE,NOW() - INTERVAL 31240 MINUTE,NULL,NULL),
(26,'NFX-00026',7,3,5,3,'Antivirus detectó una amenaza','El antivirus muestra una alerta de troyano en la PC de contabilidad.','PC Contabilidad','EN_PROCESO',NULL,NOW() - INTERVAL 13475 MINUTE,NOW() - INTERVAL 14915 MINUTE,NULL,NULL),
(27,'NFX-00027',8,4,6,3,'Impresora atasca el papel','La impresora atasca el papel en cada impresión.','HP LaserJet M404','RESUELTO','Se reinstaló el programa y se actualizaron los controladores.',NOW() - INTERVAL 941 MINUTE,NOW() - INTERVAL 2381 MINUTE,NOW() - INTERVAL 1921 MINUTE,NULL),
(28,'NFX-00028',9,NULL,6,2,'Escáner no envía a correo','El multifuncional no envía los escaneos al correo.','Ricoh MP 3055','ABIERTO',NULL,NOW() - INTERVAL -2499 MINUTE,NOW() - INTERVAL 381 MINUTE,NULL,NULL),
(29,'NFX-00029',10,NULL,2,2,'Actualizar Windows','El equipo pide actualizaciones pero fallan al instalarse.','PC Gerencia','ABIERTO',NULL,NOW() - INTERVAL -2718 MINUTE,NOW() - INTERVAL 162 MINUTE,NULL,NULL),
(30,'NFX-00030',12,4,1,2,'Teclado con teclas que no responden','Varias teclas del teclado no funcionan.','Teclado inalámbrico Logitech','EN_PROCESO',NULL,NOW() - INTERVAL 15023 MINUTE,NOW() - INTERVAL 17903 MINUTE,NULL,NULL),
(31,'NFX-00031',6,2,1,3,'La laptop no enciende','Desde esta mañana la laptop no enciende, solo parpadea la luz de carga.','Laptop Dell Latitude 5420','CERRADO','Se reemplazó el componente defectuoso y se probó el equipo.',NOW() - INTERVAL 3991 MINUTE,NOW() - INTERVAL 5431 MINUTE,NOW() - INTERVAL 4351 MINUTE,NOW() - INTERVAL 4081 MINUTE),
(32,'NFX-00032',7,3,1,4,'Pantalla con líneas verticales','El monitor muestra líneas verticales de colores al encender.','Monitor LG 24"','ASIGNADO',NULL,NOW() - INTERVAL 645 MINUTE,NOW() - INTERVAL 1125 MINUTE,NULL,NULL);

INSERT INTO historial_estados (ticket_id,usuario_id,estado_anterior,estado_nuevo,nota,creado_en) VALUES
(1,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 3044 MINUTE),
(1,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 2756 MINUTE),
(1,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 2468 MINUTE),
(1,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 1892 MINUTE),
(2,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 28720 MINUTE),
(2,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 28245 MINUTE),
(2,3,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 27770 MINUTE),
(3,8,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 32407 MINUTE),
(3,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 32083 MINUTE),
(3,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 31759 MINUTE),
(4,9,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 12531 MINUTE),
(4,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 11955 MINUTE),
(4,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 11379 MINUTE),
(4,2,'EN_PROCESO','EN_ESPERA','En espera de respuesta del cliente',NOW() - INTERVAL 10803 MINUTE),
(5,10,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 1289 MINUTE),
(6,12,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 16278 MINUTE),
(6,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 16016 MINUTE),
(6,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 15754 MINUTE),
(6,4,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 15230 MINUTE),
(6,12,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 14968 MINUTE),
(7,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 7628 MINUTE),
(7,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 7573 MINUTE),
(7,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 7518 MINUTE),
(7,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 7408 MINUTE),
(7,6,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 7353 MINUTE),
(8,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 2960 MINUTE),
(8,7,'ABIERTO','CANCELADO','Cancelado por el cliente: ya no es necesario',NOW() - INTERVAL 2368 MINUTE),
(9,8,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 35270 MINUTE),
(9,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 34954 MINUTE),
(9,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 34638 MINUTE),
(9,4,'EN_PROCESO','EN_ESPERA','En espera de respuesta del cliente',NOW() - INTERVAL 34322 MINUTE),
(10,9,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 10755 MINUTE),
(10,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 9794 MINUTE),
(10,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 8833 MINUTE),
(10,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 6911 MINUTE),
(10,9,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 5950 MINUTE),
(11,10,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 18630 MINUTE),
(11,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 18350 MINUTE),
(11,3,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 18070 MINUTE),
(11,3,'EN_PROCESO','EN_ESPERA','En espera de respuesta del cliente',NOW() - INTERVAL 17790 MINUTE),
(12,12,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 1708 MINUTE),
(12,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 1367 MINUTE),
(13,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 26797 MINUTE),
(13,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 26229 MINUTE),
(13,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 25661 MINUTE),
(13,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 24525 MINUTE),
(13,6,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 23957 MINUTE),
(14,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 564 MINUTE),
(14,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 503 MINUTE),
(15,8,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 60 MINUTE),
(16,9,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 3923 MINUTE),
(16,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 3153 MINUTE),
(16,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 2383 MINUTE),
(16,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 843 MINUTE),
(17,10,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 31294 MINUTE),
(17,10,'ABIERTO','CANCELADO','Cancelado por el cliente: ya no es necesario',NOW() - INTERVAL 30805 MINUTE),
(18,12,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 628 MINUTE),
(19,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 28075 MINUTE),
(19,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 27988 MINUTE),
(19,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 27901 MINUTE),
(19,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 27727 MINUTE),
(19,6,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 27640 MINUTE),
(20,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 17094 MINUTE),
(20,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 16850 MINUTE),
(20,3,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 16606 MINUTE),
(20,3,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 16118 MINUTE),
(20,7,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 15874 MINUTE),
(21,8,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 699 MINUTE),
(21,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 560 MINUTE),
(22,9,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 6900 MINUTE),
(22,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 6842 MINUTE),
(22,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 6784 MINUTE),
(22,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 6668 MINUTE),
(22,9,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 6610 MINUTE),
(23,10,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 33884 MINUTE),
(23,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 33424 MINUTE),
(23,3,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 32964 MINUTE),
(23,3,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 32044 MINUTE),
(23,10,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 31584 MINUTE),
(24,12,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 4165 MINUTE),
(24,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 3618 MINUTE),
(24,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 3071 MINUTE),
(24,4,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 1977 MINUTE),
(25,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 31240 MINUTE),
(25,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 31183 MINUTE),
(25,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 31126 MINUTE),
(26,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 14915 MINUTE),
(26,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 14645 MINUTE),
(26,3,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 14375 MINUTE),
(27,8,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 2381 MINUTE),
(27,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 2266 MINUTE),
(27,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 2151 MINUTE),
(27,4,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 1921 MINUTE),
(28,9,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 381 MINUTE),
(29,10,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 162 MINUTE),
(30,12,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 17903 MINUTE),
(30,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 17680 MINUTE),
(30,4,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 17457 MINUTE),
(31,6,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 5431 MINUTE),
(31,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 5161 MINUTE),
(31,2,'ASIGNADO','EN_PROCESO','Inicio de atención',NOW() - INTERVAL 4891 MINUTE),
(31,2,'EN_PROCESO','RESUELTO','Solución aplicada',NOW() - INTERVAL 4351 MINUTE),
(31,6,'RESUELTO','CERRADO','Cliente confirmó la solución',NOW() - INTERVAL 4081 MINUTE),
(32,7,NULL,'ABIERTO','Ticket creado',NOW() - INTERVAL 1125 MINUTE),
(32,1,'ABIERTO','ASIGNADO','Asignado al técnico',NOW() - INTERVAL 1092 MINUTE);

INSERT INTO seguimientos (ticket_id,usuario_id,mensaje,es_interno,creado_en) VALUES
(1,2,'Estoy revisando el equipo, le aviso en breve.',0,NOW() - INTERVAL 2463 MINUTE),
(1,6,'¿Hay alguna novedad con mi caso?',0,NOW() - INTERVAL 2438 MINUTE),
(1,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 2428 MINUTE),
(2,3,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 27765 MINUTE),
(2,7,'Gracias, quedo atento.',0,NOW() - INTERVAL 27740 MINUTE),
(3,4,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 31754 MINUTE),
(3,8,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 31729 MINUTE),
(4,2,'Se aplicó la configuración, quedo pendiente de su confirmación.',0,NOW() - INTERVAL 11374 MINUTE),
(4,9,'Pueden venir después de las 2 pm.',0,NOW() - INTERVAL 11349 MINUTE),
(4,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 11339 MINUTE),
(6,4,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 15749 MINUTE),
(6,12,'Gracias, quedo atento.',0,NOW() - INTERVAL 15724 MINUTE),
(7,2,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 7513 MINUTE),
(7,6,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 7488 MINUTE),
(7,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 7478 MINUTE),
(9,4,'Estoy revisando el equipo, le aviso en breve.',0,NOW() - INTERVAL 34633 MINUTE),
(9,8,'¿Hay alguna novedad con mi caso?',0,NOW() - INTERVAL 34608 MINUTE),
(10,2,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 8828 MINUTE),
(10,9,'Gracias, quedo atento.',0,NOW() - INTERVAL 8803 MINUTE),
(10,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 8793 MINUTE),
(11,3,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 18065 MINUTE),
(11,10,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 18040 MINUTE),
(13,2,'Estoy revisando el equipo, le aviso en breve.',0,NOW() - INTERVAL 25656 MINUTE),
(13,6,'¿Hay alguna novedad con mi caso?',0,NOW() - INTERVAL 25631 MINUTE),
(13,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 25621 MINUTE),
(16,2,'Se aplicó la configuración, quedo pendiente de su confirmación.',0,NOW() - INTERVAL 2378 MINUTE),
(16,9,'Pueden venir después de las 2 pm.',0,NOW() - INTERVAL 2353 MINUTE),
(16,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 2343 MINUTE),
(19,2,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 27896 MINUTE),
(19,6,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 27871 MINUTE),
(19,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 27861 MINUTE),
(20,3,'Se aplicó la configuración, quedo pendiente de su confirmación.',0,NOW() - INTERVAL 16601 MINUTE),
(20,7,'Pueden venir después de las 2 pm.',0,NOW() - INTERVAL 16576 MINUTE),
(22,2,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 6779 MINUTE),
(22,9,'Gracias, quedo atento.',0,NOW() - INTERVAL 6754 MINUTE),
(22,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 6744 MINUTE),
(23,3,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 32959 MINUTE),
(23,10,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 32934 MINUTE),
(24,4,'Se aplicó la configuración, quedo pendiente de su confirmación.',0,NOW() - INTERVAL 3066 MINUTE),
(24,12,'Pueden venir después de las 2 pm.',0,NOW() - INTERVAL 3041 MINUTE),
(25,2,'Estoy revisando el equipo, le aviso en breve.',0,NOW() - INTERVAL 31121 MINUTE),
(25,6,'¿Hay alguna novedad con mi caso?',0,NOW() - INTERVAL 31096 MINUTE),
(25,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 31086 MINUTE),
(26,3,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 14370 MINUTE),
(26,7,'Gracias, quedo atento.',0,NOW() - INTERVAL 14345 MINUTE),
(27,4,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 2146 MINUTE),
(27,8,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 2121 MINUTE),
(30,4,'Se reemplazó la pieza dañada, por favor confirme.',0,NOW() - INTERVAL 17452 MINUTE),
(30,12,'Gracias, quedo atento.',0,NOW() - INTERVAL 17427 MINUTE),
(31,2,'Necesito que reinicie el equipo y me indique si persiste.',0,NOW() - INTERVAL 4886 MINUTE),
(31,6,'El problema sigue ocurriendo.',0,NOW() - INTERVAL 4861 MINUTE),
(31,2,'Revisar garantía con el proveedor.',1,NOW() - INTERVAL 4851 MINUTE);

INSERT INTO valoraciones (ticket_id,cliente_id,puntuacion,comentario,creado_en) VALUES
(6,12,4,'Buen servicio, resolvieron el problema.',NOW() - INTERVAL 14958 MINUTE),
(7,6,5,'Excelente atención, muy rápidos.',NOW() - INTERVAL 7343 MINUTE),
(10,9,4,'Buen servicio, resolvieron el problema.',NOW() - INTERVAL 5940 MINUTE),
(13,6,4,'Buen servicio, resolvieron el problema.',NOW() - INTERVAL 23947 MINUTE),
(19,6,3,'Se resolvió, pero tardó más de lo esperado.',NOW() - INTERVAL 27630 MINUTE),
(22,9,5,'Excelente atención, muy rápidos.',NOW() - INTERVAL 6600 MINUTE),
(23,10,5,'Excelente atención, muy rápidos.',NOW() - INTERVAL 31574 MINUTE),
(31,6,4,'Buen servicio, resolvieron el problema.',NOW() - INTERVAL 4071 MINUTE);

INSERT INTO notificaciones (usuario_id,ticket_id,mensaje,leida,creado_en) VALUES
(6,1,'Tu ticket NFX-00001 fue marcado como RESUELTO. Confirma la solución.',0,NOW() - INTERVAL 1892 MINUTE),
(3,2,'Se te asignó el ticket NFX-00002',0,NOW() - INTERVAL 28245 MINUTE),
(4,3,'Se te asignó el ticket NFX-00003',0,NOW() - INTERVAL 32083 MINUTE),
(4,12,'Se te asignó el ticket NFX-00012',0,NOW() - INTERVAL 1367 MINUTE),
(3,14,'Se te asignó el ticket NFX-00014',0,NOW() - INTERVAL 503 MINUTE),
(9,16,'Tu ticket NFX-00016 fue marcado como RESUELTO. Confirma la solución.',0,NOW() - INTERVAL 843 MINUTE),
(4,21,'Se te asignó el ticket NFX-00021',0,NOW() - INTERVAL 560 MINUTE),
(12,24,'Tu ticket NFX-00024 fue marcado como RESUELTO. Confirma la solución.',0,NOW() - INTERVAL 1977 MINUTE),
(2,25,'Se te asignó el ticket NFX-00025',0,NOW() - INTERVAL 31183 MINUTE),
(3,26,'Se te asignó el ticket NFX-00026',0,NOW() - INTERVAL 14645 MINUTE),
(8,27,'Tu ticket NFX-00027 fue marcado como RESUELTO. Confirma la solución.',0,NOW() - INTERVAL 1921 MINUTE),
(4,30,'Se te asignó el ticket NFX-00030',0,NOW() - INTERVAL 17680 MINUTE),
(3,32,'Se te asignó el ticket NFX-00032',0,NOW() - INTERVAL 1092 MINUTE);

INSERT INTO bitacora (usuario_id,accion,detalle,ip,creado_en) VALUES
(1,'LOGIN','Inicio de sesión correcto','127.0.0.1',NOW() - INTERVAL 600 MINUTE),
(1,'USUARIO_CREADO','Creó al usuario laura.tecnico@nodofix.test','127.0.0.1',NOW() - INTERVAL 590 MINUTE),
(1,'USUARIO_ESTADO','Cambió a INACTIVO a pedro@cliente.test','127.0.0.1',NOW() - INTERVAL 580 MINUTE),
(1,'CATEGORIA_ESTADO','Cambió a INACTIVO la categoría Otros','127.0.0.1',NOW() - INTERVAL 570 MINUTE);
