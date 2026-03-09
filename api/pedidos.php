<?php
session_start();
if (ob_get_level() === 0) {
    ob_start();
}
header('Content-Type: application/json');
@ini_set('display_errors', '0');
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

function table_exists(mysqli $mysqli, string $tableName): bool {
    $safeName = $mysqli->real_escape_string($tableName);
    $res = $mysqli->query("SHOW TABLES LIKE '{$safeName}'");
    return $res && $res->num_rows > 0;
}

function load_mail_settings(mysqli $mysqli): array {
    $settings = [
        'correo_corporativo' => 'no-reply@tvirtualgaming.local',
        'smtp_host' => 'smtp.tuservidor.com',
        'smtp_user' => 'no-reply@tvirtualgaming.local',
        'smtp_pass' => '',
        'smtp_port' => 587,
        'smtp_secure' => 'tls',
    ];

    if (table_exists($mysqli, 'configuracion_general')) {
        $res = $mysqli->query("SELECT clave, valor FROM configuracion_general");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $key = $row['clave'] ?? '';
                if ($key !== '' && array_key_exists($key, $settings)) {
                    $settings[$key] = $row['valor'];
                }
            }
        }
    } elseif (table_exists($mysqli, 'configuracion')) {
        $res = $mysqli->query("SELECT * FROM configuracion ORDER BY id DESC LIMIT 1");
        if ($res && ($row = $res->fetch_assoc())) {
            foreach ($settings as $key => $defaultValue) {
                if (isset($row[$key]) && $row[$key] !== '') {
                    $settings[$key] = $row[$key];
                }
            }
        }
    }

    $settings['smtp_port'] = (int) ($settings['smtp_port'] ?: 587);
    $settings['smtp_secure'] = strtolower(trim((string) $settings['smtp_secure']));
    if (!in_array($settings['smtp_secure'], ['ssl', 'tls'], true)) {
        $settings['smtp_secure'] = 'tls';
    }

    return $settings;
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
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['ok' => false, 'message' => $message]);
    exit;
}

function send_app_mail(string $to, string $subject, string $html, ?string $from = null): void {
    global $mysqli;
    $settings = isset($mysqli) && $mysqli instanceof mysqli
        ? load_mail_settings($mysqli)
        : [
            'correo_corporativo' => 'no-reply@tvirtualgaming.local',
            'smtp_host' => 'smtp.tuservidor.com',
            'smtp_user' => 'no-reply@tvirtualgaming.local',
            'smtp_pass' => '',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
        ];
    $fromAddr = $from ?: ($settings['correo_corporativo'] ?: $settings['smtp_user']);

    try {
        require_once __DIR__ . '/../includes/PHPMailerAutoload.php';
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            throw new RuntimeException('PHPMailer no disponible');
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $smtp_host = $settings['smtp_host'];
        $smtp_user = $settings['smtp_user'];
        $smtp_pass = $settings['smtp_pass'];
        $smtp_port = $settings['smtp_port'];
        $smtp_secure = $settings['smtp_secure'];

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
    } catch (Throwable $e) {
        error_log('TVG mail error: ' . $e->getMessage());
        try {
            send_app_mail_via_smtp_socket($to, $subject, $html, $fromAddr, $settings);
        } catch (Throwable $smtpError) {
            error_log('TVG SMTP fallback error: ' . $smtpError->getMessage());
        }
    }
}

function smtp_read_response($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }
    return $response;
}

function smtp_expect_ok($socket, array $allowedCodes, string $context): string {
    $response = smtp_read_response($socket);
    $code = (int) substr(trim($response), 0, 3);
    if (!in_array($code, $allowedCodes, true)) {
        throw new RuntimeException($context . ': ' . trim($response));
    }
    return $response;
}

function smtp_send_command($socket, string $command, array $allowedCodes, string $context): string {
    fwrite($socket, $command . "\r\n");
    return smtp_expect_ok($socket, $allowedCodes, $context);
}

