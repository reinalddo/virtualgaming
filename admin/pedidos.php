<?php
session_start();
if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
    header('Location: /login.php');
    exit();
}

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/header.php';

$statuses = ['pendiente','pagado','enviado','cancelado'];
$ordersByStatus = array_fill_keys($statuses, []);

$ordersRes = $mysqli->query("SELECT * FROM pedidos ORDER BY creado_en DESC");
if ($ordersRes) {
    while ($row = $ordersRes->fetch_assoc()) {
        $estado = $row['estado'] ?? 'pendiente';
        if (!isset($ordersByStatus[$estado])) {
            $ordersByStatus[$estado] = [];
        }
        $ordersByStatus[$estado][] = $row;
    }
}

function format_money($amount): string {
    return number_format((float)$amount, 2, '.', ',');
}
?>
<main class="max-w-6xl mx-auto mt-10 mb-16 px-4">
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <div>
      <p class="text-xs uppercase tracking-[0.35em] text-cyan-300/70">Panel</p>
      <h1 class="text-2xl sm:text-3xl font-bold text-cyan-300">Gestión de Pedidos</h1>
      <p class="text-sm text-slate-400">Administra estados y revisa el histórico de compras.</p>
    </div>
  </div>

  <div class="mt-6 flex gap-2 flex-wrap" id="tabs">
    <?php foreach ($statuses as $st): ?>
      <button data-tab="<?= $st ?>" class="tab-btn rounded-full border border-slate-700 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:border-cyan-400/70" type="button">
        <?= ucfirst($st) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <?php foreach ($statuses as $st): ?>
    <?php $list = $ordersByStatus[$st] ?? []; ?>
    <section data-panel="<?= $st ?>" class="tab-panel mt-6 hidden">
      <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <span class="inline-flex h-2 w-2 rounded-full <?= $st === 'pendiente' ? 'bg-amber-400' : ($st === 'pagado' ? 'bg-emerald-400' : ($st === 'enviado' ? 'bg-cyan-400' : 'bg-rose-400')) ?>"></span>
            <h2 class="text-lg font-semibold text-slate-100">Estado: <?= ucfirst($st) ?></h2>
          </div>
          <p class="text-sm text-slate-400">Total: <?= count($list) ?> pedidos</p>
        </div>

        <?php if (count($list) === 0): ?>
          <p class="mt-4 text-sm text-slate-400">No hay pedidos en este estado.</p>
        <?php else: ?>
          <div class="hidden md:block overflow-x-auto mt-4">
            <table class="min-w-full text-sm text-slate-200">
              <thead class="text-cyan-200 bg-slate-800/70">
                <tr>
                  <th class="px-3 py-2 text-left">#</th>
                  <th class="px-3 py-2 text-left">Fecha</th>
                  <th class="px-3 py-2 text-left">Cliente</th>
                  <th class="px-3 py-2 text-left">Email</th>
                  <th class="px-3 py-2 text-left">Juego</th>
                  <th class="px-3 py-2 text-left">Paquete</th>
                  <th class="px-3 py-2 text-left">Total</th>
                  <th class="px-3 py-2 text-left">Cupón</th>
                  <th class="px-3 py-2 text-left">Estado</th>
                </tr>
              </thead>
              <tbody id="table-body-<?= $st ?>">
                <?php foreach ($list as $order): ?>
                  <tr data-order-row="<?= $order['id'] ?>" data-status="<?= $st ?>" class="border-b border-slate-800/70">
                    <td class="px-3 py-2 font-semibold text-cyan-200">#<?= $order['id'] ?></td>
                    <td class="px-3 py-2 text-slate-400"><?= htmlspecialchars($order['creado_en']) ?></td>
                    <td class="px-3 py-2 text-slate-100"><?= htmlspecialchars($order['user_identifier']) ?></td>
                    <td class="px-3 py-2 text-slate-400"><?= htmlspecialchars($order['email']) ?></td>
                    <td class="px-3 py-2 text-slate-100"><?php
                      $juegoTexto = htmlspecialchars($order['juego_nombre']);
                      if ($juegoTexto === '' || str_contains($order['juego_nombre'], '<?')) {
                        $juegoTexto = 'Juego #' . htmlspecialchars((string)($order['juego_id'] ?? '')); }
                      echo $juegoTexto;
                    ?></td>
                    <td class="px-3 py-2 text-slate-100"><?= htmlspecialchars($order['paquete_nombre'] ?? '') ?> <span class="text-xs text-slate-400"></span></td>
                    <td class="px-3 py-2 font-semibold text-emerald-300"><?= htmlspecialchars($order['moneda'] ?? '') ?> <?= format_money($order['precio']) ?></td>
                    <td class="px-3 py-2 text-slate-400">
                      <?= !empty($order['cupon']) ? htmlspecialchars($order['cupon']) : '—' ?>
                    </td>
                    <td class="px-3 py-2">
                      <select class="js-status inline-flex rounded-lg border border-slate-700 bg-slate-800/80 px-2 py-1 text-xs font-semibold text-slate-100" data-order-id="<?= $order['id'] ?>" data-status="<?= $st ?>">
                        <?php foreach ($statuses as $opt): ?>
                          <option value="<?= $opt ?>" <?= $opt === $st ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="md:hidden mt-4 space-y-3" id="cards-<?= $st ?>">
            <?php foreach ($list as $order): ?>
              <div data-order-card="<?= $order['id'] ?>" data-status="<?= $st ?>" class="rounded-xl border border-slate-800 bg-slate-900/80 p-3 shadow">
                <div class="flex items-center justify-between">
                  <p class="text-sm font-semibold text-cyan-200">#<?= $order['id'] ?></p>
                  <p class="text-xs text-slate-400"><?= htmlspecialchars($order['creado_en']) ?></p>
                </div>
                <p class="mt-1 text-slate-100 text-sm">Cliente: <?= htmlspecialchars($order['user_identifier']) ?></p>
                <p class="text-slate-400 text-sm">Email: <?= htmlspecialchars($order['email']) ?></p>
                <p class="text-slate-100 text-sm mt-1"><?php
                  $juegoTexto = htmlspecialchars($order['juego_nombre']);
                  if ($juegoTexto === '' || str_contains($order['juego_nombre'], '<?')) {
                    $juegoTexto = 'Juego #' . htmlspecialchars((string)($order['juego_id'] ?? '')); }
                  echo 'Juego: ' . $juegoTexto;
                ?></p>
                <p class="text-slate-100 text-sm">Paquete: <?= htmlspecialchars($order['paquete_nombre'] ?? '') ?> (<?= htmlspecialchars($order['paquete_cantidad'] ?? '') ?>)</p>
                <p class="text-emerald-300 font-semibold mt-1">Total: <?= htmlspecialchars($order['moneda'] ?? '') ?> <?= format_money($order['precio']) ?></p>
                <p class="text-slate-400 text-xs">Cupón: <?= !empty($order['cupon']) ? htmlspecialchars($order['cupon']) : '—' ?></p>
                <div class="mt-3">
                  <select class="js-status w-full rounded-lg border border-slate-700 bg-slate-800/80 px-3 py-2 text-sm font-semibold text-slate-100" data-order-id="<?= $order['id'] ?>" data-status="<?= $st ?>">
                    <?php foreach ($statuses as $opt): ?>
                      <option value="<?= $opt ?>" <?= $opt === $st ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>
  <?php endforeach; ?>
