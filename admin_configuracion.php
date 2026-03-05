<?php
require_once __DIR__ . '/includes/db_connect.php';

// Guardar cambios en la tabla general
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'correo_corporativo', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'
    ];
    foreach ($campos as $clave) {
        $valor = trim($_POST[$clave] ?? '');
        $stmt = $mysqli->prepare('UPDATE configuracion_general SET valor=? WHERE clave=?');
        $stmt->bind_param('ss', $valor, $clave);
        $stmt->execute();
    }
}

// Leer valores actuales
$cfg = [];
$res = $mysqli->query('SELECT clave, valor FROM configuracion_general');
while ($row = $res->fetch_assoc()) {
    $cfg[$row['clave']] = $row['valor'];
}
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
</style>
<div class="container mt-5 mb-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card neon-card mb-4">
                <div class="card-header text-center py-4" style="background: linear-gradient(90deg, #00fff7 0%, #34d399 100%); color: #181f2a; border-radius: 16px 16px 0 0;">
                  <h2 class="h4 fw-bold mb-0" style="font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif; letter-spacing: 0.08em;">Configuración de correo corporativo</h2>
                </div>
                <div class="card-body p-4">
                    <form method="post">
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
            <div class="mb-3">
              <label class="form-label text-info">SMTP Port</label>
              <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port'] ?? 587) ?>" required class="form-control border-info" placeholder="587">
            </div>
            <div class="mb-3">
              <label class="form-label text-info">SMTP Secure</label>
              <select name="smtp_secure" class="form-select border-info">
                <option value="tls" <?= ($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= ($cfg['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
              </select>
            </div>
                        <button type="submit" class="neon-btn w-100 py-3">
                          Guardar
                        </button>
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                          <div class="neon-success text-center mt-4">
                            ¡Configuración actualizada!
                          </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
