<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

require_once __DIR__ . '/includes/store_config.php';

$activeTab = defined('ADMIN_CONFIG_ACTIVE_TAB') ? ADMIN_CONFIG_ACTIVE_TAB : ($_GET['tab'] ?? 'correo');
if (!in_array($activeTab, ['correo', 'cabecera'], true)) {
  $activeTab = 'correo';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('ADMIN_CONFIG_POST_HANDLED')) {
  $activeTab = $_POST['config_section'] ?? $activeTab;
  if (!in_array($activeTab, ['correo', 'cabecera'], true)) {
    $activeTab = 'correo';
  }

  if ($activeTab === 'correo') {
    foreach (['correo_corporativo', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'] as $clave) {
      store_config_upsert($clave, trim((string) ($_POST[$clave] ?? '')));
    }
    $_SESSION['auth_flash'] = ['type' => 'success', 'message' => 'Configuración de correo actualizada.'];
    header('Location: /admin/configuracion?tab=correo');
    exit;
  }

  $nombrePrefijo = trim((string) ($_POST['nombre_prefijo'] ?? ''));
  $nombreTienda = trim((string) ($_POST['nombre_tienda'] ?? ''));
  $currentLogo = store_config_get('logo_tienda', '');
  $nextLogo = $currentLogo;
  $hasUpload = isset($_FILES['logo_tienda']) && (($_FILES['logo_tienda']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

  if ($nombrePrefijo === '' || $nombreTienda === '') {
    $_SESSION['auth_flash'] = ['type' => 'error', 'message' => 'Completa el nombre prefijo y el nombre de la tienda.'];
    header('Location: /admin/configuracion?tab=cabecera');
    exit;
  }

  if ($hasUpload) {
    $upload = store_config_store_logo_upload($_FILES['logo_tienda']);
    if (!$upload['success']) {
      $_SESSION['auth_flash'] = ['type' => 'error', 'message' => $upload['message']];
      header('Location: /admin/configuracion?tab=cabecera');
      exit;
    }
    if (!empty($upload['path'])) {
      $nextLogo = $upload['path'];
    }
  } elseif (isset($_POST['eliminar_logo_tienda'])) {
    $nextLogo = '';
  }

  store_config_upsert('nombre_prefijo', $nombrePrefijo);
  store_config_upsert('nombre_tienda', $nombreTienda);
  if ($nextLogo === '') {
    store_config_delete('logo_tienda');
  } else {
    store_config_upsert('logo_tienda', $nextLogo);
  }

  if ($currentLogo !== '' && $currentLogo !== $nextLogo) {
    store_config_delete_logo_file($currentLogo);
  }

  $_SESSION['auth_flash'] = ['type' => 'success', 'message' => 'Datos de cabecera actualizados.'];
  header('Location: /admin/configuracion?tab=cabecera');
  exit;
}

$cfg = store_config_all();
$logoTienda = trim((string) ($cfg['logo_tienda'] ?? ''));
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
  .neon-card .form-label {
    color: #00fff7 !important;
    font-weight: 600;
    letter-spacing: 0.04em;
  }
  .neon-card .form-control, .neon-card .form-select {
    background: #222c3a !important;
    color: #00fff7 !important;
    border: 1px solid #00fff7 !important;
    border-radius: 12px !important;
    box-shadow: 0 0 8px #00fff733;
  }
  .neon-card .form-control:focus, .neon-card .form-select:focus {
    border-color: #34d399 !important;
    box-shadow: 0 0 16px #34d39999;
    outline: none;
  }
  .neon-btn {
    background: linear-gradient(90deg, #00fff7 0%, #34d399 100%);
    color: #181f2a !important;
    font-weight: bold;
    border-radius: 16px !important;
    box-shadow: 0 0 16px #00fff7, 0 0 32px #34d39999;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border: none;
    transition: background 0.2s, box-shadow 0.2s;
  }
  .neon-btn:hover {
    background: linear-gradient(90deg, #34d399 0%, #00fff7 100%);
    box-shadow: 0 0 32px #00fff7, 0 0 16px #34d39999;
  }
  .neon-success {
    color: #34d399 !important;
    font-weight: bold;
    background: none;
    border: none;
    box-shadow: none;
  }
  .neon-tabs-wrap {
    border: 1px solid rgba(34, 211, 238, 0.22);
    border-radius: 20px;
    background: rgba(15, 23, 42, 0.72);
    box-shadow: inset 0 0 0 1px rgba(45, 212, 191, 0.08), 0 0 28px rgba(34, 211, 238, 0.08);
    padding: 0.5rem;
  }
  .neon-tab-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
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
  .header-logo-preview {
    width: 88px;
    height: 88px;
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.48);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.9));
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.16);
  }
  .header-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .header-logo-empty {
    color: rgba(155, 231, 255, 0.72);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }
  .config-section-note {
    border-radius: 16px;
    border: 1px solid rgba(34, 211, 238, 0.2);
    background: rgba(15, 23, 42, 0.55);
    color: rgba(216, 251, 255, 0.82);
    padding: 1rem;
  }
</style>
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="neon-tabs-wrap mb-4">
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <a href="/admin/configuracion?tab=correo" class="neon-tab-link <?= $activeTab === 'correo' ? 'active' : '' ?>">
                            Configuración de correo
                        </a>
                    </div>
                    <div class="col-12 col-md-6">
                        <a href="/admin/configuracion?tab=cabecera" class="neon-tab-link <?= $activeTab === 'cabecera' ? 'active' : '' ?>">
                            Datos de cabecera
                        </a>
                    </div>
                </div>
            </div>

            <div class="card neon-card mb-4">
                <div class="card-header text-center py-4" style="background: linear-gradient(90deg, #00fff7 0%, #34d399 100%); color: #181f2a; border-radius: 16px 16px 0 0;">
                  <h2 class="h4 fw-bold mb-0" style="font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif; letter-spacing: 0.08em;"><?= $activeTab === 'correo' ? 'Configuración de correo corporativo' : 'Datos de cabecera' ?></h2>
                </div>
                <div class="card-body p-4">
                    <?php if ($activeTab === 'correo'): ?>
                      <form method="post">
                        <input type="hidden" name="config_section" value="correo">
                        <div class="config-section-note mb-4">
                          Configura aquí el correo corporativo y la conexión SMTP utilizada para notificaciones y mensajes salientes.
                        </div>
                        <div class="mb-3">
                          <label class="form-label text-info">Correo corporativo</label>
                          <input type="email" name="correo_corporativo" value="<?= htmlspecialchars($cfg['correo_corporativo'] ?? '') ?>" required class="form-control border-info" placeholder="correo@tudominio.com">
                        </div>
                        <div class="mb-3">
                          <label class="form-label text-info">SMTP Host</label>
                          <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>" required class="form-control border-info" placeholder="smtp.tuservidor.com">
                        </div>
                        <div class="mb-3">
                          <label class="form-label text-info">SMTP User</label>
                          <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>" required class="form-control border-info" placeholder="usuario@tudominio.com">
                        </div>
                        <div class="mb-3">
                          <label class="form-label text-info">SMTP Password</label>
                          <input type="password" name="smtp_pass" value="<?= htmlspecialchars($cfg['smtp_pass'] ?? '') ?>" class="form-control border-info" placeholder="••••••••">
                        </div>
                        <div class="row g-3">
                          <div class="col-md-6">
                            <label class="form-label text-info">SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port'] ?? 587) ?>" required class="form-control border-info" placeholder="587">
                          </div>
                          <div class="col-md-6">
                            <label class="form-label text-info">SMTP Secure</label>
                            <select name="smtp_secure" class="form-select border-info">
                              <option value="tls" <?= ($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                              <option value="ssl" <?= ($cfg['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                          </div>
                        </div>
                        <button type="submit" class="neon-btn w-100 py-3 mt-4">
                          Guardar configuración de correo
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="config_section" value="cabecera">
                        <div class="config-section-note mb-4">
                          Define el texto visible en el encabezado y administra el logo que se muestra a la izquierda del branding principal.
                        </div>
                        <div class="row g-4 align-items-start">
                          <div class="col-md-8">
                            <div class="mb-3">
                              <label class="form-label text-info">Nombre Prefijo</label>
                              <input type="text" name="nombre_prefijo" value="<?= htmlspecialchars($cfg['nombre_prefijo'] ?? 'TIENDA') ?>" required class="form-control border-info" placeholder="TIENDA">
                            </div>
                            <div class="mb-3">
                              <label class="form-label text-info">Nombre Tienda</label>
                              <input type="text" name="nombre_tienda" value="<?= htmlspecialchars($cfg['nombre_tienda'] ?? 'TVirtualGaming') ?>" required class="form-control border-info" placeholder="TVirtualGaming">
                            </div>
                            <div class="mb-3">
                              <label class="form-label text-info">Logo tienda</label>
                              <input type="file" name="logo_tienda" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control border-info">
                              <div class="form-text text-info-emphasis mt-2">Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 2 MB.</div>
                            </div>
                            <div class="form-check mt-3">
                              <input class="form-check-input" type="checkbox" value="1" id="eliminarLogoTienda" name="eliminar_logo_tienda">
                              <label class="form-check-label text-info" for="eliminarLogoTienda">
                                Eliminar logo actual
                              </label>
                            </div>
                          </div>
                          <div class="col-md-4">
                            <label class="form-label text-info d-block">Vista previa del logo</label>
                            <div class="header-logo-preview">
                              <?php if ($logoTienda !== ''): ?>
                                <img src="<?= htmlspecialchars($logoTienda, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de la tienda">
                              <?php else: ?>
                                <span class="header-logo-empty">Sin logo</span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </div>
                        <button type="submit" class="neon-btn w-100 py-3 mt-4">
                          Guardar datos de cabecera
                        </button>
                      </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!defined('ADMIN_LAYOUT_EMBEDDED')) include __DIR__ . '/includes/footer.php'; ?>
