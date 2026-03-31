<?php

$optionalIncludes = [
    __DIR__ . '/includes/app_routes.php',
    __DIR__ . '/includes/app_session.php',
    __DIR__ . '/includes/tenant.php',
];
foreach ($optionalIncludes as $optionalInclude) {
    if (is_file($optionalInclude)) {
        require_once $optionalInclude;
    }
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/store_config.php';
require_once __DIR__ . '/includes/win_points.php';

if (function_exists('app_session_start')) {
    app_session_start();
} elseif (function_exists('tenant_start_session')) {
    tenant_start_session();
}

if (!function_exists('admin_win_points_set_flash')) {
    function admin_win_points_set_flash(string $type, string $message): void {
        $_SESSION['auth_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('admin_win_points_redirect')) {
    function admin_win_points_redirect(array $query = []): void {
        $target = function_exists('app_path') ? app_path('/admin/win-points') : '/admin/win-points';
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }
        header('Location: ' . $target);
        exit;
    }
}

$adminUser = auth_sync_session_user();
$adminUserRole = trim((string) ($adminUser['rol'] ?? ''));
if (!$adminUser || $adminUserRole !== 'admin') {
    admin_win_points_set_flash('error', 'No tienes permisos para acceder a Win Points.');
    $loginPath = function_exists('app_path') ? app_path('/login.php') : '/login.php';
    header('Location: ' . $loginPath);
    exit;
}

if (!win_points_enabled()) {
  admin_win_points_set_flash('error', 'El modulo de Win Points esta desactivado.');
  $dashboardPath = function_exists('app_path') ? app_path('/admin/dashboard') : '/admin/dashboard';
  header('Location: ' . $dashboardPath);
  exit;
}

win_points_ensure_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_win_points_program'])) {
            $programName = trim((string) ($_POST['win_points_name'] ?? ''));
            $currentIcon = store_config_get('win_points_icon', '');
            $nextIcon = $currentIcon;
            $hasUpload = isset($_FILES['win_points_icon']) && (($_FILES['win_points_icon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

            if ($programName === '') {
                throw new RuntimeException('Debes indicar un nombre para la moneda de premios.');
            }

            if ($hasUpload) {
                $upload = win_points_store_icon_upload($_FILES['win_points_icon']);
                if (!($upload['success'] ?? false)) {
                    throw new RuntimeException((string) ($upload['message'] ?? 'No se pudo cargar el icono del programa.'));
                }
                if (!empty($upload['path'])) {
                    $nextIcon = (string) $upload['path'];
                }
            } elseif (isset($_POST['remove_win_points_icon'])) {
                $nextIcon = '';
            }

            store_config_upsert('win_points_name', $programName);
            store_config_delete('win_points_default_award');

            if ($nextIcon === '') {
                store_config_delete('win_points_icon');
            } else {
                store_config_upsert('win_points_icon', $nextIcon);
            }

            if ($currentIcon !== '' && $currentIcon !== $nextIcon) {
                win_points_delete_icon_file($currentIcon);
            }

            admin_win_points_set_flash('success', 'Configuracion global de Win Points actualizada.');
        } elseif (isset($_POST['save_win_points_rule'])) {
            $packageId = (int) ($_POST['rule_package_id'] ?? 0);
          $rewardPoints = max(0, (int) ($_POST['rule_reward_points'] ?? 0));
            $requiredPoints = max(0, (int) ($_POST['rule_required_points'] ?? 0));
            $active = isset($_POST['rule_active']);
            $order = trim((string) ($_POST['rule_order'] ?? ''));
            $resolvedOrder = $order === '' ? null : max(1, (int) $order);

          $rule = win_points_upsert_redemption_rule($mysqli, $packageId, $rewardPoints, $requiredPoints, $active, $resolvedOrder);
            admin_win_points_set_flash('success', 'Regla de canje guardada para el paquete seleccionado.');
            admin_win_points_redirect(['rule' => (int) ($rule['id'] ?? 0)]);
        } elseif (isset($_POST['delete_win_points_rule'])) {
            $ruleId = (int) ($_POST['rule_id'] ?? 0);
            if (!win_points_delete_redemption_rule($mysqli, $ruleId)) {
                throw new RuntimeException('No se pudo eliminar la regla de canje seleccionada.');
            }
            admin_win_points_set_flash('success', 'Regla de canje eliminada.');
        } elseif (isset($_POST['adjust_win_points_balance'])) {
            $userId = (int) ($_POST['adjust_user_id'] ?? 0);
            $delta = win_points_normalize_delta($_POST['adjust_points_delta'] ?? 0);
            $reason = trim((string) ($_POST['adjust_reason'] ?? ''));

            if ($reason === '') {
                throw new RuntimeException('Debes indicar el motivo del ajuste manual.');
            }

            win_points_adjust_user_balance($mysqli, $userId, $delta, $reason, (int) ($adminUser['id'] ?? 0));
            admin_win_points_set_flash('success', 'Saldo del usuario ajustado correctamente.');
        } else {
            admin_win_points_set_flash('error', 'No se reconocio la accion solicitada en Win Points.');
        }
    } catch (Throwable $exception) {
        admin_win_points_set_flash('error', $exception->getMessage());
    }

    admin_win_points_redirect();
}

$winPointsConfig = win_points_config();
$packageOptions = win_points_fetch_admin_package_options($mysqli);
$adminUsers = win_points_fetch_admin_users($mysqli);
$adminRules = win_points_fetch_admin_rules($mysqli);
$adminWallets = win_points_fetch_admin_wallets($mysqli);
$adminTransactions = win_points_fetch_admin_transactions($mysqli, 80);
$dashboardUrl = function_exists('app_path') ? app_path('/admin/dashboard') : '/admin/dashboard';
$packageOptionsByGame = [];
$totalWalletBalance = 0;

foreach ($packageOptions as $packageOption) {
    $gameName = trim((string) ($packageOption['juego_nombre'] ?? 'Juego'));
    if (!isset($packageOptionsByGame[$gameName])) {
        $packageOptionsByGame[$gameName] = [];
    }
    $packageOptionsByGame[$gameName][] = $packageOption;
}

foreach ($adminWallets as $walletRow) {
    $totalWalletBalance += (int) ($walletRow['balance'] ?? 0);
}

$transactionTypeLabels = [
    'earn' => 'Ganado',
    'redeem' => 'Canjeado',
    'award_reversal' => 'Reverso de premio',
    'redeem_refund' => 'Reembolso de canje',
    'admin_adjustment' => 'Ajuste manual',
];

include __DIR__ . '/includes/header.php';
?>
<style>
  .win-points-shell {
    display: grid;
    gap: 1.5rem;
  }
  .win-points-panel {
    background: linear-gradient(180deg, rgba(9, 18, 33, 0.96), rgba(14, 25, 45, 0.94));
    border: 1px solid rgba(34, 211, 238, 0.22);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 18px 42px rgba(0, 0, 0, 0.22);
  }
  .win-points-kpi {
    height: 100%;
    border-radius: 20px;
    padding: 1.25rem;
    background: rgba(9, 18, 33, 0.88);
    border: 1px solid rgba(34, 211, 238, 0.18);
  }
  .win-points-icon-preview {
    width: 84px;
    aspect-ratio: 1 / 1;
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.28);
    background: rgba(8, 15, 28, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0.6rem;
  }
  .win-points-icon-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .win-points-icon-upload-stage {
    width: 100%;
    max-width: 160px;
    aspect-ratio: 1 / 1;
    border-radius: 22px;
    border: 1px solid rgba(34, 211, 238, 0.28);
    background: rgba(8, 15, 28, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0.85rem;
    margin-left: auto;
  }
  .win-points-icon-upload-stage img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
  .win-points-icon-upload-empty {
    color: #8cf6ff;
    font-weight: 700;
    text-align: center;
    opacity: 0.8;
  }
  .win-points-table td,
  .win-points-table th {
    vertical-align: middle;
  }
  .win-points-rule-inline-form {
    margin: 0;
  }
  .win-points-rule-field {
    min-width: 92px;
  }
  .win-points-rule-active {
    display: inline-flex;
    justify-content: center;
    width: 100%;
  }
  .win-points-rule-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    flex-wrap: wrap;
  }
  .win-points-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    background: rgba(34, 211, 238, 0.14);
    border: 1px solid rgba(34, 211, 238, 0.2);
    color: #8cf6ff;
    font-size: 0.85rem;
    font-weight: 700;
  }
</style>

<section class="container py-5">
  <div class="win-points-shell">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <h1 class="display-5 fw-bold text-info mb-2">Modulo de <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0">Administra el nombre, icono, las reglas de canje por paquete y los ajustes manuales de saldo.</p>
      </div>
      <a href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-info fw-bold px-4">Volver al panel</a>
    </div>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="win-points-kpi">
          <div class="text-secondary small text-uppercase mb-2">Estado global</div>
          <div class="h3 fw-bold <?= $winPointsConfig['enabled'] ? 'text-success' : 'text-danger' ?> mb-1"><?= $winPointsConfig['enabled'] ? 'Activo' : 'Inactivo' ?></div>
          <div class="text-secondary">Indica si el programa de premios por recarga esta habilitado para la tienda.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="win-points-kpi">
          <div class="text-secondary small text-uppercase mb-2">Saldo total acumulado</div>
          <div class="h3 fw-bold text-info mb-1"><?= number_format($totalWalletBalance) ?></div>
          <div class="text-secondary">Disponible entre <?= count($adminWallets) ?> usuarios con wallet registrada.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="win-points-kpi">
          <div class="text-secondary small text-uppercase mb-2">Reglas de canje</div>
          <div class="h3 fw-bold text-info mb-1"><?= count($adminRules) ?></div>
          <div class="text-secondary">Cada regla define cuantas unidades de <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?> cuesta un paquete.</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-5">
        <div class="win-points-panel h-100">
          <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
            <div>
              <h2 class="h4 text-info fw-bold mb-1">Configuracion global</h2>
              <p class="text-secondary mb-0">Nombre e icono del programa global de premios por recarga.</p>
            </div>
            <div class="win-points-icon-preview">
              <?php if ($winPointsConfig['icon_url'] !== ''): ?>
                <img src="<?= htmlspecialchars($winPointsConfig['icon_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Icono <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?>">
              <?php else: ?>
                <span class="text-info fw-bold">WP</span>
              <?php endif; ?>
            </div>
          </div>

          <form method="POST" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="save_win_points_program" value="1">
            <div class="col-md-7">
              <label class="form-label text-info">Nombre de la moneda</label>
              <input type="text" name="win_points_name" value="<?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?>" class="form-control bg-dark text-info border-info" required>
            </div>
            <div class="col-md-7">
              <label class="form-label text-info">Icono del programa</label>
              <input type="file" name="win_points_icon" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" class="form-control bg-dark text-info border-info" data-win-points-icon-input>
              <div class="form-text mt-2">La vista previa se muestra en formato cuadrado sin recortar la imagen.</div>
            </div>
            <div class="col-md-5">
              <label class="form-label text-info">Vista previa</label>
              <div class="win-points-icon-upload-stage" data-win-points-icon-stage data-default-src="<?= htmlspecialchars($winPointsConfig['icon_url'], ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($winPointsConfig['icon_url'] !== ''): ?>
                  <img src="<?= htmlspecialchars($winPointsConfig['icon_url'], ENT_QUOTES, 'UTF-8') ?>" alt="Preview <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?>" data-win-points-icon-img>
                <?php else: ?>
                  <span class="win-points-icon-upload-empty" data-win-points-icon-empty>Sin icono</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="removeWinPointsIcon" name="remove_win_points_icon" data-win-points-icon-remove>
                <label class="form-check-label" for="removeWinPointsIcon">Eliminar icono actual</label>
              </div>
            </div>
            <div class="col-12 d-grid">
              <button type="submit" class="btn btn-info fw-bold py-3">Guardar configuracion global</button>
            </div>
          </form>
        </div>
      </div>

      <div class="col-xl-7">
        <div class="win-points-panel h-100">
          <div class="mb-4">
            <h2 class="h4 text-info fw-bold mb-1">Crear o actualizar regla de canje</h2>
            <p class="text-secondary mb-0">Selecciona el paquete, define cuanto premio entrega por compra y cuantos <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?> cuesta canjearlo.</p>
          </div>

          <form method="POST" class="row g-3">
            <input type="hidden" name="save_win_points_rule" value="1">
            <div class="col-lg-5">
              <label class="form-label text-info">Paquete</label>
              <select name="rule_package_id" class="form-select bg-dark text-info border-info" data-rule-package-select required>
                <option value="">Selecciona un paquete</option>
                <?php foreach ($packageOptionsByGame as $gameName => $gamePackages): ?>
                  <optgroup label="<?= htmlspecialchars($gameName, ENT_QUOTES, 'UTF-8') ?>">
                    <?php foreach ($gamePackages as $gamePackage): ?>
                      <option value="<?= (int) ($gamePackage['id'] ?? 0) ?>" data-current-reward="<?= (int) ($gamePackage['win_points_reward'] ?? 0) ?>">
                        <?= htmlspecialchars((string) ($gamePackage['paquete_nombre'] ?? 'Paquete'), ENT_QUOTES, 'UTF-8') ?> | +<?= (int) ($gamePackage['win_points_reward'] ?? 0) ?> <?= htmlspecialchars($winPointsConfig['name'], ENT_QUOTES, 'UTF-8') ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6 col-lg-3">
              <label class="form-label text-info">Premio por compra</label>
              <input type="number" min="0" name="rule_reward_points" class="form-control bg-dark text-info border-info" data-rule-reward-input required>
            </div>
            <div class="col-sm-6 col-lg-2">
              <label class="form-label text-info">Costo en puntos</label>
              <input type="number" min="1" name="rule_required_points" class="form-control bg-dark text-info border-info" required>
            </div>
            <div class="col-sm-4 col-lg-2">
              <label class="form-label text-info">Orden</label>
              <input type="number" min="1" name="rule_order" class="form-control bg-dark text-info border-info" placeholder="1">
            </div>
            <div class="col-sm-4 col-lg-4 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="ruleActiveNew" name="rule_active" checked>
                <label class="form-check-label fw-semibold" for="ruleActiveNew">Regla activa</label>
              </div>
            </div>
            <div class="col-sm-4 col-lg-4 d-grid ms-lg-auto">
              <button type="submit" class="btn btn-success fw-bold py-3">Guardar regla</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="win-points-panel">
      <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
          <h2 class="h4 text-info fw-bold mb-1">Reglas actuales</h2>
          <p class="text-secondary mb-0">Puedes editar puntos requeridos, orden o estado directamente sobre cada regla creada.</p>
        </div>
        <span class="win-points-pill"><?= count($adminRules) ?> reglas registradas</span>
      </div>

      <?php if (empty($adminRules)): ?>
        <div class="text-secondary">Aun no hay reglas de canje creadas.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle win-points-table">
            <thead>
              <tr>
                <th>Juego</th>
                <th>Paquete</th>
                <th class="text-center">Premio por compra</th>
                <th class="text-center">Costo de canje</th>
                <th class="text-center">Orden</th>
                <th class="text-center">Activo</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($adminRules as $rule): ?>
                <?php $editFormId = 'winPointsRuleForm' . (int) ($rule['id'] ?? 0); ?>
                <tr>
                  <td>
                    <form id="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" method="POST" class="win-points-rule-inline-form">
                      <input type="hidden" name="save_win_points_rule" value="1">
                      <input type="hidden" name="rule_package_id" value="<?= (int) ($rule['paquete_id'] ?? 0) ?>">
                    </form>
                    <?= htmlspecialchars((string) ($rule['juego_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td><?= htmlspecialchars((string) ($rule['paquete_nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <input type="number" min="0" name="rule_reward_points" value="<?= max(0, (int) ($rule['win_points_reward'] ?? 0)) ?>" form="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm bg-dark text-info border-info win-points-rule-field text-center" required>
                  </td>
                  <td>
                    <input type="number" min="1" name="rule_required_points" value="<?= max(1, (int) ($rule['required_points'] ?? 0)) ?>" form="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm bg-dark text-info border-info win-points-rule-field text-center" required>
                  </td>
                  <td>
                    <input type="number" min="1" name="rule_order" value="<?= isset($rule['orden']) && $rule['orden'] !== null ? (int) $rule['orden'] : '' ?>" form="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm bg-dark text-info border-info win-points-rule-field text-center" placeholder="Orden">
                  </td>
                  <td class="text-center">
                    <div class="form-check form-switch win-points-rule-active m-0">
                      <input class="form-check-input" type="checkbox" name="rule_active" form="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" <?= !empty($rule['activo']) ? 'checked' : '' ?>>
                    </div>
                  </td>
                  <td class="text-end">
                    <div class="win-points-rule-actions">
                      <button type="submit" form="<?= htmlspecialchars($editFormId, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-info fw-bold">Guardar</button>
                      <form method="POST" onsubmit="return confirm('¿Eliminar esta regla de canje?');" class="win-points-rule-inline-form">
                        <input type="hidden" name="delete_win_points_rule" value="1">
                        <input type="hidden" name="rule_id" value="<?= (int) ($rule['id'] ?? 0) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="row g-4">
      <div class="col-xl-7">
        <div class="win-points-panel h-100">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
            <div>
              <h2 class="h4 text-info fw-bold mb-1">Wallets de usuarios</h2>
              <p class="text-secondary mb-0">Saldo disponible por usuario registrado para usar en proximas recargas.</p>
            </div>
            <span class="win-points-pill"><?= number_format($totalWalletBalance) ?> puntos</span>
          </div>

          <div class="table-responsive">
            <table class="table table-dark table-hover align-middle win-points-table">
              <thead>
                <tr>
                  <th>Usuario</th>
                  <th>Correo</th>
                  <th>Telefono</th>
                  <th class="text-end">Ganados</th>
                  <th class="text-end">Gastados</th>
                  <th class="text-end">Movimientos</th>
                  <th class="text-end">Saldo</th>
                  <th>Ultimo movimiento</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($adminWallets as $wallet): ?>
                  <tr>
                    <td><?= htmlspecialchars((string) ($wallet['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($wallet['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars(trim((string) ($wallet['telefono'] ?? '')) !== '' ? (string) $wallet['telefono'] : 'No disponible', ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="text-end text-success fw-bold"><?= number_format((int) ($wallet['earned_points'] ?? 0)) ?></td>
                    <td class="text-end text-warning fw-bold"><?= number_format((int) ($wallet['spent_points'] ?? 0)) ?></td>
                    <td class="text-end text-light"><?= number_format((int) ($wallet['total_transactions'] ?? 0)) ?></td>
                    <td class="text-end fw-bold text-info"><?= number_format((int) ($wallet['balance'] ?? 0)) ?></td>
                    <td><?= htmlspecialchars((string) ($wallet['last_transaction_at'] ?? 'Sin movimientos'), ENT_QUOTES, 'UTF-8') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="col-xl-5">
        <div class="win-points-panel h-100">
          <div class="mb-4">
            <h2 class="h4 text-info fw-bold mb-1">Ajuste manual</h2>
            <p class="text-secondary mb-0">Disponible solo para administradores. Usa valores positivos para sumar y negativos para descontar.</p>
          </div>

          <form method="POST" class="row g-3">
            <input type="hidden" name="adjust_win_points_balance" value="1">
            <div class="col-12">
              <label class="form-label text-info">Usuario</label>
              <select name="adjust_user_id" class="form-select bg-dark text-info border-info" required>
                <option value="">Selecciona un usuario</option>
                <?php foreach ($adminUsers as $user): ?>
                  <option value="<?= (int) ($user['id'] ?? 0) ?>">
                    <?= htmlspecialchars((string) ($user['nombre'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-info">Delta</label>
              <input type="number" name="adjust_points_delta" class="form-control bg-dark text-info border-info" placeholder="-20 o 50" required>
            </div>
            <div class="col-md-8">
              <label class="form-label text-info">Motivo</label>
              <input type="text" name="adjust_reason" class="form-control bg-dark text-info border-info" placeholder="Compensacion, correccion, bono especial" required>
            </div>
            <div class="col-12 d-grid">
              <button type="submit" class="btn btn-warning fw-bold py-3">Aplicar ajuste manual</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="win-points-panel">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-4">
        <div>
          <h2 class="h4 text-info fw-bold mb-1">Movimientos recientes</h2>
          <p class="text-secondary mb-0">Ultimos movimientos del ledger de premios para auditoria de ganancias, canjes, reversos y ajustes.</p>
        </div>
        <span class="win-points-pill">Ultimos <?= count($adminTransactions) ?> registros</span>
      </div>

      <?php if (empty($adminTransactions)): ?>
        <div class="text-secondary">Aun no hay movimientos registrados en Win Points.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-dark table-hover align-middle win-points-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Tipo</th>
                <th>Delta</th>
                <th>Saldo</th>
                <th>Pedido</th>
                <th>Descripcion</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($adminTransactions as $transaction): ?>
                <?php $delta = (int) ($transaction['points_delta'] ?? 0); ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($transaction['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string) ($transaction['usuario_nombre'] ?? 'Usuario'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="small text-secondary"><?= htmlspecialchars((string) ($transaction['usuario_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  </td>
                  <td><?= htmlspecialchars($transactionTypeLabels[(string) ($transaction['transaction_type'] ?? '')] ?? (string) ($transaction['transaction_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="fw-bold <?= $delta >= 0 ? 'text-success' : 'text-danger' ?>"><?= $delta >= 0 ? '+' : '' ?><?= number_format($delta) ?></td>
                  <td class="text-info fw-bold"><?= number_format((int) ($transaction['balance_after'] ?? 0)) ?></td>
                  <td><?= !empty($transaction['order_id']) ? '#' . (int) $transaction['order_id'] : '—' ?></td>
                  <td>
                    <div><?= htmlspecialchars((string) ($transaction['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    <?php if (!empty($transaction['juego_nombre']) || !empty($transaction['paquete_nombre'])): ?>
                      <div class="small text-secondary">
                        <?= htmlspecialchars(trim((string) (($transaction['juego_nombre'] ?? '') . ' ' . ($transaction['paquete_nombre'] ?? ''))), ENT_QUOTES, 'UTF-8') ?>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
  (function () {
    const iconInput = document.querySelector('[data-win-points-icon-input]');
    const iconStage = document.querySelector('[data-win-points-icon-stage]');
    const iconRemove = document.querySelector('[data-win-points-icon-remove]');
    const packageSelect = document.querySelector('[data-rule-package-select]');
    const rewardInput = document.querySelector('[data-rule-reward-input]');

    function renderIconPreview(src) {
      if (!iconStage) {
        return;
      }

      let previewImg = iconStage.querySelector('[data-win-points-icon-img]');
      let emptyState = iconStage.querySelector('[data-win-points-icon-empty]');

      if (src) {
        if (!previewImg) {
          previewImg = document.createElement('img');
          previewImg.setAttribute('data-win-points-icon-img', '1');
          iconStage.appendChild(previewImg);
        }
        previewImg.src = src;
        if (emptyState) {
          emptyState.remove();
        }
        return;
      }

      if (previewImg) {
        previewImg.remove();
      }

      if (!emptyState) {
        emptyState = document.createElement('span');
        emptyState.className = 'win-points-icon-upload-empty';
        emptyState.setAttribute('data-win-points-icon-empty', '1');
        emptyState.textContent = 'Sin icono';
        iconStage.appendChild(emptyState);
      }
    }

    function syncSelectedPackageReward() {
      if (!packageSelect || !rewardInput) {
        return;
      }

      const selectedOption = packageSelect.options[packageSelect.selectedIndex];
      rewardInput.value = selectedOption ? (selectedOption.dataset.currentReward || '0') : '0';
    }

    if (iconInput && iconStage) {
      const defaultSrc = iconStage.dataset.defaultSrc || '';

      iconInput.addEventListener('change', function () {
        const file = iconInput.files && iconInput.files[0] ? iconInput.files[0] : null;
        if (!file) {
          renderIconPreview(iconRemove && iconRemove.checked ? '' : defaultSrc);
          return;
        }

        if (iconRemove) {
          iconRemove.checked = false;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
          renderIconPreview(typeof event.target?.result === 'string' ? event.target.result : '');
        };
        reader.readAsDataURL(file);
      });

      if (iconRemove) {
        iconRemove.addEventListener('change', function () {
          if (iconRemove.checked) {
            renderIconPreview('');
            return;
          }

          if (!iconInput.files || !iconInput.files[0]) {
            renderIconPreview(defaultSrc);
          }
        });
      }
    }

    if (packageSelect && rewardInput) {
      syncSelectedPackageReward();
      packageSelect.addEventListener('change', syncSelectedPackageReward);
    }
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>