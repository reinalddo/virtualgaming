<?php
require_once __DIR__ . '/tenant.php';

// Conexión a la base de datos para todos los módulos
$tenantDatabase = tenant_database_config();
$mysqli = new mysqli(
    (string) ($tenantDatabase['host'] ?? 'localhost'),
    (string) ($tenantDatabase['user'] ?? 'root'),
    (string) ($tenantDatabase['password'] ?? ''),
    (string) ($tenantDatabase['name'] ?? 'tvirtualgaming')
);
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$mysqli->set_charset((string) ($tenantDatabase['charset'] ?? 'utf8mb4'));
