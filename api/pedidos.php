<?php
session_start();
header('Content-Type: application/json');
@ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db_connect.php';

function ensure_pedidos_table(mysqli $mysqli): void {
    $create = "CREATE TABLE IF NOT EXISTS pedidos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_slug VARCHAR(80) DEFAULT NULL,
        juego_id INT DEFAULT NULL,
        juego_nombre VARCHAR(180) DEFAULT NULL,
        paquete_nombre VARCHAR(180) DEFAULT NULL,
        paquete_cantidad VARCHAR(80) DEFAULT NULL,
        moneda VARCHAR(20) DEFAULT NULL,
        precio DECIMAL(12,2) NOT NULL DEFAULT 0,
        user_identifier VARCHAR(150) DEFAULT NULL,
        email VARCHAR(180) DEFAULT NULL,
        cupon VARCHAR(60) DEFAULT NULL,
        estado ENUM('pendiente','pagado','enviado','cancelado') NOT NULL DEFAULT 'pendiente',
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_estado (estado),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $mysqli->query($create);

    // Migración defensiva: asegurar columnas si la tabla ya existía con otro esquema
    $neededCols = [
        'tenant_slug' => "ALTER TABLE pedidos ADD COLUMN tenant_slug VARCHAR(80) NULL AFTER id",
        'juego_id' => "ALTER TABLE pedidos ADD COLUMN juego_id INT NULL AFTER tenant_slug",
        'juego_nombre' => "ALTER TABLE pedidos ADD COLUMN juego_nombre VARCHAR(180) NULL AFTER juego_id",
        'paquete_nombre' => "ALTER TABLE pedidos ADD COLUMN paquete_nombre VARCHAR(180) NULL AFTER juego_nombre",
        'paquete_cantidad' => "ALTER TABLE pedidos ADD COLUMN paquete_cantidad VARCHAR(80) NULL AFTER paquete_nombre",
        'moneda' => "ALTER TABLE pedidos ADD COLUMN moneda VARCHAR(20) NULL AFTER paquete_cantidad",
        'precio' => "ALTER TABLE pedidos ADD COLUMN precio DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER moneda",
        'user_identifier' => "ALTER TABLE pedidos ADD COLUMN user_identifier VARCHAR(150) NULL AFTER precio",
        'email' => "ALTER TABLE pedidos ADD COLUMN email VARCHAR(180) NULL AFTER user_identifier",
        'cupon' => "ALTER TABLE pedidos ADD COLUMN cupon VARCHAR(60) NULL AFTER email",
        'estado' => "ALTER TABLE pedidos ADD COLUMN estado ENUM('pendiente','pagado','enviado','cancelado') NOT NULL DEFAULT 'pendiente' AFTER cupon",
        'creado_en' => "ALTER TABLE pedidos ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER estado",
        'actualizado_en' => "ALTER TABLE pedidos ADD COLUMN actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en"
    ];
    $colResult = $mysqli->query("SHOW COLUMNS FROM pedidos");
    $existing = [];
    if ($colResult) {
        while ($row = $colResult->fetch_assoc()) {
            $existing[$row['Field']] = true;
        }
    }
    foreach ($neededCols as $col => $alterSql) {
        if (!isset($existing[$col])) {
            $mysqli->query($alterSql);
        }
    }
}

function coupon_table_exists(mysqli $mysqli): bool {
    $res = $mysqli->query("SHOW TABLES LIKE 'cupones'");
    return $res && $res->num_rows > 0;
}

