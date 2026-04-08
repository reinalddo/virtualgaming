<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  require_once __DIR__ . '/includes/tenant.php';
  tenant_start_session();
}

require_once __DIR__ . '/includes/store_config.php';
require_once __DIR__ . '/includes/home_gallery.php';
require_once __DIR__ . '/includes/payment_methods.php';
require_once __DIR__ . '/includes/google_oauth.php';
require_once __DIR__ . '/includes/win_points.php';

$cfg = store_config_all();
$activeTab = defined('ADMIN_CONFIG_ACTIVE_TAB') ? ADMIN_CONFIG_ACTIVE_TAB : ($_GET['tab'] ?? 'correo');
$startupPopupTabEnabled = store_config_get('inicio_popup_tab_habilitado', '1') === '1';
$rechargeNotificationsTabEnabled = ($cfg['notificaciones_recargas'] ?? '0') === '1';
$allowedTabs = ['correo', 'cabecera', 'sociales', 'api-banco', 'api-free-fire', 'personalizar-colores', 'galeria', 'metodos-pago'];
if ($rechargeNotificationsTabEnabled) {
  $allowedTabs[] = 'notificaciones-recargas';
}
if ($startupPopupTabEnabled) {
  $allowedTabs[] = 'ventana-inicial';
}
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'correo';
}

home_gallery_ensure_table();
payment_methods_ensure_table();
$logoTienda = trim((string) ($cfg['logo_tienda'] ?? ''));
$publicBackgroundSettings = store_config_public_background_settings();
$publicAnimatedBackgroundEnabled = !empty($publicBackgroundSettings['enabled']);
$publicBackgroundMode = $publicBackgroundSettings['mode'];
$publicBackgroundMedia = trim((string) ($publicBackgroundSettings['asset_path'] ?? ''));
$publicBackgroundHasMedia = !empty($publicBackgroundSettings['has_media']);
$publicBackgroundMediaType = trim((string) ($publicBackgroundSettings['media_type'] ?? ''));
$rechargeNotificationsLogo = trim((string) ($cfg['recarga_notificaciones_logo'] ?? ''));
$rechargeNotificationsEffectiveLogo = $rechargeNotificationsLogo !== '' ? $rechargeNotificationsLogo : $logoTienda;
$winPointsNotificationPosition = win_points_notification_position();
$winPointsNotificationPositions = win_points_notification_position_options();
$winPointsNotificationPreviewName = win_points_program_name();
$winPointsNotificationPreviewIcon = win_points_icon_url();
$winPointsEnabled = win_points_enabled();
$galleryItems = home_gallery_all();
$paymentCurrencies = payment_methods_currency_options();
$galleryEditId = isset($_GET['editar_galeria']) ? intval($_GET['editar_galeria']) : 0;
$galleryEditItem = $galleryEditId > 0 ? home_gallery_find($galleryEditId) : null;
$galleryForm = [
    'titulo' => $galleryEditItem['titulo'] ?? '',
    'descripcion1' => $galleryEditItem['descripcion1'] ?? '',
    'descripcion2' => $galleryEditItem['descripcion2'] ?? '',
    'url' => $galleryEditItem['url'] ?? '',
    'abrir_nueva_pestana' => !empty($galleryEditItem['abrir_nueva_pestana']),
    'destacado' => !empty($galleryEditItem['destacado']),
    'imagen' => $galleryEditItem['imagen'] ?? '',
];
  $paymentMethods = payment_methods_all();
  $paymentMethodEditId = isset($_GET['editar_metodo_pago']) ? intval($_GET['editar_metodo_pago']) : 0;
  $paymentMethodEditItem = $paymentMethodEditId > 0 ? payment_methods_find($paymentMethodEditId) : null;
  $paymentMethodForm = [
    'nombre' => $paymentMethodEditItem['nombre'] ?? '',
    'datos' => $paymentMethodEditItem['datos'] ?? '',
    'moneda_id' => isset($paymentMethodEditItem['moneda_id']) ? (int) $paymentMethodEditItem['moneda_id'] : 0,
    'referencia_digitos' => isset($paymentMethodEditItem['referencia_digitos']) ? max(0, (int) $paymentMethodEditItem['referencia_digitos']) : 0,
    'activo' => !array_key_exists('activo', $paymentMethodEditItem ?? []) ? true : !empty($paymentMethodEditItem['activo']),
  ];
