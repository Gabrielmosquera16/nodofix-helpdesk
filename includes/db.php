<?php
/**
 * Conexión PDO única. Todas las consultas del sistema usan sentencias preparadas.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // preparadas reales en el servidor
            ]);
            $pdo->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            error_log('Error de conexión: ' . $e->getMessage());
            http_response_code(500);
            exit('No fue posible conectar con la base de datos. Revise config/config.php.');
        }
    }
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el PDOStatement. */
function consulta(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fila(string $sql, array $params = []): ?array
{
    $r = consulta($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function filas(string $sql, array $params = []): array
{
    return consulta($sql, $params)->fetchAll();
}

function valor(string $sql, array $params = [])
{
    return consulta($sql, $params)->fetchColumn();
}