function fetch_valid_coupon(mysqli $mysqli, string $code): ?array {
    if ($code === '' || !coupon_table_exists($mysqli)) {
        return null;
    }
    $stmt = $mysqli->prepare("SELECT * FROM cupones WHERE codigo = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $res = $stmt->get_result();
    $coupon = $res ? $res->fetch_assoc() : null;
    if (!$coupon) return null;
    if (empty($coupon['activo'])) return null;
    if (!empty($coupon['fecha_expiracion']) && strtotime($coupon['fecha_expiracion']) < time()) return null;
    if (!empty($coupon['limite_usos']) && isset($coupon['usos_actuales']) && $coupon['usos_actuales'] >= $coupon['limite_usos']) return null;
    return $coupon;
}

function apply_coupon_to_price(float $price, array $coupon): float {
    $discounted = $price;
    $value = floatval($coupon['valor_descuento'] ?? 0);
    $type = $coupon['tipo_descuento'] ?? 'porcentaje';
    if ($value <= 0) return $price;
    if ($type === 'fijo') {
        $discounted = max(0, $price - $value);
    } else {
        $discounted = max(0, $price - ($price * ($value / 100)));
    }
    return $discounted;
}

function json_error(string $message, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function send_app_mail(string $to, string $subject, string $html, ?string $from = null): void {
    require_once __DIR__ . '/../includes/PHPMailerAutoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    // Obtener configuración SMTP desde la base de datos
    global $mysqli;
    $smtp_host = 'smtp.tuservidor.com';
    $smtp_user = 'no-reply@tvirtualgaming.local';
    $smtp_pass = '';
    $smtp_port = 587;
    $smtp_secure = 'tls';
    $fromAddr = $from ?: $smtp_user;
    $resCfg = $mysqli->query("SELECT * FROM configuracion LIMIT 1");
    if ($resCfg && $cfg = $resCfg->fetch_assoc()) {
        $smtp_host = $cfg['smtp_host'] ?: $smtp_host;
        $smtp_user = $cfg['smtp_user'] ?: $smtp_user;
        $smtp_pass = $cfg['smtp_pass'] ?: $smtp_pass;
        $smtp_port = $cfg['smtp_port'] ?: $smtp_port;
        $smtp_secure = $cfg['smtp_secure'] ?: $smtp_secure;
        $fromAddr = $cfg['correo_corporativo'] ?: $smtp_user;
    }
    try {
        $mail->isSMTP();
        $mail->Host = $smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = $smtp_secure;
        $mail->Port = $smtp_port;
        $mail->setFrom($fromAddr, 'TVirtualGaming');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->send();
    } catch (Exception $e) {
        // Puedes loguear el error si lo deseas
    }
}

function sanitize_str(?string $value, int $max = 255): ?string {
    if ($value === null) return null;
    $clean = trim($value);
    if ($clean === '') return null;
    return substr($clean, 0, $max);
}

function normalize_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function is_valid_coupon_code(string $value): bool {
    return $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
if (!$action) {
    json_error('Acción no especificada', 422);
}

ensure_pedidos_table($mysqli);

if ($action === 'create') {
    $game_id = isset($_POST['game_id']) ? intval($_POST['game_id']) : null;
    // Si no viene game_name, intentar obtenerlo por ID
    $game_name = sanitize_str($_POST['game_name'] ?? null, 180);
    $pack_name = sanitize_str($_POST['pack_name'] ?? null, 180);
    $pack_amount_text = sanitize_str($_POST['pack_amount'] ?? null, 80); // texto descriptivo
    $pack_amount_num = 1;
    if ($pack_amount_text !== null && is_numeric($pack_amount_text)) {
        $pack_amount_num = intval($pack_amount_text);
    }
    $currency = sanitize_str($_POST['currency'] ?? null, 20);
    $price_raw = str_replace([',', ' '], '', $_POST['price'] ?? '0');
    $price = is_numeric($price_raw) ? floatval($price_raw) : 0;
    $user_identifier = sanitize_str($_POST['user_identifier'] ?? null, 150);
    $email = sanitize_str($_POST['email'] ?? null, 180);
    $cuponInput = sanitize_str($_POST['coupon'] ?? null, 60);
    $cupon = null;
    if ($cuponInput !== null) {
        if (!is_valid_coupon_code($cuponInput)) {
            json_error('El cupón solo puede contener letras y números, sin espacios ni caracteres especiales.');
        }
        $cupon = normalize_coupon_code($cuponInput);
    }
    $tenant_slug = sanitize_str($_POST['tenant_slug'] ?? null, 80);

    $missing = [];
    if (!$game_name && $game_id) {
        $stmtG = $mysqli->prepare('SELECT nombre FROM juegos WHERE id=? LIMIT 1');
        if ($stmtG) {
            $stmtG->bind_param('i', $game_id);
            $stmtG->execute();
            $resG = $stmtG->get_result();
            $rowG = $resG ? $resG->fetch_assoc() : null;
            if ($rowG && !empty($rowG['nombre'])) {
                $game_name = $rowG['nombre'];
            }
        }
    }

    if (!$game_name) $missing[] = 'game_name';
    if (!$pack_name) $missing[] = 'pack_name';
    if (!$currency) $missing[] = 'currency';
    if (!$price || $price <= 0) $missing[] = 'price';
    if (!$user_identifier) $missing[] = 'user_identifier';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $missing[] = 'email';
    if (!empty($missing)) {
        json_error('Faltan datos obligatorios del pedido: ' . implode(', ', $missing));
    }

    // Validar y aplicar cupón si existe
    if ($cupon) {
        $couponData = fetch_valid_coupon($mysqli, $cupon);
        if (!$couponData) {
            json_error('Cupón inválido o vencido');
        }
        // Solo aplicar el cupón si el precio recibido es el base
        $precio_base = floatval($_POST['pack_base'] ?? 0);
        if ($precio_base > 0 && abs($price - $precio_base) < 0.01) {
            $price = apply_coupon_to_price($price, $couponData);
        }
        // Registrar uso del cupón (best effort)
        if (isset($couponData['id'])) {
            $upd = $mysqli->prepare("UPDATE cupones SET usos_actuales = COALESCE(usos_actuales,0) + 1 WHERE id = ?");
            if ($upd) {
                $upd->bind_param('i', $couponData['id']);
                $upd->execute();
            }
        }
        // Aseguramos que el cupón se inserte como string, no como null
        $cupon = $couponData['codigo'];
    } else {
        $cupon = null;
    }

    $stmt = $mysqli->prepare("INSERT INTO pedidos (tenant_slug, juego_id, juego_nombre, paquete_nombre, paquete_cantidad, moneda, precio, user_identifier, email, cupon, cantidad, estado) VALUES (?,?,?,?,?,?,?,?,?,?,?, 'pendiente')");
    if (!$stmt) {
        json_error('No se pudo preparar el pedido');
    }
    $stmt->bind_param('sissssdsssi', $tenant_slug, $game_id, $game_name, $pack_name, $pack_amount_text, $currency, $price, $user_identifier, $email, $cupon, $pack_amount_num);
    if (!$stmt->execute()) {
        json_error('No se pudo guardar el pedido');
    }
    $order_id = $mysqli->insert_id;
    // Obtener correo del primer usuario admin
    $adminEmail = null;
    $resAdmin = $mysqli->query("SELECT email FROM usuarios WHERE rol='admin' AND email IS NOT NULL AND email != '' LIMIT 1");
    if ($resAdmin && $rowAdmin = $resAdmin->fetch_assoc()) {
        $adminEmail = $rowAdmin['email'];
    } else {
        $adminEmail = 'admin@tvirtualgaming.local';
    }

    $summary = "<strong>Pedido #{$order_id}</strong><br>Juego: {$game_name}<br>Paquete: {$pack_name} ({$pack_amount})<br>Total: {$currency} {$price}<br>Cliente: {$user_identifier} ({$email})";
    send_app_mail($email, "Pedido recibido #{$order_id}", "<p>Hemos recibido tu pedido.</p><p>{$summary}</p><p>Estado: pendiente</p>");
    send_app_mail($adminEmail, "Nuevo pedido #{$order_id}", "<p>Se generó un nuevo pedido.</p><p>{$summary}</p>");

    echo json_encode([
        'ok' => true,
        'message' => 'Pedido registrado',
        'order_id' => $order_id,
        'estado' => 'pendiente'
    ]);
    exit;
}

if ($action === 'update_status') {
    if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
        json_error('No autorizado', 403);
    }
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = sanitize_str($_POST['estado'] ?? null, 20);
    $valid = ['pendiente','pagado','enviado','cancelado'];
    if (!$order_id || !in_array($new_status, $valid, true)) {
        json_error('Datos de estado inválidos');
    }

    $res = $mysqli->prepare('SELECT id, email, user_identifier, juego_nombre, paquete_nombre, paquete_cantidad, moneda, precio, estado FROM pedidos WHERE id=? LIMIT 1');
    $res->bind_param('i', $order_id);
    $res->execute();
    $order = $res->get_result()->fetch_assoc();
    if (!$order) {
        json_error('Pedido no encontrado', 404);
    }

    $stmt = $mysqli->prepare('UPDATE pedidos SET estado=? WHERE id=?');
    $stmt->bind_param('si', $new_status, $order_id);
    $stmt->execute();

    $adminEmail = getenv('TVG_ADMIN_EMAIL') ?: 'admin@tvirtualgaming.local';
    $summary = "<strong>Pedido #{$order_id}</strong><br>Juego: {$order['juego_nombre']}<br>Paquete: {$order['paquete_nombre']} ({$order['paquete_cantidad']})<br>Total: {$order['moneda']} {$order['precio']}<br>Cliente: {$order['user_identifier']} ({$order['email']})";
    send_app_mail($order['email'], "Estado actualizado #{$order_id}", "<p>El estado de tu pedido ahora es: <strong>{$new_status}</strong>.</p><p>{$summary}</p>");
    send_app_mail($adminEmail, "Pedido #{$order_id} cambiado a {$new_status}", "<p>Se actualizó el pedido.</p><p>{$summary}</p>");

    echo json_encode(['ok' => true, 'message' => 'Estado actualizado', 'estado' => $new_status]);
    exit;
}

json_error('Acción no soportada', 422);
?>
