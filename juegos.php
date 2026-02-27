<?php
$allGames = [
  [
    "slug" => "roblox",
    "title" => "Roblox Coins",
    "price" => "12.00",
    "image" => "/assets/img/game-roblox.jpg",
  ],
  [
    "slug" => "free-fire",
    "title" => "Free Fire",
    "price" => "8.00",
    "image" => "/assets/img/game-freefire.jpg",
  ],
  [
    "slug" => "pubg",
    "title" => "PUBG UC",
    "price" => "9.50",
    "image" => "/assets/img/game-pubg.jpg",
  ],
  [
    "slug" => "gift-cards",
    "title" => "Gift Cards",
    "price" => "15.00",
    "image" => "/assets/img/game-gift.jpg",
  ],
  [
    "slug" => "call-of-duty",
    "title" => "Call of Duty",
    "price" => "11.00",
    "image" => "/assets/img/game-cod.jpg",
  ],
  [
    "slug" => "fortnite",
    "title" => "Fortnite",
    "price" => "13.50",
    "image" => "/assets/img/game-fortnite.jpg",
  ],
  [
    "slug" => "valorant",
    "title" => "Valorant",
    "price" => "10.00",
    "image" => "/assets/img/game-valorant.jpg",
  ],
  [
    "slug" => "garena",
    "title" => "Garena Shells",
    "price" => "7.00",
    "image" => "/assets/img/game-garena.jpg",
  ],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>TVirtualGaming | Juegos</title>
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

      <section class="mt-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70">catalogo</p>
            <h2 class="mt-2 font-oxanium text-lg font-semibold">Todos los juegos</h2>
          </div>
          <a href="/populares" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Ver populares</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <?php foreach ($allGames as $game): ?>
            <a href="/tienda/<?php echo htmlspecialchars($game["slug"], ENT_QUOTES, "UTF-8"); ?>" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
              <div class="overflow-hidden rounded-xl">
                <img src="<?php echo htmlspecialchars($game["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?>" class="aspect-square w-full object-cover" />
              </div>
              <div class="mt-2 space-y-1">
                <p class="text-sm font-semibold"><?php echo htmlspecialchars($game["title"], ENT_QUOTES, "UTF-8"); ?></p>
                <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. <?php echo htmlspecialchars($game["price"], ENT_QUOTES, "UTF-8"); ?></span></p>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
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
  </script>
</body>
</html>
