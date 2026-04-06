<?php
// admin/paquetes.php - Gestión de paquetes de un juego

require_once '../includes/db_connect.php';
require_once '../includes/tenant.php';
require_once '../includes/recargas_api.php';
require_once '../includes/package_features.php';
require_once '../includes/recharge_availability.php';
require_once '../includes/win_points.php';

function admin_packages_is_ajax_request(): bool {
    if (isset($_REQUEST['ajax']) && (string) $_REQUEST['ajax'] === '1') {
        return true;
    }

    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));

    return $requestedWith === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function admin_packages_json_response(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_package_store_upload(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $permitidas, true)) {
        return null;
    }

    $dir = tenant_upload_absolute_dir('paquetes');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return null;
    }

    $fileName = uniqid('paquete_', true) . '.' . $ext;
    $destination = $dir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $destination)) {
        return null;
    }

    return tenant_upload_public_path('paquetes', $fileName, false);
}

function admin_package_delete_upload(?string $path): void {
    $absolutePath = tenant_resolve_public_path((string) $path);
    if ($absolutePath !== null && is_file($absolutePath)) {
        @unlink($absolutePath);
    }
}

function ensure_juego_paquetes_monto_ff_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'monto_ff'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN monto_ff VARCHAR(20) NULL AFTER clave");
    }
}

function ensure_juego_paquetes_activo_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'activo'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN activo TINYINT(1) DEFAULT 1 NULL AFTER imagen_icono");
    }
}

function ensure_juego_paquetes_paquete_api_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'paquete_api'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN paquete_api INT NULL AFTER monto_ff");
    }
}

function ensure_juego_paquetes_orden_column(mysqli $mysqli): void {
    $result = $mysqli->query("SHOW COLUMNS FROM juego_paquetes LIKE 'orden'");
    if (!($result instanceof mysqli_result) || $result->num_rows === 0) {
        $mysqli->query("ALTER TABLE juego_paquetes ADD COLUMN orden INT NULL AFTER activo");
    }
}

