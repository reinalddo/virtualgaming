<?php
$games = [
  "free-fire" => [
    "title" => "Free Fire Diamantes",
    "image" => "/assets/img/game-freefire.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "110", "label" => "110 Diamantes", "price" => "12.00"],
      ["amount" => "220", "label" => "220 Diamantes", "price" => "21.50"],
      ["amount" => "520", "label" => "520 Diamantes", "price" => "49.00"],
      ["amount" => "1060", "label" => "1060 Diamantes", "price" => "88.00"],
      ["amount" => "2180", "label" => "2180 Diamantes", "price" => "169.00"],
      ["amount" => "5600", "label" => "5600 Diamantes", "price" => "399.00"],
    ]
  ],
  "roblox" => [
    "title" => "Roblox Coins",
    "image" => "/assets/img/game-roblox.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "80", "label" => "80 Coins", "price" => "10.00"],
      ["amount" => "160", "label" => "160 Coins", "price" => "18.50"],
      ["amount" => "400", "label" => "400 Coins", "price" => "42.00"],
      ["amount" => "800", "label" => "800 Coins", "price" => "78.00"],
      ["amount" => "1700", "label" => "1700 Coins", "price" => "149.00"],
      ["amount" => "4500", "label" => "4500 Coins", "price" => "359.00"],
    ]
  ],
  "pubg" => [
    "title" => "PUBG UC",
    "image" => "/assets/img/game-pubg.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "60", "label" => "60 UC", "price" => "9.50"],
      ["amount" => "180", "label" => "180 UC", "price" => "26.00"],
      ["amount" => "325", "label" => "325 UC", "price" => "45.00"],
      ["amount" => "660", "label" => "660 UC", "price" => "82.00"],
      ["amount" => "1800", "label" => "1800 UC", "price" => "215.00"],
      ["amount" => "3850", "label" => "3850 UC", "price" => "420.00"],
    ]
  ],
  "gift-cards" => [
    "title" => "Gift Cards",
    "image" => "/assets/img/game-gift.jpg",
    "tags" => ["Canje inmediato", "Digital", "Soporte 24/7"],
    "packs" => [
      ["amount" => "10", "label" => "Gift Card 10", "price" => "15.00"],
      ["amount" => "25", "label" => "Gift Card 25", "price" => "35.00"],
      ["amount" => "50", "label" => "Gift Card 50", "price" => "65.00"],
      ["amount" => "100", "label" => "Gift Card 100", "price" => "120.00"],
    ]
  ],
  "call-of-duty" => [
    "title" => "Call of Duty",
    "image" => "/assets/img/game-cod.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "80", "label" => "80 CP", "price" => "11.00"],
      ["amount" => "420", "label" => "420 CP", "price" => "52.00"],
      ["amount" => "880", "label" => "880 CP", "price" => "95.00"],
      ["amount" => "2400", "label" => "2400 CP", "price" => "255.00"],
    ]
  ],
  "fortnite" => [
    "title" => "Fortnite",
    "image" => "/assets/img/game-fortnite.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "1000", "label" => "1000 V-Bucks", "price" => "13.50"],
      ["amount" => "2800", "label" => "2800 V-Bucks", "price" => "32.00"],
      ["amount" => "5000", "label" => "5000 V-Bucks", "price" => "52.00"],
      ["amount" => "13500", "label" => "13500 V-Bucks", "price" => "120.00"],
    ]
  ],
  "valorant" => [
    "title" => "Valorant",
    "image" => "/assets/img/game-valorant.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "420", "label" => "420 VP", "price" => "10.00"],
      ["amount" => "1000", "label" => "1000 VP", "price" => "21.00"],
      ["amount" => "2050", "label" => "2050 VP", "price" => "41.00"],
      ["amount" => "5350", "label" => "5350 VP", "price" => "95.00"],
    ]
  ],
  "garena" => [
    "title" => "Garena Shells",
    "image" => "/assets/img/game-garena.jpg",
    "tags" => ["Global", "Entrega inmediata", "Soporte 24/7"],
    "packs" => [
      ["amount" => "100", "label" => "100 Shells", "price" => "7.00"],
      ["amount" => "210", "label" => "210 Shells", "price" => "13.50"],
      ["amount" => "525", "label" => "525 Shells", "price" => "30.00"],
      ["amount" => "1100", "label" => "1100 Shells", "price" => "58.00"],
    ]
  ],
];