</main>

<script>
(function(){
  const tabs = Array.from(document.querySelectorAll('.tab-btn'));
  const panels = Array.from(document.querySelectorAll('.tab-panel'));
  function showTab(tab){
    panels.forEach(p => p.classList.toggle('hidden', p.dataset.panel !== tab));
    tabs.forEach(b => b.classList.toggle('border-cyan-400', b.dataset.tab === tab));
    tabs.forEach(b => b.classList.toggle('text-cyan-200', b.dataset.tab === tab));
    localStorage.setItem('tvg_tab', tab);
  }
  const initial = localStorage.getItem('tvg_tab') || 'pendiente';
  showTab(initial);
  tabs.forEach(btn => btn.addEventListener('click', () => showTab(btn.dataset.tab)));

  function moveOrder(id, newStatus){
    const row = document.querySelector(`[data-order-row="${id}"]`);
    const card = document.querySelector(`[data-order-card="${id}"]`);
    if (row) {
      row.dataset.status = newStatus;
      const targetBody = document.getElementById('table-body-' + newStatus);
      if (targetBody) targetBody.prepend(row);
      const selectRow = row.querySelector('select');
      if (selectRow) selectRow.value = newStatus;
    }
    if (card) {
      card.dataset.status = newStatus;
      const targetCards = document.getElementById('cards-' + newStatus);
      if (targetCards) targetCards.prepend(card);
      const selectCard = card.querySelector('select');
      if (selectCard) selectCard.value = newStatus;
    }
    // Ya no cambiamos de pestaña automáticamente
  }

  function bindStatusSelectors(){
    document.querySelectorAll('.js-status').forEach(sel => {
      sel.addEventListener('change', async (e) => {
        const orderId = sel.dataset.orderId;
        const prevStatus = sel.dataset.status;
        const newStatus = sel.value;
        sel.disabled = true;
        // Mueve el pedido inmediatamente en el frontend
        moveOrder(orderId, newStatus);
        const fd = new FormData();
        fd.append('action','update_status');
        fd.append('order_id', orderId);
        fd.append('estado', newStatus);
        try {
          const res = await fetch('/api/pedidos.php', { method: 'POST', body: fd });
          const txt = await res.text();
          let data;
          try { data = JSON.parse(txt); } catch (_) { throw new Error('Respuesta no válida del servidor'); }
          if (!data.ok) throw new Error(data.message || 'Error');
          // Actualiza el status en el selector para futuras referencias
          sel.dataset.status = newStatus;
        } catch(err){
          // Si hay error, revierte el cambio en el frontend
          moveOrder(orderId, prevStatus);
          sel.value = prevStatus;
          alert(err.message || 'No se pudo cambiar el estado');
        } finally {
          sel.disabled = false;
        }
      });
    });
  }
  bindStatusSelectors();
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
