<?php
// admin/monedas.php - Gestión de monedas (CRUD)
require_once '../includes/db_connect.php';

// Procesar formulario de creación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['clave'], $_POST['tasa'])) {
    $nombre = trim($_POST['nombre']);
    $clave = strtoupper(trim($_POST['clave']));
    $tasa = floatval($_POST['tasa']);
    if ($clave !== 'USD') {
        $stmt = $mysqli->prepare("INSERT INTO monedas (nombre, clave, tasa) VALUES (?, ?, ?)");
        $stmt->bind_param('ssd', $nombre, $clave, $tasa);
        $stmt->execute();
    }
    header('Location: monedas.php');
    exit;
}

// Editar moneda
if (isset($_POST['editar_id'], $_POST['editar_nombre'], $_POST['editar_clave'], $_POST['editar_tasa'])) {
    $id = intval($_POST['editar_id']);
    $nombre = trim($_POST['editar_nombre']);
    $clave = strtoupper(trim($_POST['editar_clave']));
    $tasa = floatval($_POST['editar_tasa']);
    $stmt = $mysqli->prepare("UPDATE monedas SET nombre=?, clave=?, tasa=? WHERE id=? AND es_base=0");
    $stmt->bind_param('ssdi', $nombre, $clave, $tasa, $id);
    $stmt->execute();
    header('Location: monedas.php');
    exit;
}
// Eliminar moneda
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    $stmt = $mysqli->prepare("DELETE FROM monedas WHERE id=? AND es_base=0");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: monedas.php');
    exit;
}

// Listar monedas
$res = $mysqli->query("SELECT * FROM monedas ORDER BY es_base DESC, nombre ASC");
$monedas = $res->fetch_all(MYSQLI_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<main class="max-w-2xl mx-auto mt-10 bg-slate-900/80 rounded-xl p-8 shadow-lg">
    <h2 class="text-2xl font-bold mb-6 text-cyan-300">Gestión de Monedas</h2>
    <form method="post" class="space-y-4 mb-8">
        <input type="text" name="nombre" placeholder="Nombre de la moneda" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="text" name="clave" placeholder="Clave (ej: USD, BS)" maxlength="10" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="number" step="0.000001" name="tasa" placeholder="Tasa respecto al USD" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white px-4 py-2 rounded-lg w-full">Agregar moneda</button>
    </form>
        <div class="overflow-x-auto">
            <!-- Tabla desktop -->
            <div class="hidden sm:block">
                <table class="min-w-full bg-slate-800 rounded-lg">
                        <thead>
                        <tr class="text-cyan-200 text-left">
                                <th class="px-4 py-2">Nombre</th>
                                <th class="px-4 py-2">Clave</th>
                                <th class="px-4 py-2">Tasa</th>
                                <th class="px-4 py-2">Base</th>
                                <th class="px-4 py-2">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($monedas as $m): ?>
                        <tr class="border-b border-slate-700">
                                <?php if (!$m['es_base'] && isset($_GET['editar']) && $_GET['editar'] == $m['id']): ?>
                                <form method="post" class="contents">
                                        <td class="px-4 py-2"><input type="text" name="editar_nombre" value="<?= htmlspecialchars($m['nombre']) ?>" class="rounded px-2 py-1 bg-slate-700 text-white w-full"></td>
                                        <td class="px-4 py-2"><input type="text" name="editar_clave" value="<?= htmlspecialchars($m['clave']) ?>" class="rounded px-2 py-1 bg-slate-700 text-white w-full"></td>
                                        <td class="px-4 py-2"><input type="number" step="0.000001" name="editar_tasa" value="<?= htmlspecialchars($m['tasa']) ?>" class="rounded px-2 py-1 bg-slate-700 text-white w-full"></td>
                                        <td class="px-4 py-2">No</td>
                                        <td class="px-4 py-2">
                                                <input type="hidden" name="editar_id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="text-emerald-400 hover:underline mr-2">Guardar</button>
                                                <a href="monedas.php" class="text-slate-400 hover:underline">Cancelar</a>
                                        </td>
                                </form>
                                <?php else: ?>
                                <td class="px-4 py-2"><?= htmlspecialchars($m['nombre']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($m['clave']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($m['tasa']) ?></td>
                                <td class="px-4 py-2"><?= $m['es_base'] ? '<span class=\'text-emerald-400\'>Sí</span>' : 'No' ?></td>
                                <td class="px-4 py-2">
                                        <?php if (!$m['es_base']): ?>
                                        <a href="?editar=<?= $m['id'] ?>" class="text-cyan-400 hover:underline mr-2">Editar</a>
                                        <a href="?eliminar=<?= $m['id'] ?>" onclick="return confirm('¿Eliminar moneda?')" class="text-rose-400 hover:underline">Eliminar</a>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                </td>
                                <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                </table>
            </div>
            <!-- Cards mobile -->
            <div class="sm:hidden flex flex-col gap-4 mt-4">
                <?php foreach ($monedas as $m): ?>
                <div class="rounded-xl border border-slate-700 bg-gray-900 p-4 flex flex-col gap-2 shadow">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-xs text-cyan-300 font-semibold"><?= htmlspecialchars($m['nombre']) ?></span>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars($m['clave']) ?></span>
                    </div>
                    <div class="text-sm text-slate-300">Tasa: <?= htmlspecialchars($m['tasa']) ?></div>
                    <div class="text-sm text-slate-300">Base: <?= $m['es_base'] ? '<span class=\'text-emerald-400\'>Sí</span>' : 'No' ?></div>
                    <div class="flex gap-2 mt-2">
                        <?php if (!$m['es_base']): ?>
                            <a href="?editar=<?= $m['id'] ?>" class="flex-1 text-center px-2 py-1 rounded bg-cyan-600 text-white hover:bg-cyan-700">Editar</a>
                            <a href="?eliminar=<?= $m['id'] ?>" onclick="return confirm('¿Eliminar moneda?')" class="flex-1 text-center px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700">Eliminar</a>
                        <?php else: ?>
                            <span class="flex-1 text-center text-gray-500">-</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <a href="/admin/juegos" class="inline-block mt-6 text-cyan-300 hover:underline">Volver a juegos</a>
</main>
<?php include '../includes/footer.php'; ?>