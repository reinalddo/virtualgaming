<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>TVirtualGaming | Tienda de monedas digitales</title>
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

    <div class="relative mx-auto w-full max-w-6xl px-4 pb-10 pt-5" data-tenant="tvirtualgaming">
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

      <section class="mt-6 space-y-4" style="animation: fadeUp 650ms ease-out both;">
        <div class="relative">
          <div id="promo-slider" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth rounded-2xl border border-slate-800 bg-slate-900/60 p-2 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            <article class="relative h-32 min-w-[80%] snap-start overflow-hidden rounded-2xl border border-slate-800">
              <img src="/assets/img/banner-1.jpg" alt="Recarga rapida" class="h-full w-full object-cover opacity-85" />
              <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/30 to-transparent"></div>
              <div class="absolute inset-0 flex flex-col justify-center px-4">
                <p class="text-[10px] uppercase tracking-[0.35em] text-cyan-300/70">promo activa</p>
                <h2 class="mt-1 font-oxanium text-lg font-semibold text-white">Recarga con cupon y ahorra hoy</h2>
                <p class="mt-1 text-xs text-slate-300">Entrega inmediata y soporte 24/7</p>
              </div>
            </article>
            <article class="relative h-32 min-w-[80%] snap-start overflow-hidden rounded-2xl border border-slate-800">
              <img src="/assets/img/banner-2.jpg" alt="Bono de bienvenida" class="h-full w-full object-cover opacity-85" />
              <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-transparent to-slate-950/80"></div>
              <div class="absolute inset-0 flex flex-col justify-center px-4">
                <p class="text-[10px] uppercase tracking-[0.35em] text-emerald-300/70">bienvenida</p>
                <h3 class="mt-1 font-oxanium text-lg font-semibold">+10% en tu primera compra</h3>
                <p class="mt-1 text-xs text-slate-300">Usa el codigo START10</p>
              </div>
            </article>
            <article class="relative h-32 min-w-[80%] snap-start overflow-hidden rounded-2xl border border-slate-800">
              <img src="/assets/img/banner-3.jpg" alt="Bonos semanales" class="h-full w-full object-cover opacity-85" />
              <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/20 to-transparent"></div>
              <div class="absolute inset-0 flex flex-col justify-center px-4">
                <p class="text-[10px] uppercase tracking-[0.35em] text-cyan-300/70">bonos</p>
                <h3 class="mt-1 font-oxanium text-lg font-semibold">Paquetes exclusivos cada semana</h3>
                <p class="mt-1 text-xs text-slate-300">Activa alertas y no te los pierdas</p>
              </div>
            </article>
          </div>
          <div id="promo-dots" class="mt-3 flex items-center gap-2">
            <button type="button" class="h-1.5 w-6 rounded-full bg-cyan-400 transition" data-index="0" aria-label="Banner 1"></button>
            <button type="button" class="h-1.5 w-4 rounded-full bg-slate-700 transition" data-index="1" aria-label="Banner 2"></button>
            <button type="button" class="h-1.5 w-4 rounded-full bg-slate-700 transition" data-index="2" aria-label="Banner 3"></button>
          </div>
          <div class="pointer-events-none absolute inset-y-0 left-3 right-3 hidden items-center justify-between md:flex">
            <button type="button" class="pointer-events-auto rounded-full border border-slate-700 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" data-action="prev" aria-label="Anterior">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button type="button" class="pointer-events-auto rounded-full border border-slate-700 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" data-action="next" aria-label="Siguiente">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
          <p class="mt-2 text-[11px] text-slate-500">Desliza para ver mas promociones</p>
        </div>
      </section>

      <section class="mt-8">
        <div class="flex items-center justify-between">
          <h2 class="font-oxanium text-base font-semibold">Juegos populares</h2>
          <a href="/populares" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Ver todo</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <a href="/tienda/roblox" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-roblox.jpg" alt="Roblox" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Roblox Coins</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 12.00</span></p>
            </div>
          </a>
          <a href="/tienda/free-fire" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-freefire.jpg" alt="Free Fire" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Free Fire</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 8.00</span></p>
            </div>
          </a>
          <a href="/tienda/pubg" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-pubg.jpg" alt="PUBG" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">PUBG UC</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 9.50</span></p>
            </div>
          </a>
          <a href="/tienda/gift-cards" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-gift.jpg" alt="Gift cards" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Gift Cards</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 15.00</span></p>
            </div>
          </a>
        </div>
      </section>

      <section class="mt-8">
        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
          <img src="/assets/img/featured-1.jpg" alt="Imagen destacada" class="h-36 w-full object-cover opacity-85" />
          <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/40 to-transparent"></div>
          <div class="absolute inset-0 flex flex-col justify-center px-4">
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70">destacado</p>
            <h3 class="mt-1 font-oxanium text-lg font-semibold">Nuevas ofertas multitienda</h3>
            <p class="mt-1 text-xs text-slate-300">Se actualiza desde el panel admin</p>
          </div>
        </div>
      </section>

      <section class="mt-8">
        <div class="flex items-center justify-between">
          <h2 class="font-oxanium text-base font-semibold">Mas juegos</h2>
          <a href="/juegos" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Explorar</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <a href="/tienda/call-of-duty" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-cod.jpg" alt="Call of Duty" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Call of Duty</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 11.00</span></p>
            </div>
          </a>
          <a href="/tienda/fortnite" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-fortnite.jpg" alt="Fortnite" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Fortnite</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 13.50</span></p>
            </div>
          </a>
          <a href="/tienda/valorant" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-valorant.jpg" alt="Valorant" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Valorant</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 10.00</span></p>
            </div>
          </a>
          <a href="/tienda/garena" class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-2 transition hover:border-cyan-400/60">
            <div class="overflow-hidden rounded-xl">
              <img src="/assets/img/game-garena.jpg" alt="Garena" class="aspect-square w-full object-cover" />
            </div>
            <div class="mt-2 space-y-1">
              <p class="text-sm font-semibold">Garena Shells</p>
              <p class="text-xs text-slate-400">Desde <span class="text-cyan-300">Bs. 7.00</span></p>
            </div>
          </a>
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

  const slider = document.getElementById("promo-slider");
  const dots = Array.from(document.querySelectorAll("#promo-dots [data-index]"));
  const slides = Array.from(slider.querySelectorAll("article"));

  const setActiveDot = (index) => {
    dots.forEach((dot, idx) => {
      if (idx === index) {
        dot.classList.add("bg-cyan-400", "w-6");
        dot.classList.remove("bg-slate-700", "w-4");
      } else {
        dot.classList.add("bg-slate-700", "w-4");
        dot.classList.remove("bg-cyan-400", "w-6");
      }
    });
  };

  const getActiveIndex = () => {
    const sliderRect = slider.getBoundingClientRect();
    const centerX = sliderRect.left + sliderRect.width / 2;
    let closestIndex = 0;
    let closestDistance = Infinity;

    slides.forEach((slide, index) => {
      const rect = slide.getBoundingClientRect();
      const slideCenter = rect.left + rect.width / 2;
      const distance = Math.abs(centerX - slideCenter);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    return closestIndex;
  };

  const scrollToIndex = (index) => {
    const target = slides[index];
    if (target) {
      slider.scrollTo({ left: target.offsetLeft - slider.offsetLeft, behavior: "smooth" });
    }
  };

  let scrollTimeout;
  let autoplayId;
  let isPaused = false;
  slider.addEventListener("scroll", () => {
    window.clearTimeout(scrollTimeout);
    scrollTimeout = window.setTimeout(() => {
      setActiveDot(getActiveIndex());
    }, 80);
  });

  dots.forEach((dot) => {
    dot.addEventListener("click", () => {
      scrollToIndex(Number(dot.dataset.index));
    });
  });

  document.querySelectorAll("[data-action]").forEach((button) => {
    button.addEventListener("click", () => {
      const current = getActiveIndex();
      const nextIndex = button.dataset.action === "next" ? current + 1 : current - 1;
      const boundedIndex = Math.max(0, Math.min(slides.length - 1, nextIndex));
      scrollToIndex(boundedIndex);
    });
  });

  const startAutoplay = () => {
    if (autoplayId || slides.length <= 1) return;
    autoplayId = window.setInterval(() => {
      if (isPaused) return;
      const current = getActiveIndex();
      const nextIndex = current === slides.length - 1 ? 0 : current + 1;
      scrollToIndex(nextIndex);
    }, 4500);
  };

  const stopAutoplay = () => {
    if (!autoplayId) return;
    window.clearInterval(autoplayId);
    autoplayId = null;
  };

  slider.addEventListener("mouseenter", () => {
    isPaused = true;
  });

  slider.addEventListener("mouseleave", () => {
    isPaused = false;
  });

  slider.addEventListener("touchstart", () => {
    isPaused = true;
  }, { passive: true });

  slider.addEventListener("touchend", () => {
    isPaused = false;
  });

  slider.addEventListener("focusin", () => {
    isPaused = true;
  });

  slider.addEventListener("focusout", () => {
    isPaused = false;
  });

  setActiveDot(0);
  startAutoplay();
  </script>
</body>
</html>
