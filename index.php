<?php
require_once __DIR__ . "/includes/data.php";
$pageTitle = $brandName . " | Tienda de monedas digitales";
include __DIR__ . "/includes/header.php";

$banners = $tenantData["banners"] ?? [];
$featured = $tenantData["featured"] ?? [];
$popularGames = $tenantData["popularGames"] ?? [];
$moreGames = $tenantData["moreGames"] ?? [];
$accentMap = [
  "cyan" => [
    "label" => "text-cyan-300/70",
    "gradient" => "from-slate-950/90 via-slate-950/30 to-transparent"
  ],
  "emerald" => [
    "label" => "text-emerald-300/70",
    "gradient" => "from-slate-950/85 via-transparent to-slate-950/80"
  ]
];
?>

      <section class="mt-6 space-y-4" style="animation: fadeUp 650ms ease-out both;">
        <div class="relative">
          <div id="promo-slider" class="flex snap-x snap-mandatory gap-3 overflow-x-auto scroll-smooth rounded-2xl border border-slate-800 bg-slate-900/60 p-2 [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
            <?php foreach ($banners as $banner): ?>
              <?php
                $accent = $banner["accent"] ?? "cyan";
                $labelClass = $accentMap[$accent]["label"] ?? $accentMap["cyan"]["label"];
                $gradientClass = $accentMap[$accent]["gradient"] ?? $accentMap["cyan"]["gradient"];
              ?>
              <article class="relative h-32 min-w-[80%] snap-start overflow-hidden rounded-2xl border border-slate-800">
                <img src="<?php echo htmlspecialchars($banner["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($banner["title"], ENT_QUOTES, "UTF-8"); ?>" class="h-full w-full object-cover opacity-85" />
                <div class="absolute inset-0 bg-gradient-to-r <?php echo $gradientClass; ?>"></div>
                <div class="absolute inset-0 flex flex-col justify-center px-4">
                  <p class="text-[10px] uppercase tracking-[0.35em] <?php echo $labelClass; ?>">
                    <?php echo htmlspecialchars($banner["label"], ENT_QUOTES, "UTF-8"); ?>
                  </p>
                  <h2 class="mt-1 font-oxanium text-lg font-semibold text-white">
                    <?php echo htmlspecialchars($banner["title"], ENT_QUOTES, "UTF-8"); ?>
                  </h2>
                  <p class="mt-1 text-xs text-slate-300">
                    <?php echo htmlspecialchars($banner["subtitle"], ENT_QUOTES, "UTF-8"); ?>
                  </p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
          <div id="promo-dots" class="mt-3 flex items-center gap-2">
            <?php foreach ($banners as $index => $banner): ?>
              <?php $isActive = $index === 0; ?>
              <button type="button" class="h-1.5 <?php echo $isActive ? "w-6 bg-cyan-400" : "w-4 bg-slate-700"; ?> rounded-full transition" data-index="<?php echo $index; ?>" aria-label="Banner <?php echo $index + 1; ?>"></button>
            <?php endforeach; ?>
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
          <?php foreach ($popularGames as $game): ?>
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

      <?php if (!empty($featured)): ?>
        <section class="mt-8">
          <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/70">
            <img src="<?php echo htmlspecialchars($featured["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($featured["title"], ENT_QUOTES, "UTF-8"); ?>" class="h-36 w-full object-cover opacity-85" />
            <div class="absolute inset-0 bg-gradient-to-r from-slate-950/85 via-slate-950/40 to-transparent"></div>
            <div class="absolute inset-0 flex flex-col justify-center px-4">
              <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70"><?php echo htmlspecialchars($featured["label"], ENT_QUOTES, "UTF-8"); ?></p>
              <h3 class="mt-1 font-oxanium text-lg font-semibold"><?php echo htmlspecialchars($featured["title"], ENT_QUOTES, "UTF-8"); ?></h3>
              <p class="mt-1 text-xs text-slate-300"><?php echo htmlspecialchars($featured["subtitle"], ENT_QUOTES, "UTF-8"); ?></p>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <section class="mt-8">
        <div class="flex items-center justify-between">
          <h2 class="font-oxanium text-base font-semibold">Mas juegos</h2>
          <a href="/juegos" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Explorar</a>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          <?php foreach ($moreGames as $game): ?>
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

<?php
$pageScripts = [
  <<<'SCRIPT'
<script>
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
SCRIPT
];
include __DIR__ . "/includes/footer.php";
?>