$slug = isset($_GET["slug"]) ? strtolower(trim($_GET["slug"])) : "free-fire";
if (!array_key_exists($slug, $games)) {
  $slug = "free-fire";
}
$game = $games[$slug];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>TVirtualGaming | <?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            oxanium: ["Oxanium", "sans-serif"],
            space: ["Space Grotesk", "sans-serif"]
          }
        }
      }
    };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root {
      --bg-0: #0a0f14;
      --bg-1: #0e1722;
      --neon-0: #22d3ee;
      --neon-1: #2dd4bf;
      --neon-2: #34d399;
    }
    body {
      font-family: "Space Grotesk", "Oxanium", sans-serif;
      background: radial-gradient(circle at top, #0c1522 0%, #0a0f14 55%, #080b10 100%);
    }
    .glow-ring {
      box-shadow: 0 0 0.75rem rgba(34, 211, 238, 0.4), 0 0 2.2rem rgba(45, 212, 191, 0.2);
    }
  </style>
</head>
<body class="min-h-screen text-slate-100">
  <div class="relative min-h-screen overflow-hidden">
    <div class="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-cyan-500/15 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

    <div class="relative mx-auto w-full max-w-6xl px-4 pb-12 pt-5" data-tenant="tvirtualgaming">
      <header class="flex items-center justify-between">
        <button id="menu-toggle" class="rounded-full border border-slate-800 bg-slate-900/60 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Abrir menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="text-center">
          <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-300/70">tienda</p>
          <h1 class="font-oxanium text-lg font-semibold text-white">TVirtualGaming</h1>
        </div>
        <a href="/login" class="rounded-full border border-cyan-400/30 bg-slate-900/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-cyan-200 transition hover:border-cyan-300 hover:text-white">Iniciar sesion</a>
      </header>

      <div id="menu-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm"></div>
      <nav id="menu-panel" class="fixed left-1/2 top-20 z-50 hidden w-[min(92vw,420px)] -translate-x-1/2 rounded-2xl border border-slate-800 bg-slate-900/95 p-4 shadow-2xl">
        <div class="flex items-center justify-between">
          <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Menu</p>
          <button id="menu-close" class="rounded-full border border-slate-800 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Cerrar menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="mt-4 space-y-2">
          <a href="/" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Inicio</a>
          <a href="/populares" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Juegos populares</a>
          <a href="/juegos" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Juegos</a>
        </div>
      </nav>

      <section class="mt-6 rounded-2xl border border-slate-800 bg-slate-900/70 p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex items-center gap-4">
            <div class="h-16 w-16 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950/70">
              <img src="<?php echo htmlspecialchars($game["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?>" class="h-full w-full object-cover" />
            </div>
            <div>
              <h2 class="font-oxanium text-lg font-semibold"><?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?></h2>
              <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                <?php foreach ($game["tags"] as $tag): ?>
                  <span class="rounded-full border border-slate-700 px-2 py-0.5"><?php echo htmlspecialchars($tag, ENT_QUOTES, "UTF-8"); ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="rounded-2xl border border-cyan-400/30 bg-cyan-400/10 px-4 py-2 text-xs text-cyan-200">
            URL: /tienda/<?php echo htmlspecialchars($slug, ENT_QUOTES, "UTF-8"); ?>
          </div>
        </div>
      </section>

      <section class="mt-6">
        <div class="flex items-center justify-between">
          <h3 class="font-oxanium text-base font-semibold">Paquetes disponibles</h3>
          <span class="text-xs uppercase tracking-[0.3em] text-slate-500">elige uno</span>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4" id="pack-grid">
          <?php foreach ($game["packs"] as $pack): ?>
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

  <script>
    const menuToggle = document.getElementById("menu-toggle");
    const menuOverlay = document.getElementById("menu-overlay");
    const menuPanel = document.getElementById("menu-panel");
    const menuClose = document.getElementById("menu-close");

    const openMenu = () => {
      menuOverlay.classList.remove("hidden");
      menuPanel.classList.remove("hidden");
    };

    const closeMenu = () => {
      menuOverlay.classList.add("hidden");
      menuPanel.classList.add("hidden");
    };

    menuToggle.addEventListener("click", openMenu);
    menuClose.addEventListener("click", closeMenu);
    menuOverlay.addEventListener("click", closeMenu);

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
</body>
</html>
