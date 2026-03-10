<?php
require_once __DIR__ . "/includes/db_connect.php";
$game = null;
if (isset($_GET['slug'])) {
  $slug = strtolower(trim($_GET['slug']));
  $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
  $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE slug=? LIMIT 1");
  $stmt->bind_param('s', $slug);
  $stmt->execute();
  $res = $stmt->get_result();
  $game = $res->fetch_assoc();
  $stmt->close();
} elseif (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $stmt = $mysqli->prepare("SELECT * FROM juegos WHERE id=? LIMIT 1");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $game = $res->fetch_assoc();
  $stmt->close();
}
if (!$game) {
  // Si no se encuentra, mostrar el primero
  $res = $mysqli->query("SELECT * FROM juegos ORDER BY id DESC LIMIT 1");
  $game = $res ? $res->fetch_assoc() : null;
}
if (!$game) {
  die('Juego no encontrado.');
}
$pageTitle = "TVirtualGaming | " . ($game["nombre"] ?? "Juego");
include __DIR__ . "/includes/header.php";
?>


<section class="container mt-5 mb-4 p-4 bg-dark bg-opacity-75 rounded-4 shadow">
  <div class="row align-items-center">
    <div class="col-auto">
      <div class="rounded-4 border border-info bg-dark position-relative overflow-hidden" style="width:64px; height:64px;">
        <img src="/<?= htmlspecialchars($game["imagen"] ?? '', ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?>" class="w-100 h-100 object-fit-cover" />
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
            echo '<span class="badge rounded-pill border border-info text-info px-2 py-1 bg-dark">' . htmlspecialchars($row['caracteristica']) . '</span>';
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
  <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3 mb-4" id="pack-grid">
    <?php 
      $resPaq = $mysqli->query("SELECT * FROM juego_paquetes WHERE juego_id=" . intval($game['id']) . " ORDER BY precio ASC");
      $paquetes = [];
      while ($pack = $resPaq->fetch_assoc()) {
        $paquetes[] = $pack;
      }
      foreach ($paquetes as $pack):
        $precio_base = floatval($pack['precio']);
        $precio_mostrar = $moneda_actual ? $precio_base * floatval($moneda_actual['tasa']) : $precio_base;
        $clave_moneda = $moneda_actual['clave'] ?? 'USD';
    ?>
      <div class="col">
        <button type="button" class="pack-card card border-info bg-dark text-start w-100 h-100 shadow-sm"
          data-base="<?= htmlspecialchars($precio_base) ?>"
          data-name="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>"
          data-cantidad="<?= htmlspecialchars($pack['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
          data-price="<?= number_format($precio_mostrar, 2, '.', '') ?>"
          data-moneda="<?= htmlspecialchars($clave_moneda) ?>">
          <div class="card-body p-0 d-flex flex-column">
            <?php 
              $img_paquete = !empty($pack['imagen_icono']) ? $pack['imagen_icono'] : (!empty($game['imagen_paquete']) ? $game['imagen_paquete'] : null);
            ?>
            <div class="pack-card-media">
              <?php if ($img_paquete): ?>
                <img src="/<?= htmlspecialchars($img_paquete, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>" class="pack-card-image" />
              <?php else: ?>
                <span class="pack-card-placeholder">PK</span>
              <?php endif; ?>
              <div class="pack-card-glow"></div>
            </div>
            <div class="pack-card-content">
              <p class="pack-card-name mb-0 fw-semibold text-white"><?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
              <div class="pack-card-footer">
                <span class="moneda-label text-info"><?= htmlspecialchars($clave_moneda) ?></span>
                <span class="precio-label text-info">
                  <?= number_format($precio_mostrar, 2, '.', ',') ?>
                </span>
              </div>
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
      ];
    }
  ?>
  <script>
    const monedas = <?= json_encode($monedas_js) ?>;
    let monedaActualId = "<?= $moneda_actual['id'] ?? '' ?>";
    let monedaActualClave = "<?= $moneda_actual['clave'] ?? 'USD' ?>";
    let monedaActualTasa = <?= $moneda_actual['tasa'] ?? 1 ?>;
    const monedaSelect = document.getElementById('moneda-select');
    const packCards = Array.from(document.querySelectorAll('.pack-card'));
    function updatePackPrices() {
      packCards.forEach(card => {
        const base = parseFloat(card.getAttribute('data-base'));
        const precio = base * monedaActualTasa;
        card.querySelector('.precio-label').textContent = precio.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        card.querySelector('.moneda-label').textContent = monedaActualClave;
        card.setAttribute('data-price', precio.toFixed(2).replace(/,/g, ''));
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
            <p class="small text-secondary mb-1">Paquete seleccionado</p>
            <p id="selected-pack" class="fw-bold text-white">Ninguno</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card bg-dark border-info mb-2">
          <div class="card-body">
            <p class="small text-secondary mb-1">Total</p>
            <p id="selected-price" class="fw-bold text-info fs-5"><?= $moneda_actual['clave'] ?? 'Bs.' ?> 0.00</p>
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
    <div class="col-md-4">
      <label class="form-label text-info">ID de usuario</label>
      <input type="text" name="user_id" placeholder="Ej: 12345678" class="form-control bg-dark text-info border-info" required />
    </div>
    <div class="col-md-4">
      <label class="form-label text-info">Correo</label>
      <input type="email" name="email" placeholder="tu@email.com" class="form-control bg-dark text-info border-info" required />
    </div>
    <div class="col-md-4">
      <label class="form-label text-info">Cupón</label>
      <div class="input-group">
        <input type="text" name="coupon" id="coupon-input" placeholder="Código opcional" pattern="[A-Za-z0-9]+" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" title="Solo letras y números, sin espacios ni caracteres especiales." class="form-control bg-dark text-info border-info" />
        <button type="button" id="apply-coupon-btn" class="btn btn-info fw-bold">Aplicar cupón</button>
      </div>
    </div>
    <div class="col-12">
      <button type="submit" id="buy-button" class="btn btn-success w-100 fw-bold text-uppercase" disabled>
        Compra ahora
      </button>
    </div>
  </form>


  <!-- Modal Loading Bootstrap -->
  <div id="loading-modal" class="modal fade app-overlay-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content bg-dark border-info text-center p-4">
        <div class="mb-3">
          <svg width="48" height="48" viewBox="0 0 50 50">
            <circle cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)">
              <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/>
            </circle>
          </svg>
        </div>
        <h4 class="fw-bold text-info mb-2">Procesando pedido...</h4>
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
  }

  .app-overlay-modal.is-visible {
    display: flex !important;
    opacity: 1 !important;
  }

  .app-overlay-modal .modal-dialog {
    width: min(92vw, 28rem);
    margin: 0;
  }

  body.overlay-open {
    overflow: hidden;
  }

  .pack-card {
    min-height: 15rem;
    border-width: 1px;
    border-radius: 1.1rem;
    overflow: hidden;
    background:
      radial-gradient(circle at top, rgba(34, 211, 238, 0.18), transparent 45%),
      linear-gradient(180deg, rgba(15, 23, 42, 0.98), rgba(10, 15, 24, 0.98));
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  }

  .pack-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1rem 2rem rgba(8, 145, 178, 0.2);
  }

  .pack-card .card-body {
    min-height: 100%;
  }

  .pack-card-media {
    width: 100%;
    min-height: 8.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(10, 15, 24, 0.45), rgba(10, 15, 24, 0.05));
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
    color: #22d3ee;
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
    min-height: 2.4rem;
    display: flex;
    align-items: flex-start;
    justify-content: flex-start;
    text-align: left;
    line-height: 1.15;
    width: 100%;
    font-size: 0.98rem;
    letter-spacing: 0.01em;
    text-shadow: 0 0 10px rgba(34, 211, 238, 0.18);
  }

  .pack-card-footer {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 0.65rem;
    border-top: 1px solid rgba(34, 211, 238, 0.18);
    padding-top: 0.65rem;
  }

  .moneda-label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    opacity: 0.92;
  }

  .precio-label {
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1;
    text-shadow: 0 0 12px rgba(34, 211, 238, 0.28);
  }

  .neon-selected {
    box-shadow: 0 0 16px 4px #00fff7, 0 0 32px 8px #34d399;
    border: 2px solid #00fff7 !important;
    background: #181f2a !important;
    transition: box-shadow 0.2s, border-color 0.2s;
    z-index: 2;
  }

  .neon-selected .pack-card-footer {
    border-top-color: rgba(52, 211, 153, 0.48);
  }

  @media (max-width: 575.98px) {
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
</style>
<script>
  // Todas las variables y lógica JS en un solo bloque
  const packCards2 = Array.from(document.querySelectorAll('.pack-card'));
  const selectedPack = document.getElementById("selected-pack");
  const selectedPrice = document.getElementById("selected-price");
  const orderForm = document.getElementById("order-form");
  const buyButton = document.getElementById("buy-button");
  const couponInput = document.getElementById('coupon-input');
  const couponModal = document.getElementById('coupon-modal');
  const loadingModal = document.getElementById('loading-modal');
  const modalCouponName = document.getElementById('modal-coupon-name');
  const modalYes = document.getElementById('modal-yes');
  const modalNo = document.getElementById('modal-no');
  const modalCancel = document.getElementById('modal-cancel');
  const applyCouponButton = document.getElementById('apply-coupon-btn');
  let lastFocusedElement = null;
  let activePack = null;
  let couponApplied = false;
  let couponValue = '';

  function syncOverlayState() {
    document.body.classList.toggle('overlay-open', Boolean(document.querySelector('.app-overlay-modal.is-visible')));
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
      setTimeout(() => lastFocusedElement.focus(), 0);
      lastFocusedElement = null;
    }
  }

  function removeBuySpinner() {
    const spinner = document.getElementById('spinner-compra');
    if (spinner) {
      spinner.remove();
    }
  }

  function updatePackPrices() {
    packCards.forEach(card => {
      const base = parseFloat(card.getAttribute('data-base'));
      const precio = base * monedaActualTasa;
      card.querySelector('.precio-label').textContent = precio.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      card.querySelector('.moneda-label').textContent = monedaActualClave;
      card.setAttribute('data-price', precio.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
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
    buyButton.disabled = !activePack || !requiredFilled;
  }
  function updateResumenCompra(pack) {
    if (pack) {
      selectedPack.textContent = pack.name;
      selectedPrice.textContent = `${pack.moneda} ${pack.price}`;
    } else {
      selectedPack.textContent = 'Ninguno';
      selectedPrice.textContent = `${monedaActualClave} 0.00`;
    }
  }
  packCards2.forEach((card) => {
    card.addEventListener("click", () => {
      packCards2.forEach((item) => {
        item.classList.remove("neon-selected");
      });
      card.classList.add("neon-selected");
      activePack = {
        name: card.dataset.name,
        price: card.dataset.price,
        moneda: card.dataset.moneda,
        cantidad: card.dataset.cantidad
      };
      updateResumenCompra(activePack);
      updateButtonState();
    });
  });
  if (packCards2.length) {
    // Ya no se selecciona automáticamente ningún paquete al cargar
  }
              if (couponInput) {
              // Función simple para mostrar mensajes tipo toast
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

              function normalizeCouponCode(value) {
                return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
              }

              function resetCouponState() {
                couponApplied = false;
                couponValue = '';
                couponInput.disabled = false;
                if (applyCouponButton) {
                  applyCouponButton.disabled = false;
                }
              }

              if (monedaSelect) {
                monedaSelect.addEventListener('change', function() {
                  const selectedOption = monedaSelect.options[monedaSelect.selectedIndex];
                  monedaActualId = selectedOption.value;
                  monedaActualClave = selectedOption.dataset.clave || 'USD';
                  monedaActualTasa = parseFloat(selectedOption.dataset.tasa || '1');
                  updatePackPrices();

                  if (activePack) {
                    const selectedCard = packCards2.find((card) => card.classList.contains('neon-selected'));
                    if (selectedCard) {
                      activePack = {
                        name: selectedCard.dataset.name,
                        price: selectedCard.dataset.price,
                        moneda: selectedCard.dataset.moneda,
                        cantidad: selectedCard.dataset.cantidad
                      };
                      updateResumenCompra(activePack);
                    }
                  } else {
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
                const precioNumerico = typeof pack.price === 'string' ? pack.price.replace(/,/g, '') : pack.price;
                console.log('Enviando cupón:', cupon, 'Precio:', precioNumerico);
                if (!cupon) {
                  showToast('Ingresa un cupón.', 'error');
                  return;
                }
                fetch('../api/validar_cupon.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: `code=${encodeURIComponent(cupon)}&pack_price=${encodeURIComponent(precioNumerico)}`
                })
                .then(res => res.json())
                .then(data => {
                  console.log('Respuesta backend:', data);
                  if (data.success) {
                    selectedPrice.textContent = `${pack.moneda} ${data.nuevo_total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    showToast(data.message + ` Descuento: ${data.descuento.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`,'success');
                    couponInput.disabled = true;
                    applyCouponButton.disabled = true;
                    couponApplied = true;
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
              orderForm.addEventListener('input', updateButtonState);
              orderForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const btn = document.getElementById('buy-button');
                const couponVal = normalizeCouponCode(couponInput.value);
                couponInput.value = couponVal;
                const userId = orderForm.user_id.value.trim();
                const email = orderForm.email.value.trim();
                const pack = activePack;
                if (!pack) {
                  showToast('Debes seleccionar un paquete.', 'error');
                  return;
                }
                // Validar campos obligatorios solo al intentar comprar
                const requiredFields = Array.from(orderForm.querySelectorAll('[required]'));
                let requiredFilled = true;
                requiredFields.forEach(field => {
                  const errorId = field.name + "-error";
                  let errorElem = document.getElementById(errorId);
                  if (field.value.trim() === "") {
                    requiredFilled = false;
                    if (!errorElem) {
                      errorElem = document.createElement("div");
                      errorElem.id = errorId;
                      errorElem.style.color = "#f87171";
                      errorElem.style.fontSize = "12px";
                      errorElem.textContent = "Este campo es obligatorio.";
                      field.parentNode.appendChild(errorElem);
                    }
                  } else {
                    if (errorElem) errorElem.remove();
                  }
                });
                if (!requiredFilled) {
                  return;
                }
                // Si el cupón no está aplicado y hay valor, mostrar modal
                if (couponVal && !couponApplied) {
                  setOverlayVisible(couponModal, true);
                  modalCouponName.textContent = couponVal;
                  modalYes.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    document.getElementById('apply-coupon-btn').click();
                    // Esperar a que se aplique el cupón y luego enviar el formulario
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 150);
                  };
                  modalNo.onclick = function() {
                    setOverlayVisible(couponModal, false);
                    couponApplied = false;
                    couponInput.value = '';
                    // Enviar el formulario sin cupón (ya no se mostrará el modal)
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 100);
                  };
                  modalCancel.onclick = function() {
                    setOverlayVisible(couponModal, false);
                  };
                  return;
                }
                // Envío AJAX del pedido
                                // Mostrar spinner y deshabilitar botón justo antes de enviar la compra
                                var spinner = document.getElementById('spinner-compra');
                                if (!spinner) {
                                  spinner = document.createElement('span');
                                  spinner.id = 'spinner-compra';
                                  spinner.innerHTML = `<svg width="22" height="22" viewBox="0 0 50 50" style="vertical-align:middle;"><circle cx="25" cy="25" r="20" fill="none" stroke="#34d399" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4" transform="rotate(-90 25 25)"><animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite"/></circle></svg>`;
                                  spinner.style.marginLeft = '8px';
                                  btn.appendChild(spinner);
                                }
                                btn.disabled = true;
                // El precio mostrado SIEMPRE es el que se envía, así se evita doble descuento
                let precioFinal = selectedPrice.textContent.replace(/[^\d.]/g, '');
                // Si no hay cupón aplicado, usar el precio base del paquete
                if (!couponApplied || !couponVal) {
                  precioFinal = typeof pack.price === 'string' ? pack.price.replace(/,/g, '') : pack.price;
                }
                const pedidoData = {
                  action: 'create',
                  game_id: "<?= $game['id'] ?>",
                  game_name: "<?= $game['nombre'] ?>",//window.gameName,
                  pack_name: pack.name || '',
                  pack_amount: pack.cantidad || '',
                  currency: pack.moneda || '',
                  price: precioFinal,
                  pack_base: typeof pack.price === 'string' ? pack.price.replace(/,/g, '') : pack.price,
                  user_identifier: userId,
                  email: email,
                  coupon: couponApplied ? couponVal : '',
                };
                console.log('Datos enviados a pedidos.php:', pedidoData);
                btn.disabled = true;
                setOverlayVisible(loadingModal, true);
                fetch('/api/pedidos.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: Object.keys(pedidoData).map(k => `${encodeURIComponent(k)}=${encodeURIComponent(pedidoData[k])}`).join('&')
                })
                .then(async res => {
                  let data = null;
                  try {
                    data = await res.json();
                  } catch (e) {
                    // Si no es JSON válido pero la respuesta es 200, asumimos éxito
                    if (res.ok) {
                      showToast('Pedido registrado correctamente', 'success');
                      orderForm.reset();
                      couponInput.disabled = false;
                      applyCouponButton.disabled = false;
                      couponApplied = false;
                      selectedPack.textContent = 'Ninguno';
                      selectedPrice.textContent = `${monedaActualClave} 0.00`;
                      return;
                    } else {
                      showToast('Error de red al registrar pedido', 'error');
                      return;
                    }
                  }
                  if (data && data.ok) {
                    showToast('Pedido registrado correctamente', 'success');
                    orderForm.reset();
                    couponInput.disabled = false;
                    applyCouponButton.disabled = false;
                    couponApplied = false;
                    selectedPack.textContent = 'Ninguno';
                    selectedPrice.textContent = `${monedaActualClave} 0.00`;
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
              });
              }
              </script>
            </section>
<?php
include __DIR__ . "/includes/footer.php";
?>
