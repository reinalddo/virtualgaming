<?php
require_once __DIR__ . '/tenant.php';

function create_app_mysqli_connection(): mysqli {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $tenantDatabase = tenant_database_config();
    $connection = new mysqli(
        (string) ($tenantDatabase['host'] ?? 'localhost'),
        (string) ($tenantDatabase['user'] ?? 'root'),
        (string) ($tenantDatabase['password'] ?? ''),
        (string) ($tenantDatabase['name'] ?? 'tvirtualgaming')
    );
    $connection->set_charset((string) ($tenantDatabase['charset'] ?? 'utf8mb4'));
    $connection->query("SET time_zone = '-04:00'");

    return $connection;
}

function ensure_mysqli_connection(?mysqli $connection = null): mysqli {
    if ($connection instanceof mysqli) {
        try {
            $connection->query('SELECT 1');
            return $connection;
        } catch (Throwable $e) {
            error_log('TVG MySQL reconnect triggered: ' . $e->getMessage());
            try {
                $connection->close();
            } catch (Throwable $closeError) {
            }
        }
    }

    $newConnection = create_app_mysqli_connection();
    $GLOBALS['mysqli'] = $newConnection;

    return $newConnection;
}

// Conexión a la base de datos para todos los módulos
$mysqli = create_app_mysqli_connection();
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}

$roleColumnResult = $mysqli->query("SHOW COLUMNS FROM usuarios LIKE 'rol'");
if ($roleColumnResult instanceof mysqli_result) {
    $roleColumn = $roleColumnResult->fetch_assoc();
    $roleColumnResult->free();
    $roleType = strtolower((string) ($roleColumn['Type'] ?? ''));
    if ($roleType !== '' && (strpos($roleType, "'empleado'") === false || strpos($roleType, "'influencer'") === false)) {
        $mysqli->query("ALTER TABLE usuarios MODIFY rol ENUM('admin','empleado','influencer','usuario') NOT NULL DEFAULT 'usuario'");
    }
}

$phoneColumnResult = $mysqli->query("SHOW COLUMNS FROM usuarios LIKE 'telefono'");
if ($phoneColumnResult instanceof mysqli_result) {
    $phoneColumnExists = $phoneColumnResult->fetch_assoc();
    $phoneColumnResult->free();
    if (!$phoneColumnExists) {
        $mysqli->query("ALTER TABLE usuarios ADD COLUMN telefono VARCHAR(50) NULL AFTER email");
    }
}

$lastPurchaseIdentifierColumnResult = $mysqli->query("SHOW COLUMNS FROM usuarios LIKE 'last_purchase_user_identifier'");
if ($lastPurchaseIdentifierColumnResult instanceof mysqli_result) {
    $lastPurchaseIdentifierColumnExists = $lastPurchaseIdentifierColumnResult->fetch_assoc();
    $lastPurchaseIdentifierColumnResult->free();
    if (!$lastPurchaseIdentifierColumnExists) {
        $mysqli->query("ALTER TABLE usuarios ADD COLUMN last_purchase_user_identifier VARCHAR(150) NULL AFTER telefono");
    }
}

$lastPurchasePhoneColumnResult = $mysqli->query("SHOW COLUMNS FROM usuarios LIKE 'last_purchase_phone'");
if ($lastPurchasePhoneColumnResult instanceof mysqli_result) {
    $lastPurchasePhoneColumnExists = $lastPurchasePhoneColumnResult->fetch_assoc();
    $lastPurchasePhoneColumnResult->free();
    if (!$lastPurchasePhoneColumnExists) {
        $mysqli->query("ALTER TABLE usuarios ADD COLUMN last_purchase_phone VARCHAR(50) NULL AFTER last_purchase_user_identifier");
    }
}
