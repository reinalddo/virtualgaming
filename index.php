<?php
require_once __DIR__ . "/includes/db_connect.php";
require_once __DIR__ . "/includes/store_config.php";
require_once __DIR__ . "/includes/home_gallery.php";
$pageTitle = store_config_get('nombre_tienda', 'TVirtualGaming') . " | " . store_config_get('nombre_tienda_subtitulo', 'Tienda de monedas digitales');
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

      <style>
        .promo-section-mobile,
        .featured-section-mobile {
          position: relative;
        }
        .promo-slider-shell {
          position: relative;
        }
        .promo-slider-track {
          display: flex;
          gap: 0.75rem;
          overflow-x: auto;
          scroll-snap-type: x mandatory;
          scroll-behavior: smooth;
          scrollbar-width: none;
          -ms-overflow-style: none;
          overscroll-behavior-x: contain;
          touch-action: pan-x pinch-zoom;
        }
        .promo-slider-track::-webkit-scrollbar {
          display: none;
        }
        .promo-slide-card {
          position: relative;
          flex-shrink: 0;
          width: 100%;
          min-width: 82%;
          height: 220px;
          overflow: hidden;
          border-radius: 1.5rem;
          scroll-snap-align: start;
        }
        .promo-slide-image,
        .featured-banner-image {
          width: 100%;
          height: 100%;
          object-fit: cover;
          opacity: 0.86;
        }
        .promo-slide-overlay,
        .featured-banner-overlay {
          position: absolute;
          inset: 0;
          background: linear-gradient(90deg, rgba(12, 21, 34, 0.9), rgba(12, 21, 34, 0.3), transparent);
        }
        .promo-slide-content,
        .featured-banner-content {
          position: absolute;
          inset: 0;
          display: flex;
          flex-direction: column;
          justify-content: center;
          padding-inline: 1.5rem;
        }
        .promo-dots {
          display: flex;
          align-items: center;
          gap: 0.5rem;
        }
        .promo-dot {
          appearance: none;
          border: 0;
          padding: 0;
          width: 16px;
          height: 6px;
          border-radius: 999px;
          background: #334155;
          transition: width 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }
        .promo-dot.is-active {
          width: 24px;
          background: #22d3ee;
          box-shadow: 0 0 14px rgba(34, 211, 238, 0.3);
        }
        .featured-banner-card {
          position: relative;
          display: block;
          overflow: hidden;
          border-radius: 1.5rem;
          text-decoration: none;
        }
        .featured-banner-image {
          height: 140px;
        }
        @media (min-width: 768px) {
          .promo-slide-card {
            height: 250px;
          }
          .featured-banner-image {
            height: 160px;
          }
        }
        @media (max-width: 767.98px) {
          .promo-section-mobile,
          .featured-section-mobile {
            width: calc(100% + var(--bs-gutter-x, 1.5rem));
            margin-left: calc(var(--bs-gutter-x, 1.5rem) * -0.5);
            margin-right: calc(var(--bs-gutter-x, 1.5rem) * -0.5);
          }
          .promo-slider-track {
            gap: 0;
          }
          .promo-slide-card,
          .featured-banner-card {
            min-width: 100%;
            width: 100%;
            border-radius: 0;
          }
          .promo-slide-card {
            height: min(62vw, 320px);
          }
          .featured-banner-image {
            height: min(44vw, 220px);
          }
          .promo-slide-content,
          .featured-banner-content {
            padding-inline: 1rem;
          }
        }
      </style>

      <?php if (!empty($banners)): ?>
        <section class="mt-4 promo-section-mobile" style="animation: fadeUp 650ms ease-out both;">
          <div class="promo-slider-shell">
            <div id="promo-slider" class="promo-slider-track">
              <?php foreach ($banners as $banner): ?>
                <?php
                  $accent = $banner["accent"] ?? "cyan";
                  $labelClass = $accentMap[$accent]["label"] ?? $accentMap["cyan"]["label"];
                  $gradientClass = $accentMap[$accent]["gradient"] ?? $accentMap["cyan"]["gradient"];
                  $bannerUrl = trim((string) ($banner['url'] ?? ''));
                  $bannerTarget = !empty($banner['open_in_new_tab']) ? '_blank' : '_self';
                ?>
                <<?= $bannerUrl !== '' ? 'a' : 'article' ?> class="promo-slide-card text-decoration-none"<?= $bannerUrl !== '' ? ' href="' . htmlspecialchars($bannerUrl, ENT_QUOTES, 'UTF-8') . '" target="' . htmlspecialchars($bannerTarget, ENT_QUOTES, 'UTF-8') . '"' . ($bannerTarget === '_blank' ? ' rel="noopener noreferrer"' : '') : '' ?>>
                  <img src="<?php echo htmlspecialchars($banner["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($banner["title"], ENT_QUOTES, "UTF-8"); ?>" class="promo-slide-image" />
                  <div class="promo-slide-overlay"></div>
                  <div class="promo-slide-content">
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
            <div id="promo-dots" class="promo-dots mt-3">
              <?php foreach ($banners as $index => $banner): ?>
                <?php $isActive = $index === 0; ?>
                <button type="button" class="promo-dot<?php echo $isActive ? ' is-active' : ''; ?>" data-index="<?php echo $index; ?>" aria-label="Banner <?php echo $index + 1; ?>" aria-current="<?php echo $isActive ? 'true' : 'false'; ?>"></button>
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
        <section class="mt-5 featured-section-mobile">
          <?php
            $featuredUrl = trim((string) ($featured['url'] ?? ''));
            $featuredTarget = !empty($featured['open_in_new_tab']) ? '_blank' : '_self';
          ?>
          <<?= $featuredUrl !== '' ? 'a' : 'div' ?> class="featured-banner-card"<?= $featuredUrl !== '' ? ' href="' . htmlspecialchars($featuredUrl, ENT_QUOTES, 'UTF-8') . '" target="' . htmlspecialchars($featuredTarget, ENT_QUOTES, 'UTF-8') . '"' . ($featuredTarget === '_blank' ? ' rel="noopener noreferrer"' : '') : '' ?>>
            <img src="<?php echo htmlspecialchars($featured["image"], ENT_QUOTES, "UTF-8"); ?>" alt="<?php echo htmlspecialchars($featured["title"], ENT_QUOTES, "UTF-8"); ?>" class="featured-banner-image" />
            <div class="featured-banner-overlay"></div>
            <div class="featured-banner-content">
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

  let currentIndex = 0;
  let scrollTimeout;
  let autoplayId;
  let isPaused = false;
  let touchStartX = null;
  let lastTouchX = null;

  const normalizeIndex = (index) => {
    const total = slides.length;
    return total ? ((index % total) + total) % total : 0;
  };

  const setActiveDot = (index) => {
    currentIndex = normalizeIndex(index);
    dots.forEach((dot, idx) => {
      const isActive = idx === currentIndex;
      dot.classList.toggle("is-active", isActive);
      dot.setAttribute("aria-current", isActive ? "true" : "false");
    });
  };

  const getClosestIndex = () => {
    let closestIndex = 0;
    let closestDistance = Infinity;

    slides.forEach((slide, index) => {
      const distance = Math.abs(slider.scrollLeft - slide.offsetLeft);
      if (distance < closestDistance) {
        closestDistance = distance;
        closestIndex = index;
      }
    });

    return closestIndex;
  };

  const scrollToIndex = (index, behavior = "smooth") => {
    const targetIndex = normalizeIndex(index);
    const target = slides[targetIndex];
    if (target) {
      slider.scrollTo({ left: target.offsetLeft, behavior });
      setActiveDot(targetIndex);
    }
  };

  slider.addEventListener("scroll", () => {
    window.clearTimeout(scrollTimeout);
    scrollTimeout = window.setTimeout(() => {
      setActiveDot(getClosestIndex());
    }, 70);
  });

  dots.forEach((dot) => {
    dot.addEventListener("click", () => {
      scrollToIndex(Number(dot.dataset.index));
    });
  });

  document.querySelectorAll("[data-action]").forEach((button) => {
    button.addEventListener("click", () => {
      const nextIndex = button.dataset.action === "next" ? currentIndex + 1 : currentIndex - 1;
      scrollToIndex(nextIndex);
    });
  });

  const startAutoplay = () => {
    if (autoplayId || slides.length <= 1) return;
    autoplayId = window.setInterval(() => {
      if (isPaused) return;
      scrollToIndex(currentIndex + 1);
    }, 4500);
  };

  slider.addEventListener("mouseenter", () => {
    isPaused = true;
  });

  slider.addEventListener("mouseleave", () => {
    isPaused = false;
  });

  slider.addEventListener("touchstart", (event) => {
    isPaused = true;
    touchStartX = event.changedTouches[0]?.clientX ?? null;
    lastTouchX = touchStartX;
  }, { passive: true });

  slider.addEventListener("touchmove", (event) => {
    lastTouchX = event.changedTouches[0]?.clientX ?? lastTouchX;
  }, { passive: true });

  slider.addEventListener("touchend", () => {
    isPaused = false;
    if (touchStartX !== null && lastTouchX !== null) {
      const deltaX = touchStartX - lastTouchX;
      if (Math.abs(deltaX) >= 40) {
        if (currentIndex === slides.length - 1 && deltaX > 0) {
          scrollToIndex(0);
        } else if (currentIndex === 0 && deltaX < 0) {
          scrollToIndex(slides.length - 1);
        }
      }
    }
    touchStartX = null;
    lastTouchX = null;
  });

  slider.addEventListener("touchcancel", () => {
    isPaused = false;
    touchStartX = null;
    lastTouchX = null;
  });

  slider.addEventListener("focusin", () => {
    isPaused = true;
  });

  slider.addEventListener("focusout", () => {
    isPaused = false;
  });

  const observer = new IntersectionObserver((entries) => {
    let bestIndex = null;
    let bestRatio = 0;

    entries.forEach((entry) => {
      if (!entry.isIntersecting) {
        return;
      }
      const index = slides.indexOf(entry.target);
      if (index !== -1 && entry.intersectionRatio > bestRatio) {
        bestRatio = entry.intersectionRatio;
        bestIndex = index;
      }
    });

    if (bestIndex !== null) {
      setActiveDot(bestIndex);
    }
  }, {
    root: slider,
    threshold: [0.55, 0.75, 0.95]
  });

  slides.forEach((slide) => observer.observe(slide));

  if (dots.length) {
    setActiveDot(0);
  }
  scrollToIndex(0, "auto");
  startAutoplay();
  })();
</script>
SCRIPT
];
include __DIR__ . "/includes/footer.php";
?>
