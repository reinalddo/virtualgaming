<?php
require_once __DIR__ . "/includes/tenant.php";
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/store_config.php";
require_once __DIR__ . "/includes/currency.php";
require_once __DIR__ . "/includes/payment_methods.php";
require_once __DIR__ . "/includes/recargas_api.php";
require_once __DIR__ . "/includes/slugify.php";
require_once __DIR__ . "/includes/player_verification.php";
require_once __DIR__ . "/includes/package_features.php";
require_once __DIR__ . "/includes/payment_difference.php";
require_once __DIR__ . "/includes/game_entry_window_per_game.php";
require_once __DIR__ . "/includes/win_points.php";
require_once __DIR__ . "/includes/binance_pay.php";
require_once __DIR__ . "/includes/package_account_sales.php";
currency_ensure_schema();
package_features_ensure_schema($mysqli);
package_account_sales_ensure_schema($mysqli);
$paymentSupportWhatsappBase = store_config_whatsapp_link(store_config_get('whatsapp', ''));
$binancePayCheckoutEnabled = binance_pay_is_enabled() && binance_pay_is_configured();
$rememberLastPurchaseIdentifierEnabled = trim((string) store_config_get('guardar_ultimo_id', '0')) === '1';
$packageQuantityPurchaseEnabled = trim((string) store_config_get('cantidad_paquetes', '0')) === '1';
$accountSaleFeatureEnabled = trim((string) store_config_get('vender_cuentas', '0')) === '1';

function fetch_user_legacy_purchase_defaults(mysqli $mysqli, int $userId): array {
  $defaults = [
    'user_identifier' => '',
    'phone' => '',
  ];

  if ($userId <= 0) {
    return $defaults;
  }

  $stmt = $mysqli->prepare('SELECT last_purchase_user_identifier, last_purchase_phone FROM usuarios WHERE id = ? LIMIT 1');
  if (!$stmt) {
    return $defaults;
  }

  $stmt->bind_param('i', $userId);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  $stmt->close();

  if (!is_array($row)) {
    return $defaults;
  }

  $defaults['user_identifier'] = trim((string) ($row['last_purchase_user_identifier'] ?? ''));
  $defaults['phone'] = trim((string) ($row['last_purchase_phone'] ?? ''));

  return $defaults;
}

function fetch_user_game_purchase_defaults(mysqli $mysqli, int $userId, int $gameId): array {
  $defaults = [
    'has_history' => false,
    'user_identifier' => '',
    'phone' => '',
  ];

  if ($userId <= 0 || $gameId <= 0) {
    return $defaults;
  }

  $tableResult = $mysqli->query("SHOW TABLES LIKE 'pedidos'");
  if (!($tableResult instanceof mysqli_result) || $tableResult->num_rows === 0) {
    return $defaults;
  }

  $stmt = $mysqli->prepare(
    "SELECT
        EXISTS(
          SELECT 1
          FROM pedidos p
          WHERE p.cliente_usuario_id = ? AND p.juego_id = ?
          LIMIT 1
        ) AS has_history,
        (
          SELECT TRIM(p.user_identifier)
          FROM pedidos p
          WHERE p.cliente_usuario_id = ?
            AND p.juego_id = ?
            AND p.user_identifier IS NOT NULL
            AND TRIM(p.user_identifier) <> ''
          ORDER BY p.actualizado_en DESC, p.id DESC
          LIMIT 1
        ) AS user_identifier,
        (
          SELECT TRIM(p.telefono_contacto)
          FROM pedidos p
          WHERE p.cliente_usuario_id = ?
            AND p.juego_id = ?
            AND p.telefono_contacto IS NOT NULL
            AND TRIM(p.telefono_contacto) <> ''
          ORDER BY p.actualizado_en DESC, p.id DESC
          LIMIT 1
        ) AS phone"
  );
  if (!$stmt) {
    return $defaults;
  }

  $stmt->bind_param('iiiiii', $userId, $gameId, $userId, $gameId, $userId, $gameId);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  $stmt->close();

  if (!is_array($row)) {
    return $defaults;
  }

  $defaults['has_history'] = !empty($row['has_history']);
  $defaults['user_identifier'] = trim((string) ($row['user_identifier'] ?? ''));
  $defaults['phone'] = trim((string) ($row['phone'] ?? ''));

  return $defaults;
}

$loggedUserId = 0;
$loggedUserEmail = '';
$loggedUserLastPurchaseIdentifier = '';
$loggedUserLastPurchasePhone = '';
$paymentDifferenceEnabled = false;
$activePaymentDifferenceCredit = null;
tenant_start_session();
if (!empty($_SESSION['auth_user']['id'])) {
  $loggedUserId = (int) $_SESSION['auth_user']['id'];
}
if (!empty($_SESSION['auth_user']['email'])) {
  $loggedUserEmail = (string) $_SESSION['auth_user']['email'];
}
$paymentDifferenceEnabled = payment_difference_feature_enabled();
$activePaymentDifferenceCredit = $paymentDifferenceEnabled ? payment_difference_get_credit() : null;
payment_methods_ensure_table();
$paymentMethodsByCurrency = payment_methods_active_by_currency();
$game = null;
$requestedGame = isset($_GET['slug']) || isset($_GET['id']);
$requestedSlugSegment = trim((string) ($_GET['requested_slug'] ?? ''));
$requestedSlugSegment = $requestedSlugSegment !== '' ? slugify($requestedSlugSegment) : '';
if ($requestedSlugSegment === 'n-a') {
  $requestedSlugSegment = '';
}
if (isset($_GET['slug'])) {
  $slug = slugify((string) $_GET['slug']);
  if ($slug !== 'n-a') {
    $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE slug=? AND COALESCE(activo, 1) = 1 ORDER BY id ASC LIMIT 1");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $res = $stmt->get_result();
    $game = $res->fetch_assoc();
    $stmt->close();
  }
} elseif (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE id=? AND COALESCE(activo, 1) = 1 LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $game = $res->fetch_assoc();
  $stmt->close();
}
if (!$game && !$requestedGame) {
  // Si no se encuentra, mostrar el primero
  $res = $mysqli->query("SELECT * FROM juegos WHERE COALESCE(activo, 1) = 1 ORDER BY CASE WHEN orden IS NULL THEN 1 ELSE 0 END, orden ASC, id ASC LIMIT 1");
  $game = $res ? $res->fetch_assoc() : null;
}
if (!$game) {
  die('Juego no encontrado.');
}

$gameEntryWindowPayload = game_entry_window_public_payload($mysqli, (int) ($game['id'] ?? 0));

if ($loggedUserId > 0) {
  $legacyPurchaseDefaults = fetch_user_legacy_purchase_defaults($mysqli, $loggedUserId);
  if ($rememberLastPurchaseIdentifierEnabled) {
    $loggedUserLastPurchaseIdentifier = $legacyPurchaseDefaults['user_identifier'];
  }
  $loggedUserLastPurchasePhone = $legacyPurchaseDefaults['phone'];

  $gamePurchaseDefaults = fetch_user_game_purchase_defaults($mysqli, $loggedUserId, (int) ($game['id'] ?? 0));
  if (!empty($gamePurchaseDefaults['has_history'])) {
    if ($rememberLastPurchaseIdentifierEnabled) {
      $loggedUserLastPurchaseIdentifier = $gamePurchaseDefaults['user_identifier'];
    }
    $loggedUserLastPurchasePhone = $gamePurchaseDefaults['phone'];
  }
}

if ($requestedGame) {
  $canonicalSlug = game_resolve_slug($game);
  $requiresCanonicalRedirect = isset($_GET['slug']) || $requestedSlugSegment !== $canonicalSlug;
  if ($requiresCanonicalRedirect) {
    header('Location: ' . app_path(game_route_path($game)), true, 301);
    exit;
  }
}

$playerVerificationConfig = player_verification_frontend_config($game);
$winPointsConfig = win_points_config();
$winPointsEnabled = !empty($winPointsConfig['enabled']);
$winPointsProgramName = (string) ($winPointsConfig['name'] ?? 'Win Points');
$winPointsIconUrl = (string) ($winPointsConfig['icon_url'] ?? '');
$winPointsNotificationLogoUrl = trim((string) store_config_get('recarga_notificaciones_logo', ''));
if ($winPointsNotificationLogoUrl === '') {
  $winPointsNotificationLogoUrl = trim((string) store_config_get('logo_tienda', ''));
}
$winPointsBadgeBackgroundColor = (string) ($winPointsConfig['badge_background_color'] ?? '#3E2D07');
$winPointsBadgeTextColor = (string) ($winPointsConfig['badge_text_color'] ?? '#FCD34D');
$winPointsNotificationPosition = (string) ($winPointsConfig['notification_position'] ?? 'bottom-left');
$winPointsBadgeBorderColor = win_points_hex_to_rgba($winPointsBadgeTextColor, 0.25);
$winPointsBadgeInsetColor = win_points_hex_to_rgba($winPointsBadgeTextColor, 0.08);
$winPointsGuestMessage = (string) ($winPointsConfig['guest_message'] ?? '');
$winPointsUserSummary = $winPointsEnabled && $loggedUserId > 0
  ? win_points_fetch_user_summary($mysqli, $loggedUserId)
  : win_points_empty_user_summary();
$winPointsPackageRewards = $winPointsEnabled
  ? win_points_fetch_game_package_rewards($mysqli, (int) ($game['id'] ?? 0))
  : [];
$winPointsRedemptionRules = $winPointsEnabled
  ? win_points_fetch_game_redemption_rules($mysqli, (int) ($game['id'] ?? 0))
  : [];
