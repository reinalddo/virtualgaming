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
        data-price="<?= number_format($precio_mostrar, 2, '.', ',') ?>"
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
    const isVariable = <?= $is_variable ? 'true' : 'false' ?>;
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
        card.setAttribute('data-price', precio.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        card.setAttribute('data-moneda', monedaActualClave);
      });
    }
    if (isVariable && monedaSelect) {
      monedaSelect.addEventListener('change', function() {
        monedaActualId = this.value;
        monedaActualClave = monedas[monedaActualId].clave;
        monedaActualTasa = monedas[monedaActualId].tasa;
        updatePackPrices();
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
          <div>
            <label class="text-xs text-slate-400">Cupon</label>
            <input type="text" name="coupon" placeholder="Codigo opcional" class="form-field mt-1 w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" />
          </div>
          <button type="submit" id="buy-button" class="glow-ring mt-4 w-full rounded-2xl bg-gradient-to-r from-cyan-400 via-emerald-400 to-cyan-300 px-4 py-4 text-center text-sm font-bold uppercase tracking-[0.3em] text-slate-950 transition disabled:cursor-not-allowed disabled:opacity-50" disabled>
            Compra ahora
          </button>
        </form>
      </section>
    </div>
  </div>

<?php
$pageScripts = [
  <<<'SCRIPT'
<script>
  const packCards2 = Array.from(document.querySelectorAll(".pack-card"));
  const selectedPack = document.getElementById("selected-pack");
  const selectedPrice = document.getElementById("selected-price");
  const orderForm = document.getElementById("order-form");
  const buyButton = document.getElementById("buy-button");
  let activePack = null;

  function updateButtonState() {
    const requiredFilled = Array.from(orderForm.querySelectorAll("[required]")).every(
      (field) => field.value.trim() !== ""
    );
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

  if (isVariable && monedaSelect) {
    monedaSelect.addEventListener('change', function() {
      // Limpiar selección de paquete y resumen al cambiar moneda
      activePack = null;
      updateResumenCompra(null);
      updateButtonState();
    });
  }

  orderForm.addEventListener("input", updateButtonState);
  orderForm.addEventListener("submit", (event) => {
    event.preventDefault();
  });
</script>
SCRIPT
];
include __DIR__ . "/includes/footer.php";
?>
