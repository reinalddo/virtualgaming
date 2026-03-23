<?php
require_once __DIR__ . '/tenant.php';

// Conexión PDO para VirtualGaming
$tenantDatabase = tenant_database_config();
$host = (string) ($tenantDatabase['host'] ?? 'localhost');
$db   = (string) ($tenantDatabase['name'] ?? 'tvirtualgaming');
$user = (string) ($tenantDatabase['user'] ?? 'root');
$pass = (string) ($tenantDatabase['password'] ?? '');
$charset = (string) ($tenantDatabase['charset'] ?? 'utf8mb4');
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}

try {
    $roleColumnStmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'");
    $roleColumn = $roleColumnStmt ? $roleColumnStmt->fetch() : false;
    $roleType = strtolower((string) ($roleColumn['Type'] ?? ''));
    if ($roleType !== '' && strpos($roleType, "'empleado'") === false) {
        $pdo->exec("ALTER TABLE usuarios MODIFY rol ENUM('admin','empleado','usuario') NOT NULL DEFAULT 'usuario'");
    }
} catch (Throwable $exception) {
}