$paymentHeaderMinimalEnabled = store_config_get('encabezado_pago', '0') === '1';
$paymentWindowConfigEnabled = store_config_get('ventana_pago_config', '0') === '1';
$paymentSendingOrderTitle = trim((string) store_config_get('ventana_pago_enviando_titulo', 'Enviando orden...'));
if ($paymentSendingOrderTitle === '') {
  $paymentSendingOrderTitle = 'Enviando orden...';
}
$paymentSendingOrderMessage = trim((string) store_config_get('ventana_pago_enviando_mensaje', 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.'));
if ($paymentSendingOrderMessage === '') {
  $paymentSendingOrderMessage = 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.';
}
$paymentSuccessTitle = trim((string) store_config_get('ventana_pago_exitoso_titulo', 'Pago exitoso'));
if ($paymentSuccessTitle === '') {
  $paymentSuccessTitle = 'Pago exitoso';
}
$paymentSuccessExtraMessage = trim((string) store_config_get('ventana_pago_exitoso_mensaje_extra', ''));

$scriptDir = app_base_path();
$pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming') . " | " . ($game["nombre"] ?? "Juego");
include __DIR__ . "/includes/header.php";
?>


<section class="container mt-5 mb-4 p-4 bg-dark bg-opacity-75 rounded-4 shadow">
  <div class="row align-items-center">
    <div class="col-auto">
      <div class="rounded-4 border border-info bg-dark position-relative overflow-hidden" style="width:64px; height:64px;">
        <img src="<?= htmlspecialchars(app_path('/' . ltrim((string) ($game["imagen"] ?? ''), '/')), ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?>" class="w-100 h-100 object-fit-cover" />
        <?php if (!empty($game['popular'])): ?>
          <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="right:8px;top:8px;">★</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="col">
      <h2 class="h4 fw-bold mb-2 text-info"><?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?></h2>
      <div class="d-flex flex-wrap gap-2 text-secondary small">
        <?php 
          $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($game['id']));
          while ($row = $carRes->fetch_assoc()) {
            echo '<span class="game-feature-badge">' . htmlspecialchars($row['caracteristica']) . '</span>';
          }
        ?>
      </div>
    </div>
  </div>
</section>

<section class="container mt-4">
  <div class="row mb-2 align-items-center">
    <div class="col">
      <h3 class="h5 fw-bold text-info">Paquetes disponibles</h3>
    </div>
    <div class="col-auto">
      <span class="text-uppercase text-secondary small">elige uno</span>
    </div>
  </div>
  <?php
    // Obtener todas las monedas
    $monedas = [];
    $resAllMon = $mysqli->query("SELECT * FROM monedas ORDER BY es_base DESC, nombre ASC");
    while ($row = $resAllMon->fetch_assoc()) {
      $monedas[] = $row;
    }
    $is_variable = empty($game['moneda_fija_id']);
    $moneda_actual_id = $is_variable ? ($monedas[0]['id'] ?? null) : $game['moneda_fija_id'];
    $moneda_actual = null;
    foreach ($monedas as $m) {
      if ($m['id'] == $moneda_actual_id) {
        $moneda_actual = $m;
        break;
      }
    }
    if (!$moneda_actual && count($monedas)) $moneda_actual = $monedas[0];
  ?>
  <?php if ($is_variable && count($monedas) > 1): ?>
    <div class="mb-4">
      <label for="moneda-select" class="form-label text-info">Selecciona la moneda:</label>
      <select id="moneda-select" class="form-select bg-dark text-info border-info" style="min-width:180px">
        <?php foreach ($monedas as $m): ?>
          <option value="<?= $m['id'] ?>" data-tasa="<?= htmlspecialchars($m['tasa']) ?>" data-clave="<?= htmlspecialchars($m['clave']) ?>" <?= $m['id'] == $moneda_actual['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <?php
    $usesCatalogApi = trim((string) ($game['categoria_api'] ?? '')) !== '';
    $apiProductsById = [];
    if ($usesCatalogApi && recargas_api_is_configured()) {
      try {
        foreach (recargas_api_fetch_products_by_category((string) $game['categoria_api']) as $apiProduct) {
          $apiProductsById[(int) ($apiProduct['id'] ?? 0)] = $apiProduct;
        }
      } catch (Throwable $e) {
        $apiProductsById = [];
      }
    }

    $resPaq = $mysqli->query("SELECT * FROM juego_paquetes WHERE juego_id=" . intval($game['id']) . " AND COALESCE(activo, 1) = 1 ORDER BY CASE WHEN orden IS NULL THEN 1 ELSE 0 END, orden ASC, id ASC");
    $paquetes = [];
    while ($pack = $resPaq->fetch_assoc()) {
      $paquetes[] = $pack;
    }
    $packageAccountSaleGalleryMap = $accountSaleFeatureEnabled
      ? package_account_sales_fetch_gallery_map($mysqli, array_map(static fn (array $package): int => (int) ($package['id'] ?? 0), $paquetes))
      : [];
    $packageFeaturesByPackage = package_features_for_packages($mysqli, array_map(static fn (array $package): int => (int) ($package['id'] ?? 0), $paquetes));
  ?>
  <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3 mb-4" id="pack-grid">
    <?php foreach ($paquetes as $pack):
        $precio_base = floatval($pack['precio']);
        $precio_mostrar = $moneda_actual ? currency_convert_from_base($precio_base, $moneda_actual) : currency_apply_amount_rule($precio_base, null);
        $clave_moneda = $moneda_actual['clave'] ?? 'USD';
        $mostrarDecimales = $moneda_actual ? currency_should_show_decimals($moneda_actual) : true;
        $packApiId = (int) ($pack['paquete_api'] ?? 0);
        $packId = (int) ($pack['id'] ?? 0);
        $packWinPointsReward = (int) ($winPointsPackageRewards[$packId] ?? 0);
        $packWinPointsRule = $winPointsRedemptionRules[$packId] ?? null;
        $packWinPointsRequired = max(0, (int) (($packWinPointsRule['required_points'] ?? 0)));
        $packWinPointsRuleActive = is_array($packWinPointsRule) && !empty($packWinPointsRule['activo']) && $packWinPointsRequired > 0;
        $apiRequiredFields = [];
        $packFeatures = $packageFeaturesByPackage[$packId] ?? [];
        $packIsAccountSale = package_account_sales_is_enabled_for_package($pack, $accountSaleFeatureEnabled);
        $packAccountGallery = $packIsAccountSale ? ($packageAccountSaleGalleryMap[$packId] ?? []) : [];
        $packAccountGalleryPayload = array_values(array_map(static function (array $item): array {
          $imageUrl = package_feature_public_asset_url((string) ($item['image_path'] ?? ''));
          return [
            'image_url' => $imageUrl,
            'description' => package_account_sales_normalize_caption((string) ($item['description'] ?? '')),
            'order' => max(1, (int) ($item['order'] ?? 1)),
          ];
        }, array_filter($packAccountGallery, static function (array $item): bool {
          return trim((string) ($item['image_path'] ?? '')) !== '';
        })));
        if ($usesCatalogApi && $packApiId > 0 && isset($apiProductsById[$packApiId])) {
          $apiRequiredFields = recargas_api_describe_required_fields($apiProductsById[$packApiId]);
        }
        $img_paquete = !empty($pack['imagen_icono']) ? $pack['imagen_icono'] : (!empty($game['imagen_paquete']) ? $game['imagen_paquete'] : null);
        $packImageUrl = package_feature_public_asset_url($img_paquete);
    ?>
      <div class="col">
        <button type="button" class="pack-card card border-info bg-dark text-start w-100 h-100 shadow-sm"
          data-package-id="<?= $packId ?>"
          data-base="<?= htmlspecialchars($precio_base) ?>"
          data-name="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>"
          data-cantidad="<?= htmlspecialchars($pack['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
          data-price-value="<?= htmlspecialchars((string) $precio_mostrar, ENT_QUOTES, 'UTF-8') ?>"
          data-show-decimals="<?= $mostrarDecimales ? '1' : '0' ?>"
          data-required-fields="<?= htmlspecialchars(json_encode($apiRequiredFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
          data-win-points-reward="<?= $packWinPointsReward ?>"
          data-win-points-required="<?= $packWinPointsRequired ?>"
          data-win-points-active="<?= $packWinPointsRuleActive ? '1' : '0' ?>"
          data-package-image="<?= htmlspecialchars($packImageUrl, ENT_QUOTES, 'UTF-8') ?>"
          data-package-features="<?= htmlspecialchars(json_encode($packFeatures, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
          data-account-sale="<?= $packIsAccountSale ? '1' : '0' ?>"
          data-account-gallery="<?= htmlspecialchars(json_encode($packAccountGalleryPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
          data-moneda="<?= htmlspecialchars($clave_moneda) ?>">
          <div class="card-body p-0 d-flex flex-column">
            <div class="pack-card-media">
              <?php if ($img_paquete): ?>
                <img src="<?= htmlspecialchars($packImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>" class="pack-card-image" />
              <?php else: ?>
                <span class="pack-card-placeholder">PK</span>
              <?php endif; ?>
            </div>
            <div class="pack-card-content">
              <p class="pack-card-name mb-0 fw-semibold"><?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
              <?php if ($packIsAccountSale): ?>
                <span class="pack-account-sale-badge">Cuenta</span>
              <?php endif; ?>
              <div class="pack-card-footer">
                <span class="moneda-label"><?= htmlspecialchars($clave_moneda) ?></span>
                <span class="precio-label">
                  <?= currency_format_amount($precio_mostrar, $moneda_actual) ?>
                </span>
              </div>
              <?php if ($winPointsEnabled && $packWinPointsReward > 0): ?>
                <div class="pack-win-points-badge">
                  <?php if ($winPointsIconUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($winPointsIconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($winPointsProgramName, ENT_QUOTES, 'UTF-8') ?>" class="pack-win-points-icon" />
                  <?php endif; ?>
                  <span>+<?= $packWinPointsReward ?> <?= htmlspecialchars($winPointsProgramName, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </button>
      </div>
    <?php endforeach; ?>
  </div>
  <?php 
    $monedas_js = [];
    foreach ($monedas as $m) {
      $monedas_js[$m['id']] = [
        'tasa' => floatval($m['tasa']),
        'clave' => $m['clave'],
        'mostrar_decimales' => !empty($m['mostrar_decimales']),
      ];
    }
  ?>
  <script>
    const monedas = <?= json_encode($monedas_js) ?>;
    let monedaActualId = "<?= $moneda_actual['id'] ?? '' ?>";
    let monedaActualClave = "<?= $moneda_actual['clave'] ?? 'USD' ?>";
    let monedaActualTasa = <?= $moneda_actual['tasa'] ?? 1 ?>;
    let monedaActualMostrarDecimales = <?= $moneda_actual ? (currency_should_show_decimals($moneda_actual) ? 'true' : 'false') : 'true' ?>;
    const monedaSelect = document.getElementById('moneda-select');
    const packCards = Array.from(document.querySelectorAll('.pack-card'));
    const normalizeCurrencyAmount = (amount, showDecimals) => {
      const numericAmount = Number(amount || 0);
      if (!Number.isFinite(numericAmount)) {
        return 0;
      }
      return showDecimals ? Number(numericAmount.toFixed(2)) : Math.trunc(numericAmount);
    };
    const formatCurrencyAmount = (amount, showDecimals) => {
      const normalized = normalizeCurrencyAmount(amount, showDecimals);
      return normalized.toLocaleString('en-US', {
        minimumFractionDigits: showDecimals ? 2 : 0,
        maximumFractionDigits: showDecimals ? 2 : 0,
      });
    };
    function updatePackPrices() {
      packCards.forEach(card => {
        const base = parseFloat(card.getAttribute('data-base'));
        const precio = normalizeCurrencyAmount(base * monedaActualTasa, monedaActualMostrarDecimales);
        card.querySelector('.precio-label').textContent = formatCurrencyAmount(precio, monedaActualMostrarDecimales);
        card.querySelector('.moneda-label').textContent = monedaActualClave;
        card.setAttribute('data-price-value', String(precio));
        card.setAttribute('data-show-decimals', monedaActualMostrarDecimales ? '1' : '0');
        card.setAttribute('data-moneda', monedaActualClave);
      });
    }
    updatePackPrices();
  </script>
</section>


  <div class="container mb-4">
    <div class="row mb-2">
      <div class="col">
        <h3 class="h5 fw-bold text-info">Resumen de compra</h3>
        <span class="text-uppercase text-secondary small">verifica</span>
      </div>
    </div>
    <div class="row g-3">
      <div class="col-md-8">
        <div class="card bg-dark border-info mb-2">
          <div class="card-body">
            <div class="purchase-summary-head<?= $packageQuantityPurchaseEnabled ? '' : ' purchase-summary-head-single' ?>">
              <div class="purchase-summary-pack-copy">
                <p class="small text-secondary mb-1">Paquete seleccionado</p>
                <p id="selected-pack" class="fw-bold text-white mb-0">Ninguno</p>
                <div id="selected-pack-account-actions" class="selected-pack-account-actions d-none">
                  <button type="button" id="selected-pack-preview-btn" class="btn btn-outline-info selected-pack-account-btn">Ver más</button>
                  <button type="button" id="selected-pack-buy-btn" class="btn btn-info selected-pack-account-btn">Comprar</button>
                </div>
              </div>
              <?php if ($packageQuantityPurchaseEnabled): ?>
              <div class="purchase-quantity-panel">
                <label for="order-quantity" class="purchase-quantity-label">Cantidad a comprar</label>
                <div class="purchase-quantity-stepper">
                  <button type="button" id="order-quantity-decrease" class="purchase-quantity-btn" aria-label="Disminuir cantidad" disabled>-</button>
                  <input type="number" id="order-quantity" min="1" step="1" value="1" inputmode="numeric" class="purchase-quantity-input" disabled>
                  <button type="button" id="order-quantity-increase" class="purchase-quantity-btn" aria-label="Aumentar cantidad" disabled>+</button>
                </div>
                <div id="order-quantity-help" class="purchase-quantity-help">Minimo 1 recarga.</div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-dark border-info mb-2">
          <div class="card-body">
            <p class="small text-secondary mb-1">Total</p>
            <p id="selected-price" class="fw-bold text-info fs-5"><?= ($moneda_actual['clave'] ?? 'Bs.') . ' ' . currency_format_amount(0, $moneda_actual) ?></p>
            <p id="selected-price-detail" class="small text-secondary mb-0 d-none"></p>
            <p id="selected-win-points-total" class="small fw-semibold text-warning mb-0 d-none"></p>
            <div id="payment-difference-banner" class="d-none payment-difference-banner mt-3"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<section class="container mt-5 mb-5 p-4 bg-dark bg-opacity-75 rounded-4 shadow">
  <div class="row mb-2 align-items-center">
    <div class="col">
      <h3 class="h5 fw-bold text-info">Información de pedido</h3>
      <span class="text-uppercase text-secondary small">seguro</span>
    </div>
  </div>
  <form class="row g-3" id="order-form">
    <div class="col-12">
      <div class="row g-3" id="player-fields-row">
        <div class="col-md-6 col-12" id="player-primary-field">
          <label class="form-label text-info" id="player-primary-label">ID de usuario</label>
          <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch">
            <input type="text" id="order-user-id" name="user_id" placeholder="Ej: 12345678" value="<?= htmlspecialchars($loggedUserLastPurchaseIdentifier, ENT_QUOTES, 'UTF-8') ?>" class="form-control bg-dark text-info border-info" required />
            <button type="button" id="verify-player-button" class="btn btn-outline-info fw-bold text-nowrap<?= $playerVerificationConfig ? '' : ' d-none' ?>"><?= htmlspecialchars((string) ($playerVerificationConfig['buttonLabel'] ?? 'Verificar nombre del jugador'), ENT_QUOTES, 'UTF-8') ?></button>
          </div>
          <div id="player-verification-feedback" class="d-none mt-2"></div>
        </div>
        <div id="extra-player-fields" class="col-md-6 col-12"></div>
      </div>
    </div>
    <div class="col-md-6">
      <label class="form-label text-info">Correo</label>
      <input type="email" name="email" placeholder="tu@email.com" value="<?= htmlspecialchars($loggedUserEmail, ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" class="form-control bg-dark text-info border-info" required />
    </div>
    <div class="col-md-6">
      <label class="form-label text-info">Cupón</label>
      <div class="input-group">
        <input type="text" name="coupon" id="coupon-input" placeholder="Código opcional" pattern="[A-Za-z0-9]+" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" title="Solo letras y números, sin espacios ni caracteres especiales." class="form-control bg-dark text-info border-info" />
        <button type="button" id="apply-coupon-btn" class="btn btn-info fw-bold">Activar Código</button>
      </div>
    </div>
    <div class="col-12">
      <div id="account-sale-note" class="d-none alert account-sale-note mb-0">
        Al verificar el pago te mostraremos los datos completos de la cuenta comprada junto con su galería registrada.
      </div>
    </div>
    <div class="col-12">
      <button type="submit" id="buy-button" class="btn btn-success w-100 fw-bold text-uppercase" disabled>
        Comprar Ahora
      </button>
      <?php if ($winPointsEnabled && $loggedUserId <= 0): ?>
        <div id="win-points-guest-hint" class="win-points-guest-hint">
          <?= htmlspecialchars($winPointsGuestMessage, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
    </div>
  </form>


  <?php if (!empty($gameEntryWindowPayload['enabled'])): ?>
  <div id="game-entry-window-modal" class="app-overlay-modal game-entry-window-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="game-entry-window-modal-content" style="--entry-window-background: <?= htmlspecialchars((string) ($gameEntryWindowPayload['modal_background'] ?? '#18101e'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-border-color: <?= htmlspecialchars((string) ($gameEntryWindowPayload['modal_border_color'] ?? '#fb923c'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-title-color: <?= htmlspecialchars((string) ($gameEntryWindowPayload['title_color'] ?? '#f8b53d'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-check-color: <?= htmlspecialchars((string) ($gameEntryWindowPayload['check_text_color'] ?? '#e2e8f0'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-check-background: <?= htmlspecialchars((string) ($gameEntryWindowPayload['check_background_color'] ?? '#1e293b'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-button-color: <?= htmlspecialchars((string) ($gameEntryWindowPayload['button_text_color'] ?? '#0b0f18'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-button-background: <?= htmlspecialchars((string) ($gameEntryWindowPayload['button_background_color'] ?? '#c99712'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-button-disabled-color: <?= htmlspecialchars((string) ($gameEntryWindowPayload['button_disabled_text_color'] ?? '#0b0f18'), ENT_QUOTES, 'UTF-8') ?>; --entry-window-button-disabled-background: <?= htmlspecialchars((string) ($gameEntryWindowPayload['button_disabled_background_color'] ?? '#c99712'), ENT_QUOTES, 'UTF-8') ?>;">
        <div class="game-entry-window-modal-header">
          <div class="game-entry-window-modal-media">
            <?php if (!empty($gameEntryWindowPayload['icon'])): ?>
              <img src="<?= htmlspecialchars((string) $gameEntryWindowPayload['icon'], ENT_QUOTES, 'UTF-8') ?>" alt="Ventana inicial en juegos" class="game-entry-window-modal-image">
            <?php else: ?>
              <span class="game-entry-window-modal-media-fallback">VG</span>
            <?php endif; ?>
          </div>
          <h3 class="game-entry-window-modal-heading"><?= htmlspecialchars((string) ($gameEntryWindowPayload['title'] ?? 'ANTES DE CONTINUAR'), ENT_QUOTES, 'UTF-8') ?></h3>
          <p class="game-entry-window-modal-copy"><?= htmlspecialchars((string) ($gameEntryWindowPayload['copy_text'] ?? 'Lee la información antes de continuar con la recarga.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="game-entry-window-modal-cards">
          <?php foreach (($gameEntryWindowPayload['cards'] ?? []) as $entryCard): ?>
            <article class="game-entry-window-info-card" style="--entry-card-color: <?= htmlspecialchars((string) ($entryCard['color'] ?? '#233A73'), ENT_QUOTES, 'UTF-8') ?>; --entry-card-background: <?= htmlspecialchars((string) ($entryCard['background_color'] ?? '#121a2f'), ENT_QUOTES, 'UTF-8') ?>; --entry-card-glow: <?= htmlspecialchars(game_entry_window_hex_to_rgba((string) ($entryCard['color'] ?? '#233A73'), 0.18), ENT_QUOTES, 'UTF-8') ?>;">
              <?= game_entry_window_render_card_markup(is_array($entryCard) ? $entryCard : []) ?>
            </article>
          <?php endforeach; ?>
        </div>
        <div id="game-entry-window-confirmation" class="game-entry-window-confirmation">
          <label class="game-entry-window-confirmation-toggle" for="game-entry-window-check">
            <input type="checkbox" id="game-entry-window-check" class="game-entry-window-confirmation-input" onchange="window.toggleGameEntryWindowConfirmation && window.toggleGameEntryWindowConfirmation(this.checked);">
            <span class="game-entry-window-confirmation-text"><?= htmlspecialchars((string) ($gameEntryWindowPayload['check_text'] ?? 'He leído y entiendo las condiciones del servicio'), ENT_QUOTES, 'UTF-8') ?></span>
          </label>
        </div>
        <button type="button" id="game-entry-window-continue" class="btn btn-warning fw-bold text-uppercase py-3" onclick="return window.acceptGameEntryWindow ? window.acceptGameEntryWindow() : false;" disabled><?= htmlspecialchars((string) ($gameEntryWindowPayload['button_text'] ?? 'Aceptar y continuar'), ENT_QUOTES, 'UTF-8') ?></button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Modal Loading Bootstrap -->
  <div id="loading-modal" class="modal fade app-overlay-modal<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>" tabindex="-1" aria-hidden="true" data-payment-loading-state="processing">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4 payment-loading-modal-content<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>">
        <div class="mb-3">
          <svg width="48" height="48" viewBox="0 0 50 50">
            <circle id="loading-modal-spinner-circle" cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)">
              <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
            </circle>
          </svg>
        </div>
        <h4 id="loading-modal-title" class="fw-bold text-info mb-2 payment-loading-modal-title">Procesando pedido...</h4>
        <p id="loading-modal-message" class="text-light mb-0 small payment-loading-modal-message">Espera un momento mientras completamos la operación.</p>
      </div>
    </div>
  </div>
  <div id="payment-status-modal" class="modal fade app-overlay-modal<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>" tabindex="-1" aria-hidden="true" data-payment-status-state="info">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4 payment-status-modal-content<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>">
        <h4 id="payment-status-modal-title" class="fw-bold text-info mb-3 payment-status-modal-title">Estado de la operación</h4>
        <p id="payment-status-modal-message" class="text-light mb-4 small payment-status-modal-message">Tu solicitud fue procesada.</p>
        <p id="payment-status-modal-extra-message" class="d-none text-light opacity-75 mb-4 small payment-status-modal-extra-message"></p>
        <div id="payment-status-modal-reasons" class="d-none payment-reasons-card mb-3 text-start"></div>
        <div id="payment-status-modal-actions" class="d-none payment-support-actions mb-4"></div>
        <button type="button" id="payment-status-modal-accept" class="btn btn-info fw-bold px-4 payment-status-modal-accept-btn<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>">Aceptar</button>
      </div>
    </div>
  </div>
  <div id="account-gallery-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-dark border-info text-light p-0 account-gallery-modal-content">
        <div class="account-gallery-modal-header">
          <div>
            <p class="account-gallery-modal-eyebrow mb-1">Vista previa</p>
            <h4 id="account-gallery-modal-title" class="fw-bold text-info mb-0">Cuenta disponible</h4>
          </div>
          <button type="button" id="account-gallery-modal-close" class="btn btn-outline-light btn-sm">Cerrar</button>
        </div>
        <div class="account-gallery-modal-body">
          <div class="account-gallery-modal-details">
            <p id="account-gallery-modal-price" class="account-gallery-modal-price mb-0"></p>
            <p class="account-gallery-modal-copy mb-0">La entrega de credenciales se mostrará después de verificar el pago.</p>
          </div>
          <div class="account-gallery-main-frame">
            <img id="account-gallery-modal-image" src="" alt="Vista previa de la cuenta" class="account-gallery-main-image d-none" />
            <div id="account-gallery-modal-placeholder" class="account-gallery-main-placeholder">Sin imágenes registradas</div>
            <p id="account-gallery-modal-caption" class="account-gallery-modal-caption mb-0"></p>
          </div>
          <div id="account-gallery-modal-thumbs" class="account-gallery-thumbs"></div>
        </div>
        <div class="account-gallery-modal-actions">
          <button type="button" id="account-gallery-modal-buy" class="btn btn-info fw-bold">Comprar</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Modal Cupón Bootstrap -->
  <div id="coupon-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4">
        <h4 class="fw-bold text-info mb-2">¿Desea aplicar el cupón <span id="modal-coupon-name" class="text-success"></span>?</h4>
        <div class="d-flex gap-2 justify-content-center mt-4">
          <button type="button" id="modal-yes" class="btn btn-success">Sí</button>
          <button type="button" id="modal-no" class="btn btn-info">No</button>
          <button type="button" id="modal-cancel" class="btn btn-secondary">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <div id="payment-modal" class="modal fade app-overlay-modal<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled payment-main-modal-theme-enabled' : '' ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered payment-modal-dialog">
      <div class="modal-content payment-modal-content text-light border-info<?= $paymentWindowConfigEnabled ? ' payment-modal-skin-enabled' : '' ?>">
        <div class="payment-expiration-banner" id="payment-expiration-banner">
          <span>La orden expira en:</span>
          <strong id="payment-timer-value">30:00</strong>
        </div>
        <div id="payment-modal-alert" class="d-none alert mb-3"></div>
        <div id="payment-modal-reasons" class="d-none payment-reasons-card mb-3"></div>
        <div id="payment-modal-actions" class="d-none payment-support-actions mb-4"></div>
        <div class="payment-summary-card mb-4<?= $paymentHeaderMinimalEnabled ? ' payment-summary-card--minimal' : '' ?>">
          <div class="payment-summary-minimal">
            <div class="payment-summary-minimal-media">
              <img id="payment-summary-image" src="" alt="Paquete" class="payment-summary-minimal-image d-none" />
              <span id="payment-summary-image-placeholder" class="payment-summary-minimal-placeholder">PK</span>
            </div>
            <div class="payment-summary-minimal-copy">
              <h3 id="payment-summary-minimal-product" class="payment-summary-minimal-title">-</h3>
              <div id="payment-summary-features" class="payment-summary-features d-none"></div>
              <div class="payment-summary-minimal-user">ID Jugador: <strong id="payment-summary-minimal-user">-</strong></div>
            </div>
            <div class="payment-summary-minimal-price">
              <div id="payment-summary-minimal-total" class="payment-summary-minimal-total">-</div>
            </div>
          </div>
          <h3 class="payment-summary-card-title h5 fw-bold text-white mb-3">Resumen de Pago</h3>
          <div class="payment-summary-row"><span>ID Jugador:</span><strong id="payment-summary-user">-</strong></div>
          <div class="payment-summary-row"><span>Producto:</span><strong id="payment-summary-product">-</strong></div>
          <div class="payment-summary-row payment-summary-total"><span>Total a pagar:</span><strong id="payment-summary-total">-</strong></div>
        </div>
        <div id="payment-win-points-card" class="payment-win-points-card d-none">
          <div class="payment-win-points-header">
            <div>
              <div id="payment-win-points-title" class="payment-win-points-title">Premios disponibles</div>
              <div id="payment-win-points-copy" class="payment-win-points-copy">Elige si deseas completar esta orden con transferencia o con tus premios acumulados.</div>
            </div>
            <div id="payment-win-points-balance" class="payment-win-points-balance">0</div>
          </div>
          <div id="payment-mode-options" class="payment-win-points-actions"></div>
        </div>
        <div class="payment-mode-panels mb-4">
          <div id="payment-money-panel" class="payment-mode-panel is-active">
            <div class="payment-mode-panel-inner">
              <div id="payment-method-card" class="payment-method-card">
                <div id="payment-method-select-wrap" class="mb-3 d-none">
                  <label for="payment-method-select" class="form-label text-info">Método de pago</label>
                  <select id="payment-method-select" class="form-select bg-dark text-info border-info"></select>
                </div>
                <h4 id="payment-method-title" class="h6 fw-bold text-white mb-2">Datos de pago</h4>
                <div id="payment-method-currency" class="small text-info mb-2"></div>
                <div id="payment-method-details" class="small text-light payment-method-details"></div>
              </div>
              <div id="payment-reference-group" class="mb-3">
                <label for="payment-reference-input" class="form-label text-info">Número de Referencia</label>
                <input type="text" id="payment-reference-input" class="form-control bg-dark text-info border-info" inputmode="numeric" autocomplete="off" placeholder="Inserte su número de referencia para comprobar el pago">
                <div id="payment-reference-help" class="form-text text-secondary">Inserte su número de referencia para comprobar el pago.</div>
              </div>
              <div id="payment-phone-group">
                <label for="payment-phone-input" class="form-label text-info">Número de teléfono real para contactarte</label>
                <input type="tel" id="payment-phone-input" class="form-control bg-dark text-info border-info" autocomplete="tel" value="<?= htmlspecialchars($loggedUserLastPurchasePhone, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 04121234567">
              </div>
            </div>
          </div>
        </div>
        <button type="button" id="payment-submit-btn" class="btn btn-info w-100 fw-bold text-uppercase py-3 payment-submit-btn-theme<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>">Pagado / Recargar</button>
        <button type="button" id="payment-cancel-order-btn" class="btn btn-danger w-100 fw-bold text-uppercase py-3 mt-3 payment-cancel-btn-theme<?= $paymentWindowConfigEnabled ? ' payment-window-theme-enabled' : '' ?>">Cancelar Orden</button>
      </div>
    </div>
  </div>

  <div id="payment-cancel-confirm-modal" class="modal fade app-overlay-modal payment-confirm-overlay" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-danger text-light p-4 rounded-4">
        <h4 class="fw-bold text-danger mb-3">¿Deseas cancelar esta orden?</h4>
        <p class="text-light mb-4">La orden se marcará como cancelada y deberás generar una nueva si quieres continuar con la compra.</p>
        <div class="d-flex gap-2 justify-content-end flex-wrap">
          <button type="button" id="payment-cancel-dismiss-btn" class="btn btn-outline-info">Volver</button>
          <button type="button" id="payment-cancel-confirm-btn" class="btn btn-danger">Sí, cancelar orden</button>
        </div>
      </div>
    </div>
  </div>

<style>
  .app-overlay-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 1080;
    opacity: 0;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(5, 10, 20, 0.78);
    backdrop-filter: blur(4px);
    overflow-y: auto;
    overscroll-behavior-y: contain;
    -webkit-overflow-scrolling: touch;
  }

  .app-overlay-modal.is-visible {
    display: flex !important;
    opacity: 1 !important;
  }

  #loading-modal {
    z-index: 1105;
  }

  #payment-status-modal {
    z-index: 1110;
  }

  .game-entry-window-modal {
    z-index: 1125;
  }

  .game-entry-window-modal .modal-dialog {
    width: min(94vw, 42rem);
  }

  .game-entry-window-modal-content {
    border-radius: 1.75rem;
    padding: 1rem;
    pointer-events: auto;
    background: radial-gradient(circle at top, rgba(251, 191, 36, 0.16), transparent 34%), linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0.01)), var(--entry-window-background, #18101e);
    border: 1px solid var(--entry-window-border-color, #fb923c);
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.48);
  }

  .game-entry-window-modal-header {
    text-align: center;
    margin-bottom: 0.75rem;
  }

  .game-entry-window-modal-media {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.6rem;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.12);
    background: linear-gradient(135deg, rgba(250, 204, 21, 0.18), rgba(34, 211, 238, 0.18));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    box-shadow: 0 0 0 8px rgba(255,255,255,0.04);
  }

  .game-entry-window-modal-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .game-entry-window-modal-media-fallback {
    color: #fde68a;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 0.12em;
  }

  .game-entry-window-modal-heading {
    margin: 0;
    color: var(--entry-window-title-color, #fbbf24);
    font-size: clamp(1.2rem, 3vw, 1.58rem);
    font-weight: 800;
    letter-spacing: 0.03em;
    line-height: 1.04;
  }

  .game-entry-window-modal-copy {
    margin: 0.35rem auto 0;
    max-width: 25rem;
    color: rgba(226, 232, 240, 0.84);
    font-size: 0.86rem;
    line-height: 1.45;
  }

  .game-entry-window-modal-cards {
    display: grid;
    gap: 1rem;
    margin-bottom: 1rem;
    max-height: min(48vh, 28rem);
    overflow-y: auto;
    padding-right: 0.25rem;
  }

  .game-entry-window-info-card {
    padding: 1rem 1rem 1rem 1.1rem;
    border-radius: 1.05rem;
    background: var(--entry-card-background, #121a2f);
    border: 1px solid var(--entry-card-color, #233A73);
    box-shadow: 0 12px 28px var(--entry-card-glow, rgba(35, 58, 115, 0.18));
    color: #e2e8f0;
  }

  .game-entry-window-info-card p:last-child,
  .game-entry-window-info-card ul:last-child,
  .game-entry-window-info-card ol:last-child,
  .game-entry-window-info-card blockquote:last-child,
  .game-entry-window-info-card h2:last-child,
  .game-entry-window-info-card h3:last-child {
    margin-bottom: 0;
  }

  .game-entry-window-card-media {
    margin-bottom: 0.9rem;
    border-radius: 0.9rem;
    overflow: hidden;
    background: rgba(2, 6, 23, 0.35);
  }

  .game-entry-window-card-image,
  .game-entry-window-card-video,
  .game-entry-window-card-embed {
    width: 100%;
    display: block;
    border: 0;
  }

  .game-entry-window-card-image,
  .game-entry-window-card-video {
    max-height: 320px;
    object-fit: cover;
  }

  .game-entry-window-card-embed {
    min-height: 240px;
    aspect-ratio: 16 / 9;
    background: #020617;
  }

  .game-entry-window-card-embed-tiktok {
    min-height: 520px;
    aspect-ratio: auto;
  }

  .game-entry-window-confirmation {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    position: relative;
    z-index: 3;
    pointer-events: auto;
    padding: 0.72rem 0.82rem;
    margin-bottom: 0.8rem;
    border-radius: 0.82rem;
    background: var(--entry-window-check-background, #1e293b);
    border: 1px solid rgba(255,255,255,0.08);
    color: var(--entry-window-check-color, #e2e8f0);
    cursor: pointer;
  }

  .game-entry-window-confirmation-toggle {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    width: 100%;
    margin: 0;
    color: inherit;
    cursor: pointer;
  }

  .game-entry-window-confirmation-text {
    flex: 1 1 auto;
    margin: 0;
    font-size: 0.8rem;
    line-height: 1.38;
  }

  .game-entry-window-confirmation-input {
    appearance: none;
    -webkit-appearance: none;
    pointer-events: auto;
    width: 2.2rem;
    height: 1.15rem;
    margin: 0;
    flex: 0 0 auto;
    cursor: pointer;
    background-color: rgba(7, 18, 28, 0.85);
    border-color: rgba(255,255,255,0.24);
    border: 1px solid rgba(255,255,255,0.24);
    border-radius: 999px;
    box-shadow: none;
    position: relative;
    transition: background-color 0.2s ease, border-color 0.2s ease;
  }

  .game-entry-window-confirmation-input::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 2px;
    width: 0.82rem;
    height: 0.82rem;
    border-radius: 999px;
    background: #f8fafc;
    transform: translateY(-50%);
    transition: transform 0.2s ease;
  }

  .game-entry-window-confirmation .game-entry-window-confirmation-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.18);
  }

  .game-entry-window-confirmation .game-entry-window-confirmation-input:checked {
    background-color: var(--entry-window-button-background, #c99712);
    border-color: var(--entry-window-button-background, #c99712);
  }

  .game-entry-window-confirmation .game-entry-window-confirmation-input:checked::after {
    transform: translate(1rem, -50%);
  }

  .game-entry-window-confirmation.is-checked {
    border-color: rgba(245, 158, 11, 0.55);
    box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.18);
  }

  #game-entry-window-continue {
    position: relative;
    z-index: 3;
    background: var(--entry-window-button-disabled-background, #c99712);
    border-color: transparent;
    color: var(--entry-window-button-disabled-color, #0b0f18);
    min-height: 2.8rem;
    font-size: 0.88rem;
    transition: background 0.2s ease, color 0.2s ease, opacity 0.2s ease;
  }

  @media (max-width: 575.98px) {
    .game-entry-window-modal-content {
      padding: 0.9rem;
      border-radius: 1.5rem;
    }

    .game-entry-window-modal-media {
      width: 56px;
      height: 56px;
      margin-bottom: 0.5rem;
    }

    .game-entry-window-modal-copy {
      font-size: 0.8rem;
    }

    .game-entry-window-confirmation {
      padding: 0.66rem 0.72rem;
    }

    .game-entry-window-card-embed-tiktok {
      min-height: 460px;
    }
  }

  #game-entry-window-continue:disabled {
    opacity: 0.7;
    cursor: not-allowed;
  }

  #game-entry-window-continue:not(:disabled) {
    background: var(--entry-window-button-background, #c99712);
    color: var(--entry-window-button-color, #0b0f18);
    opacity: 1;
  }

  .app-overlay-modal .modal-dialog {
    width: min(92vw, 28rem);
    margin: 0;
  }

  .payment-modal-dialog {
    width: min(94vw, 34rem) !important;
    margin: auto;
  }

  .payment-modal-content {
    position: relative;
    padding: 1.25rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    border-radius: 1.5rem;
    background: linear-gradient(180deg, rgba(31, 41, 55, 0.98), rgba(17, 24, 39, 0.98));
    box-shadow: 0 0 28px rgba(34, 211, 238, 0.16);
  }

  .payment-expiration-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 3.4rem;
    margin-bottom: 1rem;
    border: 1px solid rgba(248, 113, 113, 0.45);
    border-radius: 1rem;
    background: rgba(127, 29, 29, 0.12);
    color: #f87171;
    font-weight: 700;
  }

  .payment-summary-card,
  .payment-method-card {
    padding: 1rem;
    border-radius: 1rem;
    background: rgba(8, 15, 24, 0.74);
    border: 1px solid rgba(34, 211, 238, 0.15);
  }

  .payment-summary-card-title {
    margin-bottom: 1rem;
  }

  .payment-summary-minimal {
    display: none;
    grid-template-columns: 78px minmax(0, 1fr) auto;
    gap: 1rem;
    align-items: start;
  }

  .payment-summary-card--minimal .payment-summary-minimal {
    display: grid;
  }

  .payment-summary-card--minimal .payment-summary-card-title,
  .payment-summary-card--minimal .payment-summary-row {
    display: none;
  }

  .payment-summary-minimal-media {
    width: 78px;
    height: 78px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 1.35rem;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(22, 78, 99, 0.48));
    border: 1px solid rgba(34, 211, 238, 0.28);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.05);
    overflow: hidden;
  }

  .payment-summary-minimal-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .payment-summary-minimal-placeholder {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    color: #67e8f9;
  }

  .payment-summary-minimal-copy {
    min-width: 0;
  }

  .payment-summary-minimal-title {
    margin: 0;
    color: #f8fafc;
    font-size: 1.08rem;
    font-weight: 700;
  }

  .payment-summary-minimal-price {
    display: flex;
    align-items: flex-start;
    justify-content: flex-end;
    min-width: max-content;
    padding-top: 0.15rem;
  }

  .payment-summary-minimal-total {
    color: #22d3ee;
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1;
    text-align: right;
    white-space: nowrap;
  }

  .payment-summary-minimal-user {
    margin-top: 0.8rem;
    color: #cbd5e1;
    font-size: 0.92rem;
  }

  .payment-summary-minimal-user strong {
    color: #f8fafc;
  }

  .game-feature-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.34rem 0.7rem;
    border-radius: 999px;
    border: 1px solid var(--theme-game-feature-border, #164E63);
    background: var(--theme-game-feature-bg, #0E1722);
    color: var(--theme-game-feature-text, #22D3EE);
    font-size: 0.78rem;
    font-weight: 600;
    line-height: 1.1;
    box-shadow: inset 0 0 0 1px rgba(var(--theme-game-feature-border-rgb, 22, 78, 99), 0.14);
  }

  .payment-summary-features {
    display: flex;
    flex-wrap: wrap;
    gap: 0.42rem;
    margin-top: 0.62rem;
  }

  .payment-summary-feature {
    display: inline-flex;
    align-items: center;
    gap: 0.38rem;
    padding: 0.34rem 0.64rem;
    border-radius: 999px;
    background: var(--theme-package-feature-bg, #0F172A);
    border: 1px solid var(--theme-package-feature-border, #164E63);
    color: var(--theme-package-feature-text, #D8FBFF);
    font-size: 0.76rem;
    font-weight: 600;
    line-height: 1.05;
  }

  .payment-summary-feature-icon {
    display: inline-flex;
    width: 0.82rem;
    height: 0.82rem;
    color: var(--theme-package-feature-text, #D8FBFF);
  }

  .payment-summary-feature-icon svg {
    width: 100%;
    height: 100%;
  }

  @media (max-width: 480px) {
    .payment-summary-minimal {
      grid-template-columns: 68px minmax(0, 1fr);
      gap: 0.85rem;
    }

    .payment-summary-minimal-media {
      width: 68px;
      height: 68px;
    }

    .payment-summary-minimal-price {
      grid-column: 2;
      justify-content: flex-start;
      padding-top: 0;
    }

    .payment-summary-minimal-total {
      text-align: left;
      font-size: 1.22rem;
    }
  }

  .payment-summary-row {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 0.5rem;
    color: #cbd5e1;
  }

  .payment-summary-row strong {
    color: #f8fafc;
    text-align: right;
  }

  .payment-summary-total {
    margin-top: 0.8rem;
    padding-top: 0.8rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
  }

  .payment-summary-total strong {
    color: #22d3ee;
    font-size: 1.2rem;
  }

  .payment-difference-banner {
    padding: 0.95rem 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(45, 212, 191, 0.32);
    background: linear-gradient(135deg, rgba(8, 47, 73, 0.38), rgba(15, 23, 42, 0.92));
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.05);
  }

  .payment-difference-banner[data-variant="warning"] {
    border-color: rgba(251, 191, 36, 0.34);
    background: linear-gradient(135deg, rgba(120, 53, 15, 0.34), rgba(15, 23, 42, 0.94));
  }

  .payment-difference-banner-title {
    color: #f8fafc;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .payment-difference-banner-copy {
    color: #cbd5e1;
    line-height: 1.5;
    font-size: 0.92rem;
  }

  .payment-difference-breakdown {
    margin-top: 0.6rem;
    display: grid;
    gap: 0.25rem;
    color: #e2e8f0;
    font-size: 0.85rem;
  }

  .payment-difference-breakdown strong {
    color: #f8fafc;
  }

  .payment-difference-actions {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }

  .payment-method-details {
    white-space: pre-line;
    line-height: 1.7;
  }

  .payment-modal-content .form-control::placeholder {
    color: rgba(148, 163, 184, 0.7) !important;
  }

  .payment-reasons-card {
    padding: 0.95rem 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(248, 113, 113, 0.35);
    background: rgba(127, 29, 29, 0.12);
  }

  .payment-reasons-title {
    color: #f8fafc;
    font-weight: 700;
    margin-bottom: 0.45rem;
  }

  .payment-reasons-summary {
    color: #e2e8f0;
    margin-bottom: 0.75rem;
    line-height: 1.55;
  }

  .payment-reasons-steps {
    margin: 0;
    padding-left: 1.15rem;
    color: #e2e8f0;
  }

  .payment-reasons-steps li + li {
    margin-top: 0.4rem;
  }

  .payment-reasons-caption {
    margin-top: 0.85rem;
    color: #fecaca;
    font-size: 0.92rem;
    font-weight: 700;
  }

  .payment-reasons-card ul {
    margin: 0.65rem 0 0;
    padding-left: 1.15rem;
    color: #fecaca;
  }

  .payment-support-actions {
    display: grid;
    gap: 0.75rem;
  }

  .payment-support-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 3rem;
    padding: 0.8rem 1rem;
    border-radius: 999px;
    border: 1px solid rgba(45, 212, 191, 0.65);
    background: linear-gradient(135deg, rgba(6, 78, 59, 0.9), rgba(16, 185, 129, 0.82));
    color: #f0fdf4;
    text-decoration: none;
    font-weight: 700;
    box-shadow: 0 0 18px rgba(16, 185, 129, 0.18);
  }

  .payment-support-link:hover {
    color: #ffffff;
    box-shadow: 0 0 22px rgba(16, 185, 129, 0.28);
  }

  #payment-modal.payment-main-modal-theme-enabled {
    background: rgba(var(--theme-payment-main-overlay-bg-rgb, 5, 10, 20), 0.78);
  }

  .payment-modal-content.payment-modal-skin-enabled {
    color: var(--theme-payment-main-text, #CBD5E1);
    background: linear-gradient(180deg, rgba(var(--theme-payment-main-modal-bg-rgb, 17, 24, 39), 0.98), rgba(var(--theme-payment-main-modal-bg-rgb, 17, 24, 39), 0.94));
    border: 1px solid rgba(var(--theme-payment-main-modal-border-rgb, 34, 211, 238), 0.56);
    box-shadow: 0 0 28px rgba(var(--theme-payment-main-modal-border-rgb, 34, 211, 238), 0.16);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-expiration-banner {
    border-color: rgba(var(--theme-payment-main-timer-border-rgb, 248, 113, 113), 0.5);
    background: rgba(var(--theme-payment-main-timer-bg-rgb, 127, 29, 29), 0.2);
    color: var(--theme-payment-main-timer-text, #F87171);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-summary-card,
  .payment-modal-content.payment-modal-skin-enabled .payment-method-card,
  .payment-modal-content.payment-modal-skin-enabled .payment-win-points-card,
  .payment-modal-content.payment-modal-skin-enabled .payment-mode-item,
  .payment-modal-content.payment-modal-skin-enabled .payment-mode-item-card,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card {
    background: rgba(var(--theme-payment-main-card-bg-rgb, 8, 15, 24), 0.82);
    border-color: rgba(var(--theme-payment-main-card-border-rgb, 22, 78, 99), 0.46);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-summary-card-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-summary-minimal-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-summary-row strong,
  .payment-modal-content.payment-modal-skin-enabled .payment-summary-minimal-user strong,
  .payment-modal-content.payment-modal-skin-enabled #payment-method-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-win-points-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-mode-item-card-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-title,
  .payment-modal-content.payment-modal-skin-enabled .form-label {
    color: var(--theme-payment-main-title, #F8FAFC) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-summary-row,
  .payment-modal-content.payment-modal-skin-enabled .payment-summary-minimal-user,
  .payment-modal-content.payment-modal-skin-enabled .payment-method-details,
  .payment-modal-content.payment-modal-skin-enabled .payment-win-points-copy,
  .payment-modal-content.payment-modal-skin-enabled .payment-mode-item-currency,
  .payment-modal-content.payment-modal-skin-enabled .payment-mode-item-details,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-summary,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-steps,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card ul,
  .payment-modal-content.payment-modal-skin-enabled .form-text {
    color: var(--theme-payment-main-text, #CBD5E1) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-summary-total {
    border-top-color: rgba(var(--theme-payment-main-card-border-rgb, 22, 78, 99), 0.3);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-summary-total strong,
  .payment-modal-content.payment-modal-skin-enabled .payment-summary-minimal-total,
  .payment-modal-content.payment-modal-skin-enabled .payment-method-currency,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-caption {
    color: var(--theme-payment-main-title, #F8FAFC) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .form-control,
  .payment-modal-content.payment-modal-skin-enabled .form-select {
    background: var(--theme-payment-main-input-bg, #111827) !important;
    border-color: var(--theme-payment-main-input-border, #22D3EE) !important;
    color: var(--theme-payment-main-input-text, #22D3EE) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .form-control::placeholder {
    color: rgba(var(--theme-payment-main-input-text-rgb, 34, 211, 238), 0.65) !important;
  }

  .payment-submit-btn-theme.payment-window-theme-enabled {
    background: var(--theme-payment-main-button-bg, #22D3EE) !important;
    border-color: rgba(var(--theme-payment-main-button-bg-rgb, 34, 211, 238), 0.88) !important;
    color: var(--theme-payment-main-button-text, #081018) !important;
  }

  .payment-cancel-btn-theme.payment-window-theme-enabled {
    background: var(--theme-payment-main-cancel-bg, #F87171) !important;
    border-color: rgba(var(--theme-payment-main-cancel-bg-rgb, 248, 113, 113), 0.88) !important;
    color: var(--theme-payment-main-cancel-text, #F8FAFC) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-support-link {
    border-color: rgba(var(--theme-payment-main-button-bg-rgb, 34, 211, 238), 0.65);
    background: linear-gradient(135deg, rgba(var(--theme-payment-main-button-bg-rgb, 34, 211, 238), 0.88), rgba(var(--theme-payment-main-button-bg-rgb, 34, 211, 238), 0.72));
    color: var(--theme-payment-main-button-text, #081018);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="processing"] {
    background: rgba(var(--theme-payment-processing-overlay-bg-rgb, 5, 10, 20), 0.78);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="sending"] {
    background: rgba(var(--theme-payment-sending-overlay-bg-rgb, 5, 10, 20), 0.78);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="processing"] .payment-loading-modal-content {
    background: linear-gradient(180deg, rgba(var(--theme-payment-processing-modal-bg-rgb, 17, 24, 39), 0.98), rgba(var(--theme-payment-processing-modal-bg-rgb, 17, 24, 39), 0.94));
    border: 1px solid rgba(var(--theme-payment-processing-modal-border-rgb, 34, 211, 238), 0.56);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="processing"] #loading-modal-spinner-circle {
    stroke: var(--theme-payment-processing-spinner, #34D399);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="processing"] .payment-loading-modal-title {
    color: var(--theme-payment-processing-title, #22D3EE) !important;
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="processing"] .payment-loading-modal-message {
    color: var(--theme-payment-processing-text, #F8FAFC) !important;
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="sending"] .payment-loading-modal-content {
    background: linear-gradient(180deg, rgba(var(--theme-payment-sending-modal-bg-rgb, 17, 24, 39), 0.98), rgba(var(--theme-payment-sending-modal-bg-rgb, 17, 24, 39), 0.94));
    border: 1px solid rgba(var(--theme-payment-sending-modal-border-rgb, 34, 211, 238), 0.56);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="sending"] #loading-modal-spinner-circle {
    stroke: var(--theme-payment-sending-spinner, #22D3EE);
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="sending"] .payment-loading-modal-title {
    color: var(--theme-payment-sending-title, #22D3EE) !important;
  }

  #loading-modal.payment-window-theme-enabled[data-payment-loading-state="sending"] .payment-loading-modal-message {
    color: var(--theme-payment-sending-text, #F8FAFC) !important;
  }

  #payment-status-modal.payment-window-theme-enabled {
    background: rgba(var(--theme-payment-status-overlay-bg-rgb, 5, 10, 20), 0.78);
  }

  #payment-status-modal.payment-window-theme-enabled .payment-status-modal-content {
    background: linear-gradient(180deg, rgba(var(--theme-payment-status-modal-bg-rgb, 17, 24, 39), 0.98), rgba(var(--theme-payment-status-modal-bg-rgb, 17, 24, 39), 0.94));
    border: 1px solid rgba(var(--theme-payment-status-modal-border-rgb, 34, 211, 238), 0.56);
  }

  #payment-status-modal.payment-window-theme-enabled .payment-status-modal-message {
    color: var(--theme-payment-status-text, #F8FAFC) !important;
  }

  #payment-status-modal.payment-window-theme-enabled[data-payment-status-state="info"] .payment-status-modal-title {
    color: var(--theme-payment-status-title-info, #22D3EE) !important;
  }

  #payment-status-modal.payment-window-theme-enabled[data-payment-status-state="success"] .payment-status-modal-title {
    color: var(--theme-payment-status-title-success, #34D399) !important;
  }

  #payment-status-modal.payment-window-theme-enabled[data-payment-status-state="danger"] .payment-status-modal-title {
    color: var(--theme-payment-status-title-danger, #F87171) !important;
  }

  .payment-status-modal-accept-btn.payment-window-theme-enabled {
    background: var(--theme-payment-status-button-bg, #22D3EE) !important;
    border-color: rgba(var(--theme-payment-status-button-bg-rgb, 34, 211, 238), 0.88) !important;
    color: var(--theme-payment-status-button-text, #081018) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"],
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] {
    background: linear-gradient(180deg, rgba(var(--theme-payment-difference-underpaid-card-bg-rgb, 120, 53, 15), 0.34), rgba(var(--theme-payment-main-card-bg-rgb, 8, 15, 24), 0.92));
    border-color: rgba(var(--theme-payment-difference-underpaid-card-bg-rgb, 120, 53, 15), 0.78);
    box-shadow: inset 0 0 0 1px rgba(var(--theme-payment-difference-underpaid-card-bg-rgb, 120, 53, 15), 0.12);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-summary,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-steps,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-caption,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] ul,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-title,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-summary,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-steps,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] .payment-reasons-caption,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="underpaid"] ul {
    color: var(--theme-payment-difference-underpaid-text, #FDE68A) !important;
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"],
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] {
    background: linear-gradient(180deg, rgba(var(--theme-payment-difference-overpaid-card-bg-rgb, 6, 78, 59), 0.34), rgba(var(--theme-payment-main-card-bg-rgb, 8, 15, 24), 0.92));
    border-color: rgba(var(--theme-payment-difference-overpaid-card-bg-rgb, 6, 78, 59), 0.78);
    box-shadow: inset 0 0 0 1px rgba(var(--theme-payment-difference-overpaid-card-bg-rgb, 6, 78, 59), 0.12);
  }

  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-title,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-summary,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-steps,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-caption,
  .payment-modal-content.payment-modal-skin-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] ul,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-title,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-summary,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-steps,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] .payment-reasons-caption,
  #payment-status-modal.payment-window-theme-enabled .payment-reasons-card[data-payment-difference-variant="overpaid"] ul {
    color: var(--theme-payment-difference-overpaid-text, #D1FAE5) !important;
  }

  .payment-difference-action-btn {
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease;
  }

  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="underpaid"] .payment-difference-action-btn {
    background: var(--theme-payment-difference-underpaid-button-bg, #F59E0B) !important;
    border-color: rgba(var(--theme-payment-difference-underpaid-button-bg-rgb, 245, 158, 11), 0.88) !important;
    color: var(--theme-payment-difference-underpaid-button-text, #111827) !important;
    box-shadow: 0 0 18px rgba(var(--theme-payment-difference-underpaid-button-bg-rgb, 245, 158, 11), 0.22);
  }

  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="underpaid"] .payment-difference-action-btn:hover,
  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="underpaid"] .payment-difference-action-btn:focus {
    background: var(--theme-payment-difference-underpaid-button-hover-bg, #FBBF24) !important;
    border-color: rgba(var(--theme-payment-difference-underpaid-button-hover-bg-rgb, 251, 191, 36), 0.92) !important;
    color: var(--theme-payment-difference-underpaid-button-hover-text, #111827) !important;
  }

  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="overpaid"] .payment-difference-action-btn {
    background: var(--theme-payment-difference-overpaid-button-bg, #10B981) !important;
    border-color: rgba(var(--theme-payment-difference-overpaid-button-bg-rgb, 16, 185, 129), 0.88) !important;
    color: var(--theme-payment-difference-overpaid-button-text, #052E16) !important;
    box-shadow: 0 0 18px rgba(var(--theme-payment-difference-overpaid-button-bg-rgb, 16, 185, 129), 0.22);
  }

  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="overpaid"] .payment-difference-action-btn:hover,
  .payment-window-theme-enabled .payment-difference-actions[data-payment-difference-variant="overpaid"] .payment-difference-action-btn:focus {
    background: var(--theme-payment-difference-overpaid-button-hover-bg, #34D399) !important;
    border-color: rgba(var(--theme-payment-difference-overpaid-button-hover-bg-rgb, 52, 211, 153), 0.92) !important;
    color: var(--theme-payment-difference-overpaid-button-hover-text, #022C22) !important;
  }

  .payment-confirm-overlay {
    z-index: 1115;
    background: rgba(5, 10, 20, 0.38);
    backdrop-filter: blur(2px);
  }

  .win-points-guest-hint {
    margin-top: 0.85rem;
    padding: 0.9rem 1rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(250, 204, 21, 0.42);
    background: linear-gradient(135deg, rgba(120, 53, 15, 0.34), rgba(146, 64, 14, 0.18));
    color: #fde68a;
    font-weight: 700;
    font-size: 0.94rem;
    text-align: center;
    box-shadow: 0 0 18px rgba(251, 191, 36, 0.12);
  }

  .payment-win-points-card {
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(45, 212, 191, 0.25);
    background: linear-gradient(180deg, rgba(9, 24, 34, 0.95), rgba(8, 18, 28, 0.92));
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.06);
  }

  .payment-win-points-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.9rem;
    margin-bottom: 0.9rem;
  }

  .payment-win-points-title {
    color: #f8fafc;
    font-weight: 700;
    margin-bottom: 0.2rem;
  }

  .payment-win-points-copy {
    color: #cbd5e1;
    font-size: 0.9rem;
    line-height: 1.45;
  }

  .payment-win-points-balance {
    padding: 0.55rem 0.8rem;
    border-radius: 999px;
    border: 1px solid rgba(34, 197, 94, 0.42);
    background: rgba(6, 78, 59, 0.32);
    color: #86efac;
    font-weight: 700;
    white-space: nowrap;
  }

  .payment-win-points-actions {
    display: grid;
    gap: 0.65rem;
    grid-template-columns: 1fr;
  }

  .payment-mode-item {
    border-radius: 1rem;
    border: 1px solid rgba(56, 189, 248, 0.18);
    background: rgba(15, 23, 42, 0.48);
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.04);
    overflow: hidden;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
  }

  .payment-mode-item.is-selected {
    border-color: rgba(34, 211, 238, 0.68);
    background: linear-gradient(180deg, rgba(8, 47, 73, 0.54), rgba(15, 23, 42, 0.9));
    box-shadow: 0 0 20px rgba(34, 211, 238, 0.12);
  }

  .payment-mode-btn {
    width: 100%;
    min-height: 3.65rem;
    padding: 0.95rem 1rem;
    border: 0;
    background: transparent;
    color: #cbd5e1;
    font-weight: 700;
    text-align: left;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.9rem;
    transition: color 0.2s ease;
  }

  .payment-mode-item.is-selected .payment-mode-btn {
    color: #ecfeff;
  }

  .payment-mode-btn-main {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    min-width: 0;
    flex: 1 1 auto;
  }

  .payment-mode-btn-radio {
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 999px;
    border: 2px solid rgba(148, 163, 184, 0.65);
    background: rgba(15, 23, 42, 0.85);
    flex: 0 0 auto;
    position: relative;
    transition: border-color 0.2s ease, background 0.2s ease;
  }

  .payment-mode-btn-radio::after {
    content: '';
    position: absolute;
    inset: 0.18rem;
    border-radius: 999px;
    background: #22d3ee;
    transform: scale(0);
    transition: transform 0.18s ease;
  }

  .payment-mode-item.is-selected .payment-mode-btn-radio {
    border-color: rgba(34, 211, 238, 0.92);
  }

  .payment-mode-item.is-selected .payment-mode-btn-radio::after {
    transform: scale(1);
  }

  .payment-mode-btn-text {
    display: grid;
    gap: 0.14rem;
    min-width: 0;
  }

  .payment-mode-btn-title {
    font-size: 1rem;
    line-height: 1.2;
  }

  .payment-mode-btn-meta {
    color: #93c5fd;
    font-size: 0.84rem;
    font-weight: 500;
    line-height: 1.3;
  }

  .payment-mode-btn-caret {
    width: 0.72rem;
    height: 0.72rem;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    transition: transform 0.22s ease;
    opacity: 0.82;
    flex: 0 0 auto;
    margin-right: 0.15rem;
  }

  .payment-mode-item.is-expanded .payment-mode-btn-caret {
    transform: rotate(-135deg);
  }

  .payment-mode-item-body {
    display: grid;
    grid-template-rows: 0fr;
    transition: grid-template-rows 0.24s ease;
  }

  .payment-mode-item.is-expanded .payment-mode-item-body {
    grid-template-rows: 1fr;
  }

  .payment-mode-item-body-inner {
    overflow: hidden;
    padding: 0 1rem;
    opacity: 0;
    transform: translateY(-6px);
    transition: padding 0.24s ease, opacity 0.2s ease, transform 0.2s ease;
  }

  .payment-mode-item.is-expanded .payment-mode-item-body-inner {
    padding: 0 1rem 1rem;
    opacity: 1;
    transform: translateY(0);
  }

  .payment-mode-item-card {
    padding: 0.95rem 1rem;
    border-radius: 0.95rem;
    border: 1px solid rgba(56, 189, 248, 0.18);
    background: rgba(8, 20, 36, 0.88);
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.05);
  }

  .payment-mode-item-card.payment-mode-item-card-points {
    border-color: rgba(34, 197, 94, 0.2);
    background: linear-gradient(180deg, rgba(6, 36, 27, 0.92), rgba(8, 20, 36, 0.92));
  }

  .payment-mode-item-card-title {
    color: #f8fafc;
    font-weight: 700;
    margin-bottom: 0.4rem;
  }

  .payment-mode-item-currency {
    color: #22d3ee;
    font-size: 0.92rem;
    font-weight: 600;
    margin-bottom: 0.6rem;
  }

  .payment-mode-item-details {
    color: #e2e8f0;
    font-size: 0.92rem;
    line-height: 1.55;
  }

  .payment-mode-btn:disabled {
    cursor: not-allowed;
    opacity: 0.58;
  }

  .payment-win-points-message {
    margin-top: 0.85rem;
    color: #93c5fd;
    font-size: 0.92rem;
    line-height: 1.45;
  }

  .payment-mode-panels {
    display: grid;
    gap: 0.9rem;
  }

  .payment-mode-panel {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(12px);
    transition: grid-template-rows 0.28s ease, opacity 0.24s ease, transform 0.24s ease;
    pointer-events: none;
  }

  .payment-mode-panel.is-active {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
  }

  .payment-mode-panel-inner {
    overflow: hidden;
  }

  .payment-points-card {
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(56, 189, 248, 0.22);
    background: linear-gradient(180deg, rgba(8, 20, 36, 0.94), rgba(11, 30, 48, 0.9));
    box-shadow: inset 0 0 0 1px rgba(34, 211, 238, 0.05);
  }

  .pack-win-points-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    width: fit-content;
    max-width: 100%;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    border: 1px solid <?= htmlspecialchars($winPointsBadgeBorderColor, ENT_QUOTES, 'UTF-8') ?>;
    background: <?= htmlspecialchars($winPointsBadgeBackgroundColor, ENT_QUOTES, 'UTF-8') ?>;
    color: <?= htmlspecialchars($winPointsBadgeTextColor, ENT_QUOTES, 'UTF-8') ?>;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.15;
    box-shadow: inset 0 0 0 1px <?= htmlspecialchars($winPointsBadgeInsetColor, ENT_QUOTES, 'UTF-8') ?>;
  }

  .pack-win-points-icon {
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    object-fit: cover;
    flex: 0 0 auto;
  }

  .win-points-live-notification {
    position: fixed;
    width: min(360px, calc(100vw - 1.5rem));
    display: grid;
    grid-template-columns: auto auto 1fr;
    align-items: center;
    gap: 0.85rem;
    padding: 0.8rem 0.95rem;
    border-radius: 18px;
    border: 1px solid rgba(var(--theme-live-notification-border-rgb), 0.72);
    background: linear-gradient(135deg, rgba(var(--theme-live-notification-bg-rgb), 0.98), rgba(var(--theme-live-notification-border-rgb), 0.16));
    box-shadow: 0 18px 45px rgba(2, 6, 23, 0.42), 0 0 18px rgba(var(--theme-live-notification-border-rgb), 0.16);
    backdrop-filter: blur(14px);
    opacity: 0;
    transition: opacity 0.28s ease, transform 0.28s ease;
    z-index: 9999;
    pointer-events: none;
  }

  .win-points-live-notification.is-visible {
    opacity: 1;
  }

  .win-points-live-notification[data-position="bottom-left"] {
    left: 24px;
    bottom: 24px;
    transform: translate3d(0, 18px, 0);
  }

  .win-points-live-notification[data-position="bottom-center"] {
    left: 50%;
    bottom: 24px;
    transform: translate3d(-50%, 18px, 0);
  }

  .win-points-live-notification[data-position="bottom-right"] {
    right: 24px;
    bottom: 24px;
    transform: translate3d(0, 18px, 0);
  }

  .win-points-live-notification[data-position="top-left"] {
    left: 24px;
    top: 24px;
    transform: translate3d(0, -18px, 0);
  }

  .win-points-live-notification[data-position="top-center"] {
    left: 50%;
    top: 24px;
    transform: translate3d(-50%, -18px, 0);
  }

  .win-points-live-notification[data-position="top-right"] {
    right: 24px;
    top: 24px;
    transform: translate3d(0, -18px, 0);
  }

  .win-points-live-notification[data-position="middle-right"] {
    right: 24px;
    top: 50%;
    transform: translate3d(18px, -50%, 0);
  }

  .win-points-live-notification[data-position="middle-left"] {
    left: 24px;
    top: 50%;
    transform: translate3d(-18px, -50%, 0);
  }

  .win-points-live-notification.is-visible[data-position="bottom-left"],
  .win-points-live-notification.is-visible[data-position="bottom-right"],
  .win-points-live-notification.is-visible[data-position="top-left"],
  .win-points-live-notification.is-visible[data-position="top-right"] {
    transform: translate3d(0, 0, 0);
  }

  .win-points-live-notification.is-visible[data-position="bottom-center"],
  .win-points-live-notification.is-visible[data-position="top-center"] {
    transform: translate3d(-50%, 0, 0);
  }

  .win-points-live-notification.is-visible[data-position="middle-right"] {
    transform: translate3d(0, -50%, 0);
  }

  .win-points-live-notification.is-visible[data-position="middle-left"] {
    transform: translate3d(0, -50%, 0);
  }

  .win-points-live-notification__pulse {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    background: var(--theme-live-notification-accent);
    box-shadow: 0 0 0 0 rgba(var(--theme-live-notification-accent-rgb), 0.56);
    animation: win-points-live-pulse 1.9s ease-out infinite;
  }

  .win-points-live-notification__logo-wrap {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(var(--theme-live-notification-border-rgb), 0.34);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }

  .win-points-live-notification__logo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }

  .win-points-live-notification__logo-fallback {
    color: var(--theme-live-notification-text);
    font-weight: 800;
    letter-spacing: 0.05em;
    font-size: 0.82rem;
  }

  .win-points-live-notification__body {
    min-width: 0;
  }

  .win-points-live-notification__title {
    color: var(--theme-live-notification-text);
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 0.12rem;
    letter-spacing: 0.01em;
  }

  .win-points-live-notification__detail {
    color: var(--theme-live-notification-muted);
    font-size: 0.78rem;
    line-height: 1.35;
  }

  @keyframes win-points-live-pulse {
    0% {
      box-shadow: 0 0 0 0 rgba(var(--theme-live-notification-accent-rgb), 0.56);
    }
    70% {
      box-shadow: 0 0 0 12px rgba(var(--theme-live-notification-accent-rgb), 0);
    }
    100% {
      box-shadow: 0 0 0 0 rgba(var(--theme-live-notification-accent-rgb), 0);
    }
  }

  @media (max-width: 575.98px) {
    .app-overlay-modal {
      align-items: flex-start;
      padding: 0.55rem 0.55rem calc(1rem + env(safe-area-inset-bottom));
    }

    .app-overlay-modal .modal-dialog,
    .payment-modal-dialog {
      width: min(100%, 34rem) !important;
      margin: 0 auto;
    }

    .payment-modal-dialog {
      display: flex;
      align-items: flex-start;
      min-height: calc(100dvh - 1.1rem);
    }

    .payment-modal-content {
      padding: 1rem;
      width: 100%;
      max-height: none;
      overflow: visible;
      overscroll-behavior: auto;
      padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
      border-radius: 1.1rem;
    }

    .payment-expiration-banner {
      font-size: 0.92rem;
    }

    .payment-win-points-header,
    .payment-win-points-actions {
      grid-template-columns: 1fr;
      display: grid;
    }

    .payment-mode-panel {
      transform: translateY(8px);
    }

    .payment-win-points-balance {
      white-space: normal;
    }

    .win-points-live-notification {
      width: min(312px, calc(100vw - 72px));
      max-width: calc(100vw - 24px);
      gap: 0.62rem;
      padding: 0.64rem 0.75rem;
      border-radius: 16px;
    }

    .win-points-live-notification[data-position="bottom-left"],
    .win-points-live-notification[data-position="top-left"],
    .win-points-live-notification[data-position="middle-left"] {
      left: 12px;
      right: auto;
    }

    .win-points-live-notification[data-position="bottom-right"],
    .win-points-live-notification[data-position="top-right"],
    .win-points-live-notification[data-position="middle-right"] {
      left: auto;
      right: 12px;
    }

    .win-points-live-notification[data-position="bottom-center"] {
      left: 50%;
      right: auto;
      bottom: calc(0.75rem + env(safe-area-inset-bottom));
      transform: translate3d(-50%, 18px, 0);
    }

    .win-points-live-notification[data-position="bottom-left"],
    .win-points-live-notification[data-position="bottom-right"] {
      bottom: calc(0.75rem + env(safe-area-inset-bottom));
      transform: translate3d(0, 18px, 0);
    }

    .win-points-live-notification.is-visible[data-position="bottom-left"],
    .win-points-live-notification.is-visible[data-position="bottom-right"] {
      transform: translate3d(0, 0, 0);
    }

    .win-points-live-notification.is-visible[data-position="bottom-center"] {
      transform: translate3d(-50%, 0, 0);
    }

    .win-points-live-notification[data-position="top-center"] {
      left: 50%;
      right: auto;
      top: calc(0.75rem + env(safe-area-inset-top));
      transform: translate3d(-50%, -18px, 0);
    }

    .win-points-live-notification[data-position="top-left"],
    .win-points-live-notification[data-position="top-right"] {
      top: calc(0.75rem + env(safe-area-inset-top));
      transform: translate3d(0, -18px, 0);
    }

    .win-points-live-notification.is-visible[data-position="top-left"],
    .win-points-live-notification.is-visible[data-position="top-right"] {
      transform: translate3d(0, 0, 0);
    }

    .win-points-live-notification.is-visible[data-position="top-center"] {
      transform: translate3d(-50%, 0, 0);
    }

    .win-points-live-notification[data-position="middle-left"],
    .win-points-live-notification[data-position="middle-right"] {
      top: 50%;
    }

    .win-points-live-notification[data-position="middle-left"] {
      transform: translate3d(-18px, -50%, 0);
    }

    .win-points-live-notification[data-position="middle-right"] {
      transform: translate3d(18px, -50%, 0);
    }

    .win-points-live-notification.is-visible[data-position="middle-left"],
    .win-points-live-notification.is-visible[data-position="middle-right"] {
      transform: translate3d(0, -50%, 0);
    }

    .win-points-live-notification__pulse {
      width: 8px;
      height: 8px;
    }

    .win-points-live-notification__logo-wrap {
      width: 36px;
      height: 36px;
      border-radius: 12px;
    }

    .win-points-live-notification__logo-fallback {
      font-size: 0.72rem;
    }

    .win-points-live-notification__title {
      font-size: 0.82rem;
      margin-bottom: 0.06rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .win-points-live-notification__detail {
      font-size: 0.72rem;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  }

  body.overlay-open {
    overflow: hidden;
  }

  .purchase-summary-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(15.5rem, 17rem);
    gap: 1rem;
    align-items: start;
  }

  .purchase-summary-pack-copy {
    min-width: 0;
  }

  .purchase-summary-head-single {
    grid-template-columns: minmax(0, 1fr);
  }

  .purchase-quantity-panel {
    justify-self: end;
    width: 100%;
    max-width: 17rem;
    padding: 0.9rem;
    border-radius: 1rem;
    border: 1px solid rgba(var(--theme-button-primary-rgb), 0.28);
    background: linear-gradient(180deg, rgba(var(--theme-button-surface-rgb), 0.82), rgba(var(--theme-bg-main-rgb), 0.94));
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
  }

  .purchase-quantity-label {
    display: block;
    margin-bottom: 0.55rem;
    color: var(--theme-button-primary);
    font-size: 0.84rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .purchase-quantity-stepper {
    display: grid;
    grid-template-columns: 3.25rem minmax(0, 1fr) 3.25rem;
    gap: 0.55rem;
    align-items: center;
  }

  .purchase-quantity-btn,
  .purchase-quantity-input {
    border-radius: 0.9rem;
    border: 1px solid rgba(var(--theme-button-primary-rgb), 0.65);
  }

  .purchase-quantity-btn {
    min-height: 4rem;
    padding: 0;
    background: linear-gradient(180deg, rgba(var(--theme-button-primary-rgb), 0.22), rgba(var(--theme-button-primary-rgb), 0.1));
    color: var(--theme-button-primary);
    font-size: 1.9rem;
    font-weight: 800;
    line-height: 1;
    box-shadow: 0 0.75rem 1.75rem rgba(0, 0, 0, 0.2);
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease, opacity 0.18s ease;
  }

  .purchase-quantity-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 1rem 2rem rgba(var(--theme-button-primary-rgb), 0.18);
    border-color: rgba(var(--theme-button-primary-rgb), 0.95);
  }

  .purchase-quantity-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    box-shadow: none;
  }

  .purchase-quantity-input {
    min-height: 4rem;
    width: 100%;
    padding: 0.55rem 0.75rem;
    background: rgba(var(--theme-bg-main-rgb), 0.88);
    color: var(--theme-price-text);
    font-size: 1.65rem;
    font-weight: 800;
    text-align: center;
    letter-spacing: 0.04em;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.02);
    appearance: textfield;
  }

  .purchase-quantity-input:focus {
    outline: none;
    border-color: rgba(var(--theme-button-primary-rgb), 0.98);
    box-shadow: 0 0 0 0.2rem rgba(var(--theme-button-primary-rgb), 0.14);
  }

  .purchase-quantity-input::-webkit-outer-spin-button,
  .purchase-quantity-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  .purchase-quantity-help {
    margin-top: 0.55rem;
    color: var(--theme-text-muted);
    font-size: 0.82rem;
    line-height: 1.35;
  }

  .pack-card {
    min-height: 15rem;
    position: relative;
    padding: 0;
    border-radius: 1.1rem;
    border: 0 !important;
    overflow: hidden;
    appearance: none;
    background:
      radial-gradient(circle at top, rgba(var(--theme-button-primary-rgb), 0.18), transparent 45%),
      linear-gradient(180deg, rgba(var(--theme-button-surface-rgb), 0.98), rgba(var(--theme-bg-main-rgb), 0.98));
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }

  .pack-card::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow: inset 0 0 0 1px rgba(var(--theme-button-primary-rgb), 0.95);
    pointer-events: none;
  }

  .pack-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1rem 2rem rgba(var(--theme-button-primary-rgb), 0.2);
  }

  .pack-card .card-body {
    min-height: 100%;
    display: flex;
    flex-direction: column;
  }

  .pack-card-media {
    width: 100%;
    margin: 0;
    min-height: 8.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    border-radius: calc(1.1rem - 1px) calc(1.1rem - 1px) 0 0;
    background: linear-gradient(180deg, rgba(var(--theme-bg-main-rgb), 0.45), rgba(var(--theme-bg-main-rgb), 0.05));
    flex-shrink: 0;
  }

  .pack-card-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transform: scale(1.02);
  }

  .pack-card-glow {
    position: absolute;
    inset: auto 0 0 0;
    height: 55%;
    background: linear-gradient(180deg, rgba(3, 7, 18, 0) 0%, rgba(3, 7, 18, 0.8) 78%, rgba(3, 7, 18, 0.98) 100%);
  }

  .pack-card-placeholder {
    color: var(--theme-button-primary);
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.18em;
  }

  .pack-card-content {
    display: grid;
    gap: 0.75rem;
    padding: 0.9rem 0.95rem 1rem;
    margin-top: auto;
  }

  .pack-card-name {
    color: var(--theme-text);
    min-height: 2.4rem;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    text-align: left;
    line-height: 1.15;
    width: 100%;
    font-size: 0.98rem;
    letter-spacing: 0.01em;
    text-shadow: 0 0 10px rgba(var(--theme-button-primary-rgb), 0.18);
  }

  .pack-card-footer {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 0.65rem;
    border-top: 1px solid rgba(var(--theme-button-primary-rgb), 0.18);
    padding-top: 0.65rem;
  }

  .moneda-label {
    color: var(--theme-price-muted);
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    opacity: 0.92;
  }

  .precio-label {
    color: var(--theme-price-text);
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1;
    text-shadow: 0 0 12px rgba(var(--theme-price-text-rgb), 0.16);
  }

  .neon-selected {
    box-shadow: 0 0 16px 4px rgba(var(--theme-button-primary-rgb), 0.95), 0 0 32px 8px rgba(var(--theme-button-secondary-rgb), 0.75);
    background: var(--theme-surface-alt) !important;
    transition: box-shadow 0.2s, border-color 0.2s;
    z-index: 2;
  }

  .neon-selected::after {
    box-shadow: inset 0 0 0 2px var(--theme-button-primary);
  }

  .neon-selected .pack-card-footer {
    border-top-color: rgba(var(--theme-button-secondary-rgb), 0.48);
  }

  @media (max-width: 575.98px) {
    .purchase-summary-head {
      grid-template-columns: 1fr;
    }

    .purchase-quantity-panel {
      justify-self: stretch;
      max-width: none;
      padding: 0.8rem;
    }

    .purchase-quantity-stepper {
      grid-template-columns: 3rem minmax(0, 1fr) 3rem;
      gap: 0.45rem;
    }

    .purchase-quantity-btn,
    .purchase-quantity-input {
      min-height: 3.5rem;
    }

    .purchase-quantity-input {
      font-size: 1.45rem;
    }

    .pack-card {
      min-height: 13.75rem;
    }

    .pack-card-media {
      min-height: 7.3rem;
    }

    .pack-card-content {
      padding: 0.8rem 0.8rem 0.9rem;
      gap: 0.55rem;
    }

    .pack-card-name {
      font-size: 0.9rem;
      min-height: 2.1rem;
    }

    .precio-label {
      font-size: 1rem;
    }
  }

  .pack-account-sale-badge {
    display: inline-flex;
    align-items: center;
    width: fit-content;
    padding: 0.3rem 0.7rem;
    border-radius: 999px;
    border: 1px solid rgba(34, 211, 238, 0.45);
    background: rgba(8, 15, 24, 0.95);
    color: #67e8f9;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .selected-pack-account-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.9rem;
  }

  .selected-pack-account-btn {
    min-width: 138px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .account-sale-note {
    border: 1px solid rgba(34, 211, 238, 0.35);
    background: linear-gradient(135deg, rgba(8, 15, 24, 0.96), rgba(17, 24, 39, 0.98));
    color: #c7f9ff;
  }

  .account-gallery-modal-content {
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
  }

  .account-gallery-modal-header,
  .account-gallery-modal-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.1rem 1.35rem;
    background: linear-gradient(135deg, rgba(8, 15, 24, 0.96), rgba(17, 24, 39, 0.98));
  }

  .account-gallery-modal-eyebrow {
    color: #67e8f9;
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
  }

  .account-gallery-modal-body {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 1.35rem;
    background: #0b1220;
  }

  .account-gallery-modal-details {
    display: grid;
    gap: 0.55rem;
  }

  .account-gallery-main-frame {
    min-height: 420px;
    position: relative;
    border-radius: 22px;
    border: 1px solid rgba(34, 211, 238, 0.2);
    background: radial-gradient(circle at top, rgba(14, 165, 233, 0.18), rgba(2, 6, 23, 0.98));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .account-gallery-main-image {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 420px;
    object-fit: cover;
  }

  .account-gallery-main-placeholder {
    color: #94a3b8;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
  }

  .account-gallery-modal-price {
    color: #22d3ee;
    font-size: 1.25rem;
    font-weight: 800;
  }

  .account-gallery-modal-copy,
  .account-gallery-modal-caption {
    color: #cbd5e1;
    line-height: 1.65;
  }

  .account-gallery-modal-caption {
    position: absolute;
    left: 1rem;
    bottom: 1rem;
    z-index: 2;
    max-width: min(78%, 26rem);
    padding: 0.65rem 0.85rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(8, 15, 24, 0.88), rgba(15, 23, 42, 0.78));
    box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(10px);
    color: #f8fafc;
    font-weight: 600;
    line-height: 1.45;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.65);
  }

  .account-gallery-modal-caption:empty {
    display: none;
  }

  .account-gallery-thumbs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.7rem;
    justify-content: flex-start;
  }

  .account-gallery-thumb {
    width: 88px;
    flex: 0 0 88px;
    border: 1px solid rgba(34, 211, 238, 0.22);
    background: #081018;
    border-radius: 16px;
    padding: 0;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
  }

  .account-gallery-thumb.is-active {
    border-color: rgba(34, 211, 238, 0.9);
    box-shadow: 0 0 0 2px rgba(34, 211, 238, 0.12);
    transform: translateY(-2px);
  }

  .account-gallery-thumb img {
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
  }

  .account-sale-delivery-card {
    display: grid;
    gap: 1rem;
  }

  .account-sale-delivery-copy {
    padding: 1rem 1.05rem;
    border-radius: 16px;
    border: 1px solid rgba(52, 211, 153, 0.25);
    background: rgba(8, 15, 24, 0.9);
    color: #e2e8f0;
    white-space: pre-wrap;
    line-height: 1.65;
  }

  .account-sale-delivery-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 0.75rem;
  }

  .account-sale-delivery-gallery-item {
    display: grid;
    gap: 0.4rem;
  }

  .account-sale-delivery-gallery-item img {
    width: 100%;
    border-radius: 14px;
    border: 1px solid rgba(34, 211, 238, 0.18);
    aspect-ratio: 1 / 1;
    object-fit: cover;
  }

  .account-sale-delivery-gallery-item span {
    color: #cbd5e1;
    font-size: 0.8rem;
    line-height: 1.45;
  }

  .account-sale-copy-btn {
    justify-self: start;
  }

  @media (max-width: 767.98px) {
    .account-gallery-main-frame,
    .account-gallery-main-image {
      min-height: 260px;
    }

    .selected-pack-account-btn {
      flex: 1 1 100%;
    }
  }
</style>
<script>
  // Todas las variables y lógica JS en un solo bloque
  const appBasePath = <?= json_encode($scriptDir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const rememberLastPurchaseIdentifierEnabled = <?= $rememberLastPurchaseIdentifierEnabled ? 'true' : 'false' ?>;
  const defaultOrderEmail = <?= json_encode($loggedUserEmail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  let defaultOrderUserIdentifier = <?= json_encode($loggedUserLastPurchaseIdentifier, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  let defaultPaymentPhone = <?= json_encode($loggedUserLastPurchasePhone, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const paymentMethodsByCurrency = <?= json_encode($paymentMethodsByCurrency, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const binancePayCheckoutEnabled = <?= $binancePayCheckoutEnabled ? 'true' : 'false' ?>;
  const accountSaleFeatureEnabled = <?= $accountSaleFeatureEnabled ? 'true' : 'false' ?>;
  const binancePayButtonLabel = 'Binance Pay';
  const paymentSupportWhatsappBase = <?= json_encode($paymentSupportWhatsappBase, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const winPointsState = <?= json_encode([
    'enabled' => $winPointsEnabled,
    'loggedIn' => $loggedUserId > 0,
    'name' => $winPointsProgramName,
    'iconUrl' => $winPointsIconUrl,
    'notificationLogoUrl' => $winPointsNotificationLogoUrl,
    'notificationPosition' => $winPointsNotificationPosition,
    'guestMessage' => $winPointsGuestMessage,
    'balance' => (int) ($winPointsUserSummary['balance'] ?? 0),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const gameUsesCatalogApi = <?= $usesCatalogApi ? 'true' : 'false' ?>;
  const paymentHeaderMinimalEnabled = <?= $paymentHeaderMinimalEnabled ? 'true' : 'false' ?>;
  const packageFeatureIconSvgMap = <?= json_encode(package_feature_icon_svg_map(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const packCards2 = Array.from(document.querySelectorAll('.pack-card'));
  const packAccountPreviewButtons = Array.from(document.querySelectorAll('.pack-account-preview-btn'));
  const selectedPack = document.getElementById("selected-pack");
  const selectedPackAccountActions = document.getElementById('selected-pack-account-actions');
  const selectedPackPreviewButton = document.getElementById('selected-pack-preview-btn');
  const selectedPackBuyButton = document.getElementById('selected-pack-buy-btn');
  const orderQuantityDecreaseButton = document.getElementById('order-quantity-decrease');
  const orderQuantityIncreaseButton = document.getElementById('order-quantity-increase');
  const orderQuantityInput = document.getElementById('order-quantity');
  const orderQuantityHelp = document.getElementById('order-quantity-help');
  const selectedPrice = document.getElementById("selected-price");
  const selectedPriceDetail = document.getElementById('selected-price-detail');
  const selectedWinPointsTotal = document.getElementById('selected-win-points-total');
  const paymentDifferenceBanner = document.getElementById('payment-difference-banner');
  const orderForm = document.getElementById("order-form");
  const orderEmailInput = orderForm ? orderForm.querySelector('input[name="email"]') : null;
  const buyButton = document.getElementById("buy-button");
  const accountSaleNote = document.getElementById('account-sale-note');
  const defaultBuyButtonLabel = 'Comprar Ahora';
  const paymentDifferenceBlockedBuyButtonLabel = 'Selecciona un paquete mayor al saldo a favor';
  const defaultPaymentSubmitButtonLabel = 'Pagado / Recargar';
  const completeRechargeButtonLabel = 'Completar Recarga';
  const verifyUserBuyButtonLabel = 'Debe Verificar El usuario para poder comprar';
  const playerPrimaryField = document.getElementById('player-primary-field');
  const playerPrimaryLabel = document.getElementById('player-primary-label');
  let playerPrimaryInput = document.getElementById('order-user-id');
  const extraPlayerFields = document.getElementById('extra-player-fields');
  const verifyPlayerButton = document.getElementById('verify-player-button');
  const playerVerificationFeedback = document.getElementById('player-verification-feedback');
  const playerVerificationConfig = <?= json_encode($playerVerificationConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const couponInput = document.getElementById('coupon-input');
  const couponModal = document.getElementById('coupon-modal');
  const loadingModal = document.getElementById('loading-modal');
  const loadingModalTitle = document.getElementById('loading-modal-title');
  const loadingModalMessage = document.getElementById('loading-modal-message');
  const paymentWindowThemeEnabled = <?php echo $paymentWindowConfigEnabled ? 'true' : 'false'; ?>;
  const paymentSendingOrderContent = {
    title: <?php echo json_encode($paymentSendingOrderTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    message: <?php echo json_encode($paymentSendingOrderMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
  };
  const paymentDifferenceFeatureEnabled = <?= $paymentDifferenceEnabled ? 'true' : 'false' ?>;
  const gameEntryWindowEnabled = <?= !empty($gameEntryWindowPayload['enabled']) ? 'true' : 'false' ?>;
  const paymentSuccessContent = {
    title: <?php echo json_encode($paymentSuccessTitle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
    extraMessage: <?php echo json_encode($paymentSuccessExtraMessage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
  };
  let paymentDifferenceCreditState = <?= json_encode($activePaymentDifferenceCredit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  const paymentStatusModal = document.getElementById('payment-status-modal');
  const paymentStatusModalTitle = document.getElementById('payment-status-modal-title');
  const paymentStatusModalMessage = document.getElementById('payment-status-modal-message');
  const paymentStatusModalExtraMessage = document.getElementById('payment-status-modal-extra-message');
  const paymentStatusModalReasons = document.getElementById('payment-status-modal-reasons');
  const paymentStatusModalActions = document.getElementById('payment-status-modal-actions');
  const paymentStatusModalAccept = document.getElementById('payment-status-modal-accept');
  const defaultPaymentStatusAcceptLabel = paymentStatusModalAccept ? paymentStatusModalAccept.textContent : 'Aceptar';
  const modalCouponName = document.getElementById('modal-coupon-name');
  const modalYes = document.getElementById('modal-yes');
  const modalNo = document.getElementById('modal-no');
  const modalCancel = document.getElementById('modal-cancel');
  const applyCouponButton = document.getElementById('apply-coupon-btn');
  const paymentModal = document.getElementById('payment-modal');
  const paymentModalContent = paymentModal ? paymentModal.querySelector('.payment-modal-content') : null;
  const paymentModalAlert = document.getElementById('payment-modal-alert');
  const paymentModalReasons = document.getElementById('payment-modal-reasons');
  const paymentModalActions = document.getElementById('payment-modal-actions');
  const accountGalleryModal = document.getElementById('account-gallery-modal');
  const accountGalleryModalTitle = document.getElementById('account-gallery-modal-title');
  const accountGalleryModalPrice = document.getElementById('account-gallery-modal-price');
  const accountGalleryModalImage = document.getElementById('account-gallery-modal-image');
  const accountGalleryModalPlaceholder = document.getElementById('account-gallery-modal-placeholder');
  const accountGalleryModalCaption = document.getElementById('account-gallery-modal-caption');
  const accountGalleryModalThumbs = document.getElementById('account-gallery-modal-thumbs');
  const accountGalleryModalClose = document.getElementById('account-gallery-modal-close');
  const accountGalleryModalBuy = document.getElementById('account-gallery-modal-buy');
  const gameEntryWindowModal = document.getElementById('game-entry-window-modal');
  const gameEntryWindowConfirmation = document.getElementById('game-entry-window-confirmation');
  const gameEntryWindowCheckbox = document.getElementById('game-entry-window-check');
  const gameEntryWindowContinueButton = document.getElementById('game-entry-window-continue');

  function buildAppUrl(path) {
    const normalizedPath = String(path || '').startsWith('/') ? String(path || '') : `/${String(path || '')}`;
    return `${appBasePath}${normalizedPath}`;
  }
  let paymentStatusPollTimer = null;
  const paymentTimerValue = document.getElementById('payment-timer-value');
  const paymentSummaryCard = document.querySelector('.payment-summary-card');
  const paymentSummaryUser = document.getElementById('payment-summary-user');
  const paymentSummaryProduct = document.getElementById('payment-summary-product');
  const paymentSummaryTotal = document.getElementById('payment-summary-total');
  const paymentSummaryMinimalUser = document.getElementById('payment-summary-minimal-user');
  const paymentSummaryMinimalProduct = document.getElementById('payment-summary-minimal-product');
  const paymentSummaryMinimalTotal = document.getElementById('payment-summary-minimal-total');
  const paymentSummaryImage = document.getElementById('payment-summary-image');
  const paymentSummaryImagePlaceholder = document.getElementById('payment-summary-image-placeholder');
  const paymentSummaryFeatures = document.getElementById('payment-summary-features');
  const paymentMethodSelectWrap = document.getElementById('payment-method-select-wrap');
  const paymentMethodSelect = document.getElementById('payment-method-select');
  const paymentMethodCard = document.getElementById('payment-method-card');
  const paymentMethodTitle = document.getElementById('payment-method-title');
  const paymentMethodCurrency = document.getElementById('payment-method-currency');
  const paymentMethodDetails = document.getElementById('payment-method-details');
  const paymentWinPointsCard = document.getElementById('payment-win-points-card');
  const paymentWinPointsTitle = document.getElementById('payment-win-points-title');
  const paymentWinPointsCopy = document.getElementById('payment-win-points-copy');
  const paymentModeOptions = document.getElementById('payment-mode-options');
  const paymentMoneyPanel = document.getElementById('payment-money-panel');
  const paymentWinPointsBalance = document.getElementById('payment-win-points-balance');
  const paymentReferenceGroup = document.getElementById('payment-reference-group');
  const paymentReferenceInput = document.getElementById('payment-reference-input');
  const paymentReferenceHelp = document.getElementById('payment-reference-help');
  const paymentPhoneGroup = document.getElementById('payment-phone-group');
  const paymentPhoneInput = document.getElementById('payment-phone-input');
  const paymentSubmitButton = document.getElementById('payment-submit-btn');
  const paymentCancelOrderButton = document.getElementById('payment-cancel-order-btn');
  const paymentCancelConfirmModal = document.getElementById('payment-cancel-confirm-modal');
  const paymentCancelDismissButton = document.getElementById('payment-cancel-dismiss-btn');
  const paymentCancelConfirmButton = document.getElementById('payment-cancel-confirm-btn');
  let lastFocusedElement = null;
  let activePack = null;
  let activeAccountGalleryPreview = { pack: null, index: 0 };
  let selectedTotalValue = 0;
  let couponApplied = false;
  let couponValue = '';
  let activePaymentOrder = null;
  let paymentTimerInterval = null;
  let paymentDifferenceTicker = null;
  let gameEntryWindowAccepted = !gameEntryWindowEnabled;
  const defaultPrimaryField = {
    name: 'id_juego',
    label: 'ID de usuario',
    placeholder: 'Ej: 12345678',
    inputMode: 'text',
    maxLength: 150
  };

  function normalizeOrderQuantity(value) {
    const digitsOnly = String(value ?? '').replace(/\D+/g, '');
    const parsedValue = parseInt(digitsOnly, 10);
    return Number.isFinite(parsedValue) && parsedValue > 0 ? parsedValue : 1;
  }

  function getOrderQuantity() {
    return orderQuantityInput ? normalizeOrderQuantity(orderQuantityInput.value) : 1;
  }

  function syncOrderQuantityInput(nextValue = null) {
    const resolvedValue = normalizeOrderQuantity(nextValue === null ? getOrderQuantity() : nextValue);
    if (orderQuantityInput) {
      orderQuantityInput.value = String(resolvedValue);
      orderQuantityInput.disabled = !activePack;
    }
    if (orderQuantityDecreaseButton) {
      orderQuantityDecreaseButton.disabled = !activePack;
    }
    if (orderQuantityIncreaseButton) {
      orderQuantityIncreaseButton.disabled = !activePack;
    }
    if (orderQuantityHelp) {
      orderQuantityHelp.textContent = activePack
        ? 'Minimo 1 recarga.'
        : 'Selecciona un paquete para indicar la cantidad.';
    }
    return resolvedValue;
  }

  function getPackTotalPrice(pack, quantity = getOrderQuantity()) {
    if (!pack) {
      return 0;
    }

    return normalizeCurrencyAmount(Number(pack.priceValue || 0) * normalizeOrderQuantity(quantity), pack.showDecimals);
  }

  function getPackRewardPoints(pack, quantity = getOrderQuantity()) {
    return Math.max(0, Number(pack && pack.rewardPoints ? pack.rewardPoints : 0)) * normalizeOrderQuantity(quantity);
  }

  function getPackRequiredPoints(pack, quantity = getOrderQuantity()) {
    return Math.max(0, Number(pack && pack.redeemRequiredPoints ? pack.redeemRequiredPoints : 0)) * normalizeOrderQuantity(quantity);
  }

  function getCurrencyShowDecimals(currencyCode, fallback = monedaActualMostrarDecimales) {
    const target = String(currencyCode || '').trim().toUpperCase();
    if (target === '') {
      return fallback;
    }

    const currencyEntry = Object.values(monedas).find((item) => String(item && item.clave ? item.clave : '').trim().toUpperCase() === target);
    return currencyEntry ? Boolean(currencyEntry.mostrar_decimales) : fallback;
  }

  function normalizeCurrencyAlias(currencyCode) {
    const normalized = String(currencyCode || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '');
    if (!normalized) {
      return '';
    }

    if (
      normalized === 'BS'
      || normalized === 'BSS'
      || normalized.includes('VES')
      || normalized.includes('VEF')
      || normalized.includes('BOLIVAR')
      || normalized.includes('BOLIVARES')
      || normalized.endsWith('BS')
    ) {
      return 'VES';
    }

    return normalized;
  }

  function findCurrencyEntryByCode(currencyCode) {
    const rawTarget = String(currencyCode || '').trim().toUpperCase();
    const normalizedTarget = normalizeCurrencyAlias(currencyCode);
    return Object.values(monedas).find((item) => {
      const rawCode = String(item && item.clave ? item.clave : '').trim().toUpperCase();
      return (rawTarget !== '' && rawCode === rawTarget) || (normalizedTarget !== '' && normalizeCurrencyAlias(rawCode) === normalizedTarget);
    }) || null;
  }

  function resolvePreferredBinanceCurrencyEntry() {
    const currencyEntries = Object.values(monedas || {});
    if (!currencyEntries.length) {
      return null;
    }

    for (const preferredCode of ['USDT', 'USD', 'EUR', 'BRL', 'COP', 'MXN', 'CLP', 'PEN']) {
      const entry = findCurrencyEntryByCode(preferredCode);
      if (entry) {
        return entry;
      }
    }

    return currencyEntries.find((entry) => normalizeCurrencyAlias(entry && entry.clave ? entry.clave : '') !== 'VES') || currencyEntries[0] || null;
  }

  function convertCurrencyAmountBetweenCodes(amount, fromCode, toCode) {
    const numericAmount = Number(amount || 0);
    if (!Number.isFinite(numericAmount) || numericAmount <= 0) {
      return 0;
    }

    const targetEntry = findCurrencyEntryByCode(toCode);
    if (!targetEntry) {
      return numericAmount;
    }

    const fromNormalized = normalizeCurrencyAlias(fromCode);
    const toNormalized = normalizeCurrencyAlias(toCode);
    if (fromNormalized !== '' && fromNormalized === toNormalized) {
      return normalizeCurrencyAmount(numericAmount, Boolean(targetEntry.mostrar_decimales));
    }

    const sourceEntry = findCurrencyEntryByCode(fromCode);
    if (!sourceEntry) {
      return normalizeCurrencyAmount(numericAmount, Boolean(targetEntry.mostrar_decimales));
    }

    const sourceRate = Number(sourceEntry.tasa || 0);
    const targetRate = Number(targetEntry.tasa || 0);
    if (!Number.isFinite(sourceRate) || sourceRate <= 0 || !Number.isFinite(targetRate) || targetRate <= 0) {
      return normalizeCurrencyAmount(numericAmount, Boolean(targetEntry.mostrar_decimales));
    }

    const baseAmount = numericAmount / sourceRate;
    return normalizeCurrencyAmount(baseAmount * targetRate, Boolean(targetEntry.mostrar_decimales));
  }

  function resolveBinanceDisplayMoney(pack) {
    const targetEntry = resolvePreferredBinanceCurrencyEntry();
    const sourceCurrency = String((pack && pack.moneda) || monedaActualClave || '').trim().toUpperCase();
    const sourceAmount = Number(pack ? getPackTotalPrice(pack, Number(pack.purchaseQuantity || getOrderQuantity())) : 0);
    if (!targetEntry) {
      return {
        currency: sourceCurrency,
        amount: normalizeCurrencyAmount(sourceAmount, Boolean(pack && pack.showDecimals)),
        text: formatPaymentDifferenceMoney(sourceCurrency, sourceAmount, pack && pack.showDecimals),
      };
    }

    const targetCurrency = String(targetEntry.clave || '').trim().toUpperCase();
    const amount = convertCurrencyAmountBetweenCodes(sourceAmount, sourceCurrency, targetCurrency);
    return {
      currency: targetCurrency,
      amount,
      text: formatPaymentDifferenceMoney(targetCurrency, amount, Boolean(targetEntry.mostrar_decimales)),
    };
  }

  function formatPaymentDifferenceMoney(currencyCode, amount, showDecimals = null) {
    const useDecimals = showDecimals === null ? getCurrencyShowDecimals(currencyCode) : Boolean(showDecimals);
    return `${String(currencyCode || '').trim().toUpperCase() || monedaActualClave} ${formatCurrencyAmount(amount, useDecimals)}`;
  }

  function formatPaymentDifferenceDuration(totalSeconds) {
    const normalizedSeconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));
    const minutes = Math.floor(normalizedSeconds / 60);
    const seconds = normalizedSeconds % 60;
    if (minutes <= 0) {
      return `${seconds}s`;
    }
    if (seconds === 0) {
      return `${minutes} min`;
    }
    return `${minutes} min ${String(seconds).padStart(2, '0')}s`;
  }

  function normalizePaymentDifferenceCredit(rawCredit) {
    if (!paymentDifferenceFeatureEnabled || !rawCredit || typeof rawCredit !== 'object') {
      return null;
    }

    const availableAmount = normalizeCurrencyAmount(rawCredit.available_amount ?? rawCredit.overpayment_amount ?? 0, true);
    const currency = String(rawCredit.currency || '').trim().toUpperCase();
    const sourceOrderId = Number(rawCredit.source_order_id || 0);
    const remainingSeconds = Math.max(0, Math.floor(Number(rawCredit.remaining_seconds || 0)));

    if (!Number.isFinite(availableAmount) || availableAmount <= 0 || currency === '') {
      return null;
    }

    return {
      availableAmount,
      currency,
      sourceOrderId,
      remainingSeconds,
      status: String(rawCredit.status || '').trim().toLowerCase(),
      message: String(rawCredit.message || '').trim(),
    };
  }

  function getPaymentDifferenceBreakdown(pack, baseAmount = selectedTotalValue) {
    const currency = String((pack && pack.moneda) || monedaActualClave || '').trim().toUpperCase();
    const showDecimals = pack ? Boolean(pack.showDecimals) : getCurrencyShowDecimals(currency);
    const subtotalAmount = normalizeCurrencyAmount(baseAmount, showDecimals);
    const credit = paymentDifferenceCreditState
      && paymentDifferenceCreditState.currency === currency
      && Number(paymentDifferenceCreditState.remainingSeconds || 0) > 0
      ? paymentDifferenceCreditState
      : null;
    const appliedAmount = credit ? normalizeCurrencyAmount(Math.min(Number(credit.availableAmount || 0), subtotalAmount), showDecimals) : 0;

    return {
      currency,
      showDecimals,
      subtotalAmount,
      appliedAmount,
      finalAmount: normalizeCurrencyAmount(Math.max(subtotalAmount - appliedAmount, 0), showDecimals),
      hasCredit: Boolean(credit),
      availableAmount: credit ? normalizeCurrencyAmount(credit.availableAmount, showDecimals) : 0,
      remainingSeconds: credit ? Number(credit.remainingSeconds || 0) : 0,
      sourceOrderId: credit ? Number(credit.sourceOrderId || 0) : 0,
      blocksSelection: Boolean(credit && subtotalAmount > 0 && Number(credit.availableAmount || 0) + 0.0001 >= subtotalAmount),
      message: credit ? String(credit.message || '').trim() : '',
    };
  }

  function updateSelectedPriceDisplay(pack) {
    if (!pack) {
      selectedPrice.textContent = `${monedaActualClave} ${formatCurrencyAmount(0, monedaActualMostrarDecimales)}`;
      if (selectedPriceDetail) {
        selectedPriceDetail.textContent = '';
        selectedPriceDetail.classList.add('d-none');
      }
      refreshPaymentDifferenceBanner(null);
      return;
    }

    const breakdown = getPaymentDifferenceBreakdown(pack, selectedTotalValue);
    selectedPrice.textContent = formatPaymentDifferenceMoney(pack.moneda || monedaActualClave, breakdown.finalAmount, breakdown.showDecimals);

    if (selectedPriceDetail) {
      if (breakdown.appliedAmount > 0) {
        selectedPriceDetail.textContent = `Original ${formatPaymentDifferenceMoney(pack.moneda || monedaActualClave, breakdown.subtotalAmount, breakdown.showDecimals)} | Saldo aplicado ${formatPaymentDifferenceMoney(pack.moneda || monedaActualClave, breakdown.appliedAmount, breakdown.showDecimals)}`;
        selectedPriceDetail.classList.remove('d-none');
      } else {
        selectedPriceDetail.textContent = '';
        selectedPriceDetail.classList.add('d-none');
      }
    }

    refreshPaymentDifferenceBanner(pack);
  }

  function refreshPaymentDifferenceBanner(pack = activePack) {
    if (!paymentDifferenceBanner) {
      return;
    }

    const activeCredit = normalizePaymentDifferenceCredit(paymentDifferenceCreditState);
    if (!paymentDifferenceFeatureEnabled || !activeCredit) {
      paymentDifferenceBanner.className = 'd-none payment-difference-banner mt-3';
      paymentDifferenceBanner.innerHTML = '';
      return;
    }

    const breakdown = getPaymentDifferenceBreakdown(pack, selectedTotalValue);
    const title = breakdown.blocksSelection
      ? 'Selecciona un paquete mayor al saldo a favor'
      : 'Saldo a favor disponible para una sola recarga';
    let summary = activeCredit.message || 'Puedes usar este monto restante una sola vez antes de que expire.';
    if (pack && breakdown.hasCredit && !breakdown.blocksSelection) {
      summary = `Se aplicarán ${formatPaymentDifferenceMoney(breakdown.currency, breakdown.appliedAmount, breakdown.showDecimals)} a este paquete. Solo pagarás ${formatPaymentDifferenceMoney(breakdown.currency, breakdown.finalAmount, breakdown.showDecimals)}.`;
    } else if (pack && breakdown.blocksSelection) {
      summary = `Tu saldo a favor actual es ${formatPaymentDifferenceMoney(breakdown.currency, breakdown.availableAmount, breakdown.showDecimals)}. Debes elegir un paquete cuyo total original sea mayor a ese monto.`;
    }

    const details = [
      `<div><strong>Disponible:</strong> ${escapePaymentHtml(formatPaymentDifferenceMoney(activeCredit.currency, activeCredit.availableAmount, getCurrencyShowDecimals(activeCredit.currency)))}</div>`,
      `<div><strong>Vence en:</strong> ${escapePaymentHtml(formatPaymentDifferenceDuration(activeCredit.remainingSeconds))}</div>`,
      `<div><strong>Pedido origen:</strong> #${escapePaymentHtml(String(activeCredit.sourceOrderId || '-'))}</div>`
    ];

    paymentDifferenceBanner.className = 'payment-difference-banner mt-3';
    paymentDifferenceBanner.dataset.variant = breakdown.blocksSelection ? 'warning' : 'active';
    paymentDifferenceBanner.innerHTML = `
      <div class="payment-difference-banner-title">${escapePaymentHtml(title)}</div>
      <div class="payment-difference-banner-copy">${escapePaymentHtml(summary)}</div>
      <div class="payment-difference-breakdown">${details.join('')}</div>
    `;
  }

  function startPaymentDifferenceTicker() {
    if (paymentDifferenceTicker) {
      clearInterval(paymentDifferenceTicker);
      paymentDifferenceTicker = null;
    }

    if (!normalizePaymentDifferenceCredit(paymentDifferenceCreditState)) {
      return;
    }

    paymentDifferenceTicker = window.setInterval(() => {
      const normalizedCredit = normalizePaymentDifferenceCredit(paymentDifferenceCreditState);
      if (!normalizedCredit) {
        clearInterval(paymentDifferenceTicker);
        paymentDifferenceTicker = null;
        paymentDifferenceCreditState = null;
        refreshPaymentDifferenceBanner(activePack);
        updateButtonState();
        return;
      }

      normalizedCredit.remainingSeconds = Math.max(0, normalizedCredit.remainingSeconds - 1);
      paymentDifferenceCreditState = normalizedCredit.remainingSeconds > 0 ? normalizedCredit : null;
      refreshPaymentDifferenceBanner(activePack);
      if (activePack) {
        updateSelectedPriceDisplay(activePack);
      }
      updateButtonState();
    }, 1000);
  }

  function setPaymentDifferenceCreditState(nextCredit) {
    paymentDifferenceCreditState = normalizePaymentDifferenceCredit(nextCredit);
    startPaymentDifferenceTicker();
    refreshPaymentDifferenceBanner(activePack);
    if (activePack) {
      updateSelectedPriceDisplay(activePack);
    }
    updateButtonState();
  }

  function syncActivePaymentOrderDeadline(remainingSeconds) {
    if (!activePaymentOrder) {
      return;
    }

    const safeRemainingSeconds = Math.max(0, Math.floor(Number(remainingSeconds || 0)));
    if (safeRemainingSeconds <= 0) {
      return;
    }

    activePaymentOrder.expiresAtMs = Date.now() + (safeRemainingSeconds * 1000);
    updatePaymentTimer();
  }

  function filterBinanceReasons(data) {
    return extractPaymentReasons(data).filter((reason) => {
      const normalized = String(reason || '').trim().toLowerCase();
      return normalized !== '' && !['success', 'created', 'pending'].includes(normalized);
    });
  }

  function canSwitchFromBinanceToOtherPaymentMode() {
    if (!activePaymentOrder) {
      return false;
    }

    return !!activePaymentOrder.canUseMoney || !!activePaymentOrder.canUsePoints;
  }

  function switchFromBinanceToOtherPaymentMode() {
    if (!activePaymentOrder) {
      return;
    }

    clearPaymentStatusPolling();
    setOverlayVisible(paymentStatusModal, false);
    setPaymentFormDisabled(false);
      clearPaymentSupportUi();

    const nextMode = activePaymentOrder.canUseMoney ? 'money' : (activePaymentOrder.canUsePoints ? 'points' : 'binance');
    setActivePaymentMode(nextMode, activePaymentOrder.selectedMethodId, { expandSelected: true });
    setCancelOrderButtonMode('cancel');
      setPaymentAlert('', 'info');
    scrollPaymentSubmitIntoView();
  }

  function openBinanceCancellationFlow() {
    clearPaymentStatusPolling();
    setOverlayVisible(paymentStatusModal, false);
    if (activePaymentOrder && paymentCancelConfirmModal) {
      setOverlayVisible(paymentCancelConfirmModal, true);
    }
  }

  function buildBinancePopupLoaderHtml() {
    return `<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Abriendo Binance Pay...</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;font-family:Arial,sans-serif;background:#081018;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}.card{max-width:480px;width:100%;background:#111827;border:1px solid #22d3ee;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.35)}h1{margin:0 0 12px;font-size:24px;color:#22d3ee}p{margin:0 0 12px;line-height:1.6}.spinner{width:44px;height:44px;border-radius:999px;border:4px solid rgba(34,211,238,.18);border-top-color:#22d3ee;animation:spin .9s linear infinite;margin:0 0 20px}@keyframes spin{to{transform:rotate(360deg)}}</style></head><body><div class="card"><div class="spinner"></div><h1>Abriendo Binance Pay...</h1><p>Estamos conectando tu orden con CoinPal para mostrar el checkout de Binance Pay.</p><p>Si el redireccionamiento tarda unos segundos, deja esta ventana abierta.</p></div></body></html>`;
  }

  function openBinanceCheckoutPopup() {
    const popup = window.open('', '_blank');
    if (!popup) {
      return null;
    }

    try {
      popup.opener = null;
      popup.document.open();
      popup.document.write(buildBinancePopupLoaderHtml());
      popup.document.close();
    } catch (_) {
    }

    return popup;
  }

  function navigateBinanceCheckoutPopup(popup, checkoutUrl) {
    const targetUrl = String(checkoutUrl || '').trim();
    if (!targetUrl) {
      return false;
    }

    if (popup && !popup.closed) {
      try {
        popup.location.replace(targetUrl);
        return true;
      } catch (_) {
      }
    }

    const reopened = window.open(targetUrl, '_blank', 'noopener');
    if (reopened) {
      try {
        reopened.opener = null;
      } catch (_) {
      }
      return true;
    }

    return false;
  }

  function isCoinpalCheckoutUrl(checkoutUrl) {
    const targetUrl = String(checkoutUrl || '').trim();
    if (!targetUrl) {
      return false;
    }

    try {
      const parsed = new URL(targetUrl, window.location.origin);
      const host = String(parsed.hostname || '').toLowerCase();
      const path = String(parsed.pathname || '').toLowerCase();
      return (host === 'pay.coinpal.io' || host.endsWith('.coinpal.io')) && path.includes('/cashier/');
    } catch (_) {
      return false;
    }
  }

  async function reopenBinanceCheckout(checkoutUrl, reference, totalText) {
    const popup = openBinanceCheckoutPopup();

    if (isCoinpalCheckoutUrl(checkoutUrl)) {
      const opened = navigateBinanceCheckoutPopup(popup, checkoutUrl);
      if (opened) {
        return;
      }
    }

    if (!activePaymentOrder || !activePaymentOrder.orderId) {
      if (popup && !popup.closed) {
        popup.close();
      }
      setPaymentAlert('No hay una orden activa para reabrir el checkout de Binance Pay.', 'danger');
      return;
    }

    try {
      const response = await fetch(buildAppUrl('/api/pedidos.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: [
          'action=submit_payment',
          `order_id=${encodeURIComponent(activePaymentOrder.orderId)}`,
          'payment_mode=binance'
        ].join('&')
      });
      const data = await parseApiJsonResponse(response, 'No se pudo reabrir el checkout de Binance Pay en este momento.');
      if (!response.ok || !data.ok) {
        throw new Error((data && data.message) ? data.message : 'No se pudo reabrir el checkout de Binance Pay.');
      }

      const refreshedCheckoutUrl = String((data && data.checkout_url) || '').trim();
      if (!isCoinpalCheckoutUrl(refreshedCheckoutUrl)) {
        throw new Error('CoinPal no devolvió una URL válida del cashier para Binance Pay.');
      }

      if (data && Number.isFinite(Number(data.remaining_seconds || 0))) {
        syncActivePaymentOrderDeadline(Number(data.remaining_seconds || 0));
      }

      renderBinancePaymentDetails(data, (data && data.provider_reference) ? data.provider_reference : reference, totalText || getConfirmedPaymentTotalText());

      const opened = navigateBinanceCheckoutPopup(popup, refreshedCheckoutUrl);
      if (!opened) {
        throw new Error('No pudimos abrir automáticamente Binance Pay.');
      }
    } catch (error) {
      if (popup && !popup.closed) {
        popup.close();
      }
      setPaymentAlert(normalizeApiRequestErrorMessage(error, 'No se pudo reabrir el checkout de Binance Pay en este momento.'), 'danger');
    }
  }

  function setPaymentStatusAcceptHidden(isHidden) {
    if (!paymentStatusModalAccept) {
      return;
    }

    paymentStatusModalAccept.classList.toggle('d-none', !!isHidden);
    if (isHidden) {
      paymentStatusModalAccept.setAttribute('aria-hidden', 'true');
    } else {
      paymentStatusModalAccept.removeAttribute('aria-hidden');
    }
  }

  function renderPaymentActionButtons(actions, options = {}) {
    const variant = options && (options.variant === 'underpaid' || options.variant === 'overpaid')
      ? options.variant
      : '';
    const hideDefaultStatusAccept = !!(options && options.hideDefaultStatusAccept);

    const applyActions = (container) => {
      if (!container) {
        return;
      }

      container.innerHTML = '';
      if (!Array.isArray(actions) || actions.length === 0) {
        container.className = 'd-none payment-support-actions mb-4';
        container.removeAttribute('data-payment-difference-variant');
        return;
      }

      container.className = 'payment-support-actions payment-difference-actions mb-4';
      if (variant !== '') {
        container.setAttribute('data-payment-difference-variant', variant);
      } else {
        container.removeAttribute('data-payment-difference-variant');
      }
      actions.forEach((action) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `btn ${action.className || 'btn-info'} fw-bold payment-difference-action-btn`;
        button.textContent = action.label;
        button.addEventListener('click', action.onClick);
        container.appendChild(button);
      });
    };

    applyActions(paymentModalActions);
    applyActions(paymentStatusModalActions);
    setPaymentStatusAcceptHidden(hideDefaultStatusAccept);
  }

  function prepareSameOrderCompletion(message) {
    if (paymentStatusModal) {
      setOverlayVisible(paymentStatusModal, false);
    }
    setPaymentFormDisabled(false);
    setCancelOrderButtonMode('cancel');
    if (paymentSubmitButton) {
      paymentSubmitButton.textContent = completeRechargeButtonLabel;
    }
    if (paymentReferenceInput) {
      paymentReferenceInput.value = '';
      paymentReferenceInput.focus();
    }
    setPaymentAlert(message || 'Realiza el pago restante y luego registra la nueva referencia para completar esta recarga.', 'warning');
    scrollPaymentSubmitIntoView();
  }

  async function activatePaymentDifferenceCreditForCurrentOrder() {
    if (!activePaymentOrder || !activePaymentOrder.orderId) {
      showToast('No hay un pedido válido para activar el saldo a favor.', 'error');
      return;
    }

    setOverlayVisible(loadingModal, true);
    setLoadingModalContent('Activando saldo a favor...', 'Estamos preparando tu saldo a favor para completar otra recarga.', 'processing');

    try {
      const response = await fetch(buildAppUrl('/api/pedidos.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=activate_payment_difference_credit&order_id=${encodeURIComponent(activePaymentOrder.orderId)}`
      });
      const data = await parseApiJsonResponse(response, 'No se pudo activar el saldo a favor en este momento.');
      if (!response.ok || !data.ok) {
        throw new Error((data && data.message) ? data.message : 'No se pudo activar el saldo a favor.');
      }

      setPaymentDifferenceCreditState(data && data.payment_difference ? data.payment_difference : null);
      setOverlayVisible(loadingModal, false);
      if (paymentStatusModal) {
        setOverlayVisible(paymentStatusModal, false);
      }
      closePaymentModal(true);
      resetCheckoutState();
      showToast((data && data.message) ? data.message : 'Saldo a favor activado.', 'success');
      scrollToOrderForm();
    } catch (error) {
      setOverlayVisible(loadingModal, false);
      const errorMessage = normalizeApiRequestErrorMessage(error, 'No se pudo activar el saldo a favor en este momento.');
      setPaymentAlert(errorMessage, 'danger');
      showPaymentStatusModal('No se pudo activar el saldo a favor', errorMessage, 'danger');
    }
  }

  function renderUnderpaidPaymentDifference(data) {
    const difference = data && data.payment_difference ? data.payment_difference : null;
    if (!difference || String(difference.status || '').toLowerCase() !== 'underpaid') {
      return false;
    }

    const currency = String(difference.currency || (activePaymentOrder ? activePaymentOrder.currency : monedaActualClave)).trim().toUpperCase() || monedaActualClave;
    const showDecimals = activePaymentOrder && activePaymentOrder.pack ? Boolean(activePaymentOrder.pack.showDecimals) : getCurrencyShowDecimals(currency);
    const expectedTotal = normalizeCurrencyAmount(difference.expected_total || 0, showDecimals);
    const paidTotal = normalizeCurrencyAmount(difference.paid_total || 0, showDecimals);
    const remainingAmount = normalizeCurrencyAmount(difference.remaining_amount || 0, showDecimals);
    const summary = `Recibimos ${formatPaymentDifferenceMoney(currency, paidTotal, showDecimals)} de ${formatPaymentDifferenceMoney(currency, expectedTotal, showDecimals)}. Falta ${formatPaymentDifferenceMoney(currency, remainingAmount, showDecimals)} para completar esta misma recarga.`;

    syncActivePaymentOrderDeadline(data.remaining_seconds || difference.remaining_seconds || 0);
    renderSupportCard(paymentModalReasons, 'Pago recibido parcialmente', summary, [
      'Realiza otro pago por el monto restante para este mismo pedido.',
      'Ingresa la nueva referencia cuando el banco la refleje.',
      'No necesitas crear otra orden para completar esta recarga.'
    ], [], { variant: 'underpaid' });
    renderSupportCard(paymentStatusModalReasons, 'Pago recibido parcialmente', summary, [
      'Realiza otro pago por el monto restante para este mismo pedido.',
      'Ingresa la nueva referencia cuando el banco la refleje.',
      'No necesitas crear otra orden para completar esta recarga.'
    ], [], { variant: 'underpaid' });
    renderPaymentActionButtons([
      {
        label: completeRechargeButtonLabel,
        className: 'btn-info',
        onClick: () => prepareSameOrderCompletion('Realiza el pago restante y registra la nueva referencia para completar esta recarga.')
      }
    ], { variant: 'underpaid', hideDefaultStatusAccept: true });
    if (paymentSubmitButton) {
      paymentSubmitButton.textContent = completeRechargeButtonLabel;
    }
    setPaymentAlert(data.message || 'Tu pago fue recibido parcialmente. Completa el monto restante para procesar la recarga.', 'warning');
    setPaymentFormDisabled(false);
    showPaymentStatusModal('Pago pendiente por completar', data.message || summary, 'info');
    return true;
  }

  function renderOverpaidPaymentDifference(data) {
    const difference = data && data.payment_difference ? data.payment_difference : null;
    if (!difference || String(difference.status || '').toLowerCase() !== 'overpaid') {
      return false;
    }

    const currency = String(difference.currency || (activePaymentOrder ? activePaymentOrder.currency : monedaActualClave)).trim().toUpperCase() || monedaActualClave;
    const showDecimals = activePaymentOrder && activePaymentOrder.pack ? Boolean(activePaymentOrder.pack.showDecimals) : getCurrencyShowDecimals(currency);
    const overpaymentAmount = normalizeCurrencyAmount(difference.overpayment_amount || 0, showDecimals);
    if (overpaymentAmount <= 0) {
      return false;
    }

    const summary = `Tu pedido principal ya fue atendido y quedó un saldo a favor de ${formatPaymentDifferenceMoney(currency, overpaymentAmount, showDecimals)}.`;
    const steps = difference.can_activate_credit
      ? [
          'Si eliges Seguir con la Recarga, cerramos esta operación sin activar el saldo a favor.',
          'Si eliges Completar Recarga, activaremos el saldo restante durante 30 minutos para usarlo en otro paquete.'
        ]
      : ['Este pedido ya consumió su oportunidad de completar otra recarga con saldo a favor.'];

    if (!extractProviderCodes(data).length) {
      renderSupportCard(paymentModalReasons, 'Se detectó un monto mayor al esperado', summary, steps, [], { variant: 'overpaid' });
    }
    renderSupportCard(paymentStatusModalReasons, 'Se detectó un monto mayor al esperado', summary, steps, [], { variant: 'overpaid' });

    const actions = [
      {
        label: 'Seguir con la Recarga',
        className: 'btn-outline-light',
        onClick: () => {
          if (paymentStatusModal) {
            setOverlayVisible(paymentStatusModal, false);
          }
          closePaymentModal(true);
          resetCheckoutState();
            showToast('Tu recarga continuará con el proceso normal. El saldo a favor no fue activado.', 'success');
        }
      }
    ];

    if (difference.can_activate_credit) {
      actions.unshift({
        label: completeRechargeButtonLabel,
        className: 'btn-success',
        onClick: () => {
          activatePaymentDifferenceCreditForCurrentOrder();
        }
      });
    }

    renderPaymentActionButtons(actions, { variant: 'overpaid', hideDefaultStatusAccept: true });
    return true;
  }

  function restoreStoredPurchaseDefaults(force = false) {
    if (playerPrimaryInput) {
      if (playerPrimaryInput.tagName === 'SELECT') {
        const hasStoredOption = Array.from(playerPrimaryInput.options).some((option) => String(option.value) === String(defaultOrderUserIdentifier || ''));
        if ((force || !playerPrimaryInput.value) && hasStoredOption) {
          playerPrimaryInput.value = defaultOrderUserIdentifier || '';
        }
      } else if (force || playerPrimaryInput.value.trim() === '') {
        playerPrimaryInput.value = defaultOrderUserIdentifier || '';
      }
    }

    if (paymentPhoneInput && (force || paymentPhoneInput.value.trim() === '')) {
      paymentPhoneInput.value = defaultPaymentPhone || '';
    }
  }
  let playerVerificationState = {
    verified: false,
    playerName: '',
    signature: '',
    pending: false,
    serverUnavailable: false,
  };
  let playerVerificationAutoTimer = null;
  let playerVerificationRequestSeq = 0;
  let playerVerificationPendingSignature = '';

  function parseRequiredFields(rawValue) {
    try {
      const parsed = JSON.parse(String(rawValue || '[]'));
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function parsePackageFeatures(rawValue) {
    try {
      const parsed = JSON.parse(String(rawValue || '[]'));
      return Array.isArray(parsed)
        ? parsed.filter((feature) => feature && typeof feature === 'object' && String(feature.name || '').trim() !== '')
        : [];
    } catch (error) {
      return [];
    }
  }

  function resolvePublicImageUrl(rawPath) {
    const trimmed = String(rawPath || '').trim();
    if (trimmed === '') {
      return '';
    }

    if (/^https?:\/\//i.test(trimmed)) {
      return trimmed;
    }

    return buildAppUrl(`/${trimmed.replace(/^\/+/, '')}`);
  }

  function parseAccountSaleGallery(rawValue) {
    try {
      const parsed = JSON.parse(String(rawValue || '[]'));
      return Array.isArray(parsed)
        ? parsed
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
              imageUrl: resolvePublicImageUrl(item.image_url || item.image_path || ''),
              description: String(item.description || '').trim(),
              order: Number(item.order || 0),
            }))
            .filter((item) => item.imageUrl !== '')
        : [];
    } catch (error) {
      return [];
    }
  }

  function isAccountSalePack(pack) {
    return Boolean(accountSaleFeatureEnabled && pack && pack.accountSale);
  }

  function setAccountSaleNote(pack) {
    if (!accountSaleNote) {
      return;
    }

    const visible = isAccountSalePack(pack);
    accountSaleNote.classList.toggle('d-none', !visible);
  }

  function getAccountSalePayload(data) {
    const payload = data && typeof data.account_sale === 'object' ? data.account_sale : null;
    if (!payload || !payload.enabled) {
      return null;
    }

    return {
      delivered: !!payload.delivered,
      accountText: String(payload.account_text || '').trim(),
      gallery: Array.isArray(payload.gallery)
        ? payload.gallery
            .filter((item) => item && typeof item === 'object')
            .map((item) => ({
              imageUrl: resolvePublicImageUrl(item.image_url || item.image_path || ''),
              description: String(item.description || '').trim(),
            }))
            .filter((item) => item.imageUrl !== '')
        : [],
    };
  }

  function buildPackStateFromCard(card) {
    return {
      id: card.dataset.packageId,
      name: card.dataset.name,
      priceValue: Number(card.dataset.priceValue || 0),
      moneda: card.dataset.moneda,
      cantidad: card.dataset.cantidad,
      showDecimals: card.dataset.showDecimals === '1',
      rewardPoints: Number(card.dataset.winPointsReward || 0),
      redeemRequiredPoints: Number(card.dataset.winPointsRequired || 0),
      redeemActive: card.dataset.winPointsActive === '1',
      requiredFields: parseRequiredFields(card.dataset.requiredFields),
      imageUrl: String(card.dataset.packageImage || ''),
      features: parsePackageFeatures(card.dataset.packageFeatures),
      accountSale: card.dataset.accountSale === '1',
      accountGallery: parseAccountSaleGallery(card.dataset.accountGallery)
    };
  }

  function paymentSummaryFeatureIconMarkup(iconKey) {
    const safeKey = String(iconKey || 'sparkles').trim();
    return packageFeatureIconSvgMap[safeKey] || packageFeatureIconSvgMap.sparkles || '';
  }

  function renderPaymentSummary(pack, userId, totalText) {
    const safeUser = isAccountSalePack(pack) ? 'Entrega directa' : (userId || '-');
    const quantity = normalizeOrderQuantity(pack && pack.purchaseQuantity ? pack.purchaseQuantity : 1);
    const safeProduct = (pack && pack.name)
      ? (quantity > 1 ? `${pack.name} x${quantity}` : pack.name)
      : 'Producto';
    const safeTotal = totalText || '-';

    paymentSummaryUser.textContent = safeUser;
    paymentSummaryProduct.textContent = safeProduct;
    paymentSummaryTotal.textContent = safeTotal;

    if (!paymentHeaderMinimalEnabled || !paymentSummaryCard) {
      return;
    }

    if (paymentSummaryMinimalUser) {
      paymentSummaryMinimalUser.textContent = safeUser;
    }
    if (paymentSummaryMinimalProduct) {
      paymentSummaryMinimalProduct.textContent = safeProduct;
    }
    if (paymentSummaryMinimalTotal) {
      paymentSummaryMinimalTotal.textContent = safeTotal;
    }

    const imageUrl = String((pack && pack.imageUrl) || '').trim();
    if (paymentSummaryImage) {
      paymentSummaryImage.src = imageUrl;
      paymentSummaryImage.alt = safeProduct;
      paymentSummaryImage.classList.toggle('d-none', imageUrl === '');
    }
    if (paymentSummaryImagePlaceholder) {
      paymentSummaryImagePlaceholder.classList.toggle('d-none', imageUrl !== '');
    }

    if (paymentSummaryFeatures) {
      const features = Array.isArray(pack && pack.features) ? pack.features : [];
      if (features.length === 0) {
        paymentSummaryFeatures.innerHTML = '';
        paymentSummaryFeatures.classList.add('d-none');
      } else {
        paymentSummaryFeatures.innerHTML = features.map((feature) => {
          const iconMarkup = paymentSummaryFeatureIconMarkup(feature && feature.icon ? feature.icon : 'sparkles');
          return `<span class="payment-summary-feature"><span class="payment-summary-feature-icon" aria-hidden="true">${iconMarkup}</span><span>${escapePaymentHtml(feature && feature.name ? feature.name : '')}</span></span>`;
        }).join('');
        paymentSummaryFeatures.classList.remove('d-none');
      }
    }
  }

  function getConfirmedPaymentTotalText(fallbackText = '') {
    if (activePaymentOrder && typeof activePaymentOrder.confirmedTotalText === 'string') {
      const confirmedTotal = activePaymentOrder.confirmedTotalText.trim();
      if (confirmedTotal !== '') {
        return confirmedTotal;
      }
    }

    if (paymentSummaryTotal && typeof paymentSummaryTotal.textContent === 'string') {
      const summaryTotal = paymentSummaryTotal.textContent.trim();
      if (summaryTotal !== '') {
        return summaryTotal;
      }
    }

    return String(fallbackText || '').trim();
  }

  function formatWinPointsAmount(points) {
    return `${Number(points || 0).toLocaleString('en-US')} ${winPointsState.name || 'Win Points'}`;
  }

  function formatWinPointsExpirationText(summary, includeDate = false) {
    const status = String((summary && summary.expiration_status) || '').trim();
    const daysLabel = String((summary && summary.days_remaining_label) || '').trim();
    const expiresLabel = String((summary && summary.expires_at_label) || '').trim();
    if (status === 'expired') {
      return includeDate && expiresLabel && expiresLabel !== 'Sin saldo' ? `Vencidos | ${expiresLabel}` : 'Vencidos';
    }
    if ((status === 'active' || status === 'warning') && daysLabel !== '') {
      return includeDate && expiresLabel && expiresLabel !== 'Sin saldo'
        ? `Vence en ${daysLabel} | ${expiresLabel}`
        : `Vence en ${daysLabel}`;
    }
    return daysLabel || 'Sin saldo';
  }

  function applyWinPointsUserSummary(summary) {
    if (!summary || !Number.isFinite(Number(summary.balance))) {
      return;
    }

    const refreshedBalance = Number(summary.balance);
    const userMenuRewardsBalance = document.getElementById('user-menu-rewards-balance');
    const userRewardsBalanceValue = document.getElementById('user-rewards-balance-value');
    const userMenuRewardsExpiration = document.getElementById('user-menu-rewards-expiration');
    const userRewardsExpirationValue = document.getElementById('user-rewards-expiration-value');

    winPointsState.balance = refreshedBalance;

    if (userMenuRewardsBalance) {
      userMenuRewardsBalance.textContent = refreshedBalance.toLocaleString('en-US');
    }
    if (userRewardsBalanceValue) {
      userRewardsBalanceValue.textContent = refreshedBalance.toLocaleString('en-US');
    }
    if (userMenuRewardsExpiration) {
      userMenuRewardsExpiration.textContent = formatWinPointsExpirationText(summary, false);
    }
    if (userRewardsExpirationValue) {
      userRewardsExpirationValue.textContent = formatWinPointsExpirationText(summary, true);
    }
  }

  function buildWinPointsFloatingNotification(payload) {
    const notification = document.createElement('div');
    notification.className = 'win-points-live-notification';
    notification.dataset.position = String(winPointsState.notificationPosition || 'bottom-left');

    const notificationLogo = String(winPointsState.notificationLogoUrl || winPointsState.iconUrl || '');
    const iconMarkup = notificationLogo
      ? '<div class="win-points-live-notification__logo-wrap"><img src="' + escapePaymentHtml(notificationLogo) + '" alt="' + escapePaymentHtml(winPointsState.name || 'Win Points') + '" class="win-points-live-notification__logo"></div>'
      : '<div class="win-points-live-notification__logo-wrap"><span class="win-points-live-notification__logo-fallback">WP</span></div>';

    notification.innerHTML = ''
      + '<div class="win-points-live-notification__pulse" aria-hidden="true"></div>'
      + iconMarkup
      + '<div class="win-points-live-notification__body">'
      + '<div class="win-points-live-notification__title">' + escapePaymentHtml(payload.title || '') + '</div>'
      + '<div class="win-points-live-notification__detail">' + escapePaymentHtml(payload.detail || '') + '</div>'
      + '</div>';

    return notification;
  }

  function showWinPointsNotification(payload) {
    if (!winPointsState.enabled || !payload || !payload.title) {
      return;
    }

    const existing = document.querySelector('.win-points-live-notification[data-win-points-runtime="1"]');
    if (existing) {
      existing.remove();
    }

    const notification = buildWinPointsFloatingNotification(payload);
    notification.dataset.winPointsRuntime = '1';
    document.body.appendChild(notification);

    window.requestAnimationFrame(function () {
      notification.classList.add('is-visible');
    });

    window.setTimeout(function () {
      notification.classList.remove('is-visible');
      window.setTimeout(function () {
        notification.remove();
      }, 320);
    }, 5000);
  }

  function syncWinPointsSummaryFromResponse(summary, options = {}) {
    if (!summary || !Number.isFinite(Number(summary.balance))) {
      return;
    }

    const previousBalance = Number(winPointsState.balance || 0);
    const nextBalance = Number(summary.balance || 0);
    const spentPoints = Math.max(0, Number(summary.spent || 0));
    const earnedPoints = Math.max(0, nextBalance - previousBalance);

    applyWinPointsUserSummary(summary);

    if (options && options.silent) {
      return;
    }

    if (spentPoints > 0) {
      showWinPointsNotification({
        title: '-' + spentPoints + ' ' + (winPointsState.name || 'Win Points'),
        detail: 'Se descontaron de tu saldo para completar el canje del paquete seleccionado.'
      });
      return;
    }

    if (earnedPoints > 0) {
      showWinPointsNotification({
        title: '+' + earnedPoints + ' ' + (winPointsState.name || 'Win Points'),
        detail: 'Tu saldo fue actualizado correctamente con el premio de esta compra.'
      });
    }
  }

  function canRedeemPackWithPoints(pack) {
    return Boolean(
      winPointsState.enabled
      && winPointsState.loggedIn
      && pack
      && pack.redeemActive
      && getPackRequiredPoints(pack) > 0
      && Number(winPointsState.balance || 0) >= getPackRequiredPoints(pack)
    );
  }

  function canUseBinanceCheckout(pack) {
    return Boolean(binancePayCheckoutEnabled && pack && getPackTotalPrice(pack) > 0);
  }

  function getPaymentModeButtons() {
    return paymentModeOptions ? Array.from(paymentModeOptions.querySelectorAll('.payment-mode-btn')) : [];
  }

  function resolveSelectedPaymentMethod(currencyCode, preferredMethodId) {
    const methods = getPaymentMethodsForCurrency(currencyCode);
    if (!methods.length) {
      return null;
    }
    if (preferredMethodId !== undefined && preferredMethodId !== null && String(preferredMethodId) !== '') {
      const matchedMethod = methods.find((method) => String(method.id) === String(preferredMethodId));
      if (matchedMethod) {
        return matchedMethod;
      }
    }
    return methods[0];
  }

  function paymentPointsOptionLabel(hasRule, requiredPoints) {
    return hasRule ? `Usar ${formatWinPointsAmount(requiredPoints)}` : 'Sin canje disponible';
  }

  function paymentOptionKey(mode, methodId = '') {
    if (mode === 'points') {
      return 'points';
    }
    if (mode === 'binance') {
      return 'binance';
    }
    return `money:${String(methodId || '')}`;
  }

  function shouldExpandSinglePaymentOption() {
    if (!activePaymentOrder) {
      return false;
    }

    const methods = getPaymentMethodsForCurrency(activePaymentOrder.currency);
    const usableOptionCount = methods.length + (activePaymentOrder.canUseBinance ? 1 : 0) + (activePaymentOrder.canUsePoints ? 1 : 0);
    return usableOptionCount === 1;
  }

  function paymentMethodMetaLabel(method) {
    const currencyLabel = `${method.moneda_nombre || ''}${method.moneda_clave ? ` (${method.moneda_clave})` : ''}`.trim();
    return currencyLabel || 'Método de pago';
  }

  function paymentMethodAccordionMarkup(method) {
    const methodName = escapePaymentHtml(method.nombre || 'Método de pago');
    const methodMeta = escapePaymentHtml(paymentMethodMetaLabel(method));
    const methodDetails = escapePaymentHtml(method.datos || '').replace(/\n/g, '<br>');
    return `<div class="payment-mode-item-card"><div class="payment-mode-item-card-title">Datos para ${methodName}</div><div class="payment-mode-item-currency">${methodMeta}</div><div class="payment-mode-item-details">${methodDetails}</div></div>`;
  }

  function paymentPointsAccordionMarkup() {
    const copy = escapePaymentHtml(String(activePaymentOrder && activePaymentOrder.pointsCopy ? activePaymentOrder.pointsCopy : ''));
    const message = escapePaymentHtml(String(activePaymentOrder && activePaymentOrder.pointsMessage ? activePaymentOrder.pointsMessage : '')).replace(/\n/g, '<br>');
    return `<div class="payment-mode-item-card payment-mode-item-card-points"><div class="payment-mode-item-card-title">Canje con premios</div><div class="payment-mode-item-details">${copy}</div><div class="payment-win-points-message mt-3">${message}</div></div>`;
  }

  function paymentBinanceAccordionMarkup() {
    const binanceMoney = resolveBinanceDisplayMoney(activePaymentOrder && activePaymentOrder.pack ? activePaymentOrder.pack : null);
    const totalText = escapePaymentHtml(String((binanceMoney && binanceMoney.text) || ''));
    const totalMarkup = totalText !== ''
      ? `<div class="payment-mode-item-currency">Total estimado en Binance Pay: ${totalText}</div>`
      : '';
    return `<div class="payment-mode-item-card payment-mode-item-card-points"><div class="payment-mode-item-card-title">${escapePaymentHtml(binancePayButtonLabel)}</div>${totalMarkup}<div class="payment-mode-item-details">Paga de forma segura desde CoinPal usando tu cuenta de Binance Pay. Abriremos el checkout externo y esta ventana seguirá monitoreando la confirmación automáticamente.</div></div>`;
  }

  function renderPaymentModeOptions() {
    if (!paymentModeOptions) {
      return;
    }

    if (!activePaymentOrder || !paymentWinPointsCard || paymentWinPointsCard.classList.contains('d-none')) {
      paymentModeOptions.innerHTML = '';
      return;
    }

    const methods = getPaymentMethodsForCurrency(activePaymentOrder.currency);
    const requiredPoints = Number(activePaymentOrder.pointsRequired || 0);
    const hasRule = !!(activePaymentOrder.pack && activePaymentOrder.pack.redeemActive && requiredPoints > 0);
    const showPointsOption = !!(winPointsState.enabled && winPointsState.loggedIn && hasRule);
    const buttonsHtml = methods.map((method) => {
      const methodId = escapePaymentHtml(String(method.id));
      const methodName = escapePaymentHtml(method.nombre || 'Método');
      const methodMeta = escapePaymentHtml(paymentMethodMetaLabel(method));
      return `<div class="payment-mode-item" data-payment-option="money" data-method-id="${methodId}"><button type="button" class="payment-mode-btn" data-payment-option="money" data-method-id="${methodId}" aria-expanded="false"><span class="payment-mode-btn-main"><span class="payment-mode-btn-radio" aria-hidden="true"></span><span class="payment-mode-btn-text"><span class="payment-mode-btn-title">${methodName}</span><span class="payment-mode-btn-meta">${methodMeta}</span></span></span><span class="payment-mode-btn-caret" aria-hidden="true"></span></button><div class="payment-mode-item-body"><div class="payment-mode-item-body-inner">${paymentMethodAccordionMarkup(method)}</div></div></div>`;
    }).join('');
    const binanceHtml = activePaymentOrder.canUseBinance
      ? `<div class="payment-mode-item" data-payment-option="binance"><button type="button" class="payment-mode-btn" data-payment-option="binance" aria-expanded="false"><span class="payment-mode-btn-main"><span class="payment-mode-btn-radio" aria-hidden="true"></span><span class="payment-mode-btn-text"><span class="payment-mode-btn-title">${escapePaymentHtml(binancePayButtonLabel)}</span><span class="payment-mode-btn-meta">Checkout externo seguro con CoinPal</span></span></span><span class="payment-mode-btn-caret" aria-hidden="true"></span></button><div class="payment-mode-item-body"><div class="payment-mode-item-body-inner">${paymentBinanceAccordionMarkup()}</div></div></div>`
      : '';
    const pointsMeta = escapePaymentHtml(formatWinPointsAmount(winPointsState.balance || 0));
    const pointsHtml = `<div class="payment-mode-item" data-payment-option="points"><button type="button" class="payment-mode-btn" data-payment-option="points" aria-expanded="false"><span class="payment-mode-btn-main"><span class="payment-mode-btn-radio" aria-hidden="true"></span><span class="payment-mode-btn-text"><span class="payment-mode-btn-title">${escapePaymentHtml(paymentPointsOptionLabel(hasRule, requiredPoints))}</span><span class="payment-mode-btn-meta">Saldo disponible: ${pointsMeta}</span></span></span><span class="payment-mode-btn-caret" aria-hidden="true"></span></button><div class="payment-mode-item-body"><div class="payment-mode-item-body-inner">${paymentPointsAccordionMarkup()}</div></div></div>`;

    paymentModeOptions.innerHTML = `${buttonsHtml}${binanceHtml}${showPointsOption ? pointsHtml : ''}`;
    getPaymentModeButtons().forEach((button) => {
      button.addEventListener('click', function() {
        const buttonMode = button.dataset.paymentOption === 'points'
          ? 'points'
          : (button.dataset.paymentOption === 'binance' ? 'binance' : 'money');
        const methodId = buttonMode === 'money' ? button.dataset.methodId || '' : '';
        setActivePaymentMode(buttonMode, methodId, { expandSelected: true });
      });
    });
  }

  function setActivePaymentMode(mode, preferredMethodId, options = {}) {
    if (!activePaymentOrder) {
      return;
    }

    const selectedMethod = resolveSelectedPaymentMethod(activePaymentOrder.currency, preferredMethodId || activePaymentOrder.selectedMethodId);
    const canUseMoney = !!selectedMethod && !!activePaymentOrder.canUseMoney;
    const canUseBinance = !!activePaymentOrder.canUseBinance;
    const canUsePoints = !!activePaymentOrder.canUsePoints;
    let nextMode = mode === 'points' ? 'points' : (mode === 'binance' ? 'binance' : 'money');

    activePaymentOrder.selectedMethodId = selectedMethod ? String(selectedMethod.id) : '';

    if (nextMode === 'points' && !canUsePoints) {
      nextMode = canUseMoney ? 'money' : (canUseBinance ? 'binance' : 'points');
    }
    if (nextMode === 'binance' && !canUseBinance) {
      nextMode = canUseMoney ? 'money' : (canUsePoints ? 'points' : 'binance');
    }
    if (nextMode === 'money' && !canUseMoney) {
      nextMode = canUseBinance ? 'binance' : (canUsePoints ? 'points' : 'money');
    }

    activePaymentOrder.paymentMode = nextMode;
    const usingPoints = nextMode === 'points';
    const usingBinance = nextMode === 'binance';
    const selectedOptionKey = paymentOptionKey(nextMode, selectedMethod ? selectedMethod.id : '');

    if (Object.prototype.hasOwnProperty.call(options, 'expandSelected')) {
      activePaymentOrder.expandedPaymentOptionKey = options.expandSelected ? selectedOptionKey : '';
    } else if (activePaymentOrder.expandedPaymentOptionKey === undefined) {
      activePaymentOrder.expandedPaymentOptionKey = '';
    }

    if (paymentMethodSelect) {
      paymentMethodSelect.value = selectedMethod ? String(selectedMethod.id) : '';
    }
    renderPaymentMethodDetails(usingBinance ? null : (selectedMethod || null));
    if (paymentMethodCard) {
      const usingAccordion = paymentWinPointsCard && !paymentWinPointsCard.classList.contains('d-none');
      paymentMethodCard.classList.toggle('d-none', usingAccordion);
    }
    getPaymentModeButtons().forEach((button) => {
      const buttonMode = button.dataset.paymentOption === 'points'
        ? 'points'
        : (button.dataset.paymentOption === 'binance' ? 'binance' : 'money');
      const buttonMethodId = button.dataset.methodId || '';
      const isSelected = buttonMode === 'points'
        ? usingPoints
        : (buttonMode === 'binance'
          ? usingBinance
          : (!usingPoints && !usingBinance && String(buttonMethodId) === String(activePaymentOrder.selectedMethodId || '')));
      const isExpanded = paymentOptionKey(buttonMode, buttonMethodId) === String(activePaymentOrder.expandedPaymentOptionKey || '');
      const buttonItem = button.closest('.payment-mode-item');
      button.classList.toggle('is-active', isSelected);
      if (buttonItem) {
        buttonItem.classList.toggle('is-selected', isSelected);
        buttonItem.classList.toggle('is-expanded', isExpanded);
      }
      button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
      button.disabled = buttonMode === 'points' ? !canUsePoints : (buttonMode === 'binance' ? !canUseBinance : !canUseMoney);
    });
    if (paymentReferenceGroup) {
      paymentReferenceGroup.classList.toggle('d-none', usingPoints || usingBinance);
    }
    if (paymentPhoneGroup) {
      paymentPhoneGroup.classList.toggle('d-none', usingPoints || usingBinance);
    }
    if (paymentMoneyPanel) {
      paymentMoneyPanel.classList.toggle('is-active', !usingPoints && (canUseMoney || canUseBinance));
    }
    if (paymentSubmitButton) {
      paymentSubmitButton.textContent = usingPoints
        ? `Canjear ${formatWinPointsAmount(activePaymentOrder.pointsRequired || 0)}`
        : (usingBinance ? 'Continuar con Binance Pay' : defaultPaymentSubmitButtonLabel);
    }
  }

  function renderWinPointsPaymentState(pack, currentMethod) {
    if (!paymentWinPointsCard) {
      return;
    }

    if (!pack || !activePaymentOrder) {
      paymentWinPointsCard.classList.add('d-none');
      return;
    }

    const quantity = normalizeOrderQuantity(activePaymentOrder.purchaseQuantity || pack.purchaseQuantity || 1);
    const rewardPoints = getPackRewardPoints(pack, quantity);
    const requiredPoints = getPackRequiredPoints(pack, quantity);
    const hasRule = !!pack.redeemActive && requiredPoints > 0;
    const currentBalance = Number(winPointsState.balance || 0);
    const canUsePoints = hasRule && currentBalance >= requiredPoints;
    const canUseBinance = canUseBinanceCheckout(pack);
    const showRewardsState = !!(winPointsState.enabled && winPointsState.loggedIn);

    activePaymentOrder.canUseMoney = Boolean(currentMethod);
    activePaymentOrder.canUseBinance = canUseBinance;
    activePaymentOrder.canUsePoints = showRewardsState ? canUsePoints : false;
    activePaymentOrder.pointsRequired = showRewardsState ? requiredPoints : 0;
    activePaymentOrder.purchaseQuantity = quantity;
    activePaymentOrder.selectedMethodId = currentMethod ? String(currentMethod.id) : '';
    activePaymentOrder.expandedPaymentOptionKey = shouldExpandSinglePaymentOption()
      ? paymentOptionKey(
        activePaymentOrder.canUseMoney ? 'money' : (activePaymentOrder.canUsePoints ? 'points' : 'binance'),
        activePaymentOrder.selectedMethodId
      )
      : '';

    if (!currentMethod && !canUseBinance) {
      paymentWinPointsCard.classList.add('d-none');
      if (paymentMethodCard) {
        paymentMethodCard.classList.remove('d-none');
      }
      return;
    }

    paymentWinPointsCard.classList.remove('d-none');

    if (showRewardsState) {
      if (paymentWinPointsTitle) {
        paymentWinPointsTitle.textContent = 'Premios disponibles';
      }
      if (paymentWinPointsCopy) {
        paymentWinPointsCopy.textContent = canUseBinance
          ? 'Elige si deseas completar esta orden con transferencia, Binance Pay o con tus premios acumulados.'
          : 'Elige si deseas completar esta orden con transferencia o con tus premios acumulados.';
      }
      paymentWinPointsBalance.textContent = formatWinPointsAmount(currentBalance);
      paymentWinPointsBalance.classList.remove('d-none');
    } else {
      if (paymentWinPointsTitle) {
        paymentWinPointsTitle.textContent = 'Metodos de pago disponibles';
      }
      if (paymentWinPointsCopy) {
        paymentWinPointsCopy.textContent = canUseBinance
          ? 'Elige si deseas completar esta orden manualmente o con Binance Pay.'
          : 'Elige el metodo con el que deseas completar esta orden.';
      }
      paymentWinPointsBalance.textContent = '';
      paymentWinPointsBalance.classList.add('d-none');
    }

    if (showRewardsState && rewardPoints > 0) {
      activePaymentOrder.pointsCopy = quantity > 1
        ? `Esta compra te entrega +${rewardPoints} ${winPointsState.name} cuando las ${quantity} recargas queden enviadas.`
        : `Este paquete te entrega +${rewardPoints} ${winPointsState.name} cuando la recarga quede enviada.`;
    } else {
      activePaymentOrder.pointsCopy = showRewardsState
        ? `Tu saldo disponible se puede usar en los paquetes que tengan canje activo.`
        : '';
    }

    if (showRewardsState && hasRule && canUsePoints) {
      activePaymentOrder.pointsMessage = quantity > 1
        ? `Puedes canjear ${quantity} recargas usando ${formatWinPointsAmount(requiredPoints)}.`
        : `Puedes canjear este paquete usando ${formatWinPointsAmount(requiredPoints)}.`;
    } else if (showRewardsState && hasRule) {
      activePaymentOrder.pointsMessage = `Necesitas ${formatWinPointsAmount(requiredPoints)} para canjear este paquete. Tu saldo actual es ${formatWinPointsAmount(currentBalance)}.`;
    } else {
      activePaymentOrder.pointsMessage = showRewardsState
        ? 'Este paquete no tiene una regla activa de canje por premios. Puedes pagar normal y seguir acumulando puntos.'
        : '';
    }

    if (paymentMethodSelectWrap) {
      paymentMethodSelectWrap.classList.add('d-none');
    }
    renderPaymentModeOptions();
    setActivePaymentMode(
      showRewardsState
        ? (activePaymentOrder.canUseMoney ? 'money' : (activePaymentOrder.canUsePoints ? 'points' : 'binance'))
        : (activePaymentOrder.canUseMoney ? 'money' : 'binance'),
      activePaymentOrder.selectedMethodId,
      { expandSelected: shouldExpandSinglePaymentOption() }
    );
  }

  function clearFieldValidation(field) {
    if (!field || !field.name) {
      return;
    }

    const errorElem = document.getElementById(field.name + '-error');
    if (errorElem) {
      errorElem.remove();
    }
  }

  function normalizeFieldOptions(fieldConfig) {
    const options = fieldConfig && Array.isArray(fieldConfig.options) ? fieldConfig.options : [];
    return options
      .map((option) => {
        if (option && typeof option === 'object') {
          return {
            value: String(option.value || '').trim(),
            label: String(option.label || option.value || '').trim()
          };
        }

        const normalized = String(option || '').trim();
        return { value: normalized, label: normalized };
      })
      .filter((option) => option.value !== '');
  }

  function sanitizeFieldPlaceholder(placeholder, fallback = 'Ingresa el dato') {
    const normalized = String(placeholder || '')
      .replace(/\bAPI\b/gi, ' ')
      .replace(/\s{2,}/g, ' ')
      .trim();

    return normalized || fallback;
  }

  function getPlayerVerificationDefaultFields() {
    if (!playerVerificationConfig || !Array.isArray(playerVerificationConfig.defaultFields)) {
      return [];
    }

    return playerVerificationConfig.defaultFields;
  }

  function createDynamicFieldControl(fieldConfig, fieldNamePrefix) {
    const options = normalizeFieldOptions(fieldConfig);
    const controlName = `${fieldNamePrefix}${fieldConfig.name || 'extra'}`;
    const hasOptions = options.length > 0;
    const control = document.createElement(hasOptions ? 'select' : 'input');

    if (hasOptions) {
      control.innerHTML = `<option value="">Selecciona una opcion</option>`;
      options.forEach((option) => {
        const optionElement = document.createElement('option');
        optionElement.value = option.value;
        optionElement.textContent = option.label || option.value;
        control.appendChild(optionElement);
      });
    } else {
      control.type = 'text';
      control.placeholder = sanitizeFieldPlaceholder(fieldConfig.placeholder, 'Ingresa el dato');
      control.inputMode = fieldConfig.inputMode || 'text';
      control.maxLength = Number(fieldConfig.maxLength || 180);
    }

    control.name = controlName;
    control.dataset.apiField = fieldConfig.name || '';
    control.className = hasOptions ? 'form-select bg-dark text-info border-info' : 'form-control bg-dark text-info border-info';
    control.required = true;

    return control;
  }

  function syncPrimaryControl(fieldConfig) {
    if (!playerPrimaryField || !playerPrimaryInput) {
      return;
    }

    const normalizedConfig = fieldConfig || defaultPrimaryField;
    const options = normalizeFieldOptions(normalizedConfig);
    const needsSelect = options.length > 0;
    const currentIsSelect = playerPrimaryInput.tagName === 'SELECT';

    if (needsSelect !== currentIsSelect) {
      const replacement = createDynamicFieldControl(normalizedConfig, 'user_');
      replacement.id = 'order-user-id';
      replacement.value = '';
      playerPrimaryInput.replaceWith(replacement);
      playerPrimaryInput = replacement;
    }

    playerPrimaryInput.name = 'user_id';
    playerPrimaryInput.dataset.apiField = normalizedConfig.name || defaultPrimaryField.name;
    playerPrimaryInput.required = true;
    if (playerPrimaryInput.tagName === 'SELECT') {
      playerPrimaryInput.className = 'form-select bg-dark text-info border-info';
    } else {
      playerPrimaryInput.className = 'form-control bg-dark text-info border-info';
      playerPrimaryInput.placeholder = sanitizeFieldPlaceholder(normalizedConfig.placeholder, defaultPrimaryField.placeholder);
      playerPrimaryInput.inputMode = normalizedConfig.inputMode || 'text';
      playerPrimaryInput.maxLength = Number(normalizedConfig.maxLength || defaultPrimaryField.maxLength);
    }
  }

  function renderPlayerFields(pack) {
    const existingValues = collectPlayerFields();
    const packRequiredFields = pack && Array.isArray(pack.requiredFields) ? pack.requiredFields : [];
    const requiredFields = packRequiredFields.length ? packRequiredFields : getPlayerVerificationDefaultFields();
    const shouldShowPrimaryField = !isAccountSalePack(pack) && (!gameUsesCatalogApi || !pack || requiredFields.length > 0);
    const primaryConfig = requiredFields[0] || defaultPrimaryField;
    setAccountSaleNote(pack);

    if (playerPrimaryField && playerPrimaryInput && playerPrimaryLabel) {
      syncPrimaryControl(primaryConfig);
      playerPrimaryField.classList.toggle('d-none', !shouldShowPrimaryField);
      playerPrimaryLabel.textContent = primaryConfig.label || defaultPrimaryField.label;
      playerPrimaryInput.dataset.apiField = primaryConfig.name || defaultPrimaryField.name;
      playerPrimaryInput.required = shouldShowPrimaryField;

      const primaryFieldName = String(primaryConfig.name || defaultPrimaryField.name);
      if (shouldShowPrimaryField && existingValues[primaryFieldName] && playerPrimaryInput.value.trim() === '') {
        playerPrimaryInput.value = existingValues[primaryFieldName];
      } else if (shouldShowPrimaryField && primaryFieldName === String(defaultPrimaryField.name) && defaultOrderUserIdentifier !== '' && playerPrimaryInput.value.trim() === '') {
        playerPrimaryInput.value = defaultOrderUserIdentifier;
      }

      if (!shouldShowPrimaryField) {
        playerPrimaryInput.value = '';
        clearFieldValidation(playerPrimaryInput);
      }
    }

    if (!extraPlayerFields) {
      return;
    }

    extraPlayerFields.innerHTML = '';
    requiredFields.slice(1).forEach((fieldConfig) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'col-12';

      const label = document.createElement('label');
      label.className = 'form-label text-info';
      label.textContent = fieldConfig.label || 'Dato adicional';

      const input = createDynamicFieldControl(fieldConfig, 'player_field_');
      input.value = existingValues[fieldConfig.name || ''] || '';

      wrapper.appendChild(label);
      wrapper.appendChild(input);
      extraPlayerFields.appendChild(wrapper);
    });

    syncPlayerVerificationUi();
  }

  function collectPlayerFields() {
    const fields = {};

    if (playerPrimaryField && !playerPrimaryField.classList.contains('d-none') && playerPrimaryInput) {
      const fieldName = String(playerPrimaryInput.dataset.apiField || defaultPrimaryField.name);
      const fieldValue = playerPrimaryInput.value.trim();
      if (fieldValue !== '') {
        fields[fieldName] = fieldValue;
      }
    }

    if (extraPlayerFields) {
      extraPlayerFields.querySelectorAll('[data-api-field]').forEach((input) => {
        const fieldName = String(input.dataset.apiField || '');
        const fieldValue = input.value.trim();
        if (fieldName !== '' && fieldValue !== '') {
          fields[fieldName] = fieldValue;
        }
      });
    }

    return fields;
  }

  function buildPlayerVerificationPayload() {
    const userIdentifier = playerPrimaryInput ? playerPrimaryInput.value.trim() : '';
    const playerFields = collectPlayerFields();

    return {
      userIdentifier,
      playerFields,
      signature: JSON.stringify({
        gameKey: playerVerificationConfig ? playerVerificationConfig.gameKey : '',
        userIdentifier,
        playerFields,
      }),
    };
  }

  function getPlayerVerificationZoneValue(playerFields) {
    const fields = playerFields && typeof playerFields === 'object' ? playerFields : {};
    const candidates = ['input2', 'zone_id', 'zoneid', 'zone', 'server_id', 'serverid', 'server'];

    for (const candidate of candidates) {
      if (typeof fields[candidate] === 'string' && fields[candidate].trim() !== '') {
        return fields[candidate].trim();
      }
    }

    const extraValue = Object.entries(fields)
      .filter(([fieldName, fieldValue]) => String(fieldName || '') !== String(playerPrimaryInput ? playerPrimaryInput.dataset.apiField || '' : '') && String(fieldValue || '').trim() !== '')
      .map(([, fieldValue]) => String(fieldValue || '').trim())[0];

    return extraValue || '';
  }

  function hasPlayerVerificationInputs(payload) {
    if (!playerVerificationConfig) {
      return false;
    }

    const currentPayload = payload || buildPlayerVerificationPayload();
    if (currentPayload.userIdentifier === '') {
      return false;
    }

    if (playerVerificationConfig.requiresZone) {
      return getPlayerVerificationZoneValue(currentPayload.playerFields) !== '';
    }

    return true;
  }

  function clearPlayerVerificationFeedback() {
    if (!playerVerificationFeedback) {
      return;
    }

    playerVerificationFeedback.className = 'd-none mt-2';
    playerVerificationFeedback.textContent = '';
  }

  function setPlayerVerificationFeedback(type, message) {
    if (!playerVerificationFeedback) {
      return;
    }

    if (!message) {
      clearPlayerVerificationFeedback();
      return;
    }

    const alertType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
    playerVerificationFeedback.className = `alert alert-${alertType} py-2 px-3 mt-2 mb-0 small fw-semibold`;
    playerVerificationFeedback.textContent = message;
  }

  function clearPlayerVerificationAutoTimer() {
    if (playerVerificationAutoTimer) {
      window.clearTimeout(playerVerificationAutoTimer);
      playerVerificationAutoTimer = null;
    }
  }

  function invalidatePlayerVerificationRequests() {
    playerVerificationRequestSeq += 1;
    playerVerificationPendingSignature = '';
    clearPlayerVerificationAutoTimer();
  }

  function resetPlayerVerificationState(clearFeedback = true) {
    clearPlayerVerificationAutoTimer();
    playerVerificationPendingSignature = '';
    playerVerificationState = {
      verified: false,
      playerName: '',
      signature: '',
      pending: false,
      serverUnavailable: false,
    };

    if (clearFeedback) {
      clearPlayerVerificationFeedback();
    }
  }

  function setPlayerVerificationUnavailableState(signature, message) {
    clearPlayerVerificationAutoTimer();
    playerVerificationPendingSignature = '';
    playerVerificationState = {
      verified: false,
      playerName: '',
      signature: signature,
      pending: false,
      serverUnavailable: true,
    };

    const baseMessage = String(message || 'No se pudo verificar el jugador en este momento.').trim();
    setPlayerVerificationFeedback('info', `${baseMessage} Puedes continuar con la recarga normal.`);
  }

  function shouldAllowCheckoutOnVerificationFailure(status, message, httpStatus) {
    const normalizedStatus = String(status || '').trim().toLowerCase();
    const normalizedMessage = String(message || '').trim().toLowerCase();
    const numericHttpStatus = Number(httpStatus || 0);

    if (normalizedStatus === 'unavailable' || numericHttpStatus >= 500) {
      return true;
    }

    const temporaryFailureSnippets = [
      'no player data found for uid',
      'service unavailable',
      'temporarily unavailable',
      'internal server error',
      'gateway timeout',
      'bad gateway',
      'request timeout',
      'try again later',
      'connection refused',
      'connection reset',
      'upstream',
      'timeout',
    ];

    return temporaryFailureSnippets.some((snippet) => normalizedMessage.includes(snippet));
  }

  function requiresVerifiedPlayerForCheckout() {
    if (isAccountSalePack(activePack)) {
      return false;
    }

    return Boolean(
      playerVerificationConfig
      && (playerVerificationState.pending || (!playerVerificationState.verified && !playerVerificationState.serverUnavailable))
    );
  }

  function syncPlayerVerificationUi() {
    if (!verifyPlayerButton) {
      return;
    }

    if (!playerVerificationConfig || isAccountSalePack(activePack)) {
      verifyPlayerButton.classList.add('d-none');
      return;
    }

    verifyPlayerButton.classList.remove('d-none');
    verifyPlayerButton.disabled = playerVerificationState.pending || !hasPlayerVerificationInputs();
    verifyPlayerButton.textContent = playerVerificationState.pending
      ? 'Verificando...'
      : (playerVerificationConfig.buttonLabel || 'Verificar nombre del jugador');
  }

  function handlePlayerVerificationFieldChange() {
    if (!playerVerificationConfig || isAccountSalePack(activePack)) {
      invalidatePlayerVerificationRequests();
      resetPlayerVerificationState();
      syncPlayerVerificationUi();
      return;
    }

    const payload = buildPlayerVerificationPayload();
    const hasInputs = hasPlayerVerificationInputs(payload);
    const currentSignature = String(payload.signature || '');

    if (!hasInputs) {
      invalidatePlayerVerificationRequests();
      resetPlayerVerificationState();
      syncPlayerVerificationUi();
      return;
    }

    if (playerVerificationPendingSignature !== '' && playerVerificationPendingSignature !== currentSignature) {
      playerVerificationRequestSeq += 1;
      playerVerificationPendingSignature = '';
    }

    if (playerVerificationState.signature !== '' && playerVerificationState.signature !== currentSignature) {
      resetPlayerVerificationState();
    }

    syncPlayerVerificationUi();

    const alreadyHandledCurrentSignature = currentSignature !== '' && (
      (playerVerificationState.signature === currentSignature && (playerVerificationState.verified || playerVerificationState.serverUnavailable))
      || playerVerificationPendingSignature === currentSignature
    );

    if (alreadyHandledCurrentSignature) {
      return;
    }

    clearPlayerVerificationAutoTimer();
    playerVerificationAutoTimer = window.setTimeout(() => {
      verifyCurrentPlayer({ autoTriggered: true, expectedSignature: currentSignature });
    }, 450);
  }

  async function verifyCurrentPlayer(options = {}) {
    if (!playerVerificationConfig || isAccountSalePack(activePack)) {
      return;
    }

    clearPlayerVerificationAutoTimer();

    const payload = buildPlayerVerificationPayload();
    if (options.expectedSignature && payload.signature !== options.expectedSignature) {
      return;
    }
    if (!hasPlayerVerificationInputs(payload)) {
      setPlayerVerificationFeedback('danger', playerVerificationConfig.requiresZone
        ? 'Debes ingresar el ID del jugador y la Zona ID para verificar.'
        : 'Debes ingresar el ID del jugador para verificar.');
      updateButtonState();
      return;
    }

    const requestId = ++playerVerificationRequestSeq;
    playerVerificationPendingSignature = payload.signature;

    playerVerificationState.pending = true;
    playerVerificationState.serverUnavailable = false;
    syncPlayerVerificationUi();
    setPlayerVerificationFeedback('info', 'Verificando nombre del jugador...');
    updateButtonState();

    try {
      const requestBody = new URLSearchParams();
      requestBody.set('game_id', "<?= (string) ($game['id'] ?? '') ?>");
      requestBody.set('user_identifier', payload.userIdentifier);
      requestBody.set('player_fields_json', JSON.stringify(payload.playerFields));

      const response = await fetch(buildAppUrl('/api/verify_player.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: requestBody.toString(),
      });

      let data = null;
      try {
        data = await response.json();
      } catch (error) {
        data = null;
      }

      if (requestId !== playerVerificationRequestSeq) {
        return;
      }

      if (response.ok && data && data.ok) {
        playerVerificationState = {
          verified: true,
          playerName: String(data.player_name || ''),
          signature: payload.signature,
          pending: false,
          serverUnavailable: false,
        };
        setPlayerVerificationFeedback('success', String(data.message || 'Jugador encontrado.'));
      } else {
        const verificationStatus = String((data && data.status) || '').toLowerCase();
        const verificationMessage = String((data && data.message) || 'No se pudo verificar el jugador.');
        if (shouldAllowCheckoutOnVerificationFailure(verificationStatus, verificationMessage, response.status)) {
          setPlayerVerificationUnavailableState(payload.signature, verificationMessage);
        } else {
          resetPlayerVerificationState(false);
          setPlayerVerificationFeedback('danger', verificationMessage);
        }
      }
    } catch (error) {
      if (requestId !== playerVerificationRequestSeq) {
        return;
      }
      setPlayerVerificationUnavailableState(payload.signature, 'No se pudo verificar el jugador en este momento.');
    } finally {
      if (requestId !== playerVerificationRequestSeq) {
        return;
      }
      playerVerificationPendingSignature = '';
      playerVerificationState.pending = false;
      syncPlayerVerificationUi();
      updateButtonState();
    }
  }

  function scrollToOrderForm() {
    if (!orderForm) {
      return;
    }

    window.setTimeout(() => {
      orderForm.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
  }

  function syncOverlayState() {
    const overlayVisible = Boolean(document.querySelector('.app-overlay-modal.is-visible'));
    document.body.classList.toggle('overlay-open', overlayVisible);
    document.querySelectorAll('.floating-social-stack').forEach((element) => {
      if (!(element instanceof HTMLElement)) {
        return;
      }
      element.style.opacity = overlayVisible ? '0' : '';
      element.style.visibility = overlayVisible ? 'hidden' : '';
      element.style.pointerEvents = overlayVisible ? 'none' : '';
    });
  }

  function setOverlayVisible(modalElement, visible) {
    if (!modalElement) {
      return;
    }
    if (visible) {
      lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    } else if (modalElement.contains(document.activeElement) && document.activeElement instanceof HTMLElement) {
      document.activeElement.blur();
    }
    modalElement.classList.toggle('show', visible);
    modalElement.classList.toggle('is-visible', visible);
    modalElement.setAttribute('aria-hidden', visible ? 'false' : 'true');
    syncOverlayState();
    if (visible) {
      const autofocusTarget = modalElement.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (autofocusTarget instanceof HTMLElement) {
        setTimeout(() => autofocusTarget.focus(), 0);
      }
    } else if (lastFocusedElement instanceof HTMLElement && document.body.contains(lastFocusedElement)) {
      setTimeout(() => {
        if (lastFocusedElement instanceof HTMLElement && document.body.contains(lastFocusedElement)) {
          lastFocusedElement.focus();
        }
        lastFocusedElement = null;
      }, 0);
    } else {
      lastFocusedElement = null;
    }
  }

  function syncGameEntryWindowState() {
    if (!gameEntryWindowCheckbox || !gameEntryWindowContinueButton) {
      return;
    }

    if (gameEntryWindowConfirmation) {
      gameEntryWindowConfirmation.classList.toggle('is-checked', !!gameEntryWindowCheckbox.checked);
    }
    gameEntryWindowContinueButton.disabled = !gameEntryWindowCheckbox.checked;
  }

  function setGameEntryWindowChecked(checked) {
    if (!gameEntryWindowCheckbox) {
      return;
    }

    gameEntryWindowCheckbox.checked = !!checked;
    syncGameEntryWindowState();
  }

  window.toggleGameEntryWindowConfirmation = function (forceChecked) {
    if (!gameEntryWindowCheckbox) {
      return;
    }

    if (typeof forceChecked === 'boolean') {
      setGameEntryWindowChecked(forceChecked);
      return;
    }

    setGameEntryWindowChecked(!gameEntryWindowCheckbox.checked);
  };

  function acceptGameEntryWindow() {
    if (!gameEntryWindowCheckbox || !gameEntryWindowCheckbox.checked) {
      syncGameEntryWindowState();
      return false;
    }

    gameEntryWindowAccepted = true;
    setOverlayVisible(gameEntryWindowModal, false);
    if (gameEntryWindowContinueButton instanceof HTMLElement) {
      gameEntryWindowContinueButton.blur();
    }
    updateButtonState();
    return false;
  }

  window.acceptGameEntryWindow = acceptGameEntryWindow;

  function openGameEntryWindowIfNeeded() {
    if (!gameEntryWindowEnabled || !gameEntryWindowModal) {
      gameEntryWindowAccepted = true;
      updateButtonState();
      return;
    }

    gameEntryWindowAccepted = false;
    setGameEntryWindowChecked(false);
    setOverlayVisible(gameEntryWindowModal, true);
    updateButtonState();
  }

  if (gameEntryWindowCheckbox) {
    gameEntryWindowCheckbox.addEventListener('change', function () {
      syncGameEntryWindowState();
    });
  }

  if (gameEntryWindowContinueButton) {
    gameEntryWindowContinueButton.addEventListener('click', function (event) {
      event.preventDefault();
      acceptGameEntryWindow();
    });
  }

  function keepPaymentFieldVisible(target) {
    if (!(target instanceof HTMLElement) || !paymentModal || !paymentModal.classList.contains('is-visible')) {
      return;
    }

    if (!paymentModal.contains(target) || window.innerWidth > 575.98) {
      return;
    }

    window.setTimeout(() => {
      target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    }, 220);
  }

  if (paymentModal) {
    paymentModal.addEventListener('focusin', (event) => {
      keepPaymentFieldVisible(event.target);
    });
  }

  function removeBuySpinner() {
    const spinner = document.getElementById('spinner-compra');
    if (spinner) {
      spinner.remove();
    }
  }

  function setLoadingModalContent(title, message, state = 'processing') {
    if (loadingModalTitle) {
      loadingModalTitle.textContent = title || 'Procesando pedido...';
    }
    if (loadingModalMessage) {
      loadingModalMessage.textContent = message || 'Espera un momento mientras completamos la operación.';
    }
    if (loadingModal && paymentWindowThemeEnabled) {
      loadingModal.setAttribute('data-payment-loading-state', state === 'sending' ? 'sending' : 'processing');
    }
  }

  function scrollPaymentModalToTop() {
    if (paymentModalContent) {
      paymentModalContent.scrollTop = 0;
    }
    if (paymentModal) {
      paymentModal.scrollTop = 0;
    }
  }

  function scrollPaymentSubmitIntoView() {
    if (!paymentSubmitButton) {
      return;
    }

    window.setTimeout(() => {
      paymentSubmitButton.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    }, 120);
  }

  function showPaymentStatusModal(title, message, type) {
    const normalizedType = type === 'success' || type === 'danger' ? type : 'info';
    const successExtraMessage = normalizedType === 'success'
      ? String(paymentSuccessContent.extraMessage || '').trim()
      : '';
    if (paymentStatusModalTitle) {
      const resolvedTitle = normalizedType === 'success'
        ? (String(paymentSuccessContent.title || '').trim() || title || 'Pago exitoso')
        : (title || 'Estado de la operación');
      paymentStatusModalTitle.textContent = resolvedTitle;
      paymentStatusModalTitle.classList.remove('text-info', 'text-success', 'text-danger');
      paymentStatusModalTitle.classList.add(normalizedType === 'success' ? 'text-success' : (normalizedType === 'danger' ? 'text-danger' : 'text-info'));
    }
    if (paymentStatusModalMessage) {
      paymentStatusModalMessage.textContent = message || 'Tu solicitud fue procesada.';
      paymentStatusModalMessage.classList.toggle('mb-2', successExtraMessage !== '');
      paymentStatusModalMessage.classList.toggle('mb-4', successExtraMessage === '');
    }
    if (paymentStatusModalExtraMessage) {
      if (successExtraMessage !== '') {
        paymentStatusModalExtraMessage.textContent = successExtraMessage;
        paymentStatusModalExtraMessage.classList.remove('d-none');
      } else {
        paymentStatusModalExtraMessage.textContent = '';
        paymentStatusModalExtraMessage.classList.add('d-none');
      }
    }
    if (paymentStatusModal && paymentWindowThemeEnabled) {
      paymentStatusModal.setAttribute('data-payment-status-state', normalizedType);
    }
    scrollPaymentModalToTop();
    setOverlayVisible(paymentStatusModal, true);
  }

  function clearPaymentStatusPolling() {
    if (paymentStatusPollTimer) {
      clearTimeout(paymentStatusPollTimer);
      paymentStatusPollTimer = null;
    }
    if (paymentStatusModalAccept) {
      paymentStatusModalAccept.disabled = false;
      paymentStatusModalAccept.textContent = defaultPaymentStatusAcceptLabel;
    }
  }

  function setPaymentStatusWaiting(isWaiting) {
    if (!paymentStatusModalAccept) {
      return;
    }
    paymentStatusModalAccept.disabled = !!isWaiting;
    paymentStatusModalAccept.textContent = isWaiting ? 'Esperando confirmación...' : defaultPaymentStatusAcceptLabel;
  }

  async function pollOrderResolution(reference, totalText, attempt = 1) {
    if (!activePaymentOrder || !activePaymentOrder.orderId) {
      clearPaymentStatusPolling();
      return;
    }

    const maxAttempts = 15;
    const pollDelayMs = 4000;
    const payload = new URLSearchParams();
    payload.set('action', 'order_status');
    payload.set('order_id', String(activePaymentOrder.orderId));
    payload.set('attempt_sync', '1');
    if (activePaymentOrder.email) {
      payload.set('email', String(activePaymentOrder.email));
    }

    try {
      const response = await fetch(buildAppUrl('/api/pedidos.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString(),
      });
      const data = await parseApiJsonResponse(response, 'No se pudo consultar el estado del pedido en este momento.');
      if (!response.ok || !data.ok) {
        throw new Error((data && data.message) ? data.message : 'No se pudo consultar el estado del pedido.');
      }

      const nextState = String((data && data.estado) || '').toLowerCase();
      const providerFlow = String((data && data.provider_flow) || '').toLowerCase();
      if (nextState === 'enviado') {
        clearPaymentStatusPolling();
        renderDeliveredCodes(data);
        const successMessage = getAccountSalePayload(data)
          ? 'Pago verificado y cuenta entregada correctamente.'
          : 'Pago verificado y recarga procesada correctamente.';
        setPaymentAlert(successMessage, 'success');
        setPaymentFormDisabled(true);
        clearPaymentTimer();
        setCancelOrderButtonMode('close');
        showPaymentStatusModal('Operación exitosa', successMessage, 'success');
        return;
      }

      if (nextState === 'cancelado') {
        clearPaymentStatusPolling();
        const cancelMessage = (data && data.provider_message) ? data.provider_message : 'El proveedor canceló la compra.';
        setPaymentAlert(cancelMessage, 'danger');
        renderProviderPaymentDetails(data, reference, totalText);
        setPaymentFormDisabled(true);
        clearPaymentTimer();
        setCancelOrderButtonMode('close');
        showPaymentStatusModal('No se pudo completar la operación', cancelMessage, 'danger');
        return;
      }

      if (nextState === 'pagado') {
        const paidMessage = (data && data.provider_message) ? data.provider_message : 'El pago fue confirmado correctamente.';
        const hasProviderDetails = extractPaymentReasons(data).length > 0;
        const isAcceptedFlow = providerFlow === 'accepted' || providerFlow === 'tracking';
        const requiresManualReview = providerFlow === 'manual_review' || providerFlow === 'inventory_shortage' || (!isAcceptedFlow && hasProviderDetails);

        if (!isAcceptedFlow) {
          clearPaymentStatusPolling();
          setPaymentAlert(paidMessage, requiresManualReview ? 'warning' : 'success');
          if (providerFlow === 'inventory_shortage') {
            renderProviderPaymentDetails(data, reference, totalText);
          } else {
            clearPaymentSupportUi();
          }
          setPaymentFormDisabled(true);
          clearPaymentTimer();
          setCancelOrderButtonMode('close');
          showPaymentStatusModal(requiresManualReview ? 'Revisión requerida' : 'Operación exitosa', paidMessage, requiresManualReview ? 'danger' : 'success');
          return;
        }
      }

      if (nextState === 'pagado') {
        const paidMessage = (data && data.provider_message) ? data.provider_message : 'El pago fue confirmado correctamente.';
        const hasProviderDetails = extractPaymentReasons(data).length > 0;
        const isAcceptedFlow = providerFlow === 'accepted' || providerFlow === 'tracking';
        const requiresManualReview = providerFlow === 'manual_review' || providerFlow === 'inventory_shortage' || (!isAcceptedFlow && hasProviderDetails);

        if (!isAcceptedFlow) {
          clearPaymentStatusPolling();
          setPaymentAlert(paidMessage, requiresManualReview ? 'warning' : 'success');
          if (providerFlow === 'inventory_shortage') {
            renderProviderPaymentDetails(data, reference, totalText);
          } else {
            clearPaymentSupportUi();
          }
          setPaymentFormDisabled(true);
          clearPaymentTimer();
          setCancelOrderButtonMode('close');
          showPaymentStatusModal(requiresManualReview ? 'Revisión requerida' : 'Operación exitosa', paidMessage, requiresManualReview ? 'danger' : 'success');
          return;
        }
      }

      if (nextState === 'pendiente' && providerFlow === 'binance_checkout') {
        const pendingMessage = (data && data.provider_message) ? data.provider_message : 'Completa el pago en Binance Pay para continuar con tu pedido.';
        setPaymentAlert(pendingMessage, 'info');
        renderBinancePaymentDetails(data, (data && data.provider_reference) ? data.provider_reference : reference, totalText);
      }

      if (attempt >= maxAttempts) {
        clearPaymentStatusPolling();
        if (providerFlow === 'binance_checkout') {
          setPaymentAlert('El checkout sigue pendiente. Puedes completar el pago y volver a esta ventana para continuar el seguimiento.', 'info');
          renderBinancePaymentDetails(data, (data && data.provider_reference) ? data.provider_reference : reference, totalText);
          showPaymentStatusModal('Pago pendiente en Binance Pay', 'El checkout sigue pendiente. Puedes dejar esta ventana abierta mientras completas el pago.', 'info');
        } else {
          setPaymentAlert('La compra sigue en proceso. Puedes dejar esta ventana y el sistema continuará el seguimiento.', 'info');
          renderProviderPaymentDetails(data, reference, totalText);
          showPaymentStatusModal('Compra en proceso', 'La compra sigue en proceso. El sistema continuará el seguimiento automático.', 'info');
        }
        return;
      }

      if (providerFlow === 'binance_checkout') {
        renderBinancePaymentDetails(data, (data && data.provider_reference) ? data.provider_reference : reference, totalText);
      } else {
        renderProviderPaymentDetails(data, reference, totalText);
      }
      setPaymentStatusWaiting(true);
      paymentStatusPollTimer = setTimeout(() => {
        pollOrderResolution(reference, totalText, attempt + 1);
      }, pollDelayMs);
    } catch (error) {
      if (attempt >= maxAttempts) {
        clearPaymentStatusPolling();
        return;
      }

      paymentStatusPollTimer = setTimeout(() => {
        pollOrderResolution(reference, totalText, attempt + 1);
      }, 5000);
    }
  }

  function showToast(msg, type) {
    const toast = document.createElement('div');
    toast.textContent = msg;
    toast.style.position = 'fixed';
    toast.style.top = '30px';
    toast.style.left = '50%';
    toast.style.transform = 'translateX(-50%)';
    toast.style.background = type === 'error' ? '#f87171' : '#34d399';
    toast.style.color = '#222';
    toast.style.padding = '12px 24px';
    toast.style.borderRadius = '8px';
    toast.style.fontWeight = 'bold';
    toast.style.zIndex = '9999';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2500);
  }

  async function parseApiJsonResponse(response, fallbackMessage) {
    const rawText = await response.text();
    const trimmed = String(rawText || '').trim();

    if (trimmed === '') {
      if (response.ok) {
        return {};
      }
      throw new Error(fallbackMessage || 'No se pudo procesar la respuesta del servidor.');
    }

    try {
      return JSON.parse(trimmed);
    } catch (error) {
      throw new Error(fallbackMessage || 'No se pudo procesar la respuesta del servidor.');
    }
  }

  function normalizeApiRequestErrorMessage(error, fallbackMessage) {
    const rawMessage = String((error && error.message) || '').trim();
    if (rawMessage === '') {
      return fallbackMessage;
    }

    const loweredMessage = rawMessage.toLowerCase();
    if (loweredMessage.includes('signature verification failed')) {
      return 'No se pudo validar Binance Pay con la configuración actual de la tienda. Intenta de nuevo o contacta al administrador.';
    }

    if (loweredMessage.includes('signature verification failed')) {
      return 'No se pudo validar Binance Pay con la configuración actual de la tienda. Intenta de nuevo o contacta al administrador.';
    }

    if (
      loweredMessage === 'failed to fetch'
      || loweredMessage.includes('unexpected token')
      || loweredMessage.includes('is not valid json')
      || loweredMessage.includes('<!doctype')
      || loweredMessage.includes('<html')
    ) {
      return fallbackMessage;
    }

    return rawMessage;
  }

  function clearPaymentTimer() {
    if (paymentTimerInterval) {
      clearInterval(paymentTimerInterval);
      paymentTimerInterval = null;
    }
  }

  function escapePaymentHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function paymentReferencePlaceholder(method) {
    const digits = Number(method && method.referencia_digitos ? method.referencia_digitos : 0);
    if (digits > 0) {
      return `Inserte los últimos ${digits} dígitos de su referencia`;
    }
    return 'Inserte su número de referencia para comprobar el pago';
  }

  function paymentReferenceHelpText(method) {
    const digits = Number(method && method.referencia_digitos ? method.referencia_digitos : 0);
    if (digits > 0) {
      return `Solo debes escribir los últimos ${digits} dígitos de la referencia bancaria.`;
    }
    return 'Inserte su número de referencia para comprobar el pago.';
  }

  function getPaymentMethodsForCurrency(currencyCode) {
    const preferredCurrency = String(currencyCode || '').toUpperCase();
    const methods = [];
    const seenIds = new Set();

    const appendMethods = (items) => {
      (Array.isArray(items) ? items : []).forEach((method) => {
        const methodId = String(method && method.id ? method.id : '');
        if (!methodId || seenIds.has(methodId)) {
          return;
        }
        seenIds.add(methodId);
        methods.push(method);
      });
    };

    if (preferredCurrency) {
      appendMethods(paymentMethodsByCurrency[preferredCurrency]);
    }

    Object.keys(paymentMethodsByCurrency).forEach((currencyKey) => {
      if (currencyKey === preferredCurrency) {
        return;
      }
      appendMethods(paymentMethodsByCurrency[currencyKey]);
    });

    return methods;
  }

  function setPaymentAlert(message, type) {
    if (!paymentModalAlert) {
      return;
    }
    if (!message) {
      paymentModalAlert.className = 'd-none alert mb-3';
      paymentModalAlert.textContent = '';
      return;
    }
    paymentModalAlert.textContent = message;
    paymentModalAlert.className = `alert mb-3 alert-${type || 'info'}`;
    scrollPaymentModalToTop();
  }

  function clearPaymentSupportUi() {
    clearPaymentStatusPolling();
    if (paymentModalReasons) {
      paymentModalReasons.className = 'd-none payment-reasons-card mb-3';
      paymentModalReasons.innerHTML = '';
      paymentModalReasons.removeAttribute('data-payment-difference-variant');
    }
    if (paymentModalActions) {
      paymentModalActions.className = 'd-none payment-support-actions mb-4';
      paymentModalActions.innerHTML = '';
      paymentModalActions.removeAttribute('data-payment-difference-variant');
    }
    if (paymentStatusModalReasons) {
      paymentStatusModalReasons.className = 'd-none payment-reasons-card mb-3 text-start';
      paymentStatusModalReasons.innerHTML = '';
      paymentStatusModalReasons.removeAttribute('data-payment-difference-variant');
    }
    if (paymentStatusModalActions) {
      paymentStatusModalActions.className = 'd-none payment-support-actions mb-4';
      paymentStatusModalActions.innerHTML = '';
      paymentStatusModalActions.removeAttribute('data-payment-difference-variant');
    }
    setPaymentStatusAcceptHidden(false);
    if (paymentSubmitButton) {
      paymentSubmitButton.textContent = defaultPaymentSubmitButtonLabel;
    }
  }

  if (paymentStatusModalAccept) {
    paymentStatusModalAccept.addEventListener('click', function() {
      clearPaymentStatusPolling();
      setOverlayVisible(paymentStatusModal, false);
      scrollPaymentSubmitIntoView();
    });
  }

  function buildPaymentSupportWhatsappUrl(orderId, reference, totalText) {
    if (!paymentSupportWhatsappBase) {
      return '';
    }

    const gameName = <?= json_encode((string) ($game['nombre'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const productName = paymentSummaryProduct ? paymentSummaryProduct.textContent : '';
    const userIdentifier = paymentSummaryUser ? paymentSummaryUser.textContent : '';
    const message = [
      'Hola, necesito apoyo para revisar manualmente un pago.',
      `Pedido: #${orderId || '-'}`,
      `Juego: ${gameName || '-'}`,
      `Producto: ${productName || '-'}`,
      `ID Jugador: ${userIdentifier || '-'}`,
      `Referencia: ${reference || '-'}`,
      `Monto: ${totalText || '-'}`,
      'Adjunto o enviaré captura del comprobante para revisión manual.'
    ].join('\n');
    return `${paymentSupportWhatsappBase}?text=${encodeURIComponent(message)}`;
  }

  function extractPaymentReasons(data) {
    const reasons = Array.isArray(data && data.reasons)
      ? data.reasons.map((reason) => String(reason || '').trim()).filter(Boolean)
      : [];
    const providerMessage = String((data && data.provider_message) || '').trim();

    if (providerMessage !== '' && !reasons.includes(providerMessage)) {
      reasons.unshift(providerMessage);
    }

    return reasons;
  }

  function normalizeProviderReasonsForDisplay(providerFlow, reasons) {
    const flow = String(providerFlow || '').toLowerCase();
    const list = Array.isArray(reasons) ? reasons.slice() : [];

    if (flow !== 'tracking') {
      return list;
    }

    const filtered = list.filter((reason) => !/json|timed out|timeout|0 bytes|respuesta vac[ií]a|incompleta|empty body|empty reply/i.test(String(reason || '')));
    if (filtered.length) {
      return filtered;
    }

    return ['La confirmación automática del proveedor quedó pendiente y será resuelta por webhook o por sincronización posterior.'];
  }

  function extractProviderCodes(data) {
    const raw = String((data && data.provider_code) || '').trim();
    if (raw === '') {
      return [];
    }

    return raw.split(/\r?\n+/).map((code) => String(code || '').trim()).filter(Boolean);
  }

  async function copyTextToClipboard(value) {
    const text = String(value || '');
    if (text.trim() === '') {
      return false;
    }

    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const tempInput = document.createElement('textarea');
    tempInput.value = text;
    tempInput.setAttribute('readonly', 'readonly');
    tempInput.style.position = 'fixed';
    tempInput.style.opacity = '0';
    document.body.appendChild(tempInput);
    tempInput.focus();
    tempInput.select();

    let copied = false;
    try {
      copied = document.execCommand('copy');
    } finally {
      tempInput.remove();
    }

    return copied;
  }

  function renderDeliveredCodesCard(container, codes) {
    if (!container || !Array.isArray(codes) || !codes.length) {
      return;
    }

    const copyLabel = codes.length > 1 ? 'Copiar codigos' : 'Copiar codigo';
    container.className = `payment-reasons-card mb-3${container.id === 'payment-status-modal-reasons' ? ' text-start' : ''}`;
    container.innerHTML = `
      <div class="payment-reasons-title">${escapePaymentHtml(codes.length > 1 ? 'Codigos entregados' : 'Codigo entregado')}</div>
      <div class="payment-reasons-summary">Guarda esta informacion exactamente como aparece.</div>
      <ul>${codes.map((code) => `<li>${escapePaymentHtml(code)}</li>`).join('')}</ul>
      <button type="button" class="btn btn-info fw-bold w-100 mt-2 payment-copy-code-btn">${escapePaymentHtml(copyLabel)}</button>
    `;

    const copyButton = container.querySelector('.payment-copy-code-btn');
    if (copyButton) {
      copyButton.addEventListener('click', async () => {
        try {
          const copied = await copyTextToClipboard(codes.join('\n'));
          showToast(copied ? 'Codigo copiado.' : 'No se pudo copiar el codigo.', copied ? 'success' : 'error');
        } catch (error) {
          showToast('No se pudo copiar el codigo.', 'error');
        }
      });
    }
  }

  function renderAccountSaleDeliveryCard(container, payload) {
    if (!container || !payload) {
      return false;
    }

    const accountText = String(payload.accountText || '').trim();
    const gallery = Array.isArray(payload.gallery) ? payload.gallery : [];
    if (accountText === '' && gallery.length === 0) {
      return false;
    }

    container.className = `payment-reasons-card mb-3${container.id === 'payment-status-modal-reasons' ? ' text-start' : ''}`;
    container.innerHTML = `
      <div class="payment-reasons-title">Cuenta entregada</div>
      <div class="payment-reasons-summary">Guarda esta información. La cuenta ya quedó disponible para ti.</div>
      <div class="account-sale-delivery-card">
        ${accountText !== '' ? `<div class="account-sale-delivery-copy">${escapePaymentHtml(accountText)}</div>` : ''}
        ${accountText !== '' ? '<button type="button" class="btn btn-info fw-bold account-sale-copy-btn">Copiar datos de la cuenta</button>' : ''}
        ${gallery.length ? `<div class="account-sale-delivery-gallery">${gallery.map((item) => `
          <div class="account-sale-delivery-gallery-item">
            <img src="${escapePaymentHtml(item.imageUrl)}" alt="Vista de la cuenta">
            ${String(item.description || '').trim() !== '' ? `<span>${escapePaymentHtml(item.description)}</span>` : ''}
          </div>
        `).join('')}</div>` : ''}
      </div>
    `;

    const copyButton = container.querySelector('.account-sale-copy-btn');
    if (copyButton && accountText !== '') {
      copyButton.addEventListener('click', async () => {
        try {
          const copied = await copyTextToClipboard(accountText);
          showToast(copied ? 'Datos de la cuenta copiados.' : 'No se pudieron copiar los datos de la cuenta.', copied ? 'success' : 'error');
        } catch (error) {
          showToast('No se pudieron copiar los datos de la cuenta.', 'error');
        }
      });
    }

    return true;
  }

  function renderDeliveredCodes(data) {
    clearPaymentSupportUi();
    const accountSalePayload = getAccountSalePayload(data);
    if (accountSalePayload && renderAccountSaleDeliveryCard(paymentModalReasons, accountSalePayload)) {
      renderAccountSaleDeliveryCard(paymentStatusModalReasons, accountSalePayload);
      scrollPaymentModalToTop();
      return true;
    }

    const codes = extractProviderCodes(data);
    if (!codes.length) {
      return false;
    }

    renderDeliveredCodesCard(paymentModalReasons, codes);
    renderDeliveredCodesCard(paymentStatusModalReasons, codes);
    scrollPaymentModalToTop();
    return true;
  }

  function renderSupportCard(container, title, summary, steps, reasons, options = {}) {
    if (!container) {
      return;
    }

    const variant = options && (options.variant === 'underpaid' || options.variant === 'overpaid')
      ? options.variant
      : '';
    const safeSummary = String(summary || '').trim();
    const safeSteps = Array.isArray(steps) ? steps.filter((step) => String(step || '').trim() !== '') : [];
    const safeReasons = Array.isArray(reasons) ? reasons.filter((reason) => String(reason || '').trim() !== '') : [];

    container.className = `payment-reasons-card mb-3${container.id === 'payment-status-modal-reasons' ? ' text-start' : ''}`;
    if (variant !== '') {
      container.setAttribute('data-payment-difference-variant', variant);
    } else {
      container.removeAttribute('data-payment-difference-variant');
    }
    container.innerHTML = `
      <div class="payment-reasons-title">${escapePaymentHtml(title)}</div>
      ${safeSummary !== '' ? `<div class="payment-reasons-summary">${escapePaymentHtml(safeSummary)}</div>` : ''}
      ${safeSteps.length ? `<ol class="payment-reasons-steps">${safeSteps.map((step) => `<li>${escapePaymentHtml(step)}</li>`).join('')}</ol>` : ''}
      ${safeReasons.length ? `
        <div class="payment-reasons-caption">Detalle detectado por el sistema:</div>
        <ul>${safeReasons.map((reason) => `<li>${escapePaymentHtml(reason)}</li>`).join('')}</ul>
      ` : ''}
    `;
  }

  function renderSupportActionLinks(reference, totalText) {
    const whatsappUrl = buildPaymentSupportWhatsappUrl(activePaymentOrder ? activePaymentOrder.orderId : '', reference, totalText);
    if (!whatsappUrl) {
      return;
    }

    const actionHtml = `<a href="${escapePaymentHtml(whatsappUrl)}" target="_blank" rel="noopener noreferrer" class="payment-support-link">Contactar al administrador por WhatsApp</a>`;
    if (paymentModalActions) {
      paymentModalActions.className = 'payment-support-actions mb-4';
      paymentModalActions.innerHTML = actionHtml;
    }
    if (paymentStatusModalActions) {
      paymentStatusModalActions.className = 'payment-support-actions mb-4';
      paymentStatusModalActions.innerHTML = actionHtml;
    }
  }

  function renderPaymentFailureDetails(data, reference, totalText) {
    clearPaymentSupportUi();
    const failureType = String((data && data.failure_type) || 'server_or_data_mismatch');
    const reasons = extractPaymentReasons(data);
    let title = 'Su Pago está en proceso, Espere 1 min y vuelva a intentar';
    let summary = '';
    let steps = [];
    let displayReasons = [];

    if (failureType === 'reference_mismatch') {
      title = 'La referencia no coincide';
      summary = 'La referencia ingresada no aparece igual en la respuesta del banco.';
      steps = [
        'Revisa que hayas escrito exactamente los dígitos solicitados de la referencia bancaria.',
        'Si la transferencia es reciente, espera 1 o 2 minutos y vuelve a intentar.',
        'Si el comprobante está correcto y el problema continúa, contacta al administrador por WhatsApp.'
      ];
    } else if (failureType === 'expired_reference') {
      title = 'La referencia ya caducó';
      summary = 'Los pagos reportados en la web solo son válidos el mismo día en que se realizan.';
      steps = [
        'La referencia que ingresaste pertenece a un pago de otro día y ya no puede reutilizarse en esta ventana.',
        'Comunícate con el administrador por WhatsApp y comparte tu comprobante para que revise el caso.',
        'Si necesitas completar una nueva compra, realiza un nuevo pago y registra una referencia del mismo día.'
      ];
    } else if (failureType === 'amount_mismatch') {
      title = 'El monto no coincide';
      summary = 'La referencia sí se encontró, pero el monto recibido por el banco no coincide con el total esperado del pedido.';
      steps = [
        'Verifica que el monto transferido corresponda al total del pedido.',
        'Si el banco aún no refleja el monto correcto, espera 1 o 2 minutos y vuelve a intentar.',
        'Si el cobro fue correcto y continúa el problema, contacta al administrador por WhatsApp con tu comprobante.'
      ];
    } else if (failureType === 'server_partial_response') {
      title = 'Su Pago está en proceso, Espere 1 min y vuelva a intentar';
      summary = '';
      steps = [];
    }

    if (failureType === 'server_or_data_mismatch' || failureType === 'server_partial_response') {
      displayReasons = [];
    } else {
      displayReasons = reasons;
    }

    renderSupportCard(paymentModalReasons, title, summary, steps, displayReasons);
    renderSupportCard(paymentStatusModalReasons, title, summary, steps, displayReasons);
    renderSupportActionLinks(reference, totalText);
    scrollPaymentModalToTop();
  }

  function renderProviderPaymentDetails(data, reference, totalText) {
    clearPaymentSupportUi();

    const providerFlow = String((data && data.provider_flow) || '').toLowerCase();
    const reasons = normalizeProviderReasonsForDisplay(providerFlow, extractPaymentReasons(data));
    let title = 'La recarga requiere revisión manual';
    let summary = 'El pago bancario fue verificado, pero el proveedor no confirmó una entrega automática.';
    let steps = [
      'Conserva el comprobante de pago y el número de referencia de esta orden.',
      'Nuestro equipo revisará el pedido; si deseas acelerar la revisión, contáctanos por WhatsApp con tu comprobante.'
    ];

    if (providerFlow === 'accepted') {
      title = 'La compra quedó en proceso';
      summary = 'El proveedor aceptó la orden, pero todavía no reporta entrega final.';
      steps = [
        'Tu pago ya quedó verificado correctamente.',
        'Nuestro equipo dará seguimiento al pedido hasta que el proveedor confirme el resultado final.'
      ];
    }

    if (providerFlow === 'tracking') {
      title = 'La compra quedó en seguimiento automático';
      summary = 'El pago ya fue verificado. La API del proveedor no respondió a tiempo, pero el sistema seguirá consultando hasta confirmar el resultado.';
      steps = [
        'Tu pago quedó verificado correctamente y la orden sigue activa.',
        'Primero intentaremos resolverla por webhook; si no llega confirmación, el sistema hará sincronización automática posterior.',
        'Si el proveedor no confirma pronto, el equipo también podrá revisarla desde la sincronización manual del panel.'
      ];
    }

    if (providerFlow === 'inventory_shortage') {
      title = 'No hay recargas suficientes en este momento';
      summary = 'Tu pago ya fue verificado, pero por los momentos no hay disponibilidad suficiente para completar la recarga automática.';
      steps = [
        'Tu pedido quedó en estado verificado y no necesitas volver a pagar.',
        'Nuestro equipo enviará la recarga en cuanto haya disponibilidad nuevamente.',
        'Si deseas acelerar la atención, contáctanos por WhatsApp y comparte tu comprobante.'
      ];
    }

    renderSupportCard(paymentModalReasons, title, summary, steps, reasons);
    renderSupportCard(paymentStatusModalReasons, title, summary, steps, reasons);
    renderSupportActionLinks(reference, totalText);

    scrollPaymentModalToTop();
  }

  function renderBinancePaymentDetails(data, reference, totalText) {
    clearPaymentSupportUi();

    const checkoutUrl = String((data && data.checkout_url) || '').trim();
    const resolvedTotalText = String((data && data.binance_total_text) || totalText || '').trim();
    const reasons = filterBinanceReasons(data);
    const title = 'Completa el pago en Binance Pay';
    const summary = 'Abrimos un checkout externo de CoinPal para que completes el pago con Binance Pay mientras esta ventana sigue consultando la confirmación.';
    const steps = [
      'Abre la ventana de Binance Pay y completa el pago con tu cuenta o QR.',
      'Mantén esta ventana abierta: el sistema seguirá revisando la confirmación automáticamente.',
      'Si ya pagaste y el estado no cambia de inmediato, espera unos segundos mientras llega el webhook o la sincronización.'
    ];

    renderSupportCard(paymentModalReasons, title, summary, steps, reasons);
    renderSupportCard(paymentStatusModalReasons, title, summary, steps, reasons);

    const actions = [];
    if (checkoutUrl !== '') {
      actions.push({
        label: 'Abrir Binance Pay',
        className: 'btn-info',
        onClick: () => {
          reopenBinanceCheckout(checkoutUrl, reference, resolvedTotalText);
        },
      });
    }

    if (canSwitchFromBinanceToOtherPaymentMode()) {
      actions.push({
        label: 'Pagar con otro método',
        className: 'btn-outline-light',
        onClick: () => {
          switchFromBinanceToOtherPaymentMode();
        },
      });
    }

    actions.push({
      label: 'Cancelar operación',
      className: 'btn-danger',
      onClick: () => {
        openBinanceCancellationFlow();
      },
    });

    const whatsappUrl = buildPaymentSupportWhatsappUrl(activePaymentOrder ? activePaymentOrder.orderId : '', reference, resolvedTotalText);
    if (whatsappUrl) {
      actions.push({
        label: 'Contactar por WhatsApp',
        className: 'btn-outline-info',
        onClick: () => {
          window.open(whatsappUrl, '_blank', 'noopener');
        },
      });
    }

    renderPaymentActionButtons(actions, { hideDefaultStatusAccept: true });
    scrollPaymentModalToTop();
  }

  function renderPaymentServerFailure(errorMessage, reference, totalText) {
    renderPaymentFailureDetails({
      failure_type: 'server_or_data_mismatch',
      reasons: [errorMessage || 'No se recibió una respuesta válida del servidor bancario.']
    }, reference, totalText);
    scrollPaymentModalToTop();
  }

  function setCancelOrderButtonMode(mode) {
    if (!paymentCancelOrderButton) {
      return;
    }
    paymentCancelOrderButton.dataset.mode = mode;
    if (mode === 'close') {
      paymentCancelOrderButton.textContent = 'Cerrar ventana';
      paymentCancelOrderButton.classList.remove('btn-danger');
      paymentCancelOrderButton.classList.add('btn-outline-light');
      return;
    }
    paymentCancelOrderButton.textContent = 'Cancelar Orden';
    paymentCancelOrderButton.classList.remove('btn-outline-light');
    paymentCancelOrderButton.classList.add('btn-danger');
  }

  function setPaymentFormDisabled(disabled) {
    [paymentMethodSelect, paymentReferenceInput, paymentPhoneInput, paymentSubmitButton, ...getPaymentModeButtons()].forEach((field) => {
      if (field) {
        field.disabled = disabled;
      }
    });
  }

  function renderPaymentMethodDetails(method) {
    if (!method) {
      paymentMethodTitle.textContent = 'Datos de pago';
      paymentMethodCurrency.textContent = '';
      paymentMethodDetails.innerHTML = 'No hay datos de pago disponibles.';
      paymentReferenceInput.placeholder = paymentReferencePlaceholder(null);
      paymentReferenceHelp.textContent = paymentReferenceHelpText(null);
      paymentReferenceInput.maxLength = 120;
      return;
    }

    const currencyLabel = `${method.moneda_nombre || ''}${method.moneda_clave ? ` (${method.moneda_clave})` : ''}`.trim();
    paymentMethodTitle.textContent = `Datos para ${method.nombre || 'el pago'}`;
    paymentMethodCurrency.textContent = currencyLabel;
    paymentMethodDetails.innerHTML = escapePaymentHtml(method.datos || '').replace(/\n/g, '<br>');
    const digits = Number(method.referencia_digitos || 0);
    paymentReferenceInput.placeholder = paymentReferencePlaceholder(method);
    paymentReferenceHelp.textContent = paymentReferenceHelpText(method);
    paymentReferenceInput.maxLength = digits > 0 ? digits : 120;
    paymentReferenceInput.dataset.requiredDigits = String(digits > 0 ? digits : 0);
  }

  function renderPaymentMethodsByCurrency(currencyCode) {
    const methods = getPaymentMethodsForCurrency(currencyCode);
    if (!methods.length) {
      paymentMethodSelectWrap.classList.add('d-none');
      renderPaymentMethodDetails(null);
      return null;
    }

    if (methods.length === 1) {
      paymentMethodSelectWrap.classList.add('d-none');
      paymentMethodSelect.innerHTML = `<option value="${methods[0].id}">${escapePaymentHtml(methods[0].nombre || 'Método')}</option>`;
      renderPaymentMethodDetails(methods[0]);
      return methods[0];
    }

    paymentMethodSelectWrap.classList.remove('d-none');
    paymentMethodSelect.innerHTML = methods.map((method) => `<option value="${method.id}">${escapePaymentHtml(method.nombre || 'Método')}</option>`).join('');
    renderPaymentMethodDetails(methods[0]);
    return methods[0];
  }

  function resetCheckoutState() {
    orderForm.reset();
    orderForm.email.value = defaultOrderEmail || '';
    restoreStoredPurchaseDefaults(true);
    couponInput.value = '';
    couponInput.disabled = false;
    if (applyCouponButton) {
      applyCouponButton.disabled = false;
    }
    couponApplied = false;
    couponValue = '';
    activePack = null;
    if (paymentWinPointsCard) {
      paymentWinPointsCard.classList.add('d-none');
    }
    if (paymentMethodCard) {
      paymentMethodCard.classList.remove('d-none');
    }
    if (paymentModeOptions) {
      paymentModeOptions.innerHTML = '';
    }
    resetPlayerVerificationState();
    packCards2.forEach((item) => item.classList.remove('neon-selected'));
    renderPlayerFields(null);
    updateResumenCompra(null);
    refreshPaymentDifferenceBanner(null);
    updateButtonState();
  }

  function closePaymentModal(resetState) {
    clearPaymentTimer();
    setOverlayVisible(paymentModal, false);
    setPaymentAlert('', 'info');
    if (resetState) {
      activePaymentOrder = null;
      paymentReferenceInput.value = '';
      paymentPhoneInput.value = defaultPaymentPhone || '';
      clearPaymentSupportUi();
      setCancelOrderButtonMode('cancel');
      if (paymentWinPointsCard) {
        paymentWinPointsCard.classList.add('d-none');
      }
      if (paymentSubmitButton) {
        paymentSubmitButton.textContent = defaultPaymentSubmitButtonLabel;
      }
    }
  }

  async function expireActiveOrder() {
    if (!activePaymentOrder || activePaymentOrder.expiring) {
      return;
    }
    activePaymentOrder.expiring = true;
    clearPaymentTimer();
    setPaymentFormDisabled(true);
    setPaymentAlert('La orden expiró. Estamos cancelando el pedido y notificando por correo.', 'danger');
    try {
      const response = await fetch(buildAppUrl('/api/pedidos.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=expire_order&order_id=${encodeURIComponent(activePaymentOrder.orderId)}`
      });
      const data = await response.json();
      showToast((data && data.message) ? data.message : 'La orden expiró.', data && data.expired ? 'error' : 'info');
      setPaymentAlert((data && data.message) ? data.message : 'La orden expiró y fue cancelada automáticamente.', 'danger');
    } catch (error) {
      setPaymentAlert('La orden expiró. Si el estado no cambió todavía, vuelve a intentarlo.', 'danger');
    }
  }

  function updatePaymentTimer() {
    if (!activePaymentOrder) {
      paymentTimerValue.textContent = '30:00';
      return;
    }
    const remainingMs = activePaymentOrder.expiresAtMs - Date.now();
    if (remainingMs <= 0) {
      paymentTimerValue.textContent = '00:00';
      expireActiveOrder();
      return;
    }
    const totalSeconds = Math.floor(remainingMs / 1000);
    const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');
    paymentTimerValue.textContent = `${minutes}:${seconds}`;
  }

  function openPaymentModal(orderId, expiresAt, remainingSeconds, pack, userId, totalText, orderEmail) {
    const currentMethod = renderPaymentMethodsByCurrency(pack.moneda || '');
    const canUsePoints = canRedeemPackWithPoints(pack);
    const canUseBinance = canUseBinanceCheckout(pack);
    if (!currentMethod && !canUsePoints && !canUseBinance) {
      showToast('No hay métodos de pago activos disponibles.', 'error');
      return false;
    }

    const safeRemainingSeconds = Number.isFinite(Number(remainingSeconds)) ? Math.max(0, Number(remainingSeconds)) : 1800;

    activePaymentOrder = {
      orderId,
      pack,
      expiresAtMs: Date.now() + (safeRemainingSeconds * 1000),
      expiresAt,
      currency: pack.moneda || '',
      email: orderEmail || '',
      canUseMoney: Boolean(currentMethod),
      canUseBinance,
      canUsePoints,
      paymentMode: currentMethod ? 'money' : (canUsePoints ? 'points' : 'binance'),
      selectedMethodId: currentMethod ? String(currentMethod.id) : '',
      pointsRequired: Number(pack.redeemRequiredPoints || 0),
      confirmedTotalText: String(totalText || '-').trim() || '-',
      expiring: false,
    };

    renderPaymentSummary(pack, userId, totalText);
    paymentReferenceInput.value = '';
    paymentPhoneInput.value = defaultPaymentPhone || '';
    setPaymentFormDisabled(false);
    setPaymentAlert('', 'info');
    clearPaymentSupportUi();
    if (paymentSubmitButton) {
      paymentSubmitButton.textContent = defaultPaymentSubmitButtonLabel;
    }
    renderWinPointsPaymentState(pack, currentMethod);
    setCancelOrderButtonMode('cancel');
    setOverlayVisible(paymentModal, true);
    scrollPaymentModalToTop();
    clearPaymentTimer();
    updatePaymentTimer();
    paymentTimerInterval = setInterval(updatePaymentTimer, 1000);
    return true;
  }

  function updatePackPrices() {
    packCards.forEach(card => {
      const base = parseFloat(card.getAttribute('data-base'));
      const precio = normalizeCurrencyAmount(base * monedaActualTasa, monedaActualMostrarDecimales);
      card.querySelector('.precio-label').textContent = formatCurrencyAmount(precio, monedaActualMostrarDecimales);
      card.querySelector('.moneda-label').textContent = monedaActualClave;
      card.setAttribute('data-price-value', String(precio));
      card.setAttribute('data-show-decimals', monedaActualMostrarDecimales ? '1' : '0');
      card.setAttribute('data-moneda', monedaActualClave);
    });
  }
  updatePackPrices();

  function updateButtonState() {
    // Solo controlar el estado del botón, no mostrar mensajes de error aquí
    const requiredFields = Array.from(orderForm.querySelectorAll("[required]"));
    let requiredFilled = true;
    requiredFields.forEach(field => {
      if (field.value.trim() === "") {
        requiredFilled = false;
      }
    });
    if (!activePack) {
      selectedPack.style.color = "#f87171";
      selectedPack.textContent = "Debes seleccionar un paquete.";
    } else {
      selectedPack.style.color = "";
      selectedPack.textContent = activePack.name;
    }
    const needsPlayerVerification = requiresVerifiedPlayerForCheckout();
    const paymentDifferenceBlocked = activePack ? getPaymentDifferenceBreakdown(activePack, selectedTotalValue).blocksSelection : false;
    const blockedByGameEntryWindow = !gameEntryWindowAccepted;
    buyButton.disabled = !activePack || !requiredFilled || needsPlayerVerification || paymentDifferenceBlocked || blockedByGameEntryWindow;
    if (paymentDifferenceBlocked) {
      buyButton.textContent = paymentDifferenceBlockedBuyButtonLabel;
    } else if (blockedByGameEntryWindow) {
      buyButton.textContent = defaultBuyButtonLabel;
    } else {
      buyButton.textContent = needsPlayerVerification ? verifyUserBuyButtonLabel : defaultBuyButtonLabel;
    }
    syncPlayerVerificationUi();
  }
  function updateResumenCompra(pack) {
    const quantity = syncOrderQuantityInput();
    if (pack) {
      pack.purchaseQuantity = quantity;
      selectedPack.textContent = pack.name;
      selectedTotalValue = getPackTotalPrice(pack, quantity);
      updateSelectedPriceDisplay(pack);
      if (selectedWinPointsTotal) {
        const requiredPoints = getPackRequiredPoints(pack, quantity);
        const hasWinPointsRedemption = Boolean(pack.redeemActive) && requiredPoints > 0;
        selectedWinPointsTotal.textContent = hasWinPointsRedemption
          ? `Canje: ${formatWinPointsAmount(requiredPoints)}`
          : '';
        selectedWinPointsTotal.classList.toggle('d-none', !hasWinPointsRedemption);
      }
      if (selectedPackAccountActions) {
        selectedPackAccountActions.classList.toggle('d-none', !isAccountSalePack(pack));
      }
    } else {
      selectedTotalValue = 0;
      selectedPack.textContent = 'Ninguno';
      syncOrderQuantityInput(1);
      updateSelectedPriceDisplay(null);
      if (selectedWinPointsTotal) {
        selectedWinPointsTotal.textContent = '';
        selectedWinPointsTotal.classList.add('d-none');
      }
      if (selectedPackAccountActions) {
        selectedPackAccountActions.classList.add('d-none');
      }
    }
  }

  function findPackCardById(packageId) {
    return packCards2.find((card) => String(card.dataset.packageId || '') === String(packageId || '')) || null;
  }

  function activatePackCard(card, options = {}) {
    if (!card) {
      return;
    }

    packCards2.forEach((item) => {
      item.classList.remove('neon-selected');
    });
    card.classList.add('neon-selected');
    activePack = buildPackStateFromCard(card);
    updateResumenCompra(activePack);
    renderPlayerFields(activePack);
    handlePlayerVerificationFieldChange();
    updateButtonState();
    const shouldScroll = Object.prototype.hasOwnProperty.call(options, 'scroll')
      ? options.scroll !== false
      : !isAccountSalePack(activePack);
    if (shouldScroll) {
      scrollToOrderForm();
    }
  }

  function focusAccountSaleEmailStep() {
    closeAccountGalleryModal();
    scrollToOrderForm();
    if (orderEmailInput) {
      if (!orderEmailInput.value.trim() && defaultOrderEmail) {
        orderEmailInput.value = defaultOrderEmail;
      }
      orderEmailInput.focus();
    }
  }

  function triggerAccountSaleBuyFlow(triggerButton = buyButton) {
    if (!activePack || !isAccountSalePack(activePack)) {
      return;
    }

    const loggedEmail = String(defaultOrderEmail || '').trim();
    if (!winPointsState.loggedIn || loggedEmail === '') {
      focusAccountSaleEmailStep();
      return;
    }

    if (orderEmailInput) {
      orderEmailInput.value = loggedEmail;
    }
    closeAccountGalleryModal();
    submitOrderCreationRequest({
      triggerButton,
      forceEmail: loggedEmail,
      forceUserId: '',
      forcePlayerFields: {}
    });
  }

  function renderAccountGalleryPreview(pack, activeIndex = 0) {
    if (!accountGalleryModal || !pack) {
      return;
    }

    const gallery = Array.isArray(pack.accountGallery) ? pack.accountGallery : [];
    const safeIndex = gallery.length ? Math.max(0, Math.min(activeIndex, gallery.length - 1)) : 0;
    const activeItem = gallery[safeIndex] || null;
    activeAccountGalleryPreview = { pack, index: safeIndex };

    if (accountGalleryModalTitle) {
      accountGalleryModalTitle.textContent = pack.name || 'Cuenta disponible';
    }
    if (accountGalleryModalPrice) {
      accountGalleryModalPrice.textContent = formatPaymentDifferenceMoney(pack.moneda || monedaActualClave, getPackTotalPrice(pack, Number(pack.purchaseQuantity || getOrderQuantity())), pack.showDecimals);
    }
    if (accountGalleryModalCaption) {
      accountGalleryModalCaption.textContent = activeItem && activeItem.description ? activeItem.description : '';
    }
    if (accountGalleryModalImage && accountGalleryModalPlaceholder) {
      if (activeItem && activeItem.imageUrl) {
        accountGalleryModalImage.src = activeItem.imageUrl;
        accountGalleryModalImage.classList.remove('d-none');
        accountGalleryModalPlaceholder.classList.add('d-none');
      } else {
        accountGalleryModalImage.src = '';
        accountGalleryModalImage.classList.add('d-none');
        accountGalleryModalPlaceholder.classList.remove('d-none');
      }
    }
    if (accountGalleryModalThumbs) {
      accountGalleryModalThumbs.innerHTML = gallery.map((item, index) => `
        <button type="button" class="account-gallery-thumb${index === safeIndex ? ' is-active' : ''}" data-account-thumb-index="${index}" aria-label="Vista previa ${index + 1}">
          <img src="${escapePaymentHtml(item.imageUrl)}" alt="Vista previa ${index + 1}">
        </button>
      `).join('');
      accountGalleryModalThumbs.querySelectorAll('[data-account-thumb-index]').forEach((button) => {
        button.addEventListener('click', () => {
          renderAccountGalleryPreview(pack, Number(button.getAttribute('data-account-thumb-index') || '0'));
        });
      });
    }
  }

  function openAccountGalleryModal(pack) {
    if (!accountGalleryModal || !pack || !isAccountSalePack(pack)) {
      return;
    }

    renderAccountGalleryPreview(pack, 0);
    setOverlayVisible(accountGalleryModal, true);
  }

  function closeAccountGalleryModal() {
    if (!accountGalleryModal) {
      return;
    }

    setOverlayVisible(accountGalleryModal, false);
  }

  packCards2.forEach((card) => {
    card.addEventListener("click", () => {
      activatePackCard(card);
    });
  });
  if (selectedPackPreviewButton) {
    selectedPackPreviewButton.addEventListener('click', () => {
      if (activePack && isAccountSalePack(activePack)) {
        openAccountGalleryModal(activePack);
      }
    });
  }
  if (selectedPackBuyButton) {
    selectedPackBuyButton.addEventListener('click', () => {
      triggerAccountSaleBuyFlow(selectedPackBuyButton);
    });
  }
  if (packCards2.length) {
    // Ya no se selecciona automáticamente ningún paquete al cargar
  }
  syncOrderQuantityInput(1);
  renderPlayerFields(null);
  setAccountSaleNote(null);
  if (verifyPlayerButton) {
    verifyPlayerButton.addEventListener('click', verifyCurrentPlayer);
  }
  if (accountGalleryModalClose) {
    accountGalleryModalClose.addEventListener('click', closeAccountGalleryModal);
  }
  if (accountGalleryModalBuy) {
    accountGalleryModalBuy.addEventListener('click', () => {
      triggerAccountSaleBuyFlow(accountGalleryModalBuy);
    });
  }
              function normalizeCouponCode(value) {
                return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
              }

              function resetCouponState(clearInput = false) {
                couponApplied = false;
                couponValue = '';
                couponInput.disabled = false;
                if (clearInput && couponInput) {
                  couponInput.value = '';
                }
                if (applyCouponButton) {
                  applyCouponButton.disabled = false;
                }
              }

              if (orderQuantityInput) {
                const triggerQuantityInputUpdate = function(nextQuantity) {
                  orderQuantityInput.value = String(normalizeOrderQuantity(nextQuantity));
                  orderQuantityInput.dispatchEvent(new Event('input', { bubbles: true }));
                };

                if (orderQuantityDecreaseButton) {
                  orderQuantityDecreaseButton.addEventListener('click', function() {
                    if (orderQuantityDecreaseButton.disabled) {
                      return;
                    }
                    triggerQuantityInputUpdate(Math.max(1, getOrderQuantity() - 1));
                    orderQuantityInput.focus();
                  });
                }

                if (orderQuantityIncreaseButton) {
                  orderQuantityIncreaseButton.addEventListener('click', function() {
                    if (orderQuantityIncreaseButton.disabled) {
                      return;
                    }
                    triggerQuantityInputUpdate(getOrderQuantity() + 1);
                    orderQuantityInput.focus();
                  });
                }

                orderQuantityInput.addEventListener('input', function() {
                  const quantity = syncOrderQuantityInput(orderQuantityInput.value);
                  if (couponInput.value.trim() !== '' || couponApplied) {
                    resetCouponState(true);
                  }
                  if (activePack) {
                    activePack.purchaseQuantity = quantity;
                    updateResumenCompra(activePack);
                  } else {
                    updateResumenCompra(null);
                  }
                  updateButtonState();
                });

                orderQuantityInput.addEventListener('blur', function() {
                  syncOrderQuantityInput(orderQuantityInput.value);
                });
              }

              if (paymentMethodSelect) {
                paymentMethodSelect.addEventListener('change', function() {
                  const methods = getPaymentMethodsForCurrency(activePaymentOrder ? activePaymentOrder.currency : (activePack ? activePack.moneda : ''));
                  const selectedMethod = methods.find((method) => String(method.id) === String(paymentMethodSelect.value)) || methods[0] || null;
                  if (activePaymentOrder) {
                    activePaymentOrder.selectedMethodId = selectedMethod ? String(selectedMethod.id) : '';
                  }
                  if (activePaymentOrder && paymentWinPointsCard && !paymentWinPointsCard.classList.contains('d-none')) {
                    setActivePaymentMode('money', activePaymentOrder.selectedMethodId);
                    return;
                  }
                  renderPaymentMethodDetails(selectedMethod);
                });
              }

              if (paymentReferenceInput) {
                paymentReferenceInput.addEventListener('input', function() {
                  const digitsOnly = paymentReferenceInput.value.replace(/\D+/g, '');
                  const requiredDigits = Number(paymentReferenceInput.dataset.requiredDigits || '0');
                  paymentReferenceInput.value = requiredDigits > 0 ? digitsOnly.slice(0, requiredDigits) : digitsOnly.slice(0, 120);
                });
              }

              if (paymentCancelOrderButton) {
                paymentCancelOrderButton.addEventListener('click', function() {
                  const mode = paymentCancelOrderButton.dataset.mode || 'cancel';
                  if (mode === 'close') {
                    closePaymentModal(true);
                    resetCheckoutState();
                    return;
                  }
                  if (!activePaymentOrder) {
                    return;
                  }
                  setOverlayVisible(paymentCancelConfirmModal, true);
                });
              }

              if (paymentCancelDismissButton) {
                paymentCancelDismissButton.addEventListener('click', function() {
                  setOverlayVisible(paymentCancelConfirmModal, false);
                });
              }

              if (paymentCancelConfirmButton) {
                paymentCancelConfirmButton.addEventListener('click', function() {
                  if (!activePaymentOrder) {
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    return;
                  }
                  paymentCancelConfirmButton.disabled = true;
                  fetch(buildAppUrl('/api/pedidos.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=cancel_order&order_id=${encodeURIComponent(activePaymentOrder.orderId)}`
                  })
                  .then(async (response) => {
                    const data = await parseApiJsonResponse(response, 'No se pudo cancelar la orden en este momento.');
                    if (!response.ok || !data.ok) {
                      throw new Error((data && data.message) ? data.message : 'No se pudo cancelar la orden.');
                    }
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    showToast(data.message || 'Orden cancelada.', 'error');
                    closePaymentModal(true);
                    resetCheckoutState();
                  })
                  .catch((error) => {
                    setOverlayVisible(paymentCancelConfirmModal, false);
                    setPaymentAlert(normalizeApiRequestErrorMessage(error, 'No se pudo cancelar la orden en este momento.'), 'danger');
                  })
                  .finally(() => {
                    paymentCancelConfirmButton.disabled = false;
                  });
                });
              }

              if (paymentSubmitButton) {
                paymentSubmitButton.addEventListener('click', function() {
                  if (!activePaymentOrder) {
                    showToast('No hay una orden pendiente para confirmar.', 'error');
                    return;
                  }

                  const paymentMode = activePaymentOrder.paymentMode === 'points'
                    ? 'points'
                    : (activePaymentOrder.paymentMode === 'binance' ? 'binance' : 'money');
                  const methods = getPaymentMethodsForCurrency(activePaymentOrder.currency);
                  const selectedMethod = methods.find((method) => String(method.id) === String(activePaymentOrder.selectedMethodId || paymentMethodSelect.value)) || methods[0] || null;
                  if (paymentMode === 'money' && !selectedMethod) {
                    setPaymentAlert('No hay un método de pago disponible para esta orden.', 'danger');
                    return;
                  }

                  const reference = paymentMode === 'money' ? paymentReferenceInput.value.trim() : '';
                  const phone = paymentMode === 'money' ? paymentPhoneInput.value.trim() : '';
                  const requiredDigits = Number(selectedMethod ? (selectedMethod.referencia_digitos || 0) : 0);

                  if (paymentMode === 'points' && !activePaymentOrder.canUsePoints) {
                    setPaymentAlert('Este paquete no tiene un canje disponible con tus premios en este momento.', 'danger');
                    return;
                  }
                  if (paymentMode === 'binance' && !activePaymentOrder.canUseBinance) {
                    setPaymentAlert('Binance Pay no está disponible para esta orden.', 'danger');
                    return;
                  }
                  if (paymentMode === 'money' && !reference) {
                    setPaymentAlert('Debes ingresar el número de referencia.', 'danger');
                    return;
                  }
                  if (paymentMode === 'money' && requiredDigits > 0 && reference.length !== requiredDigits) {
                    setPaymentAlert(`La referencia debe contener exactamente ${requiredDigits} dígitos.`, 'danger');
                    return;
                  }
                  if (paymentMode === 'money' && !phone) {
                    setPaymentAlert('Debes ingresar un número de teléfono para contactarte.', 'danger');
                    return;
                  }

                  setPaymentFormDisabled(true);
                  setPaymentAlert('', 'info');
                  let checkoutWindow = null;
                  if (paymentMode === 'binance') {
                    checkoutWindow = openBinanceCheckoutPopup();
                  }
                  setLoadingModalContent(
                    paymentMode === 'points'
                      ? 'Canjeando premios...'
                      : (paymentMode === 'binance' ? 'Abriendo Binance Pay...' : (paymentSendingOrderContent.title || 'Enviando orden...')),
                    paymentMode === 'points'
                      ? 'Estamos validando tu saldo y procesando la recarga con tus premios. No cierres esta ventana.'
                      : (paymentMode === 'binance'
                        ? 'Estamos creando el checkout externo de Binance Pay. No cierres esta ventana.'
                        : (paymentSendingOrderContent.message || 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.')),
                    paymentMode === 'points' ? 'processing' : (paymentMode === 'binance' ? 'processing' : 'sending')
                  );
                  setOverlayVisible(loadingModal, true);
                  fetch(buildAppUrl('/api/pedidos.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: [
                      'action=submit_payment',
                      `order_id=${encodeURIComponent(activePaymentOrder.orderId)}`,
                      `payment_mode=${encodeURIComponent(paymentMode)}`,
                      `payment_method_id=${encodeURIComponent(selectedMethod ? selectedMethod.id : '')}`,
                      `reference_number=${encodeURIComponent(reference)}`,
                      `phone=${encodeURIComponent(phone)}`
                    ].join('&')
                  })
                  .then(async (response) => {
                    const data = await parseApiJsonResponse(response, 'No pudimos validar tu pago en este momento. Espera 1 minuto y vuelve a intentarlo.');
                    if (!response.ok || !data.ok) {
                      throw new Error((data && data.message) ? data.message : 'No se pudieron guardar los datos del pago.');
                    }

                    if (data && data.win_points && Number.isFinite(Number(data.win_points.balance))) {
                      syncWinPointsSummaryFromResponse(data.win_points);
                      renderWinPointsPaymentState(activePaymentOrder.pack || activePack, selectedMethod);
                    }
                    if (paymentMode === 'money' && phone) {
                      defaultPaymentPhone = phone;
                    }

                    setOverlayVisible(loadingModal, false);

                    if (paymentMode === 'binance' && checkoutWindow && !checkoutWindow.closed) {
                      const checkoutUrl = String((data && data.checkout_url) || '').trim();
                      if (checkoutUrl === '') {
                        checkoutWindow.close();
                      }
                    }

                    if (paymentMode === 'binance') {
                      const checkoutUrl = String((data && data.checkout_url) || '').trim();
                      if (checkoutUrl !== '') {
                        const opened = navigateBinanceCheckoutPopup(checkoutWindow, checkoutUrl);
                        if (!opened) {
                          setPaymentAlert('No pudimos abrir automáticamente Binance Pay. Usa el botón "Abrir Binance Pay" para continuar.', 'warning');
                        }
                      }
                    }

                    const nextState = String((data && data.estado) || '').toLowerCase();
                    const providerFlow = String((data && data.provider_flow) || '').toLowerCase();
                    if (nextState === 'enviado') {
                      const successMessage = data.message || (getAccountSalePayload(data)
                        ? (paymentMode === 'points' ? 'Canje realizado y cuenta entregada correctamente.' : 'La cuenta fue entregada correctamente.')
                        : (paymentMode === 'points'
                          ? 'Canje realizado y recarga procesada correctamente.'
                          : 'La recarga fue procesada correctamente.'));
                      setPaymentAlert(successMessage, 'success');
                      renderDeliveredCodes(data);
                      renderOverpaidPaymentDifference(data);
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal('Operación exitosa', successMessage, 'success');
                      return;
                    }

                    if (nextState === 'cancelado') {
                      const cancelMessage = data.message || 'La orden fue cancelada.';
                      setPaymentAlert(cancelMessage, 'danger');
                      if (String((data && data.provider_flow) || '').trim() !== '') {
                        renderProviderPaymentDetails(data, reference, getConfirmedPaymentTotalText());
                      } else {
                        renderPaymentFailureDetails(data, reference, getConfirmedPaymentTotalText());
                      }
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal('No se pudo completar la operación', cancelMessage, 'danger');
                      return;
                    }

                    if (nextState === 'pendiente' && providerFlow === 'binance_checkout') {
                      const pendingMessage = data.message || 'Completa el pago en Binance Pay para continuar con tu pedido.';
                      setPaymentAlert(pendingMessage, 'info');
                      renderBinancePaymentDetails(data, (data && data.provider_reference) ? data.provider_reference : reference, getConfirmedPaymentTotalText());
                      setCancelOrderButtonMode('cancel');
                      showPaymentStatusModal('Completa el pago en Binance Pay', pendingMessage, 'info');
                      setPaymentStatusWaiting(true);
                      pollOrderResolution((data && data.provider_reference) ? data.provider_reference : reference, getConfirmedPaymentTotalText(), 1);
                      return;
                    }

                    if (nextState === 'pagado') {
                      const paidMessage = data.message || 'El pago fue confirmado correctamente.';
                      const hasProviderDetails = extractPaymentReasons(data).length > 0;
                      const isAcceptedFlow = providerFlow === 'accepted' || providerFlow === 'tracking';
                      const requiresManualReview = providerFlow === 'manual_review' || (!isAcceptedFlow && hasProviderDetails);

                      setPaymentAlert(paidMessage, requiresManualReview ? 'warning' : (isAcceptedFlow ? 'info' : 'success'));
                      if (hasProviderDetails || providerFlow === 'accepted') {
                        renderProviderPaymentDetails(data, reference, getConfirmedPaymentTotalText());
                      } else {
                        clearPaymentSupportUi();
                      }
                      renderOverpaidPaymentDifference(data);
                      setPaymentFormDisabled(true);
                      clearPaymentTimer();
                      setCancelOrderButtonMode('close');
                      showPaymentStatusModal(
                        requiresManualReview ? 'Revisión requerida' : (isAcceptedFlow ? 'Compra en proceso' : 'Operación exitosa'),
                        paidMessage,
                        requiresManualReview ? 'danger' : (isAcceptedFlow ? 'info' : 'success')
                      );
                      if (providerFlow === 'accepted' || providerFlow === 'tracking') {
                        setPaymentStatusWaiting(true);
                        pollOrderResolution(reference, getConfirmedPaymentTotalText(), 1);
                      }
                      return;
                    }

                    if (nextState === 'pendiente' && data && data.bank_checked) {
                      if (renderUnderpaidPaymentDifference(data)) {
                        return;
                      }
                      const pendingMessage = data.message || 'No pudimos validar el pago automáticamente.';
                      setPaymentAlert(pendingMessage, 'danger');
                      renderPaymentFailureDetails(data, reference, getConfirmedPaymentTotalText());
                      setPaymentFormDisabled(false);
                      showPaymentStatusModal('Revisión requerida', pendingMessage, 'danger');
                      return;
                    }

                    closePaymentModal(true);
                    resetCheckoutState();
                  })
                  .catch((error) => {
                    setOverlayVisible(loadingModal, false);
                    if (checkoutWindow && !checkoutWindow.closed) {
                      checkoutWindow.close();
                    }
                    const errorMessage = normalizeApiRequestErrorMessage(
                      error,
                      'No pudimos validar tu pago en este momento. Espera 1 minuto y vuelve a intentarlo.'
                    );
                    setPaymentAlert(errorMessage, 'danger');
                    renderPaymentServerFailure(errorMessage, reference, getConfirmedPaymentTotalText());
                    setPaymentFormDisabled(false);
                    showPaymentStatusModal('No se pudo completar la validación', errorMessage, 'danger');
                    if (activePaymentOrder && activePaymentOrder.expiresAtMs <= Date.now()) {
                      expireActiveOrder();
                    }
                  });
                });
              }

              if (monedaSelect) {
                monedaSelect.addEventListener('change', function() {
                  const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
                  monedaActualId = selectedOption.value;
                  monedaActualClave = selectedOption.dataset.clave || 'USD';
                  monedaActualTasa = parseFloat(selectedOption.dataset.tasa || '1');
                  monedaActualMostrarDecimales = Boolean(monedas[monedaActualId] && monedas[monedaActualId].mostrar_decimales);
                  updatePackPrices();

                  if (activePack) {
                    const selectedCard = packCards2.find((card) => card.classList.contains('neon-selected'));
                    if (selectedCard) {
                      activePack = buildPackStateFromCard(selectedCard);
                      updateResumenCompra(activePack);
                      renderPlayerFields(activePack);
                    }
                  } else {
                    renderPlayerFields(null);
                    updateResumenCompra(null);
                  }

                  if (couponInput.value.trim() !== '') {
                    couponInput.value = '';
                  }
                  resetCouponState();
                });
              }

              couponInput.addEventListener('input', function() {
                const normalized = normalizeCouponCode(couponInput.value);
                if (couponInput.value !== normalized) {
                  couponInput.value = normalized;
                }
              });

              // Validación de cupón por AJAX
              applyCouponButton.addEventListener('click', function() {
                const cupon = normalizeCouponCode(couponInput.value);
                couponInput.value = cupon;
                const pack = activePack;
                if (!pack) {
                  showToast('Selecciona un paquete antes de aplicar el cupón.', 'error');
                  return;
                }
                // Aseguramos que el precio sea un número puro
                const precioNumerico = String(getPackTotalPrice(pack));
                console.log('Enviando cupón:', cupon, 'Precio:', precioNumerico);
                if (!cupon) {
                  showToast('Ingresa un cupón.', 'error');
                  return;
                }
                fetch(buildAppUrl('/api/validar_cupon.php'), {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: `code=${encodeURIComponent(cupon)}&pack_price=${encodeURIComponent(precioNumerico)}&currency=${encodeURIComponent(pack.moneda || '')}`
                })
                .then(res => res.json())
                .then(data => {
                  console.log('Respuesta backend:', data);
                  if (data.success) {
                    selectedTotalValue = normalizeCurrencyAmount(data.nuevo_total, pack.showDecimals);
                    pack.purchaseQuantity = getOrderQuantity();
                    updateSelectedPriceDisplay(pack);
                    showToast(data.message + ` Descuento: ${formatCurrencyAmount(data.descuento, pack.showDecimals)}`,'success');
                    couponInput.disabled = true;
                    applyCouponButton.disabled = true;
                    couponApplied = true;
                    updateButtonState();
                  } else {
                    showToast(data.message, 'error');
                  }
                })
                .catch(() => {
                  showToast('Error de red al validar cupón.', 'error');
                });
              });
              modalNo.addEventListener('click', function() {
                couponApplied = false;
                couponValue = couponInput.value.trim();
                setOverlayVisible(couponModal, false);
                showToast('Compra sin cupón aplicado', 'info');
              });
              modalCancel.addEventListener('click', function() {
                setOverlayVisible(couponModal, false);
              });
              orderForm.addEventListener('input', function() {
                handlePlayerVerificationFieldChange();
                updateButtonState();
              });
              orderForm.addEventListener('change', function() {
                handlePlayerVerificationFieldChange();
                updateButtonState();
              });
              setPaymentDifferenceCreditState(paymentDifferenceCreditState);
              openGameEntryWindowIfNeeded();
              function submitOrderCreationRequest(options = {}) {
                const btn = options.triggerButton instanceof HTMLElement ? options.triggerButton : buyButton;
                const couponVal = normalizeCouponCode(couponInput.value);
                couponInput.value = couponVal;
                const pack = options.pack || activePack;
                const userId = typeof options.forceUserId === 'string'
                  ? options.forceUserId.trim()
                  : (playerPrimaryInput ? playerPrimaryInput.value.trim() : '');
                const playerFields = options.forcePlayerFields && typeof options.forcePlayerFields === 'object'
                  ? options.forcePlayerFields
                  : collectPlayerFields();
                const email = typeof options.forceEmail === 'string'
                  ? options.forceEmail.trim()
                  : (orderEmailInput ? orderEmailInput.value.trim() : '');

                if (orderEmailInput && email !== '') {
                  orderEmailInput.value = email;
                }

                if (!pack) {
                  showToast('Debes seleccionar un paquete.', 'error');
                  return;
                }
                const paymentMethods = getPaymentMethodsForCurrency(pack.moneda || '');
                const pointsCheckoutAvailable = canRedeemPackWithPoints(pack);
                const binanceCheckoutAvailable = canUseBinanceCheckout(pack);
                if (!paymentMethods.length && !pointsCheckoutAvailable && !binanceCheckoutAvailable) {
                  showToast('No hay métodos de pago activos disponibles.', 'error');
                  return;
                }

                const requiredFields = Array.from(orderForm.querySelectorAll('[required]'));
                let requiredFilled = true;
                requiredFields.forEach(field => {
                  const errorId = `${field.name}-error`;
                  let errorElem = document.getElementById(errorId);
                  if (field.value.trim() === '') {
                    requiredFilled = false;
                    if (!errorElem) {
                      errorElem = document.createElement('div');
                      errorElem.id = errorId;
                      errorElem.style.color = '#f87171';
                      errorElem.style.fontSize = '12px';
                      errorElem.textContent = 'Este campo es obligatorio.';
                      field.parentNode.appendChild(errorElem);
                    }
                  } else if (errorElem) {
                    errorElem.remove();
                  }
                });

                if (!requiredFilled) {
                  return;
                }

                if (requiresVerifiedPlayerForCheckout()) {
                  setPlayerVerificationFeedback('danger', 'Debes verificar el nombre del jugador antes de comprar.');
                  return;
                }

                if (couponVal && !couponApplied) {
                  if (modalCouponName) {
                    modalCouponName.textContent = couponVal;
                  }
                  setOverlayVisible(couponModal, true);
                  modalYes.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    applyCouponButton.click();
                    setTimeout(() => submitOrderCreationRequest(options), 150);
                  };
                  modalNo.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    couponApplied = false;
                    couponInput.value = '';
                    setTimeout(() => submitOrderCreationRequest(options), 100);
                  };
                  modalCancel.onclick = function() {
                    setOverlayVisible(couponModal, false);
                  };
                  return;
                }

                let spinner = document.getElementById('spinner-compra');
                if (!spinner) {
                  spinner = document.createElement('span');
                  spinner.id = 'spinner-compra';
                  spinner.innerHTML = `<svg width="22" height="22" viewBox="0 0 50 50" style="vertical-align:middle;"><circle cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/></circle></svg>`;
                  spinner.style.marginLeft = '8px';
                  btn.appendChild(spinner);
                }

                const purchaseQuantity = getOrderQuantity();
                pack.purchaseQuantity = purchaseQuantity;
                const precioFinal = String(normalizeCurrencyAmount(selectedTotalValue, pack.showDecimals));
                const pedidoData = {
                  action: 'create',
                  game_id: "<?= $game['id'] ?>",
                  package_id: pack.id || '',
                  game_name: "<?= $game['nombre'] ?>",
                  pack_name: pack.name || '',
                  pack_amount: pack.cantidad || '',
                  quantity: String(purchaseQuantity),
                  currency: pack.moneda || '',
                  price: precioFinal,
                  pack_base: String(getPackTotalPrice(pack, purchaseQuantity)),
                  user_identifier: userId,
                  player_fields_json: JSON.stringify(playerFields),
                  email: email,
                  coupon: couponApplied ? couponVal : '',
                };

                console.log('Datos enviados a pedidos.php:', pedidoData);
                btn.disabled = true;
                setLoadingModalContent('Procesando pedido...', 'Estamos registrando tu pedido para abrir el formulario de pago.', 'processing');
                setOverlayVisible(loadingModal, true);

                fetch(buildAppUrl('/api/pedidos.php'), {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: Object.keys(pedidoData).map(k => `${encodeURIComponent(k)}=${encodeURIComponent(pedidoData[k])}`).join('&')
                })
                .then(async res => {
                  let data = null;
                  try {
                    data = await res.json();
                  } catch (e) {
                    if (res.ok) {
                      showToast('Pedido registrado correctamente', 'success');
                      resetCheckoutState();
                      return;
                    }
                    showToast('Error de red al registrar pedido', 'error');
                    return;
                  }
                  if (data && data.ok) {
                    if (rememberLastPurchaseIdentifierEnabled && userId) {
                      defaultOrderUserIdentifier = userId;
                    }
                    if (data.win_points && Number.isFinite(Number(data.win_points.balance))) {
                      syncWinPointsSummaryFromResponse(data.win_points, { silent: true });
                    }
                    if (data && data.payment_difference && String(data.payment_difference.status || '').toLowerCase() === 'credit_applied') {
                      setPaymentDifferenceCreditState(null);
                    }
                    const createdOrderTotalText = String((data && data.total_text) || '').trim() || (
                      data && data.payment_difference && String(data.payment_difference.status || '').toLowerCase() === 'credit_applied'
                        ? formatPaymentDifferenceMoney(pack.moneda || monedaActualClave, Number(data.payment_difference.remaining_amount || 0), pack.showDecimals)
                        : selectedPrice.textContent
                    );
                    const opened = openPaymentModal(data.order_id, data.expires_at, data.remaining_seconds, pack, userId, createdOrderTotalText, email);
                    if (opened) {
                      showToast('Pedido registrado. Completa ahora los datos del pago.', 'success');
                    }
                  } else {
                    showToast((data && data.message) ? data.message : 'Error al registrar pedido', 'error');
                  }
                })
                .catch(() => {
                  showToast('Error de red al registrar pedido.', 'error');
                })
                .finally(() => {
                  btn.disabled = false;
                  removeBuySpinner();
                  setOverlayVisible(loadingModal, false);
                });
              }

              orderForm.addEventListener('submit', function(event) {
                event.preventDefault();
                submitOrderCreationRequest({ triggerButton: buyButton });
              });
              </script>
            </section>
<?php
include __DIR__ . "/includes/footer.php";
?>
