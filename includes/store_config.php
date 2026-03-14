<?php

function store_config_descriptions(): array {
    return [
        'correo_corporativo' => 'Correo usado para notificaciones',
        'smtp_host' => 'Host SMTP para envío de correos',
        'smtp_user' => 'Usuario SMTP',
        'smtp_pass' => 'Contraseña SMTP',
        'smtp_port' => 'Puerto SMTP',
        'smtp_secure' => 'Tipo de seguridad SMTP',
        'nombre_prefijo' => 'Texto superior del encabezado de la tienda',
        'nombre_tienda' => 'Nombre principal visible de la tienda',
        'logo_tienda' => 'Ruta del logo visible en el encabezado',
        'facebook' => 'URL de Facebook de la tienda',
        'instagram' => 'URL de Instagram de la tienda',
        'whatsapp' => 'Número o enlace de WhatsApp de la tienda',
        'mensaje_whatsapp' => 'Mensaje predefinido para el botón flotante de WhatsApp',
        'whatsapp_channel' => 'URL del canal de WhatsApp de la tienda',
        'ff_bank_posicion' => 'Posicion para la conexion al banco de Free Fire',
        'ff_bank_token' => 'Token para la conexion al banco de Free Fire',
        'ff_bank_clave' => 'Clave para la conexion al banco de Free Fire',
        'ff_api_usuario' => 'Usuario para la API de Free Fire',
        'ff_api_clave' => 'Clave para la API de Free Fire',
        'ff_api_tipo' => 'Tipo para la API de Free Fire',
    ];
}

function store_config_defaults(): array {
    return [
        'correo_corporativo' => '',
        'smtp_host' => '',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_port' => '587',
        'smtp_secure' => 'tls',
        'nombre_prefijo' => 'TIENDA',
        'nombre_tienda' => 'TVirtualGaming',
        'logo_tienda' => '',
        'facebook' => '',
        'instagram' => '',
        'whatsapp' => '',
        'mensaje_whatsapp' => '',
        'whatsapp_channel' => '',
        'ff_bank_posicion' => '0',
        'ff_bank_token' => '',
        'ff_bank_clave' => '',
        'ff_api_usuario' => '',
        'ff_api_clave' => '',
        'ff_api_tipo' => 'recargaFreefire',
    ];
}

function store_config_db(): mysqli {
    global $mysqli;

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        require_once __DIR__ . '/db_connect.php';
    }

    return $mysqli;
}

function store_config_all(bool $refresh = false): array {
    static $cache = null;

    if ($refresh || $cache === null) {
        $cache = store_config_defaults();
        $mysqli = store_config_db();
        $res = $mysqli->query('SELECT clave, valor FROM configuracion_general');
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $cache[$row['clave']] = $row['valor'];
            }
        }
    }

    return $cache;
}

function store_config_get(string $key, ?string $default = null): string {
    $config = store_config_all();
    if (array_key_exists($key, $config)) {
        return (string) $config[$key];
    }

    return $default ?? '';
}

function store_config_normalize_social_url(string $value): string {
    return trim($value);
}

function store_config_is_valid_social_url(string $value): bool {
    $normalized = store_config_normalize_social_url($value);
    if ($normalized === '') {
        return false;
    }

    if (filter_var($normalized, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $scheme = strtolower((string) parse_url($normalized, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true);
}

function store_config_normalize_whatsapp(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $trimmed);
    if ($digits === null || $digits === '') {
        return '';
    }

    return '+' . $digits;
}

function store_config_is_valid_whatsapp(string $value): bool {
    $normalized = store_config_normalize_whatsapp($value);
    if ($normalized === '') {
        return false;
    }

    return preg_match('/^\+[1-9]\d{9,14}$/', $normalized) === 1;
}

function store_config_whatsapp_link(string $value): string {
    if (!store_config_is_valid_whatsapp($value)) {
        return '';
    }

    $normalized = store_config_normalize_whatsapp($value);
    return 'https://wa.me/' . ltrim($normalized, '+');
}

function store_config_normalize_whatsapp_message(string $value): string {
    $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return $normalized;
}

function store_config_whatsapp_link_with_message(string $value, string $message = ''): string {
    $baseLink = store_config_whatsapp_link($value);
    if ($baseLink === '') {
        return '';
    }

    $normalizedMessage = store_config_normalize_whatsapp_message($message);
    if ($normalizedMessage === '') {
        return $baseLink;
    }

    return $baseLink . '?text=' . rawurlencode($normalizedMessage);
}

function store_config_upsert(string $key, string $value, ?string $description = null): bool {
    $mysqli = store_config_db();
    $descriptions = store_config_descriptions();
    $resolvedDescription = $description ?? ($descriptions[$key] ?? null);

    $stmt = $mysqli->prepare(
        'INSERT INTO configuracion_general (clave, valor, descripcion) VALUES (?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = COALESCE(VALUES(descripcion), descripcion)'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sss', $key, $value, $resolvedDescription);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        store_config_all(true);
    }

    return $ok;
}

function store_config_delete(string $key): bool {
    $mysqli = store_config_db();
    $stmt = $mysqli->prepare('DELETE FROM configuracion_general WHERE clave = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $key);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        store_config_all(true);
    }

    return $ok;
}

function store_config_is_managed_logo_path(string $relativePath): bool {
    return str_starts_with($relativePath, '/assets/img/store/');
}

function store_config_delete_logo_file(string $relativePath): void {
    if ($relativePath === '' || !store_config_is_managed_logo_path($relativePath)) {
        return;
    }

    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function store_config_store_logo_upload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No se pudo cargar el logo.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'El archivo del logo no es válido.'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El logo no puede superar 2 MB.'];
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'El logo debe ser una imagen válida.'];
    }

    $mime = $imageInfo['mime'] ?? '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        return ['success' => false, 'message' => 'Formato de logo no permitido. Usa JPG, PNG, WEBP o GIF.'];
    }

    $targetDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'store';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['success' => false, 'message' => 'No se pudo crear la carpeta del logo.'];
    }

    $fileName = 'store-logo-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return ['success' => false, 'message' => 'No se pudo guardar el logo en el servidor.'];
    }

    return ['success' => true, 'path' => '/assets/img/store/' . $fileName];
}