function admin_package_next_order(mysqli $mysqli, int $juegoId): int {
    $stmt = $mysqli->prepare("SELECT COALESCE(MAX(orden), 0) + 1 AS next_order FROM juego_paquetes WHERE juego_id = ?");
    $stmt->bind_param('i', $juegoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return max(1, (int) ($row['next_order'] ?? 1));
}

function free_fire_api_amount_options(): array {
    return [
        '1' => ['suggested_name' => 'FF_110', 'diamonds' => '110 diamantes'],
        '2' => ['suggested_name' => 'FF_341', 'diamonds' => '341 diamantes'],
        '3' => ['suggested_name' => 'FF_572', 'diamonds' => '572 diamantes'],
        '4' => ['suggested_name' => 'FF_1166', 'diamonds' => '1166 diamantes'],
        '5' => ['suggested_name' => 'FF_2376', 'diamonds' => '2376 diamantes'],
        '6' => ['suggested_name' => 'FF_6138', 'diamonds' => '6138 diamantes'],
    ];
}

function free_fire_api_amount_label(string $amount): string {
    $options = free_fire_api_amount_options();
    if (!isset($options[$amount])) {
        return $amount;
    }

    $option = $options[$amount];
    return $amount . ' - ' . $option['suggested_name'] . ' - ' . $option['diamonds'];
}

function admin_package_feature_icon_options_html(array $iconOptions, string $selected = 'sparkles'): string {
    $selectedIcon = package_feature_normalize_icon($selected);
    $iconSymbols = [
        'sparkles' => '✦',
        'diamond' => '◆',
        'lightning' => '⚡',
        'shield' => '🛡',
        'gift' => '🎁',
        'controller' => '🎮',
        'trophy' => '🏆',
        'rocket' => '🚀',
        'star' => '★',
        'layers' => '▣',
    ];
    $html = '';
    foreach ($iconOptions as $iconKey => $label) {
        $optionLabel = trim((string) (($iconSymbols[$iconKey] ?? '•') . ' ' . $label));
        $html .= '<option value="' . htmlspecialchars($iconKey, ENT_QUOTES, 'UTF-8') . '"'
            . ($selectedIcon === $iconKey ? ' selected' : '')
            . '>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html;
}

function admin_package_feature_badges_html(array $features): string {
    if (empty($features)) {
        return '';
    }

    ob_start();
    ?>
    <div class="d-flex flex-wrap gap-2 mt-2">
        <?php foreach ($features as $feature): ?>
            <span class="badge rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2" style="background:rgba(15,23,42,0.9);border:1px solid rgba(34,211,238,0.28);color:#d8fbff;">
                <?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles'), 'package-feature-badge-icon') ?>
                <span><?= htmlspecialchars((string) ($feature['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        <?php endforeach; ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

function admin_package_normalize_apply_mode(?string $value): string {
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['replace', 'add'], true) ? $normalized : '';
}

function admin_package_collect_bulk_feature_ids_from_existing(array $featureIds, array $modes): array {
    $actions = ['replace' => [], 'add' => []];
    foreach ($modes as $index => $mode) {
        $normalizedMode = admin_package_normalize_apply_mode((string) $mode);
        $featureId = (int) ($featureIds[$index] ?? 0);
        if ($normalizedMode === '' || $featureId <= 0) {
            continue;
        }
        if (!in_array($featureId, $actions[$normalizedMode], true)) {
            $actions[$normalizedMode][] = $featureId;
        }
    }
    return $actions;
}

function admin_package_collect_bulk_feature_ids_from_new(mysqli $mysqli, array $names, array $icons, array $modes): array {
    $actions = ['replace' => [], 'add' => []];
    foreach ($modes as $index => $mode) {
        $normalizedMode = admin_package_normalize_apply_mode((string) $mode);
        if ($normalizedMode === '') {
            continue;
        }
        $featureName = package_feature_normalize_name((string) ($names[$index] ?? ''));
        if ($featureName === '') {
            continue;
        }
        $featureId = package_feature_catalog_find_or_create($mysqli, $featureName, (string) ($icons[$index] ?? 'sparkles'));
        if ($featureId > 0 && !in_array($featureId, $actions[$normalizedMode], true)) {
            $actions[$normalizedMode][] = $featureId;
        }
    }
    return $actions;
}

function admin_package_merge_bulk_actions(array ...$actionGroups): array {
    $merged = ['replace' => [], 'add' => []];
    foreach ($actionGroups as $group) {
        foreach (['replace', 'add'] as $mode) {
            foreach ((array) ($group[$mode] ?? []) as $featureId) {
                $normalizedId = (int) $featureId;
                if ($normalizedId > 0 && !in_array($normalizedId, $merged[$mode], true)) {
                    $merged[$mode][] = $normalizedId;
                }
            }
        }
    }
    return $merged;
}

function admin_package_apply_bulk_feature_actions(mysqli $mysqli, int $gameId, int $currentPackageId, array $actions): void {
    $replaceIds = array_values(array_filter(array_map('intval', (array) ($actions['replace'] ?? [])), static fn ($id) => $id > 0));
    $addIds = array_values(array_filter(array_map('intval', (array) ($actions['add'] ?? [])), static fn ($id) => $id > 0));
    if (empty($replaceIds) && empty($addIds)) {
        return;
    }
    if (!empty($replaceIds)) {
        package_apply_feature_ids_to_game_packages($mysqli, $gameId, $replaceIds, true, $currentPackageId);
    }
    if (!empty($addIds)) {
        package_apply_feature_ids_to_game_packages($mysqli, $gameId, $addIds, false, $currentPackageId);
    }
}

ensure_juego_paquetes_monto_ff_column($mysqli);
ensure_juego_paquetes_activo_column($mysqli);
ensure_juego_paquetes_paquete_api_column($mysqli);
ensure_juego_paquetes_orden_column($mysqli);
package_features_ensure_schema($mysqli);
win_points_ensure_schema();

$adminGamesUrl = app_path('/admin/juegos');
$adminPackageBaseUrl = app_path('/admin/paquetes');

$juego_id = 0;
if (isset($_GET['juego'])) {
    $juego_id = intval($_GET['juego']);
} elseif (isset($_SERVER['REQUEST_URI'])) {
    // Soporta /admin/paquetes/2
    if (preg_match('#/admin/paquetes/(\\d+)#', $_SERVER['REQUEST_URI'], $m)) {
        $juego_id = intval($m[1]);
    }
}
if ($juego_id <= 0) { die('Juego no especificado.'); }

$juego = [];
$res_juego = $mysqli->prepare("SELECT * FROM juegos WHERE id=?");
$res_juego->bind_param('i', $juego_id);
$res_juego->execute();
$juego = $res_juego->get_result()->fetch_assoc();
$freeFireApiOptions = free_fire_api_amount_options();
$juegoCategoriaApi = trim((string) ($juego['categoria_api'] ?? ''));
$usesApiCatalog = $juegoCategoriaApi !== '';
$usesLegacyFreeFire = !$usesApiCatalog && !empty($juego['api_free_fire']);
$winPointsName = win_points_program_name();
$defaultWinPointsReward = 0;
$apiProducts = [];
$apiProductsById = [];
$apiProductsError = null;

if ($usesApiCatalog) {
    try {
        $apiProducts = recargas_api_fetch_products_by_category($juegoCategoriaApi);
        foreach ($apiProducts as $apiProduct) {
            $apiProductsById[(int) ($apiProduct['id'] ?? 0)] = $apiProduct;
        }
    } catch (Throwable $e) {
        $apiProductsError = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['package_feature_catalog_action'])) {
    $catalogAction = trim((string) ($_POST['package_feature_catalog_action'] ?? ''));
    $featureId = (int) ($_POST['package_feature_id'] ?? 0);
    $featureName = (string) ($_POST['package_feature_name'] ?? '');
    $featureIcon = (string) ($_POST['package_feature_icon'] ?? 'sparkles');

    if ($catalogAction === 'create') {
        package_feature_catalog_find_or_create($mysqli, $featureName, $featureIcon);
    } elseif ($catalogAction === 'update' && $featureId > 0) {
        package_feature_catalog_update($mysqli, $featureId, $featureName, $featureIcon);
    } elseif ($catalogAction === 'delete' && $featureId > 0) {
        package_feature_catalog_delete($mysqli, $featureId);
    }

    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_paquete_activo'], $_POST['paquete_id'], $_POST['activo'])) {
    $packageId = intval($_POST['paquete_id']);
    $activeValue = intval($_POST['activo']) === 1 ? 1 : 0;
    if ($packageId > 0) {
        recharge_availability_set_package_active($mysqli, $packageId, $juego_id, $activeValue === 1);
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $packageId, 'activo' => $activeValue]);
        }
    }
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

if (isset($_GET['toggle_activo'])) {
    $toggleId = intval($_GET['toggle_activo']);
    if ($toggleId > 0) {
        $nextActive = !recharge_availability_is_package_active($mysqli, $toggleId, $juego_id);
        recharge_availability_set_package_active($mysqli, $toggleId, $juego_id, $nextActive);
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $toggleId, 'activo' => $nextActive ? 1 : 0]);
        }
    }
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_orden_paquete'], $_POST['paquete_id'], $_POST['orden'])) {
    $packageId = intval($_POST['paquete_id']);
    $order = max(1, intval($_POST['orden']));
    if ($packageId > 0) {
        $stmtOrder = $mysqli->prepare("UPDATE juego_paquetes SET orden = ? WHERE id = ? AND juego_id = ?");
        $stmtOrder->bind_param('iii', $order, $packageId, $juego_id);
        $stmtOrder->execute();
        $stmtOrder->close();
        if (admin_packages_is_ajax_request()) {
            admin_packages_json_response(['ok' => true, 'id' => $packageId, 'orden' => $order]);
        }
    }
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

// Procesar eliminación de paquete (antes de cualquier salida)
if (isset($_GET['eliminar'])) {
    $del_id = intval($_GET['eliminar']);
    // Obtener la ruta de la imagen antes de borrar
    $stmt_img = $mysqli->prepare("SELECT imagen_icono FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt_img->bind_param('ii', $del_id, $juego_id);
    $stmt_img->execute();
    $stmt_img->bind_result($img_path);
    $stmt_img->fetch();
    $stmt_img->close();
    // Borrar el registro
    $stmt = $mysqli->prepare("DELETE FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt->bind_param('ii', $del_id, $juego_id);
    $stmt->execute();
    package_delete_feature_assignments($mysqli, $del_id);
    // Borrar la imagen física si existe y no está vacía
    if ($img_path) {
        admin_package_delete_upload((string) $img_path);
    }
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

// Procesar edición de paquete (antes de cualquier salida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_paquete_id'])) {
    $edit_id = intval($_POST['edit_paquete_id']);
    $edit_nombre = trim($_POST['edit_nombre'] ?? '');
    $edit_clave = trim($_POST['edit_clave'] ?? '');
    $edit_monto_ff = $usesLegacyFreeFire ? trim((string) ($_POST['edit_monto_ff'] ?? '')) : '';
    $edit_paquete_api = $usesApiCatalog ? trim((string) ($_POST['edit_paquete_api'] ?? '')) : '';
    $edit_cantidad = intval($_POST['edit_cantidad'] ?? 0);
    $edit_precio = floatval($_POST['edit_precio'] ?? 0);
    $edit_win_points_reward = max(0, (int) ($_POST['edit_win_points_reward'] ?? 0));
    $edit_activo = isset($_POST['edit_activo']) ? 1 : 0;
    $edit_imagen_icono = admin_package_store_upload($_FILES['edit_imagen_icono'] ?? []);
    if ($edit_imagen_icono) {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=NULLIF(?, ''), paquete_api=NULLIF(?, ''), cantidad=?, precio=?, win_points_reward=?, imagen_icono=?, activo=? WHERE id=?");
        $stmt->bind_param('ssssidisii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_paquete_api, $edit_cantidad, $edit_precio, $edit_win_points_reward, $edit_imagen_icono, $edit_activo, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, monto_ff=NULLIF(?, ''), paquete_api=NULLIF(?, ''), cantidad=?, precio=?, win_points_reward=?, activo=? WHERE id=?");
        $stmt->bind_param('ssssidiii', $edit_nombre, $edit_clave, $edit_monto_ff, $edit_paquete_api, $edit_cantidad, $edit_precio, $edit_win_points_reward, $edit_activo, $edit_id);
    }
    $stmt->execute();
    if ($edit_activo === 1) {
        recharge_availability_set_game_active($mysqli, $juego_id, true);
    }
    $editAssignedFeatureIds = $_POST['edit_assigned_feature_id'] ?? [];
    $editAssignedFeatureNames = $_POST['edit_assigned_feature_name'] ?? [];
    $editAssignedFeatureIcons = $_POST['edit_assigned_feature_icon'] ?? [];
    foreach ($editAssignedFeatureIds as $index => $featureId) {
        $normalizedFeatureId = (int) $featureId;
        if ($normalizedFeatureId <= 0) {
            continue;
        }
        package_feature_catalog_update(
            $mysqli,
            $normalizedFeatureId,
            (string) ($editAssignedFeatureNames[$index] ?? ''),
            (string) ($editAssignedFeatureIcons[$index] ?? 'sparkles')
        );
    }
    $editNewFeatureNames = $_POST['edit_new_feature_name'] ?? [];
    $editNewFeatureIcons = $_POST['edit_new_feature_icon'] ?? [];
    $editNewFeatures = package_feature_pairs_from_request($editNewFeatureNames, $editNewFeatureIcons);
    package_assign_features_to_package(
        $mysqli,
        $edit_id,
        $_POST['edit_package_feature_ids'] ?? [],
        $editNewFeatures
    );
    $editBulkActions = admin_package_merge_bulk_actions(
        admin_package_collect_bulk_feature_ids_from_existing($editAssignedFeatureIds, $_POST['edit_assigned_feature_apply_mode'] ?? []),
        admin_package_collect_bulk_feature_ids_from_new($mysqli, $editNewFeatureNames, $editNewFeatureIcons, $_POST['edit_new_feature_apply_mode'] ?? [])
    );
    admin_package_apply_bulk_feature_actions($mysqli, $juego_id, $edit_id, $editBulkActions);
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

// Procesar creación de paquete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['clave'], $_POST['cantidad'], $_POST['precio'])) {
    $nombre = trim($_POST['nombre']);
    $clave = trim($_POST['clave']);
    $monto_ff = $usesLegacyFreeFire ? trim((string) ($_POST['monto_ff'] ?? '')) : '';
    $paquete_api = $usesApiCatalog ? trim((string) ($_POST['paquete_api'] ?? '')) : '';
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);
    $win_points_reward = max(0, (int) ($_POST['win_points_reward'] ?? $defaultWinPointsReward));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $orden = admin_package_next_order($mysqli, $juego_id);
    $imagen_icono = admin_package_store_upload($_FILES['imagen_icono'] ?? []);
    $stmt = $mysqli->prepare("INSERT INTO juego_paquetes (juego_id, nombre, clave, monto_ff, paquete_api, cantidad, precio, win_points_reward, imagen_icono, activo, orden) VALUES (?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssidisii', $juego_id, $nombre, $clave, $monto_ff, $paquete_api, $cantidad, $precio, $win_points_reward, $imagen_icono, $activo, $orden);
    $stmt->execute();
    $newPackageId = (int) $mysqli->insert_id;
    if ($activo === 1) {
        recharge_availability_set_game_active($mysqli, $juego_id, true);
    }
    $newFeatureNames = $_POST['new_feature_name'] ?? [];
    $newFeatureIcons = $_POST['new_feature_icon'] ?? [];
    $newFeatures = package_feature_pairs_from_request($newFeatureNames, $newFeatureIcons);
    package_assign_features_to_package(
        $mysqli,
        $newPackageId,
        $_POST['package_feature_ids'] ?? [],
        $newFeatures
    );
    $createBulkActions = admin_package_collect_bulk_feature_ids_from_new($mysqli, $newFeatureNames, $newFeatureIcons, $_POST['new_feature_apply_mode'] ?? []);
    admin_package_apply_bulk_feature_actions($mysqli, $juego_id, $newPackageId, $createBulkActions);
    header('Location: ' . $adminPackageBaseUrl . '/' . $juego_id);
    exit;
}

// Listar paquetes
$res = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE juego_id=? ORDER BY CASE WHEN orden IS NULL THEN 1 ELSE 0 END, orden ASC, id ASC");
$res->bind_param('i', $juego_id);
$res->execute();
$result = $res->get_result();
$paquetes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$packageFeatureCatalog = package_feature_catalog_all($mysqli);
$packageFeatureIconOptions = package_feature_icon_options();
$packageFeaturesByPackage = package_features_for_packages($mysqli, array_map(static fn (array $package): int => (int) ($package['id'] ?? 0), $paquetes));
$packageFeatureIconOptionsHtml = admin_package_feature_icon_options_html($packageFeatureIconOptions);

// Incluir header
include '../includes/header.php';
?>
<main class="container py-4">
    <h2 class="mb-4 text-neon">Paquetes de <?= htmlspecialchars($juego['nombre'] ?? 'Juego') ?></h2>
    <form method="post" enctype="multipart/form-data" class="row g-3 mb-4" style="background:#181f2a; border-radius:16px; border:2px solid #22d3ee; box-shadow:0 0 24px #22d3ee33; padding:2rem;">
        <div class="col-md-6">
            <label class="form-label text-neon">Nombre del paquete</label>
            <input type="text" name="nombre" placeholder="Nombre del paquete" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <div class="col-md-6">
            <label class="form-label text-neon">Clave interna</label>
            <input type="text" name="clave" placeholder="Clave" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <?php if ($usesApiCatalog): ?>
            <div class="col-md-6">
                <label class="form-label text-neon">Producto API</label>
                <select name="paquete_api" required class="form-select" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
                    <option value="">Selecciona un producto API</option>
                    <?php foreach ($apiProducts as $apiProduct): ?>
                        <option value="<?= (int) ($apiProduct['id'] ?? 0) ?>"><?= htmlspecialchars(recargas_api_product_label($apiProduct), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text mt-2" style="color:#8be9fd;">Categoría API vinculada: <?= htmlspecialchars($juegoCategoriaApi, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        <?php elseif ($usesLegacyFreeFire): ?>
            <div class="col-md-6">
                <label class="form-label text-neon">Montos (API)</label>
                <select name="monto_ff" required class="form-select" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
                    <option value="">Selecciona un monto API</option>
                    <?php foreach ($freeFireApiOptions as $amount => $option): ?>
                        <option value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>">&#128142; <?= htmlspecialchars($option['suggested_name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($option['diamonds'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="col-md-4" style="display:none;">
            <label class="form-label text-neon">Cantidad</label>
            <input type="number" name="cantidad_visible" min="0" placeholder="Cantidad" class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;" value="1">
        </div>
        <input type="hidden" name="cantidad" value="1">
        <div class="col-md-4">
            <label class="form-label text-neon">Precio USD</label>
            <input type="number" step="0.01" min="0" name="precio" placeholder="Precio" required class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <div class="col-md-4">
            <label class="form-label text-neon"><?= htmlspecialchars($winPointsName, ENT_QUOTES, 'UTF-8') ?> a ganar</label>
            <input type="number" min="0" name="win_points_reward" value="<?= $defaultWinPointsReward ?>" class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;">
        </div>
        <div class="col-md-4">
            <label class="form-label text-neon">Icono del paquete</label>
            <input type="file" name="imagen_icono" accept="image/*" class="form-control" style="background:#222c3a; color:#22d3ee; border:1px solid #22d3ee;" onchange="previewNuevoPaqueteImg(event)">
        </div>
        <div class="col-12">
            <div class="rounded-4 p-3" style="background:#101826;border:1px solid rgba(34,211,238,0.18);">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                    <div>
                        <div class="text-neon fw-semibold">Caracteristicas reutilizables</div>
                        <div class="small" style="color:#8be9fd;">Selecciona caracteristicas ya creadas o agrega nuevas sin salir del formulario.</div>
                    </div>
                    <button type="button" class="btn btn-outline-info btn-sm" onclick="window.addPackageFeatureRow('package-new-features', 'new_feature_name[]', 'new_feature_icon[]', <?= htmlspecialchars(json_encode($packageFeatureIconOptionsHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>, 'new_feature_apply_mode[]')">Nueva caracteristica</button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php if (!empty($packageFeatureCatalog)): ?>
                        <?php foreach ($packageFeatureCatalog as $feature): ?>
                            <label class="badge rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2" style="cursor:pointer;background:rgba(15,23,42,0.92);border:1px solid rgba(34,211,238,0.28);color:#d8fbff;">
                                <input type="checkbox" name="package_feature_ids[]" value="<?= (int) ($feature['id'] ?? 0) ?>" class="form-check-input mt-0 me-1" style="float:none;">
                                <?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles'), 'package-feature-badge-icon') ?>
                                <span><?= htmlspecialchars((string) ($feature['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="small" style="color:#8be9fd;">Aun no hay caracteristicas guardadas en el catalogo.</span>
                    <?php endif; ?>
                </div>
                <div class="small mb-3" style="color:#8be9fd;">Cada caracteristica nueva puede marcarse con Aplicar a todos para copiarla al resto de paquetes de este juego al guardar.</div>
                <div id="package-new-features" class="d-grid gap-2"></div>
            </div>
        </div>
        <div class="col-12">
            <div class="form-check mt-2">
                <input type="checkbox" name="activo" class="form-check-input" id="paqueteActivoCheck" checked>
                <label class="form-check-label text-neon" for="paqueteActivoCheck">Paquete activo / publicado</label>
            </div>
        </div>
        <div class="col-12 text-center">
            <img id="preview-nuevo-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;border:2px solid #22d3ee;background:#222c3a;" />
        </div>
        <div class="col-12">
            <button type="submit" class="btn neon-btn-info w-100">Agregar paquete</button>
        </div>
    </form>
    <div class="mb-4 rounded-4 p-4" style="background:#181f2a;border:1px solid rgba(34,211,238,0.18);box-shadow:0 0 20px rgba(34,211,238,0.08);">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
            <div>
                <h3 class="h5 text-neon mb-1">Catalogo de caracteristicas</h3>
                <p class="mb-0 small" style="color:#8be9fd;">Edita el nombre e icono de cada caracteristica para reutilizarla en varios paquetes.</p>
            </div>
        </div>
        <form method="post" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="package_feature_catalog_action" value="create">
            <div class="col-md-4">
                <label class="form-label text-neon">Icono</label>
                <div class="d-flex align-items-center gap-2" data-package-feature-editor>
                    <select name="package_feature_icon" data-package-feature-icon-select class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;"><?= $packageFeatureIconOptionsHtml ?></select>
                    <span data-package-feature-icon-preview class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width:44px;height:44px;background:#0f172a;border:1px solid rgba(34,211,238,0.22);color:#67e8f9;"><?= package_feature_render_icon('sparkles') ?></span>
                </div>
            </div>
            <div class="col-md-5">
                <label class="form-label text-neon">Nombre</label>
                <input type="text" name="package_feature_name" class="form-control" required placeholder="Ej: Entrega inmediata" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn neon-btn-info w-100">Guardar en catalogo</button>
            </div>
        </form>
        <div class="d-grid gap-3">
            <?php foreach ($packageFeatureCatalog as $feature): ?>
                <form method="post" class="row g-2 align-items-center rounded-4 p-3" style="background:#101826;border:1px solid rgba(34,211,238,0.14);">
                    <input type="hidden" name="package_feature_id" value="<?= (int) ($feature['id'] ?? 0) ?>">
                    <div class="col-md-3">
                        <label class="form-label text-neon small mb-1">Icono</label>
                        <div class="d-flex align-items-center gap-2" data-package-feature-editor>
                            <select name="package_feature_icon" data-package-feature-icon-select class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;"><?= admin_package_feature_icon_options_html($packageFeatureIconOptions, (string) ($feature['icon'] ?? 'sparkles')) ?></select>
                            <span data-package-feature-icon-preview class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width:44px;height:44px;background:#0f172a;border:1px solid rgba(34,211,238,0.22);color:#67e8f9;"><?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles')) ?></span>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label text-neon small mb-1">Nombre</label>
                        <input type="text" name="package_feature_name" value="<?= htmlspecialchars((string) ($feature['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="form-control" required style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                    </div>
                    <div class="col-md-2 text-center">
                        <span class="badge rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2" style="background:rgba(15,23,42,0.92);border:1px solid rgba(34,211,238,0.28);color:#d8fbff;">
                            <?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles'), 'package-feature-badge-icon') ?>
                            <span>ID <?= (int) ($feature['id'] ?? 0) ?></span>
                        </span>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" name="package_feature_catalog_action" value="update" class="btn neon-btn-info btn-sm flex-fill">Actualizar</button>
                        <button type="submit" name="package_feature_catalog_action" value="delete" class="btn btn-danger btn-sm flex-fill" onclick="return confirm('¿Eliminar esta caracteristica del catalogo?')">Borrar</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
    <?php if ($usesApiCatalog && $apiProductsError !== null): ?>
        <div class="alert alert-warning mb-4">No se pudieron cargar los productos de la categoría API: <?= htmlspecialchars($apiProductsError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($usesApiCatalog && empty($apiProducts)): ?>
        <div class="alert alert-warning mb-4">No hay productos disponibles en la API para la categoría <?= htmlspecialchars($juegoCategoriaApi, ENT_QUOTES, 'UTF-8') ?>.</div>
    <?php endif; ?>
    <div class="table-responsive d-none d-md-block">
        <table class="table table-dark table-bordered align-middle" style="border:2px solid #22d3ee;">
            <thead>
                <tr>
                    <th style="color:#22d3ee; background:#181f2a;">Icono</th>
                    <th style="color:#22d3ee; background:#181f2a;">Nombre</th>
                    <th style="color:#22d3ee; background:#181f2a;">Clave</th>
                    <th style="color:#22d3ee; background:#181f2a;">Orden</th>
                    <?php if ($usesApiCatalog): ?>
                        <th style="color:#22d3ee; background:#181f2a;">Producto API</th>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <th style="color:#22d3ee; background:#181f2a;">Monto FF</th>
                    <?php endif; ?>
                    <th style="color:#22d3ee; background:#181f2a;">Activo</th>
                    <th style="color:#22d3ee; background:#181f2a;">Precio</th>
                    <th style="color:#22d3ee; background:#181f2a;"><?= htmlspecialchars($winPointsName, ENT_QUOTES, 'UTF-8') ?></th>
                    <th style="color:#22d3ee; background:#181f2a;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paquetes as $p): ?>
                <?php $packageFeatures = $packageFeaturesByPackage[(int) ($p['id'] ?? 0)] ?? []; ?>
                <tr style="background:#181f2a; color:#fff;">
                    <td style="background:#181f2a;">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold text-neon" style="background:#181f2a; color:#22d3ee;">
                        <div><?= htmlspecialchars($p['nombre']) ?></div>
                        <?= admin_package_feature_badges_html($packageFeatures) ?>
                    </td>
                    <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($p['clave']) ?></td>
                    <td class="text-center" style="background:#181f2a;">
                        <form method="post" action="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>" class="d-inline-flex align-items-center gap-2 m-0 js-ajax-order-form">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="update_orden_paquete" value="1">
                            <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                            <input type="number" name="orden" min="1" value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" class="form-control form-control-sm text-center js-ajax-order-input" style="width:84px;background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" data-last-value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" onchange="window.adminPackageOrderChange(this)">
                        </form>
                    </td>
                    <?php if ($usesApiCatalog): ?>
                        <?php $apiProductId = (int) ($p['paquete_api'] ?? 0); ?>
                        <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($apiProductId > 0 && isset($apiProductsById[$apiProductId]) ? recargas_api_product_label($apiProductsById[$apiProductId]) : ($apiProductId > 0 ? 'ID ' . $apiProductId : '—'), ENT_QUOTES, 'UTF-8') ?></td>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></td>
                    <?php endif; ?>
                    <td class="text-center" style="background:#181f2a;">
                        <form method="post" action="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>" class="m-0 d-inline-block js-ajax-toggle-form">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="toggle_paquete_activo" value="1">
                            <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="activo" value="<?= !isset($p['activo']) || !empty($p['activo']) ? '1' : '0' ?>" class="js-ajax-toggle-value">
                            <div class="form-check form-switch d-inline-flex justify-content-center mb-0">
                                <input class="form-check-input js-ajax-toggle-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" onchange="window.adminPackageToggle(this)">
                            </div>
                        </form>
                    </td>
                    <td class="text-neon" style="background:#181f2a; color:#22d3ee;">$<?= number_format($p['precio'], 2) ?></td>
                    <td style="background:#181f2a; color:#fff;"><?= (int) ($p['win_points_reward'] ?? 0) ?></td>
                    <td style="background:#181f2a;" class="text-nowrap">
                        <a href="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm me-2">Editar</a>
                        <a href="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Cards móvil -->
    <div class="d-md-none">
        <div class="row gy-4">
            <?php foreach ($paquetes as $p): ?>
            <?php $packageFeatures = $packageFeaturesByPackage[(int) ($p['id'] ?? 0)] ?? []; ?>
            <div class="col-12">
                <div class="card neon-card p-3" style="background:#181f2a; border:2px solid #22d3ee; box-shadow:0 0 16px #22d3ee,0 0 4px #2dd4bf; color:#22d3ee;">
                    <div class="d-flex align-items-center mb-2">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-neon" style="font-size:1.1rem; color:#22d3ee;"><?= htmlspecialchars($p['nombre']) ?></div>
                            <div class="small" style="font-size:0.85rem; color:#b2f6ff;">Orden: <?= max(1, (int) ($p['orden'] ?? 0)) ?></div>
                            <div class="text-muted" style="font-size:0.85rem; color:#b2f6ff;">ID: <?= $p['id'] ?></div>
                            <div class="mt-2">
                                <form method="post" action="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>" class="m-0 d-inline-flex align-items-center gap-2 js-ajax-toggle-form">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="toggle_paquete_activo" value="1">
                                    <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                                    <input type="hidden" name="activo" value="<?= !isset($p['activo']) || !empty($p['activo']) ? '1' : '0' ?>" class="js-ajax-toggle-value">
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input js-ajax-toggle-input" type="checkbox" <?= !isset($p['activo']) || !empty($p['activo']) ? 'checked' : '' ?> aria-label="Activar o desactivar paquete <?= htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') ?>" onchange="window.adminPackageToggle(this)">
                                    </div>
                                    <span style="color:#b2f6ff;font-size:0.85rem;" class="js-ajax-toggle-label"><?= !isset($p['activo']) || !empty($p['activo']) ? 'Activo' : 'Inactivo' ?></span>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div style="color:#fff;"><span class="fw-semibold">Clave:</span> <?= htmlspecialchars($p['clave']) ?></div>
                    <?= admin_package_feature_badges_html($packageFeatures) ?>
                    <?php if ($usesApiCatalog): ?>
                        <?php $apiProductId = (int) ($p['paquete_api'] ?? 0); ?>
                        <div style="color:#fff;"><span class="fw-semibold">Producto API:</span> <?= htmlspecialchars($apiProductId > 0 && isset($apiProductsById[$apiProductId]) ? recargas_api_product_label($apiProductsById[$apiProductId]) : ($apiProductId > 0 ? 'ID ' . $apiProductId : '—'), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php elseif ($usesLegacyFreeFire): ?>
                        <div style="color:#fff;"><span class="fw-semibold">Monto FF:</span> <?= htmlspecialchars(!empty($p['monto_ff']) ? free_fire_api_amount_label((string) $p['monto_ff']) : '—') ?></div>
                    <?php endif; ?>
                    <div class="text-neon" style="color:#22d3ee;"><span class="fw-semibold">Precio:</span> $<?= number_format($p['precio'], 2) ?></div>
                    <div style="color:#fff;"><span class="fw-semibold"><?= htmlspecialchars($winPointsName, ENT_QUOTES, 'UTF-8') ?>:</span> <?= (int) ($p['win_points_reward'] ?? 0) ?></div>
                    <form method="post" action="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>" class="mt-3 d-flex align-items-center gap-2 flex-wrap js-ajax-order-form">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="update_orden_paquete" value="1">
                        <input type="hidden" name="paquete_id" value="<?= (int) $p['id'] ?>">
                        <label class="small" style="color:#b2f6ff;">Orden</label>
                        <input type="number" name="orden" min="1" value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" class="form-control form-control-sm js-ajax-order-input" style="width:96px;background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" data-last-value="<?= max(1, (int) ($p['orden'] ?? 0)) ?>" onchange="window.adminPackageOrderChange(this)">
                    </form>
                    <div class="mt-3 d-flex gap-2">
                        <a href="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm flex-fill">Editar</a>
                        <a href="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm flex-fill" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <a href="<?= htmlspecialchars($adminGamesUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-block mt-4 text-neon">&larr; Volver a juegos</a>
</main>

<div id="package-feature-apply-modal" class="position-fixed top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center" style="background:rgba(2,6,23,0.82);z-index:1080;padding:1rem;">
    <div class="bg-dark rounded-4 p-4 w-100" style="max-width:520px;border:1px solid rgba(34,211,238,0.22);box-shadow:0 0 24px rgba(34,211,238,0.18);">
        <h3 class="h5 text-neon mb-2">Aplicar a todos</h3>
        <p class="small mb-4" style="color:#8be9fd;">Elige cómo deseas propagar esta caracteristica al resto de paquetes del juego actual.</p>
        <div class="d-grid gap-2">
            <button type="button" id="package-feature-apply-replace" class="btn btn-outline-danger text-start" onclick="window.selectPackageFeatureApplyMode('replace')">Eliminar caracteristicas de los demas paquetes del juego</button>
            <button type="button" id="package-feature-apply-add" class="btn btn-outline-info text-start" onclick="window.selectPackageFeatureApplyMode('add')">Agregar esta caracteristica a los demas paquetes</button>
            <button type="button" id="package-feature-apply-cancel" class="btn btn-secondary text-start" onclick="window.closePackageFeatureApplyModal()">Cancelar</button>
        </div>
    </div>
</div>


<?php
// Modal edición de paquete
if (isset($_GET['editar'])) {
    $edit_id = intval($_GET['editar']);
    $res_edit = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE id=? AND juego_id=?");
    $res_edit->bind_param('ii', $edit_id, $juego_id);
    $res_edit->execute();
    $paq_edit = $res_edit->get_result()->fetch_assoc();
    $paqEditFeatureIds = package_feature_catalog_ids_for_package($mysqli, $edit_id);
    $paqEditFeatures = $packageFeaturesByPackage[$edit_id] ?? [];
    if ($paq_edit):
?>
<div class="fixed-top w-100 h-100 d-flex align-items-start justify-content-center" style="background:rgba(0,0,0,0.7);z-index:1050;overflow-y:auto;padding:1rem;">
    <form method="post" enctype="multipart/form-data" class="bg-dark neon-card p-4 rounded-4 position-relative" style="max-width:560px;width:100%;max-height:calc(100vh - 2rem);overflow-y:auto;box-shadow:0 0 2rem #22d3ee33;">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <h3 class="text-neon mb-0">Editar paquete</h3>
            <a href="<?= htmlspecialchars($adminPackageBaseUrl, ENT_QUOTES, 'UTF-8') ?>/<?= $juego_id ?>" class="btn btn-outline-info btn-sm flex-shrink-0">Cerrar</a>
        </div>
        <input type="hidden" name="edit_paquete_id" value="<?= $paq_edit['id'] ?>">
        <div class="mb-3">
            <label class="form-label text-neon">Nombre</label>
            <input type="text" name="edit_nombre" value="<?= htmlspecialchars($paq_edit['nombre']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Clave interna</label>
            <input type="text" name="edit_clave" value="<?= htmlspecialchars($paq_edit['clave']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <?php if ($usesApiCatalog): ?>
            <div class="mb-3">
                <label class="form-label text-neon">Producto API</label>
                <select name="edit_paquete_api" required class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                    <option value="">Selecciona un producto API</option>
                    <?php foreach ($apiProducts as $apiProduct): ?>
                        <option value="<?= (int) ($apiProduct['id'] ?? 0) ?>" <?= (int) ($paq_edit['paquete_api'] ?? 0) === (int) ($apiProduct['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars(recargas_api_product_label($apiProduct), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($usesLegacyFreeFire): ?>
            <div class="mb-3">
                <label class="form-label text-neon">Montos (API)</label>
                <select name="edit_monto_ff" required class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                    <option value="">Selecciona un monto API</option>
                    <?php foreach ($freeFireApiOptions as $amount => $option): ?>
                        <option value="<?= htmlspecialchars($amount, ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($paq_edit['monto_ff'] ?? '') === (string) $amount ? 'selected' : '' ?>>&#128142; <?= htmlspecialchars($option['suggested_name'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($option['diamonds'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label text-neon">Cantidad</label>
            <input type="number" name="edit_cantidad" value="<?= htmlspecialchars($paq_edit['cantidad']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Precio USD</label>
            <input type="number" step="0.01" name="edit_precio" value="<?= htmlspecialchars($paq_edit['precio']) ?>" required class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="mb-3">
            <label class="form-label text-neon"><?= htmlspecialchars($winPointsName, ENT_QUOTES, 'UTF-8') ?> a ganar</label>
            <input type="number" min="0" name="edit_win_points_reward" value="<?= (int) ($paq_edit['win_points_reward'] ?? 0) ?>" class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" name="edit_activo" class="form-check-input" id="editPaqueteActivoCheck" <?= !isset($paq_edit['activo']) || !empty($paq_edit['activo']) ? 'checked' : '' ?>>
            <label class="form-check-label text-neon" for="editPaqueteActivoCheck">Paquete activo / publicado</label>
        </div>
        <div class="mb-3">
            <label class="form-label text-neon">Icono actual:</label><br>
            <?php if ($paq_edit['imagen_icono']): ?>
                <img src="/<?= htmlspecialchars($paq_edit['imagen_icono']) ?>" alt="Icono actual" class="mb-2 rounded" style="max-width:80px;max-height:80px;border:2px solid #22d3ee;background:#222c3a;box-shadow:0 0 8px #22d3ee;">
            <?php endif; ?>
            <input type="file" name="edit_imagen_icono" accept="image/*" class="form-control mt-2" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" onchange="previewEditPaqueteImg(event)">
            <div class="text-center my-2">
                <img id="preview-edit-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
        </div>
        <div class="mb-3 rounded-4 p-3" style="background:#101826;border:1px solid rgba(34,211,238,0.18);">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                <div>
                    <div class="text-neon fw-semibold">Caracteristicas del paquete</div>
                    <div class="small" style="color:#8be9fd;">Puedes reutilizar caracteristicas existentes o crear nuevas para este paquete.</div>
                </div>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="window.addPackageFeatureRow('edit-package-new-features', 'edit_new_feature_name[]', 'edit_new_feature_icon[]', <?= htmlspecialchars(json_encode($packageFeatureIconOptionsHtml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>, 'edit_new_feature_apply_mode[]')">Nueva caracteristica</button>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php if (!empty($packageFeatureCatalog)): ?>
                    <?php foreach ($packageFeatureCatalog as $feature): ?>
                        <label class="badge rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2" style="cursor:pointer;background:rgba(15,23,42,0.92);border:1px solid rgba(34,211,238,0.28);color:#d8fbff;">
                            <input type="checkbox" name="edit_package_feature_ids[]" value="<?= (int) ($feature['id'] ?? 0) ?>" class="form-check-input mt-0 me-1" style="float:none;" <?= in_array((int) ($feature['id'] ?? 0), $paqEditFeatureIds, true) ? 'checked' : '' ?>>
                            <?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles'), 'package-feature-badge-icon') ?>
                            <span><?= htmlspecialchars((string) ($feature['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="small" style="color:#8be9fd;">Aun no hay caracteristicas guardadas en el catalogo.</span>
                <?php endif; ?>
            </div>
            <div class="small mb-3" style="color:#8be9fd;">Usa Aplicar a todos en una caracteristica editada o nueva para copiarla al resto de paquetes de este juego cuando guardes.</div>
            <?php if (!empty($paqEditFeatures)): ?>
                <div class="d-grid gap-2 mb-3">
                    <div class="small fw-semibold" style="color:#8be9fd;">Editar caracteristicas asignadas</div>
                    <?php foreach ($paqEditFeatures as $feature): ?>
                        <div class="row g-2 align-items-center rounded-4 p-2" style="background:#0f172a;border:1px solid rgba(34,211,238,0.12);">
                            <input type="hidden" name="edit_assigned_feature_id[]" value="<?= (int) ($feature['id'] ?? 0) ?>">
                            <input type="hidden" name="edit_assigned_feature_apply_mode[]" value="" data-package-feature-apply-input>
                            <div class="col-md-4">
                                <label class="form-label text-neon small mb-1">Icono</label>
                                <div class="d-flex align-items-center gap-2" data-package-feature-editor>
                                    <select name="edit_assigned_feature_icon[]" data-package-feature-icon-select class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;"><?= admin_package_feature_icon_options_html($packageFeatureIconOptions, (string) ($feature['icon'] ?? 'sparkles')) ?></select>
                                    <span data-package-feature-icon-preview class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width:44px;height:44px;background:#0f172a;border:1px solid rgba(34,211,238,0.22);color:#67e8f9;"><?= package_feature_render_icon((string) ($feature['icon'] ?? 'sparkles')) ?></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-neon small mb-1">Nombre</label>
                                <input type="text" name="edit_assigned_feature_name[]" value="<?= htmlspecialchars((string) ($feature['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="form-control" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-neon small mb-1 d-block">Aplicacion</label>
                                <button type="button" class="btn btn-outline-warning btn-sm w-100" data-package-feature-apply-button onclick="window.openPackageFeatureApplyModal(this)">Aplicar a todos</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div id="edit-package-new-features" class="d-grid gap-2"></div>
        </div>
        <button type="submit" name="edit_paquete_submit" class="btn neon-btn-info w-100 mt-3">Guardar cambios</button>
    </form>
</div>
<script>
const adminPackageFeatureIconMap = <?= json_encode(package_feature_icon_svg_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function updatePackageFeatureIconPreview(select) {
    if (!select) {
        return;
    }

    const editor = select.closest('[data-package-feature-editor]');
    const preview = editor ? editor.querySelector('[data-package-feature-icon-preview]') : null;
    if (!preview) {
        return;
    }

    const iconKey = String(select.value || 'sparkles').trim();
    preview.innerHTML = adminPackageFeatureIconMap[iconKey] || adminPackageFeatureIconMap.sparkles || '';
}

function bindPackageFeatureIconPreview(root = document) {
    root.querySelectorAll('[data-package-feature-icon-select]').forEach((select) => {
        if (select.dataset.iconPreviewBound === '1') {
            updatePackageFeatureIconPreview(select);
            return;
        }

        select.dataset.iconPreviewBound = '1';
        select.addEventListener('change', function() {
            updatePackageFeatureIconPreview(this);
        });
        updatePackageFeatureIconPreview(select);
    });
}

function previewNuevoPaqueteImg(event) {
    const input = event.target;
    const img = document.getElementById('preview-nuevo-paquete-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}

function previewEditPaqueteImg(event) {
        const input = event.target;
        const img = document.getElementById('preview-edit-paquete-img');
        if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
        } else {
                img.src = '#';
                img.style.display = 'none';
        }
}

async function submitAjaxAdminForm(form, requestData = null) {
    const method = (form.method || 'POST').toUpperCase();
    const formData = requestData instanceof FormData ? requestData : new FormData(form);
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json, text/plain, */*'
    };
    let response;
    if (method === 'GET') {
        const params = new URLSearchParams(formData);
        const separator = (form.action || window.location.href).includes('?') ? '&' : '?';
        response = await fetch((form.action || window.location.href) + separator + params.toString(), {
            method,
            headers,
            cache: 'no-store'
        });
    } else {
        response = await fetch(form.action || window.location.href, {
            method,
            headers,
            body: formData
        });
    }
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar el cambio.');
    }
    return payload;
}

window.adminPackageToggle = async function(input) {
    if (!input || input.dataset.busy === '1' || !input.form) {
        return;
    }

    const form = input.form;
    const valueInput = form.querySelector('.js-ajax-toggle-value');
    const label = form.querySelector('.js-ajax-toggle-label');

    if (valueInput) {
        valueInput.value = input.checked ? '1' : '0';
    }

    const requestData = new FormData(form);
    input.dataset.busy = '1';
    input.disabled = true;

    try {
        const payload = await submitAjaxAdminForm(form, requestData);
        input.checked = String(payload.activo || 0) === '1';
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        if (label) {
            label.textContent = input.checked ? 'Activo' : 'Inactivo';
        }
    } catch (error) {
        input.checked = !input.checked;
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        window.alert(error.message);
    } finally {
        input.disabled = false;
        input.dataset.busy = '0';
    }
};

window.adminPackageOrderChange = async function(input) {
    if (!input || !input.form) {
        return;
    }

    const form = input.form;
    const normalized = String(Math.max(1, parseInt(input.value || '1', 10) || 1));
    const lastValue = String(input.dataset.lastValue || input.defaultValue || '1');
    if (normalized === lastValue) {
        input.value = normalized;
        return;
    }

    input.value = normalized;
    const requestData = new FormData(form);
    input.readOnly = true;

    try {
        const payload = await submitAjaxAdminForm(form, requestData);
        input.dataset.lastValue = String(payload.orden || normalized);
        input.value = input.dataset.lastValue;
    } catch (error) {
        input.value = lastValue;
        window.alert(error.message);
    } finally {
        input.readOnly = false;
    }
};
</script>
<?php endif; }
?>

<script>
if (typeof window.previewNuevoPaqueteImg !== 'function') {
    window.previewNuevoPaqueteImg = function(event) {
        const input = event.target;
        const img = document.getElementById('preview-nuevo-paquete-img');
        if (!img) {
            return;
        }

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            img.src = '#';
            img.style.display = 'none';
        }
    };
}

if (typeof window.previewEditPaqueteImg !== 'function') {
    window.previewEditPaqueteImg = function(event) {
        const input = event.target;
        const img = document.getElementById('preview-edit-paquete-img');
        if (!img) {
            return;
        }

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        } else {
            img.src = '#';
            img.style.display = 'none';
        }
    };
}

if (typeof window.removePackageFeatureRow !== 'function') {
    window.removePackageFeatureRow = function(button) {
        const row = button ? button.closest('.package-feature-inline-row') : null;
        if (row) {
            row.remove();
        }
    };
}

if (typeof window.addPackageFeatureRow !== 'function') {
    window.addPackageFeatureRow = function(containerId, nameField, iconField, iconOptionsHtml, applyModeField = '') {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }

        const row = document.createElement('div');
        row.className = 'package-feature-inline-row d-grid gap-2';
        row.innerHTML = `
            <div class="row g-2 align-items-center rounded-4 p-2" style="background:#0f172a;border:1px solid rgba(34,211,238,0.12);">
                <input type="hidden" name="${applyModeField}" value="" data-package-feature-apply-input>
                <div class="col-md-4">
                    <label class="form-label text-neon small mb-1">Icono</label>
                    <div class="d-flex align-items-center gap-2" data-package-feature-editor>
                        <select name="${iconField}" data-package-feature-icon-select class="form-select" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">${iconOptionsHtml}</select>
                        <span data-package-feature-icon-preview class="d-inline-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width:44px;height:44px;background:#0f172a;border:1px solid rgba(34,211,238,0.22);color:#67e8f9;"></span>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label text-neon small mb-1">Nombre</label>
                    <input type="text" name="${nameField}" class="form-control" placeholder="Nombre de la caracteristica" style="background:#222c3a;color:#22d3ee;border:1px solid #22d3ee;">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-neon small mb-1 d-block">Aplicacion</label>
                    <button type="button" class="btn btn-outline-warning btn-sm w-100" data-package-feature-apply-button onclick="window.openPackageFeatureApplyModal(this)">Aplicar a todos</button>
                </div>
                <div class="col-md-2">
                    <label class="form-label text-neon small mb-1 d-block">Accion</label>
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="window.removePackageFeatureRow(this)">Quitar</button>
                </div>
            </div>`;
        container.appendChild(row);
        bindPackageFeatureIconPreview(row);
        if (typeof window.bindPackageFeatureApplyButtons === 'function') {
            window.bindPackageFeatureApplyButtons(row);
        }
    };
}

if (typeof window.openPackageFeatureApplyModal !== 'function') {
    window.openPackageFeatureApplyModal = function(button) {
        const modal = document.getElementById('package-feature-apply-modal');
        const input = button && button.closest('.row') ? button.closest('.row').querySelector('[data-package-feature-apply-input]') : null;
        if (!modal || !input) {
            return;
        }
        window.__packageFeatureApplyTarget = input;
        modal.classList.remove('d-none');
        modal.classList.add('d-flex');
    };
}

if (typeof window.closePackageFeatureApplyModal !== 'function') {
    window.closePackageFeatureApplyModal = function() {
        const modal = document.getElementById('package-feature-apply-modal');
        if (!modal) {
            return;
        }
        modal.classList.add('d-none');
        modal.classList.remove('d-flex');
        window.__packageFeatureApplyTarget = null;
    };
}

if (typeof window.selectPackageFeatureApplyMode !== 'function') {
    window.selectPackageFeatureApplyMode = function(mode) {
        const normalizedMode = mode === 'replace' ? 'replace' : (mode === 'add' ? 'add' : '');
        if (normalizedMode !== '' && window.__packageFeatureApplyTarget) {
            window.__packageFeatureApplyTarget.value = normalizedMode;
            const button = window.__packageFeatureApplyTarget.closest('.row') ? window.__packageFeatureApplyTarget.closest('.row').querySelector('[data-package-feature-apply-button]') : null;
            window.syncPackageFeatureApplyButton(button);
        }
        window.closePackageFeatureApplyModal();
    };
}

if (typeof window.syncPackageFeatureApplyButton !== 'function') {
    window.syncPackageFeatureApplyButton = function(button) {
        if (!button) {
            return;
        }
        const input = button.closest('.row') ? button.closest('.row').querySelector('[data-package-feature-apply-input]') : null;
        const mode = input ? String(input.value || '').trim() : '';
        button.classList.remove('btn-outline-warning', 'btn-outline-info', 'btn-outline-danger');
        if (mode === 'replace') {
            button.classList.add('btn-outline-danger');
            button.textContent = 'Reemplazar en los demas';
            return;
        }
        if (mode === 'add') {
            button.classList.add('btn-outline-info');
            button.textContent = 'Agregar en los demas';
            return;
        }
        button.classList.add('btn-outline-warning');
        button.textContent = 'Aplicar a todos';
    };
}

if (typeof window.bindPackageFeatureApplyButtons !== 'function') {
    window.bindPackageFeatureApplyButtons = function(root = document) {
        root.querySelectorAll('[data-package-feature-apply-button]').forEach((button) => {
            window.syncPackageFeatureApplyButton(button);
        });
    };
}

bindPackageFeatureIconPreview();
window.bindPackageFeatureApplyButtons();

const packageFeatureApplyReplaceButton = document.getElementById('package-feature-apply-replace');
const packageFeatureApplyAddButton = document.getElementById('package-feature-apply-add');
const packageFeatureApplyCancelButton = document.getElementById('package-feature-apply-cancel');
const packageFeatureApplyModal = document.getElementById('package-feature-apply-modal');

if (packageFeatureApplyReplaceButton && !packageFeatureApplyReplaceButton.dataset.boundAction) {
    packageFeatureApplyReplaceButton.dataset.boundAction = '1';
    packageFeatureApplyReplaceButton.addEventListener('click', function() {
        window.selectPackageFeatureApplyMode('replace');
    });
}

if (packageFeatureApplyAddButton && !packageFeatureApplyAddButton.dataset.boundAction) {
    packageFeatureApplyAddButton.dataset.boundAction = '1';
    packageFeatureApplyAddButton.addEventListener('click', function() {
        window.selectPackageFeatureApplyMode('add');
    });
}

if (packageFeatureApplyCancelButton && !packageFeatureApplyCancelButton.dataset.boundAction) {
    packageFeatureApplyCancelButton.dataset.boundAction = '1';
    packageFeatureApplyCancelButton.addEventListener('click', function() {
        window.closePackageFeatureApplyModal();
    });
}

if (packageFeatureApplyModal && !packageFeatureApplyModal.dataset.boundDismiss) {
    packageFeatureApplyModal.dataset.boundDismiss = '1';
    packageFeatureApplyModal.addEventListener('click', function(event) {
        if (event.target === packageFeatureApplyModal) {
            window.closePackageFeatureApplyModal();
        }
    });
}

if (typeof window.submitAjaxAdminForm !== 'function') {
    window.submitAjaxAdminForm = async function(form, requestData = null) {
        const method = (form.method || 'POST').toUpperCase();
        const formData = requestData instanceof FormData ? requestData : new FormData(form);
        const headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json, text/plain, */*'
        };

        let response;
        if (method === 'GET') {
            const params = new URLSearchParams(formData);
            const separator = (form.action || window.location.href).includes('?') ? '&' : '?';
            response = await fetch((form.action || window.location.href) + separator + params.toString(), {
                method,
                headers,
                cache: 'no-store'
            });
        } else {
            response = await fetch(form.action || window.location.href, {
                method,
                headers,
                body: formData
            });
        }

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || payload.ok !== true) {
            throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar el cambio.');
        }

        return payload;
    };
}

window.adminPackageToggle = async function(input) {
    if (!input || input.dataset.busy === '1' || !input.form) {
        return;
    }

    const form = input.form;
    const valueInput = form.querySelector('.js-ajax-toggle-value');
    const label = form.querySelector('.js-ajax-toggle-label');

    if (valueInput) {
        valueInput.value = input.checked ? '1' : '0';
    }

    const requestData = new FormData(form);
    input.dataset.busy = '1';
    input.disabled = true;

    try {
        const payload = await window.submitAjaxAdminForm(form, requestData);
        input.checked = String(payload.activo || 0) === '1';
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        if (label) {
            label.textContent = input.checked ? 'Activo' : 'Inactivo';
        }
    } catch (error) {
        input.checked = !input.checked;
        if (valueInput) {
            valueInput.value = input.checked ? '1' : '0';
        }
        window.alert(error.message);
    } finally {
        input.disabled = false;
        input.dataset.busy = '0';
    }
};

window.adminPackageOrderChange = async function(input) {
    if (!input || !input.form) {
        return;
    }

    const form = input.form;
    const normalized = String(Math.max(1, parseInt(input.value || '1', 10) || 1));
    const lastValue = String(input.dataset.lastValue || input.defaultValue || '1');
    if (normalized === lastValue) {
        input.value = normalized;
        return;
    }

    input.value = normalized;
    const requestData = new FormData(form);
    input.readOnly = true;

    try {
        const payload = await window.submitAjaxAdminForm(form, requestData);
        input.dataset.lastValue = String(payload.orden || normalized);
        input.value = input.dataset.lastValue;
    } catch (error) {
        input.value = lastValue;
        window.alert(error.message);
    } finally {
        input.readOnly = false;
    }
};
</script>

<?php include '../includes/footer.php'; ?>