function send_app_mail_via_smtp_socket(string $to, string $subject, string $html, string $fromAddr, array $settings): void {
    $host = (string) ($settings['smtp_host'] ?? '');
    $port = (int) ($settings['smtp_port'] ?? 587);
    $secure = strtolower((string) ($settings['smtp_secure'] ?? 'tls'));
    $username = (string) ($settings['smtp_user'] ?? '');
    $password = (string) ($settings['smtp_pass'] ?? '');

    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('Configuración SMTP incompleta');
    }

    $transport = $secure === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $transport . $host . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect_ok($socket, [220], 'Conexión SMTP');
        smtp_send_command($socket, 'EHLO virtualgaming.local', [250], 'EHLO inicial');

        if ($secure === 'tls') {
            smtp_send_command($socket, 'STARTTLS', [220], 'STARTTLS');
            $cryptoEnabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new RuntimeException('No se pudo habilitar TLS');
            }
            smtp_send_command($socket, 'EHLO virtualgaming.local', [250], 'EHLO tras TLS');
        }

        smtp_send_command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
        smtp_send_command($socket, base64_encode($username), [334], 'SMTP usuario');
        smtp_send_command($socket, base64_encode($password), [235], 'SMTP contraseña');
        smtp_send_command($socket, 'MAIL FROM:<' . $fromAddr . '>', [250], 'MAIL FROM');
        smtp_send_command($socket, 'RCPT TO:<' . $to . '>', [250, 251], 'RCPT TO');
        smtp_send_command($socket, 'DATA', [354], 'DATA');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: TVirtualGaming <' . $fromAddr . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $body = implode("\r\n", $headers) . "\r\n\r\n" . $html;
        $body = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $body);
        fwrite($socket, $body . "\r\n.\r\n");
        smtp_expect_ok($socket, [250], 'Envío de mensaje');
        smtp_send_command($socket, 'QUIT', [221], 'QUIT');
    } finally {
        fclose($socket);
    }
}

function sanitize_str(?string $value, int $max = 255): ?string {
    if ($value === null) return null;
    $clean = trim($value);
    if ($clean === '') return null;
    return substr($clean, 0, $max);
}

