
<?php
require_once __DIR__ . "/includes/db_connect.php";
$pageTitle = "TVirtualGaming | Juegos populares";
include __DIR__ . "/includes/header.php";

$res = $mysqli->query("SELECT * FROM juegos WHERE popular=1 ORDER BY id DESC");
$popularGames = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>


<section class="mt-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70">seleccionados</p>
      <h2 class="game-section-title">Juegos populares</h2>
    </div>
    <a href="/juegos" class="text-xs font-semibold uppercase tracking-wide text-cyan-300">Ver todo</a>
  </div>
  <div class="mt-4 row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
    <?php foreach ($popularGames as $game): ?>
      <?php
        $resPaqCount = $mysqli->query("SELECT COUNT(*) as total FROM juego_paquetes WHERE juego_id=" . intval($game['id']));
        $paqCount = $resPaqCount ? $resPaqCount->fetch_assoc()['total'] : 0;
        if ($paqCount == 0) continue;
      ?>
      <div class="col">
        <a href="/juego/<?= urlencode($game['id']) ?>" class="game-card h-100 d-block text-decoration-none">
        <div class="overflow-hidden rounded-xl position-relative">
          <img src="/<?= htmlspecialchars($game['imagen'] ?? '', ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="img-fluid w-100" style="aspect-ratio:1/1;object-fit:cover;" />
          <span title="Popular" class="position-absolute top-0 end-0 text-success fs-4" style="text-shadow:0 0 4px #000;">★</span>
        </div>
        <div class="mt-2">
          <p class="game-card-title mb-1">
            <?= htmlspecialchars($game['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </p>
          <p class="game-card-price mb-0">
            <?php if (!empty($game['imagen_paquete'])): ?>
              <img src="/<?= htmlspecialchars($game['imagen_paquete'], ENT_QUOTES, 'UTF-8') ?>" alt="Paquete" class="inline-block h-5 w-5 rounded mr-1 align-middle" />
            <?php endif; ?>
            <?php
              $min_precio_bs = null;
              if (!empty($game['moneda_fija_id'])) {
                $resPaq = $mysqli->query("SELECT precio FROM juego_paquetes WHERE juego_id=" . intval($game['id']) . " ORDER BY precio ASC LIMIT 1");
                $paq = $resPaq ? $resPaq->fetch_assoc() : null;
                if ($paq) {
                  $resMon = $mysqli->query("SELECT tasa, clave FROM monedas WHERE id=" . intval($game['moneda_fija_id']) . " LIMIT 1");
                  $mon = $resMon ? $resMon->fetch_assoc() : null;
                  if ($mon) {
                    $min_precio_bs = $paq['precio'] * floatval($mon['tasa']);
                  }
                }
              }
            ?>
            <?php if ($min_precio_bs !== null && isset($mon['clave'])): ?>
              Desde <span class="text-cyan-300"><?= htmlspecialchars(strtoupper($mon['clave'])) ?> <?= number_format($min_precio_bs, 2, '.', ',') ?></span>
            <?php endif; ?>
          </p>
        </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__ . "/includes/footer.php"; ?>
