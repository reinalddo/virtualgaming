<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EnlineaCoins | Tienda de monedas digitales</title>
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
      background: radial-gradient(circle at top, #0c1522 0%, #0a0f14 50%, #080b10 100%);
    }
    .glow-ring {
      box-shadow: 0 0 0.75rem rgba(34, 211, 238, 0.4), 0 0 2.2rem rgba(45, 212, 191, 0.2);
    }
    @keyframes floaty {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="min-h-screen text-slate-100">
  <div class="relative min-h-screen overflow-hidden">
    <div class="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-cyan-500/15 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

    <div class="relative mx-auto w-full max-w-6xl px-4 pb-10 pt-5" data-tenant="enlineacoins">
      <header class="flex items-center justify-between">
        <button class="rounded-full border border-slate-800 bg-slate-900/60 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="text-center">
          <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-300/70">tienda</p>
          <h1 class="font-oxanium text-lg font-semibold text-white">EnlineaCoins</h1>
        </div>
        <a href="/login" class="rounded-full border border-cyan-400/30 bg-slate-900/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-cyan-200 transition hover:border-cyan-300 hover:text-white">Iniciar sesion</a>
      </header>

      <section class="mt-6 space-y-4" style="animation: fadeUp 650ms ease-out both;">
        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
          <img src="assets/img/banner-1.jpg" alt="Recarga rapida" class="h-28 w-full object-cover opacity-80" />
          <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/40 to-transparent"></div>
          <div class="absolute inset-0 flex flex-col justify-center px-4">
            <p class="text-xs uppercase tracking-[0.3em] text-cyan-300/70">promo activa</p>
            <h2 class="mt-1 font-oxanium text-lg font-semibold text-white">Recarga con cupon y ahorra hoy</h2>
            <p class="mt-1 text-xs text-slate-300">Entrega inmediata y soporte 24/7</p>
          </div>
        </div>
        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
          <img src="assets/img/banner-2.jpg" alt="Bono de bienvenida" class="h-24 w-full object-cover opacity-80" />
          <div class="absolute inset-0 bg-gradient-to-r from-slate-950/80 via-transparent to-slate-950/90"></div>
          <div class="absolute inset-0 flex items-center justify-between px-4">
            <div>
              <p class="text-xs uppercase tracking-[0.3em] text-emerald-300/70">bienvenida</p>
              <h3 class="mt-1 font-oxanium text-base font-semibold">+10% en tu primera compra</h3>
            </div>
            <span class="rounded-full border border-emerald-400/40 bg-emerald-400/10 px-3 py-1 text-[11px] font-semibold text-emerald-200">Usa: START10</span>
          </div>
        </div>
      </section>

      <section class="mt-8">
        <div class="flex items-center justify-between">
          <h2 class="font-oxanium text-base font-semibold">Juegos populares</h2>
          <a href="/tienda" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Ver todo</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <a href="/tienda/roblox" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <img src="assets/img/game-roblox.jpg" alt="Roblox" class="h-24 w-full rounded-xl object-cover" />
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Roblox Coins</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 12.00</span></p>
            </div>
          </a>
          <a href="/tienda/free-fire" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <img src="assets/img/game-freefire.jpg" alt="Free Fire" class="h-24 w-full rounded-xl object-cover" />
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Free Fire</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 8.00</span></p>
            </div>
          </a>
          <a href="/tienda/pubg" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <img src="assets/img/game-pubg.jpg" alt="PUBG" class="h-24 w-full rounded-xl object-cover" />
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">PUBG UC</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 9.50</span></p>
            </div>
          </a>
          <a href="/tienda/gift-cards" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <img src="assets/img/game-gift.jpg" alt="Gift cards" class="h-24 w-full rounded-xl object-cover" />
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Gift Cards</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 15.00</span></p>
            </div>
          </a>
        </div>
      </section>

    </div>
  </div>
</body>
</html>
