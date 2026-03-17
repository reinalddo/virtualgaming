<?php
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/store_config.php";
require_once __DIR__ . "/includes/home_gallery.php";
$pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming') . " | Tienda de monedas digitales";
include __DIR__ . "/includes/header.php";
home_gallery_ensure_table();
$galleryItems = home_gallery_all();
$galleryFeatured = home_gallery_featured();

$banners = [];
foreach ($galleryItems as $item) {
  $banners[] = [
    'label' => $item['titulo'],
    'title' => $item['descripcion1'],
    'subtitle' => $item['descripcion2'],
    'image' => $item['imagen'],
    'url' => $item['url'],
    'open_in_new_tab' => !empty($item['abrir_nueva_pestana']),
  ];
}

$featured = [];
if (!empty($galleryFeatured)) {
  $featured = [
    'label' => $galleryFeatured['titulo'],
    'title' => $galleryFeatured['descripcion1'],
    'subtitle' => $galleryFeatured['descripcion2'],
    'image' => $galleryFeatured['imagen'],
    'url' => $galleryFeatured['url'],
    'open_in_new_tab' => !empty($galleryFeatured['abrir_nueva_pestana']),
  ];
}

$gameCurrencyMap = [];
$resCurrencies = $mysqli->query("SELECT id, tasa, clave FROM monedas");
if ($resCurrencies instanceof mysqli_result) {
  while ($currency = $resCurrencies->fetch_assoc()) {
    $gameCurrencyMap[(int) $currency['id']] = [
      'tasa' => (float) ($currency['tasa'] ?? 0),
      'clave' => (string) ($currency['clave'] ?? ''),
    ];
  }
}

$gameCards = [];
$resGames = $mysqli->query(
  "SELECT j.*, COUNT(jp.id) AS paquetes_total, MIN(jp.precio) AS precio_minimo\n"
  . "FROM juegos j\n"
  . "INNER JOIN juego_paquetes jp ON jp.juego_id = j.id\n"
  . "GROUP BY j.id\n"
  . "ORDER BY j.id DESC"
);
if ($resGames instanceof mysqli_result) {
  while ($game = $resGames->fetch_assoc()) {
    $currency = null;
    $minPriceLabel = null;
    $currencyId = (int) ($game['moneda_fija_id'] ?? 0);
    if ($currencyId > 0 && isset($gameCurrencyMap[$currencyId])) {
      $currency = $gameCurrencyMap[$currencyId];
      $minPriceLabel = strtoupper($currency['clave']) . ' ' . number_format(((float) ($game['precio_minimo'] ?? 0)) * $currency['tasa'], 2, '.', ',');
    }

    $game['paquetes_total'] = (int) ($game['paquetes_total'] ?? 0);
    $game['min_price_label'] = $minPriceLabel;
    $gameCards[] = $game;
  }
}

