<?php
require_once __DIR__ . "/includes/data.php";
$allGames = $tenantData["games"] ?? [];
$pageTitle = $brandName . " | Juegos";
include __DIR__ . "/includes/header.php";
?>

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
<?php include __DIR__ . "/includes/footer.php"; ?>