$themeDefinitions = store_theme_definitions();
$themeBaseValues = store_theme_base_values();
$themeValues = store_theme_values();
$paymentHeaderMinimalEnabled = ($cfg['encabezado_pago'] ?? '0') === '1';
$paymentWindowConfigEnabled = ($cfg['ventana_pago_config'] ?? '0') === '1';
$paymentWindowSendingTitle = trim((string) ($cfg['ventana_pago_enviando_titulo'] ?? 'Enviando orden...'));
if ($paymentWindowSendingTitle === '') {
  $paymentWindowSendingTitle = 'Enviando orden...';
}
$paymentWindowSendingMessage = trim((string) ($cfg['ventana_pago_enviando_mensaje'] ?? 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.'));
if ($paymentWindowSendingMessage === '') {
  $paymentWindowSendingMessage = 'Estamos registrando tu comprobante y procesando la orden según la moneda del pedido. No cierres esta ventana.';
}
$paymentWindowSuccessTitle = trim((string) ($cfg['ventana_pago_exitoso_titulo'] ?? 'Pago exitoso'));
if ($paymentWindowSuccessTitle === '') {
  $paymentWindowSuccessTitle = 'Pago exitoso';
}
$paymentWindowSuccessExtraMessage = trim((string) ($cfg['ventana_pago_exitoso_mensaje_extra'] ?? ''));
$paymentWindowThemeGroups = [
  'Ventana de pago principal' => [
    'theme_payment_main_overlay_bg',
    'theme_payment_main_modal_bg',
    'theme_payment_main_modal_border',
    'theme_payment_main_title',
    'theme_payment_main_text',
    'theme_payment_main_timer_bg',
    'theme_payment_main_timer_border',
    'theme_payment_main_timer_text',
    'theme_payment_main_card_bg',
    'theme_payment_main_card_border',
    'theme_payment_main_input_bg',
    'theme_payment_main_input_border',
    'theme_payment_main_input_text',
    'theme_payment_main_button_bg',
    'theme_payment_main_button_text',
    'theme_payment_main_cancel_bg',
    'theme_payment_main_cancel_text',
  ],
  'Procesando pedido' => [
    'theme_payment_processing_overlay_bg',
    'theme_payment_processing_modal_bg',
    'theme_payment_processing_modal_border',
    'theme_payment_processing_spinner',
    'theme_payment_processing_title',
    'theme_payment_processing_text',
  ],
  'Enviando orden' => [
    'theme_payment_sending_overlay_bg',
    'theme_payment_sending_modal_bg',
    'theme_payment_sending_modal_border',
    'theme_payment_sending_spinner',
    'theme_payment_sending_title',
    'theme_payment_sending_text',
  ],
  'Pago exitoso y estado final' => [
    'theme_payment_status_overlay_bg',
    'theme_payment_status_modal_bg',
    'theme_payment_status_modal_border',
    'theme_payment_status_text',
    'theme_payment_status_title_info',
    'theme_payment_status_title_success',
    'theme_payment_status_title_danger',
    'theme_payment_status_button_bg',
    'theme_payment_status_button_text',
  ],
];
$paymentWindowThemeKeys = [];
foreach ($paymentWindowThemeGroups as $groupKeys) {
  foreach ($groupKeys as $groupKey) {
    $paymentWindowThemeKeys[] = $groupKey;
  }
}
$themeFieldGroups = [
  'Fondos y paneles' => ['theme_bg_main', 'theme_bg_alt', 'theme_surface', 'theme_surface_alt', 'theme_border'],
  'Neón y acciones' => ['theme_primary', 'theme_highlight', 'theme_secondary', 'theme_success'],
  'Botones y paquetes' => ['theme_button_primary', 'theme_button_secondary', 'theme_button_surface'],
  'Características de juegos' => ['theme_game_feature_bg', 'theme_game_feature_border', 'theme_game_feature_text'],
  'Botones flotantes' => ['theme_float_whatsapp_bg', 'theme_float_whatsapp_text', 'theme_float_channel_bg', 'theme_float_channel_text'],
  'Notificaciones de recargas' => ['theme_live_notification_bg', 'theme_live_notification_border', 'theme_live_notification_accent', 'theme_live_notification_text', 'theme_live_notification_muted'],
  'Ventana inicial' => ['theme_startup_popup_surface', 'theme_startup_popup_border', 'theme_startup_popup_accent', 'theme_startup_popup_chip', 'theme_startup_popup_button_text'],
  'Ventana inicial con video' => ['theme_startup_video_popup_surface', 'theme_startup_video_popup_border', 'theme_startup_video_popup_accent', 'theme_startup_video_popup_button_bg', 'theme_startup_video_popup_button_text'],
];
if ($paymentHeaderMinimalEnabled) {
  $themeFieldGroups['Características de paquetes'] = ['theme_package_feature_bg', 'theme_package_feature_border', 'theme_package_feature_text'];
}
if ($paymentWindowConfigEnabled) {
  foreach ($paymentWindowThemeGroups as $paymentWindowGroupTitle => $paymentWindowGroupKeys) {
    $themeFieldGroups[$paymentWindowGroupTitle] = $paymentWindowGroupKeys;
  }
}
$themeFieldGroups['Textos y estados'] = ['theme_text', 'theme_text_muted', 'theme_price_text', 'theme_price_muted', 'theme_warning', 'theme_danger'];
$startupPopupMode = 'none';
if (($cfg['inicio_popup_video_activo'] ?? '0') === '1') {
  $startupPopupMode = 'video';
} elseif (($cfg['inicio_popup_activo'] ?? '1') === '1') {
  $startupPopupMode = 'normal';
}
$startupPopupVideoUrl = store_config_normalize_youtube_url((string) ($cfg['inicio_popup_video_url'] ?? ''));
$startupPopupChannelUrl = store_config_normalize_social_url((string) ($cfg['whatsapp_channel'] ?? ''));
$startupPopupChannelReady = store_config_is_valid_social_url($startupPopupChannelUrl);
$googleCallbackUrl = google_oauth_callback_url();
?>
<style>
  .neon-card {
    background: #181f2a !important;
    border-radius: 18px !important;
    border: 2px solid #00fff7 !important;
    box-shadow: 0 0 32px #00fff733, 0 0 8px #00fff7;
    color: #00fff7;
    font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif;
  }
  .neon-card .form-label,
  .neon-card .form-check-label,
  .neon-card .form-text,
  .neon-card .table,
  .neon-card .table td,
  .neon-card .table th {
    color: #c9f9ff !important;
  }
  .neon-card .form-control,
  .neon-card .form-select {
    background: #222c3a !important;
    color: #e9fdff !important;
    border: 1px solid #00fff7 !important;
    border-radius: 12px !important;
    box-shadow: 0 0 8px #00fff733;
  }
  .neon-card .form-control:focus,
  .neon-card .form-select:focus {
    border-color: #34d399 !important;
    box-shadow: 0 0 16px #34d39999;
    outline: none;
  }
  .neon-btn {
    background: linear-gradient(90deg, var(--theme-button-primary) 0%, var(--theme-button-secondary) 100%);
    color: var(--theme-button-text) !important;
    font-weight: bold;
    border-radius: 16px !important;
    box-shadow: 0 0 16px rgba(var(--theme-button-primary-rgb), 0.95), 0 0 32px rgba(var(--theme-button-secondary-rgb), 0.6);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border: none;
    transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
  }
  .neon-btn:hover {
    background: linear-gradient(90deg, var(--theme-button-secondary) 0%, var(--theme-button-primary) 100%);
    box-shadow: 0 0 32px rgba(var(--theme-button-primary-rgb), 0.95), 0 0 16px rgba(var(--theme-button-secondary-rgb), 0.6);
    transform: translateY(-1px);
  }
  .neon-tabs-wrap {
    border: 1px solid rgba(34, 211, 238, 0.22);
    border-radius: 20px;
    background: rgba(15, 23, 42, 0.72);
    box-shadow: inset 0 0 0 1px rgba(45, 212, 191, 0.08), 0 0 28px rgba(34, 211, 238, 0.08);
    padding: 0.5rem;
  }
  .neon-tabs-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
  }
  .neon-tabs-item {
    flex: 1 1 220px;
    min-width: 220px;
  }
  .neon-tab-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    border: 1px solid rgba(34, 211, 238, 0.24);
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.76);
    color: #9be7ff;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
  }
  .neon-tab-link:hover {
    color: #d8fbff;
    border-color: rgba(45, 212, 191, 0.58);
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.14);
    transform: translateY(-1px);
  }
  .neon-tab-link.active {
    background: linear-gradient(135deg, rgba(34, 211, 238, 0.22), rgba(52, 211, 153, 0.12));
    color: #ffffff;
    border-color: rgba(34, 211, 238, 0.7);
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.22), inset 0 0 12px rgba(34, 211, 238, 0.08);
  }
  .config-section-note {
    border-radius: 16px;
    border: 1px solid rgba(34, 211, 238, 0.2);
    background: rgba(15, 23, 42, 0.55);
    color: rgba(216, 251, 255, 0.82);
    padding: 1rem;
  }
  .header-logo-preview,
  .gallery-image-preview {
    width: 100%;
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.48);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.9));
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.16);
  }
  .header-logo-preview {
    max-width: 128px;
    aspect-ratio: 1 / 1;
  }
  .gallery-image-preview {
    aspect-ratio: 1280 / 500;
    max-width: none;
  }
  .header-logo-preview img,
  .gallery-image-preview img,
  .gallery-image-preview video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
  }
  .header-logo-empty,
  .gallery-image-empty {
    color: rgba(155, 231, 255, 0.72);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }
  .config-live-notification-card {
    margin-top: 1.5rem;
    border-radius: 20px;
    border: 1px solid rgba(var(--theme-primary-rgb), 0.24);
    background: linear-gradient(180deg, rgba(var(--theme-bg-alt-rgb), 0.84), rgba(var(--theme-surface-rgb), 0.72));
    box-shadow: 0 0 26px rgba(var(--theme-primary-rgb), 0.08);
    padding: 1.25rem;
  }
  .config-live-notification-header {
    display: grid;
    gap: 0.35rem;
    margin-bottom: 1rem;
  }
  .config-live-notification-title {
    margin: 0;
    color: var(--theme-highlight);
    font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif;
    font-size: 1.15rem;
    font-weight: 700;
  }
  .config-live-notification-copy {
    margin: 0;
    color: rgba(var(--theme-text-muted-rgb), 0.94);
  }
  .config-live-notification-actions {
    display: grid;
    gap: 0.75rem;
  }
  .config-live-notification-save-wrap {
    display: flex;
    align-items: flex-end;
    justify-content: flex-end;
  }
  .config-live-notification-save {
    width: min(100%, 220px);
    min-height: 52px;
    border-radius: 14px;
  }
  .config-live-notification-simulate {
    width: 100%;
    min-height: 58px;
    border: 1px solid rgba(var(--theme-border-rgb), 0.7);
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(var(--theme-button-primary-rgb), 0.98), rgba(var(--theme-button-secondary-rgb), 0.92));
    color: var(--theme-button-text-strong) !important;
    box-shadow: 0 16px 32px rgba(var(--theme-button-primary-rgb), 0.24), 0 0 0 1px rgba(var(--theme-primary-rgb), 0.22);
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
  }
  .config-live-notification-simulate:hover,
  .config-live-notification-simulate:focus {
    color: var(--theme-button-text-strong) !important;
    filter: brightness(1.04);
    transform: translateY(-1px);
    box-shadow: 0 20px 38px rgba(var(--theme-button-primary-rgb), 0.3), 0 0 0 1px rgba(var(--theme-highlight-rgb), 0.3);
  }
  .config-live-notification-help {
    margin: 0;
    color: var(--theme-text-muted);
    font-size: 0.88rem;
    line-height: 1.45;
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
    z-index: 1200;
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
  .win-points-live-notification.is-visible[data-position="middle-left"],
  .win-points-live-notification.is-visible[data-position="middle-right"] {
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
  .gallery-table-wrap {
    border: 1px solid rgba(34, 211, 238, 0.2);
    border-radius: 18px;
    background: rgba(15, 23, 42, 0.58);
    padding: 1rem;
    box-shadow: 0 0 24px rgba(34, 211, 238, 0.08);
  }
  .gallery-table-wrap .table {
    margin-bottom: 0;
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(34, 211, 238, 0.04);
    --bs-table-striped-color: #e9fdff;
    --bs-table-border-color: rgba(34, 211, 238, 0.15);
  }
  .gallery-thumb {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid rgba(34, 211, 238, 0.42);
    box-shadow: 0 0 14px rgba(34, 211, 238, 0.16);
    background: #0f172a;
  }
  .gallery-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .gallery-card-mobile {
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.28);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(30, 41, 59, 0.78));
    box-shadow: 0 0 22px rgba(34, 211, 238, 0.08);
    padding: 1rem;
  }
  .gallery-badge-neon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid rgba(34, 211, 238, 0.5);
    padding: 0.25rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #9be7ff;
    background: rgba(34, 211, 238, 0.08);
  }
  .theme-swatch-card {
    height: 100%;
    border-radius: 18px;
    border: 1px solid rgba(var(--theme-primary-rgb), 0.2);
    background: rgba(var(--theme-bg-alt-rgb), 0.58);
    padding: 1rem;
    box-shadow: 0 0 20px rgba(var(--theme-primary-rgb), 0.08);
  }
  .theme-swatch-preview {
    width: 100%;
    height: 4.5rem;
    border-radius: 14px;
    border: 1px solid rgba(var(--theme-text-rgb), 0.14);
    box-shadow: inset 0 0 0 1px rgba(var(--theme-text-rgb), 0.04);
  }
  .theme-swatch-card .form-control-color {
    width: 100%;
    height: 3rem;
    padding: 0.25rem;
    border-radius: 12px;
  }
  .theme-group-title {
    color: var(--theme-highlight);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.85rem;
    font-weight: 700;
  }
  .theme-accordion {
    display: grid;
    gap: 0.9rem;
  }
  .theme-accordion-item {
    border-radius: 18px;
    border: 1px solid rgba(var(--theme-primary-rgb), 0.2);
    background: rgba(var(--theme-bg-alt-rgb), 0.42);
    box-shadow: 0 0 20px rgba(var(--theme-primary-rgb), 0.06);
    overflow: hidden;
  }
  .theme-accordion-trigger {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.15rem;
    border: 0;
    background: linear-gradient(180deg, rgba(var(--theme-bg-alt-rgb), 0.84), rgba(var(--theme-surface-rgb), 0.72));
    color: var(--theme-text);
    text-align: left;
  }
  .theme-accordion-trigger:hover {
    background: linear-gradient(180deg, rgba(var(--theme-bg-alt-rgb), 0.94), rgba(var(--theme-surface-rgb), 0.82));
  }
  .theme-accordion-trigger:focus-visible {
    outline: 2px solid rgba(var(--theme-primary-rgb), 0.88);
    outline-offset: -2px;
  }
  .theme-accordion-trigger::after {
    content: '';
    flex: 0 0 auto;
    width: 0.72rem;
    height: 0.72rem;
    border-right: 2px solid rgba(var(--theme-primary-rgb), 0.92);
    border-bottom: 2px solid rgba(var(--theme-primary-rgb), 0.92);
    transform: rotate(45deg);
    transition: transform 0.18s ease;
    margin-right: 0.2rem;
  }
  .theme-accordion-item.is-open .theme-accordion-trigger::after {
    transform: rotate(-135deg);
    margin-top: 0.3rem;
  }
  .theme-accordion-label {
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
    min-width: 0;
  }
  .theme-accordion-title {
    color: var(--theme-highlight);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.85rem;
    font-weight: 700;
  }
  .theme-accordion-copy {
    color: rgba(var(--theme-text-muted-rgb), 0.92);
    font-size: 0.8rem;
  }
  .theme-accordion-panel {
    display: none;
    padding: 0 1.15rem 1.15rem;
  }
  .theme-accordion-item.is-open .theme-accordion-panel {
    display: block;
  }
  .theme-default-note {
    color: rgba(var(--theme-text-muted-rgb), 0.92);
    font-size: 0.84rem;
  }
  .theme-action-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  .theme-action-row > * {
    flex: 1 1 260px;
  }
  .theme-reset-btn {
    border-radius: 16px !important;
    min-height: 60px;
    border: 1px solid rgba(var(--theme-warning-rgb), 0.5) !important;
    background: rgba(var(--theme-warning-rgb), 0.12) !important;
    color: var(--theme-text) !important;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    box-shadow: 0 0 16px rgba(var(--theme-warning-rgb), 0.16);
  }
  .theme-reset-btn:hover {
    background: rgba(var(--theme-warning-rgb), 0.2) !important;
    border-color: rgba(var(--theme-warning-rgb), 0.72) !important;
  }
  .neon-card {
    background: var(--theme-surface-alt) !important;
    border-color: var(--theme-highlight) !important;
    box-shadow: 0 0 32px rgba(var(--theme-highlight-rgb), 0.2), 0 0 8px rgba(var(--theme-highlight-rgb), 0.95);
    color: var(--theme-highlight);
  }
  .neon-card .form-control,
  .neon-card .form-select {
    background: rgba(var(--theme-bg-alt-rgb), 0.92) !important;
    color: var(--theme-text) !important;
    border-color: var(--theme-highlight) !important;
    box-shadow: 0 0 8px rgba(var(--theme-highlight-rgb), 0.2);
  }
  .neon-card .form-control:focus,
  .neon-card .form-select:focus {
    border-color: var(--theme-success) !important;
    box-shadow: 0 0 16px rgba(var(--theme-success-rgb), 0.6);
  }
  .neon-tabs-wrap,
  .config-section-note,
  .gallery-table-wrap,
  .gallery-card-mobile,
  .header-logo-preview,
  .gallery-image-preview,
  .gallery-thumb,
  .gallery-badge-neon,
  .neon-tab-link,
  .neon-tab-link.active,
  .neon-tab-link:hover {
    border-color: rgba(var(--theme-primary-rgb), 0.24) !important;
  }
  @media (max-width: 575.98px) {
    .neon-tabs-item {
      min-width: 100%;
    }
    .config-live-notification-save {
      width: 100%;
    }
    .win-points-live-notification {
      width: auto;
      max-width: none;
    }
    .win-points-live-notification[data-position="bottom-left"],
    .win-points-live-notification[data-position="bottom-center"],
    .win-points-live-notification[data-position="bottom-right"] {
      left: 0.5rem;
      right: 0.5rem;
      bottom: calc(0.75rem + env(safe-area-inset-bottom));
      width: auto;
      transform: translate3d(0, 18px, 0);
    }
    .win-points-live-notification.is-visible[data-position="bottom-left"],
    .win-points-live-notification.is-visible[data-position="bottom-center"],
    .win-points-live-notification.is-visible[data-position="bottom-right"] {
      transform: translate3d(0, 0, 0);
    }
    .win-points-live-notification[data-position="top-left"],
    .win-points-live-notification[data-position="top-center"],
    .win-points-live-notification[data-position="top-right"] {
      left: 0.5rem;
      right: 0.5rem;
      top: calc(0.75rem + env(safe-area-inset-top));
      width: auto;
      transform: translate3d(0, -18px, 0);
    }
    .win-points-live-notification.is-visible[data-position="top-left"],
    .win-points-live-notification.is-visible[data-position="top-center"],
    .win-points-live-notification.is-visible[data-position="top-right"] {
      transform: translate3d(0, 0, 0);
    }
    .win-points-live-notification[data-position="middle-left"],
    .win-points-live-notification[data-position="middle-right"] {
      left: 0.5rem;
      right: 0.5rem;
      top: 50%;
      width: auto;
    }
  }
