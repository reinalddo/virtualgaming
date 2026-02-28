<?php
// admin/paquetes.php - Gestión de paquetes de un juego
require_once '../includes/db_connect.php';

$juego_id = 0;
if (isset($_GET['juego'])) {
    $juego_id = intval($_GET['juego']);
} elseif (isset($_SERVER['REQUEST_URI'])) {
    // Soporta /admin/paquetes/2
    if (preg_match('#/admin/paquetes/(\\d+)#', $_SERVER['REQUEST_URI'], $m)) {
        $juego_id = intval($m[1]);
    }
}
if ($juego_id <= 0) { die('Juego no especificado.'); }

// Procesar edición de paquete (antes de cualquier salida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_paquete_id'])) {
    $edit_id = intval($_POST['edit_paquete_id']);
    $edit_nombre = trim($_POST['edit_nombre'] ?? '');
    $edit_clave = trim($_POST['edit_clave'] ?? '');
    $edit_cantidad = intval($_POST['edit_cantidad'] ?? 0);
    $edit_precio = floatval($_POST['edit_precio'] ?? 0);
    $edit_imagen_icono = null;
    if (isset($_FILES['edit_imagen_icono']) && $_FILES['edit_imagen_icono']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['edit_imagen_icono']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/paquetes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('paquete_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['edit_imagen_icono']['tmp_name'], $destino)) {
                $edit_imagen_icono = 'assets/img/paquetes/' . $nombre_archivo;
            }
        }
    }
    if ($edit_imagen_icono) {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, cantidad=?, precio=?, imagen_icono=? WHERE id=?");
        $stmt->bind_param('ssidsi', $edit_nombre, $edit_clave, $edit_cantidad, $edit_precio, $edit_imagen_icono, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juego_paquetes SET nombre=?, clave=?, cantidad=?, precio=? WHERE id=?");
        $stmt->bind_param('ssidi', $edit_nombre, $edit_clave, $edit_cantidad, $edit_precio, $edit_id);
    }
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Procesar creación de paquete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['clave'], $_POST['cantidad'], $_POST['precio'])) {
    $nombre = trim($_POST['nombre']);
    $clave = trim($_POST['clave']);
    $cantidad = intval($_POST['cantidad']);
    $precio = floatval($_POST['precio']);
    $imagen_icono = null;
    if (isset($_FILES['imagen_icono']) && $_FILES['imagen_icono']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen_icono']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/paquetes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('paquete_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['imagen_icono']['tmp_name'], $destino)) {
                $imagen_icono = 'assets/img/paquetes/' . $nombre_archivo;
            }
        }
    }
    $stmt = $mysqli->prepare("INSERT INTO juego_paquetes (juego_id, nombre, clave, cantidad, precio, imagen_icono) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issids', $juego_id, $nombre, $clave, $cantidad, $precio, $imagen_icono);
    $stmt->execute();
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

// Listar paquetes existentes
$res = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE juego_id=?");
$res->bind_param('i', $juego_id);
$res->execute();
$paquetes = $res->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener nombre del juego
$resj = $mysqli->prepare("SELECT nombre FROM juegos WHERE id=?");
$resj->bind_param('i', $juego_id);
$resj->execute();
$juego = $resj->get_result()->fetch_assoc();
?>
<?php include '../includes/header.php'; ?>
<main class="max-w-2xl mx-auto mt-10 bg-slate-900/80 rounded-xl p-8 shadow-lg">
    <h2 class="text-2xl font-bold mb-6 text-cyan-300">Paquetes de <?= htmlspecialchars($juego['nombre']) ?></h2>
    <form method="post" enctype="multipart/form-data" class="space-y-4 mb-8">
        <input type="text" name="nombre" placeholder="Nombre del paquete" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="text" name="clave" placeholder="Clave interna" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="number" name="cantidad" placeholder="Cantidad" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="number" step="0.01" name="precio" placeholder="Precio USD" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <input type="file" name="imagen_icono" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400" onchange="previewPaqueteImg(event)">
        <div class="flex justify-center my-2">
            <img id="preview-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
        </div>
        <button type="submit" class="bg-cyan-700 hover:bg-cyan-600 text-white px-4 py-2 rounded-lg w-full">Agregar paquete</button>
    </form>
        <h3 class="text-xl font-semibold mt-10 mb-4">Paquetes existentes</h3>
        <!-- Desktop Table -->
        <div class="overflow-x-auto hidden md:block">
            <table class="min-w-full text-sm text-left text-white">
                <thead class="bg-slate-700 text-cyan-200">
                    <tr>
                        <th class="px-3 py-2">Imagen</th>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Clave</th>
                        <th class="px-3 py-2">Cantidad</th>
                        <th class="px-3 py-2">Precio</th>
                        <th class="px-3 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($paquetes as $p): ?>
                    <tr class="bg-slate-800 border-b border-slate-700">
                        <td class="px-3 py-2">
                            <?php if (!empty($p['imagen_icono'])): ?>
                                <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded-lg max-h-12 max-w-12">
                            <?php else: ?>
                                <span class="italic text-slate-400">Sin imagen</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 font-semibold"><?= htmlspecialchars($p['nombre']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($p['clave']) ?></td>
                        <td class="px-3 py-2"><?= htmlspecialchars($p['cantidad']) ?></td>
                        <td class="px-3 py-2">$<?= number_format($p['precio'], 2) ?></td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="text-emerald-400 hover:underline mr-4">Editar</a>
                            <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="text-rose-400 hover:underline" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Mobile Cards -->
        <div class="block md:hidden space-y-4">
            <?php foreach ($paquetes as $p): ?>
                <div class="bg-slate-800 rounded-lg p-4 shadow flex flex-col gap-2">
                    <div class="flex items-center gap-4">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded-lg max-h-12 max-w-12">
                        <?php else: ?>
                            <span class="italic text-slate-400">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="font-bold text-lg text-cyan-200"><?= htmlspecialchars($p['nombre']) ?></div>
                            <div class="text-xs text-slate-400">ID: <?= $p['id'] ?></div>
                        </div>
                    </div>
                    <div class="text-slate-300"><span class="font-semibold">Clave:</span> <?= htmlspecialchars($p['clave']) ?></div>
                    <div class="text-slate-300"><span class="font-semibold">Cantidad:</span> <?= htmlspecialchars($p['cantidad']) ?></div>
                    <div class="text-slate-300"><span class="font-semibold">Precio:</span> $<?= number_format($p['precio'], 2) ?></div>
                    <div class="flex gap-4 mt-2">
                        <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="text-emerald-400 hover:underline">Editar</a>
                        <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="text-rose-400 hover:underline" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>


        
    <?php
    // Procesar eliminación de paquete
    if (isset($_GET['eliminar'])) {
        $del_id = intval($_GET['eliminar']);
        $stmt = $mysqli->prepare("DELETE FROM juego_paquetes WHERE id=? AND juego_id=?");
        $stmt->bind_param('ii', $del_id, $juego_id);
        $stmt->execute();
        header('Location: /admin/paquetes/' . $juego_id);
        exit;
    }
    ?>
    </ul>

<?php
// Formulario de edición de paquete
if (isset($_GET['editar'])) {
    $edit_id = intval($_GET['editar']);
    $res_edit = $mysqli->prepare("SELECT * FROM juego_paquetes WHERE id=? AND juego_id=?");
    $res_edit->bind_param('ii', $edit_id, $juego_id);
    $res_edit->execute();
    $paq_edit = $res_edit->get_result()->fetch_assoc();
    if ($paq_edit):
?>
<div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
  <form method="post" enctype="multipart/form-data" class="bg-slate-900 rounded-xl p-8 max-w-lg w-full relative" style="box-shadow:0 0 2rem #22d3ee33;">
    <h3 class="text-xl font-bold mb-4 text-cyan-300">Editar paquete</h3>
    <input type="hidden" name="edit_paquete_id" value="<?= $paq_edit['id'] ?>">
    <input type="text" name="edit_nombre" value="<?= htmlspecialchars($paq_edit['nombre']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
    <input type="text" name="edit_clave" value="<?= htmlspecialchars($paq_edit['clave']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
    <input type="number" name="edit_cantidad" value="<?= htmlspecialchars($paq_edit['cantidad']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
    <input type="number" step="0.01" name="edit_precio" value="<?= htmlspecialchars($paq_edit['precio']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
    <label class="block text-slate-300 mb-1">Icono actual:</label>
    <?php if ($paq_edit['imagen_icono']): ?>
      <img src="/<?= htmlspecialchars($paq_edit['imagen_icono']) ?>" alt="Icono actual" class="mb-2 rounded-lg max-h-24">
    <?php endif; ?>
    <input type="file" name="edit_imagen_icono" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2" onchange="previewEditPaqueteImg(event)">
    <div class="flex justify-center my-2">
      <img id="preview-edit-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
    </div>
    <button type="submit" name="edit_paquete_submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg w-full">Guardar cambios</button>
    <a href="/admin/paquetes/<?= $juego_id ?>" class="absolute top-2 right-4 text-cyan-300 hover:underline text-lg">&times;</a>
  </form>
</div>
<script>
function previewEditPaqueteImg(event) {
    const input = event.target;
    const img = document.getElementById('preview-edit-paquete-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}
</script>
<?php endif; } ?>
    <a href="/admin/juegos" class="inline-block mt-6 text-cyan-300 hover:underline">Volver a juegos</a>
<script>
function previewPaqueteImg(event) {
    const input = event.target;
    const img = document.getElementById('preview-paquete-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        img.src = '#';
        img.style.display = 'none';
    }
}
</script>
</main>
<?php include '../includes/footer.php'; ?>