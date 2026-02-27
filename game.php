<?php
require_once __DIR__ . "/includes/data.php";
$slug = isset($_GET["slug"]) ? strtolower(trim($_GET["slug"])) : "free-fire";
$slug = preg_replace("/[^a-z0-9-]/", "", $slug);
$gamesBySlug = $tenantData["gamesBySlug"] ?? [];

if (!array_key_exists($slug, $gamesBySlug)) {
  $slug = array_key_first($gamesBySlug) ?: "free-fire";
}

$game = $gamesBySlug[$slug] ?? [
  "title" => "Juego",
  "image" => "/assets/img/game-freefire.jpg",
  "tags" => [],
  "packs" => []
];

$pageTitle = $brandName . " | " . ($game["title"] ?? "Juego");
include __DIR__ . "/includes/header.php";
?>

      <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-4">
            <div class="h-16 w-16 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
              <img src="<?php echo htmlspecialchars($game["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?>" class="h-full w-full object-cover" />
            </div>
            <div>
              <h2 class="font-oxanium text-lg font-semibold"><?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?></h2>
              <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                <?php foreach (($game["tags"] ?? []) as $tag): ?>
                  <span class="rounded-full border border-slate-700 px-2 py-0.5"><?php echo htmlspecialchars($tag, ENT_QUOTES, "UTF-8"); ?></span>
                <?php endforeach; ?>
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
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" id="pack-grid">
          <?php foreach (($game["packs"] ?? []) as $pack): ?>
            <button type="button" class="pack-card rounded-2xl border border-slate-800 bg-slate-900/60 p-3 text-left transition hover:border-cyan-400/60" data-name="<?php echo htmlspecialchars($pack["label"], ENT_QUOTES, "UTF-8"); ?>" data-price="<?php echo htmlspecialchars($pack["price"], ENT_QUOTES, "UTF-8"); ?>">
              <div class="flex h-20 items-center justify-center rounded-xl border border-slate-800 bg-slate-950/70">
                <span class="text-sm font-semibold text-cyan-200"><?php echo htmlspecialchars($pack["amount"], ENT_QUOTES, "UTF-8"); ?></span>
              </div>
              <p class="mt-3 text-sm font-semibold"><?php echo htmlspecialchars($pack["label"], ENT_QUOTES, "UTF-8"); ?></p>
              <p class="text-xs text-slate-400">Bs. <span class="text-cyan-300"><?php echo htmlspecialchars($pack["price"], ENT_QUOTES, "UTF-8"); ?></span></p>
            </button>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <div class="flex items-center justify-between">
          <h3 class="font-oxanium text-base font-semibold">Resumen de compra</h3>
          <span class="text-[10px] uppercase tracking-[0.3em] text-slate-500">verifica</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-[1.2fr_1fr]">
          <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3">
            <p class="text-xs text-slate-400">Paquete seleccionado</p>
            <p id="selected-pack" class="mt-2 text-sm font-semibold text-white">Ninguno</p>
          </div>
          <div class="rounded-xl border border-slate-800 bg-slate-950/70 p-3">
            <p class="text-xs text-slate-400">Total</p>
            <p id="selected-price" class="mt-2 text-lg font-semibold text-cyan-300">Bs. 0.00</p>
          </div>
        </div>
      </section>

      <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
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
  const packCards = Array.from(document.querySelectorAll(".pack-card"));
  const selectedPack = document.getElementById("selected-pack");
  const selectedPrice = document.getElementById("selected-price");
  const orderForm = document.getElementById("order-form");
  const buyButton = document.getElementById("buy-button");
  let activePack = null;

  const updateButtonState = () => {
    const requiredFilled = Array.from(orderForm.querySelectorAll("[required]")).every(
      (field) => field.value.trim() !== ""
    );
    buyButton.disabled = !activePack || !requiredFilled;
  };

  packCards.forEach((card) => {
    card.addEventListener("click", () => {
      packCards.forEach((item) => {
        item.classList.remove("border-cyan-400", "bg-slate-900/90");
        item.classList.add("border-slate-800", "bg-slate-900/60");
      });
      card.classList.remove("border-slate-800", "bg-slate-900/60");
      card.classList.add("border-cyan-400", "bg-slate-900/90");

      activePack = {
        name: card.dataset.name,
        price: card.dataset.price
      };
      selectedPack.textContent = activePack.name;
      selectedPrice.textContent = `Bs. ${activePack.price}`;
      updateButtonState();
    });
  });

  orderForm.addEventListener("input", updateButtonState);

  orderForm.addEventListener("submit", (event) => {
    event.preventDefault();
  });
</script>
SCRIPT
];
include __DIR__ . "/includes/footer.php";
?>
