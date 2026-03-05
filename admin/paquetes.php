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

// Procesar eliminación de paquete (antes de cualquier salida)
if (isset($_GET['eliminar'])) {
    $del_id = intval($_GET['eliminar']);
    // Obtener la ruta de la imagen antes de borrar
    $stmt_img = $mysqli->prepare("SELECT imagen_icono FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt_img->bind_param('ii', $del_id, $juego_id);
    $stmt_img->execute();
    $stmt_img->bind_result($img_path);
    $stmt_img->fetch();
    $stmt_img->close();
    // Borrar el registro
    $stmt = $mysqli->prepare("DELETE FROM juego_paquetes WHERE id=? AND juego_id=?");
    $stmt->bind_param('ii', $del_id, $juego_id);
    $stmt->execute();
    // Borrar la imagen física si existe y no está vacía
    if ($img_path && file_exists('../' . $img_path)) {
        unlink('../' . $img_path);
    }
    header('Location: /admin/paquetes/' . $juego_id);
    exit;
}

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

// Obtener nombre e imagen_paquete del juego
$resj = $mysqli->prepare("SELECT nombre, imagen_paquete FROM juegos WHERE id=?");
$resj->bind_param('i', $juego_id);
$resj->execute();
$juego = $resj->get_result()->fetch_assoc();
?>
<?php include '../includes/header.php'; ?>
<main class="container-sm mt-5 bg-dark bg-opacity-75 rounded-4 p-4 shadow">
    <h2 class="text-center text-info mb-4">Paquetes de <?= htmlspecialchars($juego['nombre']) ?></h2>
    <form method="post" enctype="multipart/form-data" class="row g-3 mb-4">
        <div class="col-md-4">
            <label class="form-label">Nombre del paquete</label>
            <input type="text" name="nombre" placeholder="Nombre del paquete" required class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Clave interna</label>
            <input type="text" name="clave" placeholder="Clave interna" required class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Cantidad</label>
            <input type="number" name="cantidad" placeholder="Cantidad" required class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Precio USD</label>
            <input type="number" step="0.01" name="precio" placeholder="Precio USD" required class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Imagen del paquete</label>
            <input type="file" name="imagen_icono" accept="image/*" class="form-control" onchange="previewPaqueteImg(event)">
            <div class="text-center mt-2">
                <img id="preview-paquete-img" src="#" alt="Previsualización" style="display:none;max-width:120px;max-height:120px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success w-100">Agregar paquete</button>
        </div>
    </form>
    <h3 class="text-info mt-5 mb-3">Paquetes existentes</h3>
    <!-- Tabla desktop -->
    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle" style="background:#181f2a; color:#22d3ee; border-radius:12px; border:2px solid #22d3ee; box-shadow:0 0 24px #22d3ee33;">
            <thead style="background:#181f2a; color:#22d3ee; border-bottom:2px solid #22d3ee;">
                <tr>
                    <th style="color:#22d3ee; background:#181f2a;">Imagen</th>
                    <th style="color:#22d3ee; background:#181f2a;">Nombre</th>
                    <th style="color:#22d3ee; background:#181f2a;">Clave</th>
                    <th style="color:#22d3ee; background:#181f2a;">Cantidad</th>
                    <th style="color:#22d3ee; background:#181f2a;">Precio</th>
                    <th style="color:#22d3ee; background:#181f2a;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($paquetes as $p): ?>
                <tr style="background:#181f2a; color:#fff;">
                    <td style="background:#181f2a;">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail" style="max-height:48px;max-width:48px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                    </td>
                    <td class="fw-semibold text-neon" style="background:#181f2a; color:#22d3ee;"><?= htmlspecialchars($p['nombre']) ?></td>
                    <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($p['clave']) ?></td>
                    <td style="background:#181f2a; color:#fff;"><?= htmlspecialchars($p['cantidad']) ?></td>
                    <td class="text-neon" style="background:#181f2a; color:#22d3ee;">$<?= number_format($p['precio'], 2) ?></td>
                    <td style="background:#181f2a;" class="text-nowrap">
                        <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm me-2">Editar</a>
                        <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm neon-btn" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Cards móvil -->
    <div class="d-md-none">
        <div class="row gy-4">
            <?php foreach ($paquetes as $p): ?>
            <div class="col-12">
                <div class="card neon-card p-3" style="background:#181f2a; border:2px solid #22d3ee; box-shadow:0 0 16px #22d3ee,0 0 4px #2dd4bf; color:#22d3ee;">
                    <div class="d-flex align-items-center mb-2">
                        <?php if (!empty($p['imagen_icono'])): ?>
                            <img src="/<?= htmlspecialchars($p['imagen_icono']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php elseif (!empty($juego['imagen_paquete'])): ?>
                            <img src="/<?= htmlspecialchars($juego['imagen_paquete']) ?>" alt="icono" class="rounded img-thumbnail me-3" style="max-height:56px;max-width:56px;box-shadow:0 0 8px #22d3ee; border:2px solid #22d3ee; background:#222c3a;">
                        <?php else: ?>
                            <span class="fst-italic text-secondary">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="fw-bold text-neon" style="font-size:1.1rem; color:#22d3ee;"><?= htmlspecialchars($p['nombre']) ?></div>
                            <div class="text-muted" style="font-size:0.85rem; color:#b2f6ff;">ID: <?= $p['id'] ?></div>
                        </div>
                    </div>
                    <div style="color:#fff;"><span class="fw-semibold">Clave:</span> <?= htmlspecialchars($p['clave']) ?></div>
                    <div style="color:#fff;"><span class="fw-semibold">Cantidad:</span> <?= htmlspecialchars($p['cantidad']) ?></div>
                    <div class="text-neon" style="color:#22d3ee;"><span class="fw-semibold">Precio:</span> $<?= number_format($p['precio'], 2) ?></div>
                    <div class="mt-3 d-flex gap-2">
                        <a href="/admin/paquetes/<?= $juego_id ?>?editar=<?= $p['id'] ?>" class="btn neon-btn-info btn-sm flex-fill">Editar</a>
                        <a href="/admin/paquetes/<?= $juego_id ?>?eliminar=<?= $p['id'] ?>" class="btn btn-danger btn-sm neon-btn flex-fill" onclick="return confirm('¿Eliminar este paquete?')">Eliminar</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>


        
    <!-- Eliminación de paquete ahora se procesa al inicio del archivo -->
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