</style>
<div class="container mt-5 mb-5">
  <div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
      <div class="neon-tabs-wrap mb-4">
        <div class="neon-tabs-grid">
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=correo" class="neon-tab-link <?= $activeTab === 'correo' ? 'active' : '' ?>">Configuración de correo</a>
          </div>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=cabecera" class="neon-tab-link <?= $activeTab === 'cabecera' ? 'active' : '' ?>">Datos de cabecera</a>
          </div>
          <?php if ($rechargeNotificationsTabEnabled): ?>
            <div class="neon-tabs-item">
              <a href="/admin/configuracion?tab=notificaciones-recargas" class="neon-tab-link <?= $activeTab === 'notificaciones-recargas' ? 'active' : '' ?>">Notificaciones Recargas</a>
            </div>
          <?php endif; ?>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=sociales" class="neon-tab-link <?= $activeTab === 'sociales' ? 'active' : '' ?>">Redes Sociales</a>
          </div>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=api-banco" class="neon-tab-link <?= $activeTab === 'api-banco' ? 'active' : '' ?>">Datos conexión Banco</a>
          </div>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=api-free-fire" class="neon-tab-link <?= $activeTab === 'api-free-fire' ? 'active' : '' ?>">Datos API</a>
          </div>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=personalizar-colores" class="neon-tab-link <?= $activeTab === 'personalizar-colores' ? 'active' : '' ?>">Personalizar Colores</a>
          </div>
          <?php if ($startupPopupTabEnabled): ?>
            <div class="neon-tabs-item">
              <a href="/admin/configuracion?tab=ventana-inicial" class="neon-tab-link <?= $activeTab === 'ventana-inicial' ? 'active' : '' ?>">Ventana Inicial</a>
            </div>
          <?php endif; ?>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=galeria" class="neon-tab-link <?= $activeTab === 'galeria' ? 'active' : '' ?>">Galería</a>
          </div>
          <div class="neon-tabs-item">
            <a href="/admin/configuracion?tab=metodos-pago" class="neon-tab-link <?= $activeTab === 'metodos-pago' ? 'active' : '' ?>">Métodos de Pago</a>
          </div>
        </div>
      </div>

      <div class="card neon-card mb-4">
        <div class="card-header text-center py-4" style="background: linear-gradient(90deg, var(--theme-highlight) 0%, var(--theme-success) 100%); color: var(--theme-button-text-strong); border-radius: 16px 16px 0 0;">
          <h2 class="h4 fw-bold mb-0" style="font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif; letter-spacing: 0.08em;">
            <?php if ($activeTab === 'correo'): ?>Configuración de correo corporativo<?php elseif ($activeTab === 'cabecera'): ?>Datos de cabecera<?php elseif ($activeTab === 'notificaciones-recargas'): ?>Notificaciones Recargas<?php elseif ($activeTab === 'sociales'): ?>Redes Sociales<?php elseif ($activeTab === 'api-banco'): ?>Datos conexión Banco<?php elseif ($activeTab === 'api-free-fire'): ?>Datos API<?php elseif ($activeTab === 'personalizar-colores'): ?>Personalizar Colores<?php elseif ($activeTab === 'ventana-inicial'): ?>Ventana Inicial<?php elseif ($activeTab === 'galeria'): ?>Galería principal del index<?php else: ?>Métodos de Pago<?php endif; ?>
          </h2>
        </div>
        <div class="card-body p-4">
          <?php if ($activeTab === 'correo'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="correo">
              <div class="config-section-note mb-4">Configura aquí el correo corporativo y los parámetros SMTP usados por la tienda.</div>
              <div class="mb-3">
                <label class="form-label">Correo corporativo</label>
                <input type="email" name="correo_corporativo" value="<?= htmlspecialchars($cfg['correo_corporativo'] ?? '') ?>" required class="form-control" placeholder="correo@tudominio.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>" required class="form-control" placeholder="smtp.tuservidor.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP User</label>
                <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>" required class="form-control" placeholder="usuario@tudominio.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP Password</label>
                <input type="password" name="smtp_pass" value="<?= htmlspecialchars($cfg['smtp_pass'] ?? '') ?>" class="form-control" placeholder="••••••••">
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">SMTP Port</label>
                  <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port'] ?? 587) ?>" required class="form-control" placeholder="587">
                </div>
                <div class="col-md-6">
                  <label class="form-label">SMTP Secure</label>
                  <select name="smtp_secure" class="form-select">
                    <option value="tls" <?= ($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($cfg['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                  </select>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar configuración de correo</button>
            </form>
          <?php elseif ($activeTab === 'cabecera'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="config_section" value="cabecera">
              <div class="config-section-note mb-4">Controla el prefijo, nombre y logo de la tienda. El mismo logo también se usa como favicon.</div>
              <div class="row g-4 align-items-start">
                <div class="col-md-8">
                  <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="googleAnalyticsActivo" name="google_analytics_activo" value="1" <?= ($cfg['google_analytics_activo'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="googleAnalyticsActivo">Insertar script de Google Analytics en el footer público</label>
                  </div>
                  <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="instruccionesInfluencerActivas" name="instrucciones_influencer" value="1" <?= ($cfg['instrucciones_influencer'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="instruccionesInfluencerActivas">Activar modulo Instrucciones Influencer en menu publico y admin</label>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Nombre Prefijo</label>
                    <input type="text" name="nombre_prefijo" value="<?= htmlspecialchars($cfg['nombre_prefijo'] ?? 'TIENDA') ?>" required class="form-control" placeholder="TIENDA">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Nombre Tienda</label>
                    <input type="text" name="nombre_tienda" value="<?= htmlspecialchars($cfg['nombre_tienda'] ?? 'TVirtualGaming') ?>" required class="form-control" placeholder="TVirtualGaming">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Subtítulo del navegador / instalación</label>
                    <input type="text" name="nombre_tienda_subtitulo" value="<?= htmlspecialchars($cfg['nombre_tienda_subtitulo'] ?? 'Tienda de monedas digitales') ?>" required class="form-control" placeholder="Tienda de monedas digitales">
                    <div class="form-text mt-2">Este texto se usa en el título del inicio y puede aparecer en el aviso de instalar la app en el navegador.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Meta título SEO</label>
                    <input type="text" name="meta_titulo" value="<?= htmlspecialchars($cfg['meta_titulo'] ?? 'TVirtualGaming | Tienda de monedas digitales') ?>" required maxlength="160" class="form-control" placeholder="TVirtualGaming | Tienda de monedas digitales">
                    <div class="form-text mt-2">Este título se usa en la etiqueta title, en Google y al compartir en redes. Idealmente mantenlo entre 50 y 60 caracteres.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Meta descripción SEO</label>
                    <textarea name="meta_descripcion" rows="4" required maxlength="320" class="form-control" placeholder="Describe la tienda en una o dos frases para Google y redes sociales."><?= htmlspecialchars($cfg['meta_descripcion'] ?? 'Compra monedas y recargas digitales en TVirtualGaming. Recibe ofertas, promociones y novedades directamente en tu WhatsApp.') ?></textarea>
                    <div class="form-text mt-2">Google suele usar este texto como descripción del resultado de búsqueda. Intenta mantenerlo entre 140 y 160 caracteres para un snippet más estable.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Script de Google Analytics / Google Tag</label>
                    <textarea name="google_analytics_script" rows="7" class="form-control" placeholder="Pega aquí el código completo que te entrega Google, incluyendo las etiquetas <script>."><?= htmlspecialchars($cfg['google_analytics_script'] ?? '') ?></textarea>
                    <div class="form-text mt-2">Si el interruptor está activo y este campo tiene contenido, el script se insertará al final del sitio, antes de cerrar el body.</div>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Logo tienda</label>
                    <input type="file" name="logo_tienda" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control">
                    <div class="form-text mt-2">Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 2 MB.</div>
                  </div>
                  <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="eliminarLogoTienda" name="eliminar_logo_tienda">
                    <label class="form-check-label" for="eliminarLogoTienda">Eliminar logo actual</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label d-block">Vista previa del logo</label>
                  <div class="header-logo-preview">
                    <?php if ($logoTienda !== ''): ?>
                      <img src="<?= htmlspecialchars($logoTienda, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de la tienda">
                    <?php else: ?>
                      <span class="header-logo-empty">Sin logo</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php if ($publicAnimatedBackgroundEnabled): ?>
                <hr class="my-4 border-info-subtle">
                <div class="row g-4 align-items-start">
                  <div class="col-md-8">
                    <div class="mb-3">
                      <label class="form-label">Modo de fondo del sitio público</label>
                      <select name="fondo_publico_modo" class="form-select">
                        <option value="normal" <?= $publicBackgroundMode === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="media" <?= $publicBackgroundMode === 'media' ? 'selected' : '' ?>>Multimedia fija</option>
                      </select>
                      <div class="form-text mt-2">En modo Normal la tienda mantiene exactamente el fondo actual. En Multimedia fija se mostrará una imagen, GIF o video en toda la página pública, fijo durante el scroll.</div>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Archivo de fondo multimedia</label>
                      <input type="file" name="fondo_publico_media" accept="image/png,image/jpeg,image/webp,image/gif,video/mp4,video/webm,video/ogg" class="form-control">
                      <div class="form-text mt-2">Formatos permitidos: MP4, WEBM, OGG, JPG, PNG, WEBP o GIF. Tamaño máximo: 25 MB.</div>
                    </div>
                    <div class="form-check mt-3">
                      <input class="form-check-input" type="checkbox" value="1" id="eliminarFondoPublicoMedia" name="eliminar_fondo_publico_media">
                      <label class="form-check-label" for="eliminarFondoPublicoMedia">Eliminar fondo multimedia actual</label>
                    </div>
                    <div class="row g-3 mt-1">
                      <div class="col-md-6">
                        <label class="form-label">Color de overlay</label>
                        <input type="color" name="fondo_publico_overlay_color" value="<?= htmlspecialchars($publicBackgroundSettings['overlay_color'] ?? '#081018', ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-color">
                        <div class="form-text mt-2">Capa colocada sobre el fondo para conservar legibilidad sin tapar del todo el archivo.</div>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Opacidad del overlay (%)</label>
                        <input type="number" name="fondo_publico_overlay_opacity" min="0" max="100" step="1" value="<?= (int) ($publicBackgroundSettings['overlay_opacity'] ?? 52) ?>" class="form-control">
                      </div>
                    </div>
                    <div class="form-check form-switch mt-4 mb-3">
                      <input class="form-check-input" type="checkbox" role="switch" id="fondoPublicoAudioActivo" name="fondo_publico_audio_activo" value="1" <?= !empty($publicBackgroundSettings['sound_enabled']) ? 'checked' : '' ?>>
                      <label class="form-check-label" for="fondoPublicoAudioActivo">Intentar reproducir audio cuando el fondo sea un video</label>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Volumen del video (%)</label>
                      <input type="number" name="fondo_publico_volumen" min="0" max="100" step="1" value="<?= (int) ($publicBackgroundSettings['volume'] ?? 35) ?>" class="form-control">
                      <div class="form-text mt-2">Los navegadores pueden bloquear el autoplay con sonido hasta que el usuario interactúe con la página. El sistema hará el intento automáticamente.</div>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label d-block">Vista previa del fondo público</label>
                    <div class="gallery-image-preview">
                      <?php if ($publicBackgroundHasMedia && $publicBackgroundMediaType === 'video'): ?>
                        <video src="<?= htmlspecialchars($publicBackgroundMedia, ENT_QUOTES, 'UTF-8') ?>" muted loop autoplay playsinline></video>
                      <?php elseif ($publicBackgroundHasMedia): ?>
                        <img src="<?= htmlspecialchars($publicBackgroundMedia, ENT_QUOTES, 'UTF-8') ?>" alt="Fondo multimedia público">
                      <?php else: ?>
                        <span class="gallery-image-empty">Sin fondo multimedia</span>
                      <?php endif; ?>
                    </div>
                    <div class="form-text mt-3">
                      <?= $publicBackgroundMode === 'media' ? 'El fondo multimedia solo se mostrará en páginas públicas; el panel admin seguirá usando el fondo normal.' : 'Actualmente la tienda pública seguirá usando el fondo normal del tema.' ?>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar datos de cabecera</button>
            </form>
          <?php elseif ($activeTab === 'notificaciones-recargas'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="config_section" value="notificaciones-recargas">
              <div class="config-section-note mb-4">Activa o desactiva los avisos globales de recargas, y define un logo exclusivo para la notificación. Si no subes uno, se usará el logo configurado en Datos de cabecera.</div>
              <div class="row g-4 align-items-start">
                <div class="col-md-8">
                  <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="recargaNotificacionesActivas" name="recarga_notificaciones_activas" value="1" <?= ($cfg['recarga_notificaciones_activas'] ?? '1') !== '0' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="recargaNotificacionesActivas">Mostrar notificaciones de recargas en todo el sitio público</label>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Logo de la notificación</label>
                    <input type="file" name="recarga_notificaciones_logo" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control">
                    <div class="form-text mt-2">Si lo dejas vacío, la notificación usará automáticamente el logo principal de la tienda.</div>
                  </div>
                  <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="eliminarLogoNotificacionRecarga" name="eliminar_recarga_notificaciones_logo">
                    <label class="form-check-label" for="eliminarLogoNotificacionRecarga">Eliminar logo propio y volver a usar el logo principal</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label d-block">Vista previa del logo</label>
                  <div class="header-logo-preview">
                    <?php if ($rechargeNotificationsEffectiveLogo !== ''): ?>
                      <img src="<?= htmlspecialchars($rechargeNotificationsEffectiveLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de notificación de recarga">
                    <?php else: ?>
                      <span class="header-logo-empty">Sin logo</span>
                    <?php endif; ?>
                  </div>
                  <div class="form-text mt-3">
                    <?= $rechargeNotificationsLogo !== '' ? 'Se está usando un logo exclusivo para esta notificación.' : 'Actualmente se usará el logo principal configurado en la cabecera.' ?>
                  </div>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar notificaciones de recargas</button>
            </form>

            <div class="config-live-notification-card">
              <div class="config-live-notification-header">
                <h3 class="config-live-notification-title">Posición de notificación de Win Points</h3>
                <p class="config-live-notification-copy">Configura dónde aparecerá la notificación flotante de Win Points en la página pública usando el mismo estilo visual definido en este módulo.</p>
                <?php if (!$winPointsEnabled): ?>
                  <p class="config-live-notification-copy">Win Points está desactivado actualmente. Puedes dejar esta posición configurada desde ahora para cuando el módulo esté activo.</p>
                <?php endif; ?>
              </div>

              <form method="post" class="row g-3" data-win-points-notification-config>
                <input type="hidden" name="config_section" value="notificaciones-recargas">
                <input type="hidden" name="save_win_points_notification_position" value="1">
                <div class="col-lg-8">
                  <label class="form-label">Posición de la notificación</label>
                  <select name="win_points_notification_position" class="form-select" data-win-points-notification-position-select>
                    <?php foreach ($winPointsNotificationPositions as $positionValue => $positionLabel): ?>
                      <option value="<?= htmlspecialchars($positionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $winPointsNotificationPosition === $positionValue ? 'selected' : '' ?>><?= htmlspecialchars($positionLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text mt-2">La simulación usa la posición seleccionada y el estilo visual configurado en Personalizar Colores → Notificaciones de recargas.</div>
                </div>
                <div class="col-lg-4 config-live-notification-save-wrap">
                  <button type="submit" class="btn neon-btn px-4 py-2 config-live-notification-save">Guardar posición</button>
                </div>
                <div class="col-12">
                  <div class="config-live-notification-actions">
                    <button type="button" class="btn fw-bold config-live-notification-simulate" data-win-points-simulate-notification>Simular Notificación</button>
                    <p class="config-live-notification-help">Este botón muestra al instante una vista previa exacta de cómo aparecerá la notificación de Win Points en la página pública con la posición seleccionada y los colores definidos en Notificaciones de recargas.</p>
                  </div>
                </div>
              </form>
            </div>
          <?php elseif ($activeTab === 'sociales'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="sociales">
              <div class="config-section-note mb-4">Registra los enlaces oficiales de la tienda para mostrarlos o reutilizarlos desde otras secciones del sitio.</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Facebook</label>
                  <input type="url" name="facebook" value="<?= htmlspecialchars($cfg['facebook'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://facebook.com/tupagina">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Instagram</label>
                  <input type="url" name="instagram" value="<?= htmlspecialchars($cfg['instagram'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://instagram.com/tucuenta">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Whatsapp</label>
                  <input type="tel" name="whatsapp" value="<?= htmlspecialchars($cfg['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="+584121234567" pattern="^\+?[1-9]\d{9,14}$" inputmode="tel">
                  <div class="form-text">Ingresa solo el número en formato internacional, con código de país y sin enlaces. Ejemplo: +584121234567.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Whatsapp Channel</label>
                  <input type="url" name="whatsapp_channel" value="<?= htmlspecialchars($cfg['whatsapp_channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://whatsapp.com/channel/...">
                </div>
                <div class="col-12">
                  <label class="form-label">Mensaje del botón de Whatsapp</label>
                  <textarea name="mensaje_whatsapp" rows="3" class="form-control" placeholder="Hola, quiero información sobre sus productos."><?= htmlspecialchars($cfg['mensaje_whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                  <div class="form-text">Este texto se enviará automáticamente al abrir el flotante de WhatsApp.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Google Client ID</label>
                  <input type="text" name="google_client_id" value="<?= htmlspecialchars($cfg['google_client_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="xxxxxxxx.apps.googleusercontent.com">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Google Client Secret</label>
                  <input type="password" name="google_client_secret" value="<?= htmlspecialchars($cfg['google_client_secret'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="GOCSPX-...">
                </div>
                <div class="col-12">
                  <label class="form-label">Callback autorizado para Google Cloud</label>
                  <input type="text" value="<?= htmlspecialchars($googleCallbackUrl, ENT_QUOTES, 'UTF-8') ?>" class="form-control" readonly>
                  <div class="form-text">Copia esta URL exactamente en Google Cloud Console > OAuth 2.0 Client IDs > Authorized redirect URIs.</div>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar redes sociales</button>
            </form>
          <?php elseif ($activeTab === 'api-banco'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="api-banco">
              <div class="config-section-note mb-4">Configura aquí los datos de conexión al banco usados para verificar automáticamente los pagos.</div>

              <?php
                $bankPreviewBaseUrl = store_config_normalize_bank_api_base_url((string) ($cfg['ff_bank_api_base_url'] ?? 'https://pagonorte.net'));
                if ($bankPreviewBaseUrl === '') {
                  $bankPreviewBaseUrl = 'https://pagonorte.net';
                }
                $bankPreviewUrl = store_config_build_bank_movements_url($bankPreviewBaseUrl, [
                  'posicion' => trim((string) ($cfg['ff_bank_posicion'] ?? '')),
                  'token' => trim((string) ($cfg['ff_bank_token'] ?? '')),
                  'password' => trim((string) ($cfg['ff_bank_clave'] ?? '')),
                ]);
              ?>

              <?php $bankAvailableDays = trim((string) ($cfg['ff_bank_dias_disponibles'] ?? '')); ?>
              <?php if ($bankAvailableDays !== ''): ?>
                <div class="alert alert-info rounded-4 mb-4" role="status">
                  La API bancaria reporta actualmente <strong><?= htmlspecialchars($bankAvailableDays, ENT_QUOTES, 'UTF-8') ?> días disponibles</strong> en la consulta de movimientos. Este dato se actualiza cuando se verifica un pago o se sincronizan movimientos manualmente.
                </div>
              <?php endif; ?>

              <div class="gallery-table-wrap mb-2">
                <h3 class="h5 fw-bold text-info mb-3">Datos para conexión al banco</h3>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">Enlace base API banco</label>
                    <input type="url" name="ff_bank_api_base_url" value="<?= htmlspecialchars($cfg['ff_bank_api_base_url'] ?? 'https://pagonorte.net', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://pagonorte.net">
                    <div class="form-text">Por defecto usa Pagonorte, pero aquí puedes colocar la IP o dominio base del servidor bancario.</div>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Posicion</label>
                    <select name="ff_bank_posicion" class="form-select">
                      <option value="" <?= (string) ($cfg['ff_bank_posicion'] ?? '') === '' ? 'selected' : '' ?>>Selecciona una posicion</option>
                      <?php for ($position = 0; $position <= 5; $position++): ?>
                        <option value="<?= $position ?>" <?= (string) ($cfg['ff_bank_posicion'] ?? '') === (string) $position ? 'selected' : '' ?>><?= $position ?></option>
                      <?php endfor; ?>
                    </select>
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Token</label>
                    <input type="text" name="ff_bank_token" value="<?= htmlspecialchars($cfg['ff_bank_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Clave</label>
                    <input type="text" name="ff_bank_clave" value="<?= htmlspecialchars($cfg['ff_bank_clave'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" pattern="^[A-Za-z0-9._!-]+$">
                    <div class="form-text">Solo letras, números y estos caracteres especiales: . - _ ! sin espacios.</div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Enlace final enviado a la API</label>
                    <input type="text" id="bank-api-preview-url" value="<?= htmlspecialchars($bankPreviewUrl, ENT_QUOTES, 'UTF-8') ?>" class="form-control" readonly onclick="this.select()">
                    <div class="form-text">Este campo es solo de lectura y se actualiza automáticamente con el enlace exacto que se enviará a la API bancaria.</div>
                  </div>
                </div>
              </div>

              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar datos de conexión del banco</button>
            </form>
            <script>
              (function() {
                const baseInput = document.querySelector('input[name="ff_bank_api_base_url"]');
                const positionInput = document.querySelector('select[name="ff_bank_posicion"]');
                const tokenInput = document.querySelector('input[name="ff_bank_token"]');
                const passwordInput = document.querySelector('input[name="ff_bank_clave"]');
                const previewInput = document.getElementById('bank-api-preview-url');

                if (!baseInput || !positionInput || !tokenInput || !passwordInput || !previewInput) {
                  return;
                }

                const buildPreviewUrl = function() {
                  let baseUrl = String(baseInput.value || '').trim();
                  if (baseUrl === '') {
                    baseUrl = 'https://pagonorte.net';
                  }
                  baseUrl = baseUrl.replace(/\/+$/, '');
                  let endpointUrl = baseUrl;
                  try {
                    const parsedUrl = new URL(baseUrl);
                    endpointUrl = /\.jsp$/i.test(parsedUrl.pathname || '')
                      ? parsedUrl.toString().replace(/\?$/, '')
                      : (baseUrl + '/recargas/movimientos.jsp');
                  } catch (error) {
                    endpointUrl = /\.jsp$/i.test(baseUrl)
                      ? baseUrl
                      : (baseUrl + '/recargas/movimientos.jsp');
                  }
                  const query = new URLSearchParams({
                    posicion: String(positionInput.value || '').trim(),
                    token: String(tokenInput.value || '').trim(),
                    password: String(passwordInput.value || '').trim()
                  });
                  previewInput.value = endpointUrl + '?' + query.toString();
                };

                [baseInput, positionInput, tokenInput, passwordInput].forEach((field) => {
                  field.addEventListener('input', buildPreviewUrl);
                  field.addEventListener('change', buildPreviewUrl);
                });

                buildPreviewUrl();
              })();
            </script>
          <?php elseif ($activeTab === 'api-free-fire'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="api-free-fire">
              <div class="config-section-note mb-4">Registra aquí tu API KEY para la integración automática de recargas.</div>

              <div class="gallery-table-wrap mb-2">
                <h3 class="h5 fw-bold text-info mb-3">Datos API</h3>
                <div class="row g-3">
                  <div class="col-12">
                    <label class="form-label">API KEY</label>
                    <input type="text" name="recargas_api_key" value="<?= htmlspecialchars($cfg['recargas_api_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Pega aquí tu API KEY">
                  </div>
                </div>
              </div>

              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar datos API</button>
            </form>
          <?php elseif ($activeTab === 'personalizar-colores'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="personalizar-colores">
              <?php if (!$paymentHeaderMinimalEnabled): ?>
                <input type="hidden" name="theme_package_feature_bg" value="<?= htmlspecialchars($themeValues['theme_package_feature_bg'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="theme_package_feature_border" value="<?= htmlspecialchars($themeValues['theme_package_feature_border'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="theme_package_feature_text" value="<?= htmlspecialchars($themeValues['theme_package_feature_text'], ENT_QUOTES, 'UTF-8') ?>">
              <?php endif; ?>
              <?php if (!$paymentWindowConfigEnabled): ?>
                <?php foreach ($paymentWindowThemeKeys as $paymentWindowThemeKey): ?>
                  <input type="hidden" name="<?= htmlspecialchars($paymentWindowThemeKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($themeValues[$paymentWindowThemeKey], ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
              <?php endif; ?>
              <div class="config-section-note mb-4">Los valores `theme_*` quedan como base fija. Aquí solo editas una copia activa de esa paleta. Si el cliente quiere volver al diseño original, puedes restaurar la copia editable desde los valores base.</div>
              <div class="theme-accordion" data-theme-accordion>
                <?php foreach ($themeFieldGroups as $groupTitle => $groupKeys): ?>
                  <div class="theme-accordion-item" data-theme-accordion-item data-theme-accordion-key="<?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="theme-accordion-trigger" data-theme-accordion-trigger aria-expanded="false">
                      <span class="theme-accordion-label">
                        <span class="theme-accordion-title"><?= htmlspecialchars($groupTitle, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="theme-accordion-copy"><?= count($groupKeys) ?> colores disponibles en esta sección.</span>
                      </span>
                    </button>
                    <div class="theme-accordion-panel" data-theme-accordion-panel>
                      <div class="row g-3">
                        <?php foreach ($groupKeys as $themeKey): ?>
                          <?php $definition = $themeDefinitions[$themeKey]; ?>
                          <div class="col-md-6 col-xl-4">
                            <div class="theme-swatch-card">
                              <div class="theme-swatch-preview mb-3" style="background: <?= htmlspecialchars($themeValues[$themeKey], ENT_QUOTES, 'UTF-8') ?>;"></div>
                              <label class="form-label fw-semibold"><?= htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') ?></label>
                              <input type="color" name="<?= htmlspecialchars($themeKey, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($themeValues[$themeKey], ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-color mb-2">
                              <div class="small text-info mb-1">Editable: <?= htmlspecialchars($themeValues[$themeKey], ENT_QUOTES, 'UTF-8') ?></div>
                              <div class="theme-default-note mb-2">Base fija: <?= htmlspecialchars($themeBaseValues[$themeKey], ENT_QUOTES, 'UTF-8') ?></div>
                              <div class="form-text"><?= htmlspecialchars($definition['description'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                        <?php if ($paymentWindowConfigEnabled && $groupTitle === 'Enviando orden'): ?>
                          <div class="col-12">
                            <div class="theme-swatch-card">
                              <label class="form-label fw-semibold">Texto principal del modal</label>
                              <input type="text" name="ventana_pago_enviando_titulo" value="<?= htmlspecialchars($paymentWindowSendingTitle, ENT_QUOTES, 'UTF-8') ?>" class="form-control mb-3" maxlength="120" placeholder="Enviando orden...">
                              <div class="form-text mb-3">Este texto reemplaza el título del modal mostrado mientras se envía la orden.</div>
                              <label class="form-label fw-semibold">Texto explicativo debajo del título</label>
                              <textarea name="ventana_pago_enviando_mensaje" class="form-control" rows="3" maxlength="500" placeholder="Explica aquí qué está ocurriendo mientras se procesa la orden."><?= htmlspecialchars($paymentWindowSendingMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                              <div class="form-text">Este texto se mostrará debajo del título del modal Enviando orden.</div>
                            </div>
                          </div>
                        <?php endif; ?>
                        <?php if ($paymentWindowConfigEnabled && $groupTitle === 'Pago exitoso y estado final'): ?>
                          <div class="col-12">
                            <div class="theme-swatch-card">
                              <label class="form-label fw-semibold">Título cuando el pago es exitoso</label>
                              <input type="text" name="ventana_pago_exitoso_titulo" value="<?= htmlspecialchars($paymentWindowSuccessTitle, ENT_QUOTES, 'UTF-8') ?>" class="form-control mb-3" maxlength="120" placeholder="Pago exitoso">
                              <div class="form-text mb-3">Este texto reemplaza el título del modal final cuando la operación termina con éxito.</div>
                              <label class="form-label fw-semibold">Texto adicional debajo del mensaje principal</label>
                              <textarea name="ventana_pago_exitoso_mensaje_extra" class="form-control" rows="3" maxlength="500" placeholder="Agrega aquí un texto complementario opcional para el cliente."><?= htmlspecialchars($paymentWindowSuccessExtraMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                              <div class="form-text">Este texto aparecerá debajo del mensaje principal del modal final solo cuando el pago sea exitoso.</div>
                            </div>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="theme-action-row">
                <button type="submit" class="neon-btn py-3">Guardar paleta de colores</button>
                <button type="submit" name="restore_theme_defaults" value="1" class="btn theme-reset-btn" onclick="return confirm('Esto reemplazará la paleta editable actual por los valores base. ¿Deseas continuar?');">Restaurar a default</button>
              </div>
            </form>
            <script>
              (function () {
                const accordion = document.querySelector('[data-theme-accordion]');
                if (!accordion) {
                  return;
                }

                const items = Array.from(accordion.querySelectorAll('[data-theme-accordion-item]'));
                const storageKey = 'theme-color-accordion-open:' + window.location.pathname;
                const closeAllItems = () => {
                  items.forEach((item) => {
                    const trigger = item.querySelector('[data-theme-accordion-trigger]');
                    item.classList.remove('is-open');
                    if (trigger) {
                      trigger.setAttribute('aria-expanded', 'false');
                    }
                  });
                };
                const setOpenItem = (targetItem) => {
                  closeAllItems();
                  if (!targetItem) {
                    sessionStorage.removeItem(storageKey);
                    return;
                  }

                  const trigger = targetItem.querySelector('[data-theme-accordion-trigger]');
                  targetItem.classList.add('is-open');
                  if (trigger) {
                    trigger.setAttribute('aria-expanded', 'true');
                  }
                  sessionStorage.setItem(storageKey, targetItem.getAttribute('data-theme-accordion-key') || '');
                };

                items.forEach((item) => {
                  const trigger = item.querySelector('[data-theme-accordion-trigger]');
                  if (!trigger) {
                    return;
                  }

                  trigger.addEventListener('click', function () {
                    if (item.classList.contains('is-open')) {
                      setOpenItem(null);
                      return;
                    }
                    setOpenItem(item);
                  });
                });

                const savedKey = sessionStorage.getItem(storageKey);
                if (!savedKey) {
                  closeAllItems();
                  return;
                }

                const savedItem = items.find((item) => item.getAttribute('data-theme-accordion-key') === savedKey);
                if (savedItem) {
                  setOpenItem(savedItem);
                } else {
                  closeAllItems();
                }
              }());
            </script>
          <?php elseif ($activeTab === 'ventana-inicial'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="ventana-inicial">
              <div class="config-section-note mb-4">Controla la ventana emergente inicial del index. Puedes mostrar la ventana normal, la ventana con video o ninguna, pero nunca ambas al mismo tiempo. El botón principal usa automáticamente el enlace configurado en Redes Sociales, en el campo Whatsapp Channel.</div>
              <div class="row g-4 align-items-start">
                <div class="col-lg-7">
                  <div class="gallery-table-wrap">
                    <div class="mb-4">
                      <label class="form-label d-block">Tipo de ventana inicial</label>
                      <div class="d-grid gap-3">
                        <label class="rounded-4 border p-3" style="border-color: rgba(34, 211, 238, 0.24); background: rgba(15, 23, 42, 0.48);">
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="inicio_popup_modo" id="inicioPopupModeNone" value="none" <?= $startupPopupMode === 'none' ? 'checked' : '' ?>>
                            <span class="form-check-label fw-semibold">No mostrar ninguna ventana inicial</span>
                          </div>
                        </label>
                        <label class="rounded-4 border p-3" style="border-color: rgba(34, 211, 238, 0.24); background: rgba(15, 23, 42, 0.48);">
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="inicio_popup_modo" id="inicioPopupModeNormal" value="normal" <?= $startupPopupMode === 'normal' ? 'checked' : '' ?>>
                            <span class="form-check-label fw-semibold">Mostrar ventana inicial normal</span>
                          </div>
                        </label>
                        <label class="rounded-4 border p-3" style="border-color: rgba(34, 211, 238, 0.24); background: rgba(15, 23, 42, 0.48);">
                          <div class="form-check mb-0">
                            <input class="form-check-input" type="radio" name="inicio_popup_modo" id="inicioPopupModeVideo" value="video" <?= $startupPopupMode === 'video' ? 'checked' : '' ?>>
                            <span class="form-check-label fw-semibold">Mostrar ventana inicial con video</span>
                          </div>
                          <div class="form-text mt-2">Esta opción solo puede activarse cuando el enlace de YouTube esté completo y válido.</div>
                        </label>
                      </div>
                    </div>
                    <div class="mb-4">
                      <label class="form-label">Nombre del canal</label>
                      <input type="text" name="inicio_popup_nombre_canal" value="<?= htmlspecialchars($cfg['inicio_popup_nombre_canal'] ?? 'DanisA Gamer Store', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="DanisA Gamer Store">
                      <div class="form-text">Este nombre se usa en la ventana inicial normal.</div>
                    </div>
                    <div class="mb-4">
                      <label class="form-label">Enlace de YouTube para la ventana con video</label>
                      <input type="url" name="inicio_popup_video_url" id="inicioPopupVideoUrl" value="<?= htmlspecialchars($startupPopupVideoUrl, ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://www.youtube.com/shorts/...">
                      <div class="form-text">Acepta enlaces de YouTube Shorts, watch, embed o youtu.be. Si este campo está vacío, la ventana con video no puede seleccionarse.</div>
                    </div>
                    <div>
                      <label class="form-label">Frecuencia de aparición</label>
                      <select name="inicio_popup_frecuencia" class="form-select">
                        <option value="always" <?= ($cfg['inicio_popup_frecuencia'] ?? 'per_session') === 'always' ? 'selected' : '' ?>>Siempre que se navegue en el inicio</option>
                        <option value="per_entry" <?= ($cfg['inicio_popup_frecuencia'] ?? 'per_session') === 'per_entry' ? 'selected' : '' ?>>1 vez cada vez que se entre a la tienda</option>
                        <option value="per_session" <?= ($cfg['inicio_popup_frecuencia'] ?? 'per_session') === 'per_session' ? 'selected' : '' ?>>1 vez por sesion</option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="col-lg-5">
                  <div class="config-section-note h-100">
                    <div class="fw-semibold text-info mb-2">Resumen</div>
                    <div class="small">Modo seleccionado: <?php if ($startupPopupMode === 'normal'): ?>Ventana normal<?php elseif ($startupPopupMode === 'video'): ?>Ventana con video<?php else: ?>Ninguna<?php endif; ?>.</div>
                    <div class="small mt-2">Canal mostrado: <?= htmlspecialchars($cfg['inicio_popup_nombre_canal'] ?? 'DanisA Gamer Store', ENT_QUOTES, 'UTF-8') ?>.</div>
                    <div class="small mt-2">Enlace del canal: <?= $startupPopupChannelReady ? htmlspecialchars($startupPopupChannelUrl, ENT_QUOTES, 'UTF-8') : 'No configurado aún en Redes Sociales' ?></div>
                    <div class="small mt-2">Video de YouTube: <?= $startupPopupVideoUrl !== '' ? htmlspecialchars($startupPopupVideoUrl, ENT_QUOTES, 'UTF-8') : 'No configurado' ?></div>
                  </div>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar ventana inicial</button>
            </form>
          <?php elseif ($activeTab === 'galeria'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="config_section" value="galeria">
              <input type="hidden" name="gallery_id" value="<?= $galleryEditItem ? (int) $galleryEditItem['id'] : 0 ?>">
              <div class="config-section-note mb-4">Administra el slider principal del index. Si marcas un elemento como destacado, también aparecerá en el bloque inferior y se desmarcará cualquier otro destacado existente. Recomendación: sube imágenes en tamaño 1280x500px para obtener el mejor resultado tanto en desktop como en responsive.</div>
              <div class="row g-4 align-items-start">
                <div class="col-12">
                  <label class="form-label d-block">Vista previa de imagen</label>
                  <div class="gallery-image-preview mb-2" id="gallery-image-preview" data-original-src="<?= htmlspecialchars($galleryForm['imagen'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($galleryForm['imagen'] !== ''): ?>
                      <img src="<?= htmlspecialchars($galleryForm['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Vista previa de galería" id="gallery-image-preview-img">
                    <?php else: ?>
                      <span class="gallery-image-empty" id="gallery-image-preview-empty">Sin imagen</span>
                    <?php endif; ?>
                  </div>
                  <div class="form-text">La vista previa usa proporción 1280x500 para acercarse a cómo se verá en el inicio.</div>
                </div>
                <div class="col-lg-8">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label">Título</label>
                      <input type="text" name="titulo" value="<?= htmlspecialchars($galleryForm['titulo']) ?>" class="form-control" placeholder="Bienvenida">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Descripción 1</label>
                      <input type="text" name="descripcion1" value="<?= htmlspecialchars($galleryForm['descripcion1']) ?>" class="form-control" placeholder="+10% en tu primera compra">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Descripción 2</label>
                      <input type="text" name="descripcion2" value="<?= htmlspecialchars($galleryForm['descripcion2']) ?>" class="form-control" placeholder="Usa el código START10">
                    </div>
                    <div class="col-md-7">
                      <label class="form-label">URL</label>
                      <input type="url" name="url" value="<?= htmlspecialchars($galleryForm['url']) ?>" class="form-control" placeholder="https://tusitio.com/promocion">
                      <div class="form-text">Si la dejas vacía, la imagen no tendrá enlace.</div>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label">Comportamiento del enlace</label>
                      <select name="abrir_nueva_pestana" class="form-select">
                        <option value="0" <?= !$galleryForm['abrir_nueva_pestana'] ? 'selected' : '' ?>>Abrir en la misma página</option>
                        <option value="1" <?= $galleryForm['abrir_nueva_pestana'] ? 'selected' : '' ?>>Abrir en otra pestaña</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Imagen</label>
                      <input type="file" name="imagen" id="gallery-image-input" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control" <?= $galleryEditItem ? '' : 'required' ?>>
                      <div class="form-text">Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 4 MB. Tamaño recomendado: 1280x500px.</div>
                    </div>
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="destacadoGaleria" name="destacado" <?= $galleryForm['destacado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="destacadoGaleria">Marcar como destacado</label>
                      </div>
                      <div class="form-text mt-2">El orden se asigna automáticamente al crear. Luego puedes ajustarlo directamente en la tabla inferior.</div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4">
                  <?php if ($galleryEditItem): ?>
                    <a href="/admin/configuracion?tab=galeria" class="btn btn-outline-info w-100 rounded-4">Cancelar edición</a>
                  <?php endif; ?>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4"><?= $galleryEditItem ? 'Actualizar elemento de galería' : 'Crear elemento de galería' ?></button>
            </form>

            <div class="mt-5">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="h5 fw-bold mb-0 text-info">Elementos registrados</h3>
                <span class="gallery-badge-neon"><?= count($galleryItems) ?> elementos</span>
              </div>
              <?php if (empty($galleryItems)): ?>
                <div class="config-section-note">Aún no hay elementos en la galería. Crea el primero para que aparezca en el slider del index.</div>
              <?php else: ?>
                <div class="gallery-table-wrap d-none d-md-block">
                  <div class="table-responsive">
                    <table class="table table-striped align-middle">
                      <thead>
                        <tr>
                          <th>Imagen</th>
                          <th>Título</th>
                          <th>Textos</th>
                          <th>Orden</th>
                          <th>URL</th>
                          <th>Destino</th>
                          <th>Destacado</th>
                          <th class="text-end">Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($galleryItems as $item): ?>
                          <tr>
                            <td>
                              <div class="gallery-thumb">
                                <img src="<?= htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                              </div>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                              <div><?= htmlspecialchars($item['descripcion1'], ENT_QUOTES, 'UTF-8') ?></div>
                              <div class="small text-secondary"><?= htmlspecialchars($item['descripcion2'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                              <form method="post" class="d-inline-flex align-items-center gap-2 m-0 js-gallery-order-form">
                                <input type="hidden" name="ajax" value="1">
                                <input type="hidden" name="config_section" value="galeria">
                                <input type="hidden" name="gallery_order_update" value="1">
                                <input type="hidden" name="gallery_order_id" value="<?= (int) $item['id'] ?>">
                                <input type="number" name="gallery_order" min="1" value="<?= max(1, (int) ($item['orden'] ?? 0)) ?>" class="form-control form-control-sm text-center js-gallery-order-input" style="width:84px;">
                              </form>
                            </td>
                            <td>
                              <?php if (!empty($item['url'])): ?>
                                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-info text-break"><?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?></a>
                              <?php else: ?>
                                <span class="text-secondary">Sin URL</span>
                              <?php endif; ?>
                            </td>
                            <td><?= !empty($item['abrir_nueva_pestana']) ? 'Nueva pestaña' : 'Misma página' ?></td>
                            <td><?= !empty($item['destacado']) ? '<span class="gallery-badge-neon">Sí</span>' : '<span class="text-secondary">No</span>' ?></td>
                            <td class="text-end">
                              <div class="d-inline-flex gap-2">
                                <a href="/admin/configuracion?tab=galeria&editar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-info btn-sm rounded-4">Editar</a>
                                <a href="/admin/configuracion?tab=galeria&eliminar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4" onclick="return confirm('¿Eliminar este elemento de galería?');">Eliminar</a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="d-grid gap-3 d-md-none">
                  <?php foreach ($galleryItems as $item): ?>
                    <div class="gallery-card-mobile">
                      <div class="d-flex gap-3 align-items-start">
                        <div class="gallery-thumb flex-shrink-0">
                          <img src="<?= htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="flex-grow-1">
                          <div class="d-flex justify-content-between gap-2 align-items-start">
                            <h4 class="h6 fw-bold mb-1 text-info"><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <?php if (!empty($item['destacado'])): ?>
                              <span class="gallery-badge-neon">Destacado</span>
                            <?php endif; ?>
                          </div>
                          <div class="small text-light"><?= htmlspecialchars($item['descripcion1'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-secondary"><?= htmlspecialchars($item['descripcion2'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small mt-2 text-light">Orden: <?= max(1, (int) ($item['orden'] ?? 0)) ?></div>
                          <div class="small mt-2 text-info-emphasis"><?= !empty($item['url']) ? htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') : 'Sin URL' ?></div>
                          <div class="small text-secondary mt-1"><?= !empty($item['abrir_nueva_pestana']) ? 'Nueva pestaña' : 'Misma página' ?></div>
                        </div>
                      </div>
                      <form method="post" class="d-flex gap-2 mt-3 align-items-center js-gallery-order-form">
                        <input type="hidden" name="ajax" value="1">
                        <input type="hidden" name="config_section" value="galeria">
                        <input type="hidden" name="gallery_order_update" value="1">
                        <input type="hidden" name="gallery_order_id" value="<?= (int) $item['id'] ?>">
                        <input type="number" name="gallery_order" min="1" value="<?= max(1, (int) ($item['orden'] ?? 0)) ?>" class="form-control form-control-sm js-gallery-order-input" style="max-width:110px;">
                      </form>
                      <div class="d-flex gap-2 mt-3">
                        <a href="/admin/configuracion?tab=galeria&editar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-info btn-sm rounded-4 flex-fill">Editar</a>
                        <a href="/admin/configuracion?tab=galeria&eliminar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4 flex-fill" onclick="return confirm('¿Eliminar este elemento de galería?');">Eliminar</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="config_section" value="metodos-pago">
              <input type="hidden" name="payment_method_id" value="<?= $paymentMethodEditItem ? (int) $paymentMethodEditItem['id'] : 0 ?>">
              <div class="config-section-note mb-4">Registra los métodos de pago disponibles para transferencias, con el nombre visible al cliente y los datos exactos donde debe realizar el pago.</div>
              <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label">Nombre Método de Pago</label>
                      <input type="text" name="nombre_metodo_pago" value="<?= htmlspecialchars($paymentMethodForm['nombre'], ENT_QUOTES, 'UTF-8') ?>" required class="form-control" placeholder="Mercantil, Binance, Zelle">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Datos Método de Pago</label>
                      <textarea name="datos_metodo_pago" rows="6" required class="form-control" placeholder="Titular, número de cuenta, correo, teléfono o cualquier dato necesario para transferir."><?= htmlspecialchars($paymentMethodForm['datos'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Moneda del Método</label>
                      <select name="moneda_metodo_pago" required class="form-select">
                        <option value="">Selecciona una moneda</option>
                        <?php foreach ($paymentCurrencies as $currency): ?>
                          <option value="<?= (int) $currency['id'] ?>" <?= (int) $paymentMethodForm['moneda_id'] === (int) $currency['id'] ? 'selected' : '' ?>><?= htmlspecialchars($currency['nombre'] . ' (' . $currency['clave'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Dígitos de referencia permitidos</label>
                      <input type="number" name="referencia_digitos_metodo_pago" min="0" step="1" value="<?= (int) $paymentMethodForm['referencia_digitos'] ?>" class="form-control" placeholder="0 = sin límite">
                      <div class="form-text">Si colocas `0` o lo dejas vacío, el número de referencia será sin límite. Si colocas `5`, se validarán 5 dígitos; si colocas `7`, se validarán 7, y así sucesivamente.</div>
                    </div>
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="activoMetodoPago" name="activo_metodo_pago" <?= $paymentMethodForm['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activoMetodoPago">Método de pago activo</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="config-section-note">
                    Usa este tab para crear, editar o desactivar cuentas receptoras como bancos, billeteras o servicios de pago.
                  </div>
                  <?php if ($paymentMethodEditItem): ?>
                    <a href="/admin/configuracion?tab=metodos-pago" class="btn btn-outline-info w-100 rounded-4 mt-3">Cancelar edición</a>
                  <?php endif; ?>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4"><?= $paymentMethodEditItem ? 'Actualizar método de pago' : 'Crear método de pago' ?></button>
            </form>

            <div class="mt-5">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="h5 fw-bold mb-0 text-info">Métodos registrados</h3>
                <span class="gallery-badge-neon"><?= count($paymentMethods) ?> métodos</span>
              </div>
              <?php if (empty($paymentMethods)): ?>
                <div class="config-section-note">Aún no hay métodos de pago registrados. Crea el primero para empezar a administrarlos.</div>
              <?php else: ?>
                <div class="gallery-table-wrap d-none d-md-block">
                  <div class="table-responsive">
                    <table class="table table-striped align-middle">
                      <thead>
                        <tr>
                          <th>Nombre</th>
                          <th>Moneda</th>
                          <th>Dígitos Ref.</th>
                          <th>Datos</th>
                          <th>Estado</th>
                          <th class="text-end">Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($paymentMethods as $method): ?>
                          <tr>
                            <td class="fw-bold"><?= htmlspecialchars($method['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(trim((string) (($method['moneda_nombre'] ?? '') . ' ' . (!empty($method['moneda_clave']) ? '(' . $method['moneda_clave'] . ')' : ''))) ?: 'Sin moneda', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($method['referencia_digitos']) ? (int) $method['referencia_digitos'] : 'Sin límite' ?></td>
                            <td style="white-space: pre-line;"><?= htmlspecialchars($method['datos'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($method['activo']) ? '<span class="gallery-badge-neon">Activo</span>' : '<span class="text-secondary">Inactivo</span>' ?></td>
                            <td class="text-end">
                              <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a href="/admin/configuracion?tab=metodos-pago&editar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-info btn-sm rounded-4">Editar</a>
                                <a href="/admin/configuracion?tab=metodos-pago&toggle_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-4"><?= !empty($method['activo']) ? 'Desactivar' : 'Activar' ?></a>
                                <a href="/admin/configuracion?tab=metodos-pago&eliminar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4" onclick="return confirm('¿Eliminar este método de pago?');">Eliminar</a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="d-grid gap-3 d-md-none">
                  <?php foreach ($paymentMethods as $method): ?>
                    <div class="gallery-card-mobile">
                      <div class="d-flex justify-content-between gap-2 align-items-start">
                        <h4 class="h6 fw-bold mb-1 text-info"><?= htmlspecialchars($method['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <?= !empty($method['activo']) ? '<span class="gallery-badge-neon">Activo</span>' : '<span class="text-secondary small">Inactivo</span>' ?>
                      </div>
                      <div class="small text-info-emphasis mt-2"><?= htmlspecialchars(trim((string) (($method['moneda_nombre'] ?? '') . ' ' . (!empty($method['moneda_clave']) ? '(' . $method['moneda_clave'] . ')' : ''))) ?: 'Sin moneda', ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small text-secondary mt-1">Dígitos de referencia: <?= !empty($method['referencia_digitos']) ? (int) $method['referencia_digitos'] : 'Sin límite' ?></div>
                      <div class="small text-light mt-2" style="white-space: pre-line;"><?= htmlspecialchars($method['datos'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="d-flex gap-2 mt-3 flex-wrap">
                        <a href="/admin/configuracion?tab=metodos-pago&editar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-info btn-sm rounded-4 flex-fill">Editar</a>
                        <a href="/admin/configuracion?tab=metodos-pago&toggle_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-4 flex-fill"><?= !empty($method['activo']) ? 'Desactivar' : 'Activar' ?></a>
                        <a href="/admin/configuracion?tab=metodos-pago&eliminar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4 flex-fill" onclick="return confirm('¿Eliminar este método de pago?');">Eliminar</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  (() => {
    const notificationPositionSelect = document.querySelector('[data-win-points-notification-position-select]');
    const simulateNotificationButton = document.querySelector('[data-win-points-simulate-notification]');
    if (!notificationPositionSelect || !simulateNotificationButton) {
      return;
    }

    const previewConfig = {
      programName: <?= json_encode($winPointsNotificationPreviewName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      iconSrc: <?= json_encode($winPointsNotificationPreviewIcon, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
      points: 3,
    };
    let simulationTimer = null;

    const escapeHtml = (value) => String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');

    const buildNotificationElement = () => {
      const notification = document.createElement('div');
      notification.className = 'win-points-live-notification';
      notification.dataset.position = notificationPositionSelect.value || 'bottom-left';

      const iconMarkup = previewConfig.iconSrc
        ? '<div class="win-points-live-notification__logo-wrap"><img src="' + escapeHtml(previewConfig.iconSrc) + '" alt="' + escapeHtml(previewConfig.programName) + '" class="win-points-live-notification__logo"></div>'
        : '<div class="win-points-live-notification__logo-wrap"><span class="win-points-live-notification__logo-fallback">WP</span></div>';

      notification.innerHTML = ''
        + '<div class="win-points-live-notification__pulse" aria-hidden="true"></div>'
        + iconMarkup
        + '<div class="win-points-live-notification__body">'
        + '<div class="win-points-live-notification__title">+' + previewConfig.points + ' ' + escapeHtml(previewConfig.programName || 'Win Points') + '</div>'
        + '<div class="win-points-live-notification__detail">Vista previa exacta de la notificación que verá el cliente al recibir premios por recarga.</div>'
        + '</div>';

      return notification;
    };

    const showSimulation = () => {
      const existing = document.querySelector('.win-points-live-notification[data-admin-simulation="1"]');
      if (existing) {
        existing.remove();
      }

      if (simulationTimer) {
        window.clearTimeout(simulationTimer);
        simulationTimer = null;
      }

      const notification = buildNotificationElement();
      notification.dataset.adminSimulation = '1';
      document.body.appendChild(notification);

      window.requestAnimationFrame(() => {
        notification.classList.add('is-visible');
      });

      simulationTimer = window.setTimeout(() => {
        notification.classList.remove('is-visible');
        window.setTimeout(() => {
          notification.remove();
        }, 320);
      }, 5000);
    };

    simulateNotificationButton.addEventListener('click', showSimulation);
  })();

  (() => {
    const fileInput = document.getElementById('gallery-image-input');
    const previewContainer = document.getElementById('gallery-image-preview');
    if (!fileInput || !previewContainer) {
      return;
    }

    const originalSrc = previewContainer.dataset.originalSrc || '';
    let objectUrl = null;

    const renderPreview = (src) => {
      previewContainer.innerHTML = '';
      if (!src) {
        const empty = document.createElement('span');
        empty.className = 'gallery-image-empty';
        empty.id = 'gallery-image-preview-empty';
        empty.textContent = 'Sin imagen';
        previewContainer.appendChild(empty);
        return;
      }

      const image = document.createElement('img');
      image.id = 'gallery-image-preview-img';
      image.alt = 'Vista previa de galería';
      image.src = src;
      previewContainer.appendChild(image);
    };

    const clearObjectUrl = () => {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
    };

    fileInput.addEventListener('change', () => {
      const [file] = fileInput.files || [];
      clearObjectUrl();

      if (!file) {
        renderPreview(originalSrc);
        return;
      }

      if (!file.type.startsWith('image/')) {
        renderPreview(originalSrc);
        return;
      }

      objectUrl = URL.createObjectURL(file);
      renderPreview(objectUrl);
    });

    renderPreview(originalSrc);
  })();

  (() => {
    const noneOption = document.getElementById('inicioPopupModeNone');
    const videoOption = document.getElementById('inicioPopupModeVideo');
    const videoUrlInput = document.getElementById('inicioPopupVideoUrl');
    if (!noneOption || !videoOption || !videoUrlInput) {
      return;
    }

    const syncVideoModeAvailability = () => {
      const hasVideoUrl = videoUrlInput.value.trim() !== '';
      videoOption.disabled = !hasVideoUrl;
      if (!hasVideoUrl && videoOption.checked) {
        noneOption.checked = true;
      }
    };

    videoUrlInput.addEventListener('input', syncVideoModeAvailability);
    syncVideoModeAvailability();
  })();

  (() => {
    const submitGalleryOrderForm = async (form) => {
      const response = await fetch(form.action || window.location.href, {
        method: (form.method || 'POST').toUpperCase(),
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json, text/plain, */*'
        },
        body: new FormData(form)
      });

      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(payload && payload.message ? payload.message : 'No se pudo guardar el orden de la galería.');
      }

      return payload;
    };

    document.querySelectorAll('.js-gallery-order-form').forEach((form) => {
      const input = form.querySelector('.js-gallery-order-input');
      if (!input) {
        return;
      }

      input.dataset.lastValue = input.value;
      input.addEventListener('change', async () => {
        const normalized = String(Math.max(1, parseInt(input.value || '1', 10) || 1));
        if (normalized === input.dataset.lastValue) {
          input.value = normalized;
          return;
        }

        input.value = normalized;
        input.readOnly = true;
        try {
          const payload = await submitGalleryOrderForm(form);
          input.dataset.lastValue = String(payload.orden || normalized);
          input.value = input.dataset.lastValue;
        } catch (error) {
          input.value = input.dataset.lastValue || '1';
          window.alert(error.message);
        } finally {
          input.readOnly = false;
        }
      });
    });
  })();
</script>
<?php if (!defined('ADMIN_LAYOUT_EMBEDDED')) include __DIR__ . '/includes/footer.php'; ?>
