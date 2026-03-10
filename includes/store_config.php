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