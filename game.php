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

<section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-4">
      <div class="h-16 w-16 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70 relative">
        <img src="/<?= htmlspecialchars($game["imagen"] ?? '', ENT_QUOTES, "UTF-8") ?>" alt="<?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?>" class="h-full w-full object-cover" />
        <?php if (!empty($game['popular'])): ?>
          <span title="Popular" class="absolute top-2 right-2 text-emerald-400 text-xl drop-shadow">★</span>
        <?php endif; ?>
      </div>
      <div>
        <h2 class="font-oxanium text-lg font-semibold flex items-center">
          <?= htmlspecialchars($game["nombre"] ?? '', ENT_QUOTES, "UTF-8") ?>
        </h2>
        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
          <?php 
            $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($game['id']));
            while ($row = $carRes->fetch_assoc()) {
              echo '<span class="rounded-full border border-slate-700 px-2 py-0.5">' . htmlspecialchars($row['caracteristica']) . '</span>';
            }
          ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mt-6">
  <div class="flex items-center justify-between">
    <h3 class="font-oxanium text-base font-semibold">Paquetes disponibles</h3>
    <span class="text-xs uppercase tracking-[0.3em] text-slate-500">elige uno</span>
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
      <label for="moneda-select" class="block text-slate-300 text-sm mb-1">Selecciona la moneda:</label>
      <select id="moneda-select" class="rounded-lg px-3 py-2 bg-slate-800 text-white" style="min-width:180px">
        <?php foreach ($monedas as $m): ?>
          <option value="<?= $m['id'] ?>" data-tasa="<?= htmlspecialchars($m['tasa']) ?>" data-clave="<?= htmlspecialchars($m['clave']) ?>" <?= $m['id'] == $moneda_actual['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 max-w-4xl mx-auto" id="pack-grid">
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
      <button type="button" class="pack-card relative rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-left transition hover:border-cyan-400/60"
        data-base="<?= htmlspecialchars($precio_base) ?>"
        data-name="<?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?>"
        data-cantidad="<?= htmlspecialchars($pack['cantidad'], ENT_QUOTES, 'UTF-8') ?>"
        data-price="<?= number_format($precio_mostrar, 2, '.', '') ?>"
        data-moneda="<?= htmlspecialchars($clave_moneda) ?>">
        <div class="flex flex-col items-center justify-center h-20 rounded-xl border border-slate-800 bg-slate-950/70 overflow-hidden">
          <span class="text-sm font-semibold text-cyan-200"><?= htmlspecialchars($pack['cantidad'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <p class="mt-3 text-sm font-semibold"><?= htmlspecialchars($pack['nombre'], ENT_QUOTES, 'UTF-8') ?></p>
        <div class="flex items-end justify-between mt-1">
          <p class="text-xs text-slate-400">
            <span class="moneda-label"><?= htmlspecialchars($clave_moneda) ?></span>.
            <span class="text-cyan-300 precio-label">
              <?= number_format($precio_mostrar, 2, '.', ',') ?>
            </span>
          </p>
          <?php 
            $img_paquete = !empty($pack['imagen_icono']) ? $pack['imagen_icono'] : (!empty($game['imagen_paquete']) ? $game['imagen_paquete'] : null);
            if ($img_paquete):
          ?>
            <img src="/<?= htmlspecialchars($img_paquete, ENT_QUOTES, 'UTF-8') ?>" alt="Imagen Paquete" class="h-10 w-10 object-cover rounded absolute bottom-3 right-3 shadow-md border border-slate-700 bg-slate-900" />
          <?php endif; ?>
        </div>
      </button>
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

      <br><br>
      <h3 class="font-oxanium text-base font-semibold">Resumen de compra</h3>
      <span class="text-[10px] uppercase tracking-[0.3em] text-slate-500">verifica</span>
    </div>
    <div class="mt-4 grid gap-3 sm:grid-cols-[1.2fr_1fr] max-w-4xl mx-auto">
      <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3">
        <p class="text-xs text-slate-400">Paquete seleccionado</p>
        <p id="selected-pack" class="mt-2 text-sm font-semibold text-white">Ninguno</p>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3">
        <p class="text-xs text-slate-400">Total</p>
        <p id="selected-price" class="mt-2 text-lg font-semibold text-cyan-300"><?= $moneda_actual['clave'] ?? 'Bs.' ?> 0.00</p>
      </div>
    </div>
  </section>

      <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-4 max-w-4xl mx-auto">
        <div class="flex items-center justify-between">
          <h3 class="font-oxanium text-base font-semibold">Informacion de pedido</h3>
          <span class="text-[10px] uppercase tracking-[0.3em] text-slate-500">seguro</span>
        </div>
        <form class="mt-4 space-y-3" id="order-form">
          <div>
            <label class="text-xs text-slate-400">ID de usuario</label>
            <input type="text" name="user_id" placeholder="Ej: 12345678" class="form-field mt-1 w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" required />
          </div>
          <div>
            <label class="text-xs text-slate-400">Correo</label>
            <input type="email" name="email" placeholder="tu@email.com" class="form-field mt-1 w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" required />
          </div>
          <div class="relative">
            <label class="text-xs text-slate-400">Cupon</label>
            <div style="display: flex; gap: 8px;">
              <input type="text" name="coupon" id="coupon-input" placeholder="Codigo opcional" class="form-field mt-1 w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" />
              <button type="button" id="apply-coupon-btn" class="mt-1 px-3 py-2 rounded-xl bg-cyan-400 text-slate-900 font-bold shadow hover:bg-cyan-300 transition">Aplicar cupón</button>
            </div>
          </div>
          <button type="submit" id="buy-button" class="glow-ring mt-4 w-full rounded-2xl bg-gradient-to-r from-cyan-400 via-emerald-400 to-cyan-300 px-4 py-4 text-center text-sm font-bold uppercase tracking-[0.3em] text-slate-950 transition disabled:cursor-not-allowed disabled:opacity-50" disabled>
            Compra ahora
          </button>
        </form>

        <!-- Modal Neon Tailwind -->
        <div id="coupon-modal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 hidden">
          <div class="bg-slate-900 rounded-2xl border-2 border-cyan-400 shadow-lg p-6 max-w-xs w-full text-center animate-fadeUp">
            <h4 class="text-lg font-bold text-cyan-300 mb-2">¿Desea aplicar el cupón <span id="modal-coupon-name" class="text-emerald-400"></span>?</h4>
            <div class="flex gap-2 justify-center mt-4">
              <button id="modal-yes" class="px-4 py-2 rounded-lg bg-emerald-400 text-slate-900 font-bold shadow hover:bg-emerald-300 transition">Sí</button>
              <button id="modal-no" class="px-4 py-2 rounded-lg bg-cyan-400 text-slate-900 font-bold shadow hover:bg-cyan-300 transition">No</button>
              <button id="modal-cancel" class="px-4 py-2 rounded-lg bg-slate-700 text-white font-bold shadow hover:bg-slate-600 transition">Cancelar</button>
            </div>
          </div>
        </div>

<script>
              // Todas las variables y lógica JS en un solo bloque
              const packCards2 = Array.from(document.querySelectorAll('.pack-card'));
              const selectedPack = document.getElementById("selected-pack");
              const selectedPrice = document.getElementById("selected-price");
              const orderForm = document.getElementById("order-form");
              const buyButton = document.getElementById("buy-button");
              const couponInput = document.getElementById('coupon-input');
              const couponModal = document.getElementById('coupon-modal');
              const modalCouponName = document.getElementById('modal-coupon-name');
              const modalYes = document.getElementById('modal-yes');
              const modalNo = document.getElementById('modal-no');
              const modalCancel = document.getElementById('modal-cancel');
              let activePack = null;
              let couponApplied = false;
              let couponValue = '';

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
                    item.classList.remove("border-cyan-400", "bg-slate-900/90");
                    item.classList.add("border-slate-800", "bg-slate-900/60");
                  });
                  card.classList.remove("border-slate-800", "bg-slate-900/60");
                  card.classList.add("border-cyan-400", "bg-slate-900/90");
                  activePack = {
                    name: card.dataset.name,
                    price: card.dataset.price,
                    moneda: card.dataset.moneda
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
                toast.style.bottom = '30px';
                toast.style.left = '50%';
                toast.style.transform = 'translateX(-50%)';
                toast.style.background = type === 'error' ? '#f87171' : '#34d399';
                toast.style.color = '#222';
                toast.style.padding = '12px 24px';
                toast.style.borderRadius = '8px';
                toast.style.fontWeight = 'bold';
                toast.style.zIndex = '9999';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
              }
              // Validación de cupón por AJAX
              document.getElementById('apply-coupon-btn').addEventListener('click', function() {
                const cupon = couponInput.value.trim();
                const pack = activePack;
                // Aseguramos que el precio sea un número puro
                const precioNumerico = typeof pack.price === 'string' ? pack.price.replace(/,/g, '') : pack.price;
                console.log('Enviando cupón:', cupon, 'Precio:', precioNumerico);
                if (!cupon) {
                  showToast('Ingresa un cupón.', 'error');
                  return;
                }
                if (!pack) {
                  showToast('Selecciona un paquete antes de aplicar el cupón.', 'error');
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
                    document.getElementById('apply-coupon-btn').disabled = true;
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
                couponModal.classList.add('hidden');
                showToast('Compra sin cupón aplicado', 'info');
              });
              modalCancel.addEventListener('click', function() {
                couponModal.classList.add('hidden');
              });
              orderForm.addEventListener('input', updateButtonState);
              orderForm.addEventListener('submit', function(event) {
                event.preventDefault();
                const couponVal = couponInput.value.trim();
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
                  couponModal.classList.remove('hidden');
                  modalCouponName.textContent = couponVal;
                  modalYes.onclick = function() {
                    couponModal.classList.add('hidden');
                    document.getElementById('apply-coupon-btn').click();
                    // Esperar a que se aplique el cupón y luego enviar el formulario
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 500);
                  };
                  modalNo.onclick = function() {
                    couponModal.classList.add('hidden');
                    couponApplied = false;
                    // Enviar el formulario sin aplicar cupón
                    setTimeout(() => orderForm.dispatchEvent(new Event('submit', {cancelable: true})), 100);
                  };
                  modalCancel.onclick = function() {
                    couponModal.classList.add('hidden');
                  };
                  return;
                }
                // Envío AJAX del pedido
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
                fetch('../api/pedidos.php', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: Object.keys(pedidoData).map(k => `${encodeURIComponent(k)}=${encodeURIComponent(pedidoData[k])}`).join('&')
                })
                .then(res => res.json())
                .then(data => {
                  if (data.ok) {
                    showToast('Pedido registrado correctamente', 'success');
                    orderForm.reset();
                    couponInput.disabled = false;
                    document.getElementById('apply-coupon-btn').disabled = false;
                    couponApplied = false;
                    selectedPack.textContent = 'Ninguno';
                    selectedPrice.textContent = `${monedaActualClave} 0.00`;
                  } else {
                    showToast(data.message || 'Error al registrar pedido', 'error');
                  }
                })
                .catch(() => {
                  showToast('Error de red al registrar pedido.', 'error');
                });
              });
              }
              </script>
            </section>
<?php
include __DIR__ . "/includes/footer.php";
?>