$popularGames = array_values(array_filter($gameCards, static fn ($game) => !empty($game['popular'])));
$moreGames = $gameCards;
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

      <?php if (!empty($banners)): ?>
        <section class="mt-4" style="animation: fadeUp 650ms ease-out both;">
          <div class="position-relative">
            <div id="promo-slider" class="d-flex gap-3 overflow-auto rounded-4 border bg-dark p-2" style="scroll-snap-type:x mandatory;">
              <?php foreach ($banners as $banner): ?>
                <?php
                  $accent = $banner["accent"] ?? "cyan";
                  $labelClass = $accentMap[$accent]["label"] ?? $accentMap["cyan"]["label"];
                  $gradientClass = $accentMap[$accent]["gradient"] ?? $accentMap["cyan"]["gradient"];
                  $bannerUrl = trim((string) ($banner['url'] ?? ''));
                  $bannerTarget = !empty($banner['open_in_new_tab']) ? '_blank' : '_self';
                ?>
                <<?= $bannerUrl !== '' ? 'a' : 'article' ?> class="position-relative flex-shrink-0 w-100 text-decoration-none" style="height:220px;min-width:80%;scroll-snap-align:start;overflow:hidden;border-radius:1.5rem;border:1px solid #334155;"<?= $bannerUrl !== '' ? ' href="' . htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') . '" target="' . htmlspecialchars($bannerTarget, ENT_QUOTES, 'UTF-8') . '"' . ($bannerTarget === '_blank' ? ' rel="noopener noreferrer"' : '') : '' ?>>
                  <img src="<?php echo htmlspecialchars($banner["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($banner["title"], ENT_QUOTES, "UTF-8"); ?>" class="img-fluid w-100 h-100 object-fit-cover" style="opacity:0.85;" />
                  <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(90deg,rgba(12,21,34,0.9),rgba(12,21,34,0.3),transparent);"></div>
                  <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center px-4">
                    <p class="small text-uppercase text-info mb-0" style="letter-spacing:0.35em;">
                      <?php echo htmlspecialchars($banner["label"], ENT_QUOTES, "UTF-8"); ?>
                    </p>
                    <h2 class="mt-1 fw-bold" style="font-family:'Oxanium',sans-serif;font-size:1.25rem;color:#fff;">
                      <?php echo htmlspecialchars($banner["title"], ENT_QUOTES, "UTF-8"); ?>
                    </h2>
                    <p class="mt-1 small text-secondary">
                      <?php echo htmlspecialchars($banner["subtitle"], ENT_QUOTES, "UTF-8"); ?>
                    </p>
                  </div>
                </<?= $bannerUrl !== '' ? 'a' : 'article' ?>>
              <?php endforeach; ?>
            </div>
            <div id="promo-dots" class="mt-3 d-flex align-items-center gap-2">
              <?php foreach ($banners as $index => $banner): ?>
                <?php $isActive = $index === 0; ?>
                <button type="button" class="btn p-0" style="height:6px;width:<?php echo $isActive ? '24px' : '16px'; ?>;background:<?php echo $isActive ? '#22d3ee' : '#334155'; ?>;border-radius:1rem;transition:all 0.2s;" data-index="<?php echo $index; ?>" aria-label="Banner <?php echo $index + 1; ?>"></button>
              <?php endforeach; ?>
            </div>
            <div class="position-absolute top-0 start-0 end-0 h-100 d-none d-md-flex align-items-center justify-content-between" style="pointer-events:none;">
              <button type="button" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;pointer-events:auto;background:rgba(34,211,238,0.15);border:2px solid #22d3ee;color:#22d3ee;position:relative;z-index:2;" data-action="prev" aria-label="Anterior">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-left" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
                </svg>
              </button>
              <button type="button" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;pointer-events:auto;background:rgba(34,211,238,0.15);border:2px solid #22d3ee;color:#22d3ee;position:relative;z-index:2;" data-action="next" aria-label="Siguiente">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-chevron-right" viewBox="0 0 16 16">
                  <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
                </svg>
              </button>
            </div>
            <p class="mt-2 small text-secondary">Desliza para ver más promociones</p>
          </div>
        </section>
      <?php endif; ?>

      <section class="mt-5">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="fw-bold" style="font-family:'Oxanium',sans-serif;font-size:1.1rem;">Juegos populares</h2>
          <a href="/populares" class="small fw-semibold text-info text-uppercase">Ver todo</a>
        </div>
        <div class="mt-4 row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3">
          <?php foreach ($popularGames as $game): ?>
            <div class="col">
              <a href="/juego/<?= urlencode($game['id']) ?>" class="d-block rounded-4 border bg-dark p-2 h-100 text-decoration-none">
                <div class="position-relative overflow-hidden rounded-3" style="aspect-ratio:1/1;">
                  <img src="/<?= htmlspecialchars($game['imagen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="img-fluid w-100 h-100 object-fit-cover" style="aspect-ratio:1/1;" />
                  <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="text-shadow:0 0 4px #000;">★</span>
                </div>
                <div class="mt-2">
                  <p class="store-game-title fw-semibold d-flex align-items-center mb-1" style="font-size:1rem;">
                    <?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <p class="store-game-price-prefix small mb-0">
                    <?php if (!empty($game['imagen_paquete'])): ?>
                      <img src="/<?= htmlspecialchars($game['imagen_paquete'], ENT_QUOTES, 'UTF-8') ?>" alt="Paquete" class="img-fluid rounded me-1 align-middle" style="height:20px;width:20px;display:inline-block;" />
                    <?php endif; ?>
                    <?php if (!empty($game['min_price_label'])): ?>
                      Desde <span class="store-game-price"><?= htmlspecialchars($game['min_price_label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                  </p>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <?php if (!empty($featured)): ?>
        <section class="mt-5">
          <?php
            $featuredUrl = trim((string) ($featured['url'] ?? ''));
            $featuredTarget = !empty($featured['open_in_new_tab']) ? '_blank' : '_self';
          ?>
          <<?= $featuredUrl !== '' ? 'a' : 'div' ?> class="position-relative overflow-hidden rounded-4 border bg-dark d-block text-decoration-none"<?= $featuredUrl !== '' ? ' href="' . htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') . '" target="' . htmlspecialchars($featuredTarget, ENT_QUOTES, 'UTF-8') . '"' . ($featuredTarget === '_blank' ? ' rel="noopener noreferrer"' : '') : '' ?>>
            <img src="<?php echo htmlspecialchars($featured["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($featured["title"], ENT_QUOTES, "UTF-8"); ?>" class="img-fluid w-100" style="height:140px;object-fit:cover;opacity:0.85;" />
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background:linear-gradient(90deg,rgba(12,21,34,0.85),rgba(12,21,34,0.4),transparent);"></div>
            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex flex-column justify-content-center px-4">
              <p class="small text-uppercase text-info mb-0" style="letter-spacing:0.35em;"><?php echo htmlspecialchars($featured["label"], ENT_QUOTES, "UTF-8"); ?></p>
              <h3 class="mt-1 fw-bold" style="font-family:'Oxanium',sans-serif;font-size:1.25rem;"><?php echo htmlspecialchars($featured["title"], ENT_QUOTES, "UTF-8"); ?></h3>
              <p class="mt-1 small text-secondary"><?php echo htmlspecialchars($featured["subtitle"], ENT_QUOTES, "UTF-8"); ?></p>
            </div>
          </<?= $featuredUrl !== '' ? 'a' : 'div' ?>>
        </section>
      <?php endif; ?>

      <section class="mt-5">
        <div class="d-flex align-items-center justify-content-between">
          <h2 class="fw-bold" style="font-family:'Oxanium',sans-serif;font-size:1.1rem;">Más juegos</h2>
          <a href="/juegos" class="small fw-semibold text-info text-uppercase">Explorar</a>
        </div>
        <div class="mt-4 row row-cols-2 row-cols-sm-3 row-cols-lg-4 g-3">
          <?php foreach ($moreGames as $game): ?>
            <div class="col">
              <a href="/juego/<?= urlencode($game['id']) ?>" class="d-block rounded-4 border bg-dark p-2 h-100 text-decoration-none">
                <div class="position-relative overflow-hidden rounded-3" style="aspect-ratio:1/1;">
                  <img src="/<?= htmlspecialchars($game['imagen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="img-fluid w-100 h-100 object-fit-cover" style="aspect-ratio:1/1;" />
                  <?php if (!empty($game['popular'])): ?>
                    <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="text-shadow:0 0 4px #000;">★</span>
                  <?php endif; ?>
                </div>
                <div class="mt-2">
                  <p class="store-game-title fw-semibold d-flex align-items-center mb-1" style="font-size:1rem;">
                    <?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                  <p class="store-game-price-prefix small mb-0">
                    <?php if (!empty($game['imagen_paquete'])): ?>
                      <img src="/<?= htmlspecialchars($game['imagen_paquete'], ENT_QUOTES, 'UTF-8') ?>" alt="Paquete" class="img-fluid rounded me-1 align-middle" style="height:20px;width:20px;display:inline-block;" />
                    <?php endif; ?>
                    <?php if (!empty($game['min_price_label'])): ?>
                      Desde <span class="store-game-price"><?= htmlspecialchars($game['min_price_label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                  </p>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

<?php
$pageScripts = [
  <<<'SCRIPT'
<script>
  (() => {
  const slider = document.getElementById("promo-slider");
  if (!slider) {
    return;
  }
  const dots = Array.from(document.querySelectorAll("#promo-dots [data-index]"));
  const slides = Array.from(slider.children);
  if (!slides.length) {
    return;
  }

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

  if (dots.length) {
    setActiveDot(0);
  }
  startAutoplay();
  })();
</script>
SCRIPT
];
include __DIR__ . "/includes/footer.php";
?>