function email_escape(?string $value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function render_order_email(string $title, string $eyebrow, string $messageHtml, array $orderData, string $accent = '#22d3ee'): string {
    $orderId = email_escape((string) ($orderData['order_id'] ?? ''));
    $gameName = email_escape($orderData['game_name'] ?? '');
    $packName = email_escape($orderData['pack_name'] ?? '');
    $packAmount = email_escape($orderData['pack_amount'] ?? '');
    $currency = email_escape($orderData['currency'] ?? '');
    $price = email_escape($orderData['price'] ?? '');
    $userIdentifier = email_escape($orderData['user_identifier'] ?? '');
    $email = email_escape($orderData['email'] ?? '');
    $coupon = trim((string) ($orderData['coupon'] ?? ''));
    $status = email_escape($orderData['status'] ?? '');
    $couponRow = $coupon !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Cupón</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . email_escape($coupon) . '</td></tr>'
        : '';
    $statusRow = $status !== ''
        ? '<tr><td style="padding:10px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Estado</td><td style="padding:10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $status . '</td></tr>'
        : '';

    return '<!doctype html>'
        . '<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . email_escape($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#0a0f14;font-family:Arial,Helvetica,sans-serif;color:#e2e8f0;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0a0f14;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#111827;border:1px solid #164e63;border-radius:20px;overflow:hidden;box-shadow:0 0 0 1px rgba(34,211,238,0.08),0 20px 40px rgba(0,0,0,0.35);">'
        . '<tr><td style="padding:28px 32px;background:linear-gradient(135deg,#0b1220 0%,#102133 55%,#0f3b46 100%);text-align:center;">'
        . '<div style="color:#67e8f9;font-size:12px;letter-spacing:4px;text-transform:uppercase;margin-bottom:10px;">' . email_escape($eyebrow) . '</div>'
        . '<div style="color:#ffffff;font-size:32px;line-height:1.2;font-weight:700;margin-bottom:8px;">TVirtualGaming</div>'
        . '<div style="display:inline-block;padding:6px 14px;border:1px solid ' . $accent . ';border-radius:999px;color:' . $accent . ';font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;">Notificación de pedido</div>'
        . '</td></tr>'
        . '<tr><td style="padding:32px;">'
        . '<h1 style="margin:0 0 14px;color:#f8fafc;font-size:28px;line-height:1.2;">' . email_escape($title) . '</h1>'
        . '<div style="color:#cbd5e1;font-size:15px;line-height:1.7;margin-bottom:24px;">' . $messageHtml . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;background:#0f172a;border:1px solid #1e293b;border-radius:16px;overflow:hidden;">'
        . '<tr><td colspan="2" style="padding:16px 20px;background:#0b1220;color:#67e8f9;font-size:16px;font-weight:700;">Pedido #' . $orderId . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Juego</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $gameName . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Paquete</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $packName . ($packAmount !== '' ? ' (' . $packAmount . ')' : '') . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Total</td><td style="padding:10px 20px 10px 0;color:' . $accent . ';font-size:18px;font-weight:700;text-align:right;border-bottom:1px solid #1e293b;">' . $currency . ' ' . $price . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Cliente</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $userIdentifier . '</td></tr>'
        . '<tr><td style="padding:10px 0 10px 20px;color:#94a3b8;font-size:14px;border-bottom:1px solid #1e293b;">Correo</td><td style="padding:10px 20px 10px 0;color:#e2e8f0;font-size:14px;text-align:right;border-bottom:1px solid #1e293b;">' . $email . '</td></tr>'
        . $couponRow
        . $statusRow
        . '</table>'
        . '<div style="margin-top:24px;padding:16px 18px;background:#0b1220;border:1px solid #1e293b;border-radius:14px;color:#94a3b8;font-size:13px;line-height:1.6;">'
        . 'Este correo fue generado automáticamente por TVirtualGaming. Si necesitas revisar el pedido, ingresa al panel o responde desde los canales de soporte configurados.'
        . '</div>'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
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

    $customerMessage = '<p style="margin:0 0 10px;">Hemos recibido tu pedido correctamente y ya quedó registrado en el sistema.</p>'
        . '<p style="margin:0;">Te notificaremos cuando el estado cambie o cuando el equipo procese la entrega.</p>';
    $adminMessage = '<p style="margin:0 0 10px;">Se generó un nuevo pedido y ya está disponible para revisión en el panel administrativo.</p>'
        . '<p style="margin:0;">Valida los datos del cliente y procede con la gestión correspondiente.</p>';
    $customerHtml = render_order_email('Pedido recibido', 'Cliente', $customerMessage, [
        'order_id' => $order_id,
        'game_name' => $game_name,
        'pack_name' => $pack_name,
        'pack_amount' => $pack_amount_text,
        'currency' => $currency,
        'price' => number_format($price, 2, '.', ','),
        'user_identifier' => $user_identifier,
        'email' => $email,
        'coupon' => $cupon,
        'status' => 'Pendiente',
    ]);
    $adminHtml = render_order_email('Nuevo pedido', 'Administrador', $adminMessage, [
        'order_id' => $order_id,
        'game_name' => $game_name,
        'pack_name' => $pack_name,
        'pack_amount' => $pack_amount_text,
        'currency' => $currency,
        'price' => number_format($price, 2, '.', ','),
        'user_identifier' => $user_identifier,
        'email' => $email,
        'coupon' => $cupon,
        'status' => 'Pendiente',
    ], '#34d399');
    send_app_mail($email, "Pedido recibido #{$order_id}", $customerHtml);
    send_app_mail($adminEmail, "Nuevo pedido #{$order_id}", $adminHtml);

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
    $statusLabel = ucfirst($new_status);
    $customerStatusMessage = '<p style="margin:0 0 10px;">El estado de tu pedido fue actualizado correctamente.</p>'
        . '<p style="margin:0;">Estado actual: <strong style="color:#22d3ee;">' . email_escape($statusLabel) . '</strong>.</p>';
    $adminStatusMessage = '<p style="margin:0 0 10px;">Se actualizó el estado de un pedido desde el panel administrativo.</p>'
        . '<p style="margin:0;">Estado actual: <strong style="color:#34d399;">' . email_escape($statusLabel) . '</strong>.</p>';
    $customerStatusHtml = render_order_email('Estado actualizado', 'Cliente', $customerStatusMessage, [
        'order_id' => $order_id,
        'game_name' => $order['juego_nombre'],
        'pack_name' => $order['paquete_nombre'],
        'pack_amount' => $order['paquete_cantidad'],
        'currency' => $order['moneda'],
        'price' => number_format((float) $order['precio'], 2, '.', ','),
        'user_identifier' => $order['user_identifier'],
        'email' => $order['email'],
        'status' => $statusLabel,
    ]);
    $adminStatusHtml = render_order_email('Pedido actualizado', 'Administrador', $adminStatusMessage, [
        'order_id' => $order_id,
        'game_name' => $order['juego_nombre'],
        'pack_name' => $order['paquete_nombre'],
        'pack_amount' => $order['paquete_cantidad'],
        'currency' => $order['moneda'],
        'price' => number_format((float) $order['precio'], 2, '.', ','),
        'user_identifier' => $order['user_identifier'],
        'email' => $order['email'],
        'coupon' => null,
        'status' => $statusLabel,
    ], '#34d399');
    send_app_mail($order['email'], "Estado actualizado #{$order_id}", $customerStatusHtml);
    send_app_mail($adminEmail, "Pedido #{$order_id} cambiado a {$new_status}", $adminStatusHtml);

    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(['ok' => true, 'message' => 'Estado actualizado', 'estado' => $new_status, 'order_id' => $order_id]);
    exit;
}

json_error('Acción no soportada', 422);
?>
