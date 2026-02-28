
<?php
// admin/juegos.php - Gestión de juegos y características
require_once '../includes/db_connect.php';

// Procesar edición de cabecera de juego (antes de cualquier salida)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_juego_submit'], $_POST['edit_juego_id'], $_POST['edit_nombre'], $_POST['edit_descripcion'])) {
    $edit_id = intval($_POST['edit_juego_id']);
    $edit_nombre = trim($_POST['edit_nombre']);
    $edit_descripcion = trim($_POST['edit_descripcion']);
    $edit_imagen = null;
    if (isset($_FILES['edit_imagen']) && $_FILES['edit_imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['edit_imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juego_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['edit_imagen']['tmp_name'], $destino)) {
                $edit_imagen = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    if ($edit_imagen) {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen=? WHERE id=?");
        $stmt->bind_param('sssi', $edit_nombre, $edit_descripcion, $edit_imagen, $edit_id);
    } else {
        $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=? WHERE id=?");
        $stmt->bind_param('ssi', $edit_nombre, $edit_descripcion, $edit_id);
    }
    $stmt->execute();
    header('Location: /admin/juegos');
    exit;
}

// Procesar creación de juego y características
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['descripcion'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $moneda_fija_id = !empty($_POST['moneda_fija_id']) ? intval($_POST['moneda_fija_id']) : null;
    $imagen = null;
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $permitidas)) {
            $dir = '../assets/img/juegos/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $nombre_archivo = uniqid('juego_') . '.' . $ext;
            $destino = $dir . $nombre_archivo;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                $imagen = 'assets/img/juegos/' . $nombre_archivo;
            }
        }
    }
    $stmt = $mysqli->prepare("INSERT INTO juegos (nombre, imagen, descripcion, moneda_fija_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $nombre, $imagen, $descripcion, $moneda_fija_id);
    $stmt->execute();
    $juego_id = $mysqli->insert_id;
    // Características seleccionadas del select múltiple
    if (!empty($_POST['caracteristicas_select'])) {
        foreach ($_POST['caracteristicas_select'] as $car) {
            $car = trim($car);
            if ($car !== '') {
                $stmt2 = $mysqli->prepare("INSERT INTO juego_caracteristicas (juego_id, caracteristica) VALUES (?, ?)");
                $stmt2->bind_param('is', $juego_id, $car);
                $stmt2->execute();
            }
        }
    }
    // Características nuevas escritas
    if (!empty($_POST['caracteristicas'])) {
        foreach ($_POST['caracteristicas'] as $car) {
            $car = trim($car);
            if ($car !== '') {
                $stmt2 = $mysqli->prepare("INSERT INTO juego_caracteristicas (juego_id, caracteristica) VALUES (?, ?)");
                $stmt2->bind_param('is', $juego_id, $car);
                $stmt2->execute();
            }
        }
    }
    header('Location: /admin/juegos');
    exit;
}

// Listar monedas para el select
$resm = $mysqli->query("SELECT * FROM monedas ORDER BY nombre ASC");
$monedas = $resm->fetch_all(MYSQLI_ASSOC);
// Listar características únicas
$rescar = $mysqli->query("SELECT DISTINCT caracteristica FROM juego_caracteristicas ORDER BY caracteristica ASC");
$caracteristicas_unicas = [];
while ($row = $rescar->fetch_assoc()) {
    $caracteristicas_unicas[] = $row['caracteristica'];
}
// Listar juegos existentes
$resj = $mysqli->query("SELECT * FROM juegos ORDER BY id DESC");
$juegos = $resj->fetch_all(MYSQLI_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<main class="max-w-5xl mx-auto mt-10 bg-slate-900/80 rounded-xl p-8 shadow-lg">
    <h2 class="text-2xl font-bold mb-6 text-cyan-300">Gestión de Juegos</h2>
    <form method="post" enctype="multipart/form-data" class="space-y-4">
        <input type="text" name="nombre" placeholder="Nombre del juego" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        <textarea name="descripcion" placeholder="Descripción" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400"></textarea>
        <input type="file" name="imagen" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400" onchange="previewImagenJuego(event)">
        <div class="flex justify-center my-2">
            <img id="preview-juego-img" src="#" alt="Previsualización" style="display:none;max-width:180px;max-height:180px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
        </div>
        <select name="moneda_fija_id" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white">
            <option value="">Moneda variable (usuario elige)</option>
            <?php foreach ($monedas as $m): ?>
            <option value="<?= $m['id'] ?>">Solo <?= htmlspecialchars($m['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="block text-slate-300 font-medium">Seleccionar características existentes:</label>
        <select name="caracteristicas_select[]" multiple class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white" size="3">
            <?php foreach ($caracteristicas_unicas as $car): ?>
                <option value="<?= htmlspecialchars($car) ?>"><?= htmlspecialchars($car) ?></option>
            <?php endforeach; ?>
        </select>
        <div id="caracteristicas" class="space-y-2 mt-2">
            <input type="text" name="caracteristicas[]" placeholder="Nueva característica" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400">
        </div>
        <button type="button" onclick="addCarField()" class="bg-cyan-700 hover:bg-cyan-600 text-white px-4 py-2 rounded-lg">Agregar nueva característica</button>
        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg w-full">Agregar juego</button>
    </form>
        <h3 class="text-xl font-semibold mt-10 mb-4">Juegos existentes</h3>
        <!-- Desktop Table -->
        <div class="overflow-x-auto hidden md:block">
            <table class="min-w-full text-sm text-left text-white">
                <thead class="bg-slate-700 text-cyan-200">
                    <tr>
                        <th class="px-3 py-2">Imagen</th>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Descripción</th>
                        <th class="px-3 py-2">Moneda</th>
                        <th class="px-3 py-2">Características</th>
                        <th class="px-3 py-2">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($juegos as $j): ?>
                    <tr class="bg-slate-800 border-b border-slate-700">
                        <td class="px-3 py-2">
                            <?php if (!empty($j['imagen'])): ?>
                                <img src="/<?= htmlspecialchars($j['imagen']) ?>" alt="img" class="rounded-lg max-h-16 max-w-16">
                            <?php else: ?>
                                <span class="italic text-slate-400">Sin imagen</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-2 font-semibold"><?= htmlspecialchars($j['nombre']) ?></td>
                        <td class="px-3 py-2" style="max-width:220px;overflow-x:auto;white-space:pre-line;"><?= nl2br(htmlspecialchars($j['descripcion'])) ?></td>
                        <td class="px-3 py-2">
                            <?php 
                                if (!empty($j['moneda_fija_id'])) {
                                    $mon = $mysqli->query("SELECT nombre FROM monedas WHERE id=" . intval($j['moneda_fija_id']));
                                    $moneda = $mon && $mon->num_rows ? $mon->fetch_assoc()['nombre'] : 'Desconocida';
                                    echo htmlspecialchars($moneda);
                                } else {
                                    echo '<span class="italic text-slate-400">Variable</span>';
                                }
                            ?>
                        </td>
                        <td class="px-3 py-2">
                            <?php 
                                $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($j['id']));
                                $cars = [];
                                while ($row = $carRes->fetch_assoc()) $cars[] = $row['caracteristica'];
                                echo $cars ? htmlspecialchars(implode(', ', $cars)) : '<span class="italic text-slate-400">Ninguna</span>';
                            ?>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <a href="/admin/juegos?editar=<?= $j['id'] ?>" class="text-emerald-400 hover:underline mr-4">Editar</a>
                            <a href="/admin/paquetes/<?= $j['id'] ?>" class="text-cyan-400 hover:underline mr-4">Paquetes</a>
                            <a href="/admin/juegos?eliminar=<?= $j['id'] ?>" class="text-rose-400 hover:underline" onclick="return confirm('¿Eliminar este juego y todos sus paquetes/características?')">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Mobile Cards -->
        <div class="block md:hidden space-y-4">
            <?php foreach ($juegos as $j): ?>
                <div class="bg-slate-800 rounded-lg p-4 shadow flex flex-col gap-2">
                    <div class="flex items-center gap-4">
                        <?php if (!empty($j['imagen'])): ?>
                            <img src="/<?= htmlspecialchars($j['imagen']) ?>" alt="img" class="rounded-lg max-h-16 max-w-16">
                        <?php else: ?>
                            <span class="italic text-slate-400">Sin imagen</span>
                        <?php endif; ?>
                        <div>
                            <div class="font-bold text-lg text-cyan-200"><?= htmlspecialchars($j['nombre']) ?></div>
                            <div class="text-xs text-slate-400">ID: <?= $j['id'] ?></div>
                        </div>
                    </div>
                    <div class="text-slate-300"><span class="font-semibold">Descripción:</span> <?= nl2br(htmlspecialchars($j['descripcion'])) ?></div>
                    <div class="text-slate-300"><span class="font-semibold">Moneda:</span> <?php 
                        if (!empty($j['moneda_fija_id'])) {
                            $mon = $mysqli->query("SELECT nombre FROM monedas WHERE id=" . intval($j['moneda_fija_id']));
                            $moneda = $mon && $mon->num_rows ? $mon->fetch_assoc()['nombre'] : 'Desconocida';
                            echo htmlspecialchars($moneda);
                        } else {
                            echo '<span class="italic text-slate-400">Variable</span>';
                        }
                    ?></div>
                    <div class="text-slate-300"><span class="font-semibold">Características:</span> <?php 
                        $carRes = $mysqli->query("SELECT caracteristica FROM juego_caracteristicas WHERE juego_id=" . intval($j['id']));
                        $cars = [];
                        while ($row = $carRes->fetch_assoc()) $cars[] = $row['caracteristica'];
                        echo $cars ? htmlspecialchars(implode(', ', $cars)) : '<span class="italic text-slate-400">Ninguna</span>';
                    ?></div>
                    <div class="flex gap-4 mt-2">
                        <a href="/admin/juegos?editar=<?= $j['id'] ?>" class="text-emerald-400 hover:underline">Editar</a>
                        <a href="/admin/paquetes/<?= $j['id'] ?>" class="text-cyan-400 hover:underline">Paquetes</a>
                        <a href="/admin/juegos?eliminar=<?= $j['id'] ?>" class="text-rose-400 hover:underline" onclick="return confirm('¿Eliminar este juego y todos sus paquetes/características?')">Eliminar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php
    // Procesar eliminación de juego
    if (isset($_GET['eliminar'])) {
        $del_id = intval($_GET['eliminar']);
        $stmt = $mysqli->prepare("DELETE FROM juegos WHERE id=?");
        $stmt->bind_param('i', $del_id);
        $stmt->execute();
        header('Location: /admin/juegos');
        exit;
    }
    ?>
    <?php
    // Formulario de edición de cabecera de juego
    if (isset($_GET['editar'])) {
            $edit_id = intval($_GET['editar']);
            $res_edit = $mysqli->prepare("SELECT * FROM juegos WHERE id=?");
            $res_edit->bind_param('i', $edit_id);
            $res_edit->execute();
            $juego_edit = $res_edit->get_result()->fetch_assoc();
            if ($juego_edit):
    ?>
    <div class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
        <form method="post" action="/admin/juegos" enctype="multipart/form-data" class="bg-slate-900 rounded-xl p-8 max-w-lg w-full relative" style="box-shadow:0 0 2rem #22d3ee33;">
            <h3 class="text-xl font-bold mb-4 text-cyan-300">Editar juego</h3>
            <input type="hidden" name="edit_juego_id" value="<?= $juego_edit['id'] ?>">
            <input type="text" name="edit_nombre" value="<?= htmlspecialchars($juego_edit['nombre']) ?>" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2">
            <textarea name="edit_descripcion" required class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2"><?= htmlspecialchars($juego_edit['descripcion']) ?></textarea>
            <label class="block text-slate-300 mb-1">Imagen actual:</label>
            <?php if ($juego_edit['imagen']): ?>
                <img src="/<?= htmlspecialchars($juego_edit['imagen']) ?>" alt="Imagen actual" class="mb-2 rounded-lg max-h-32">
            <?php endif; ?>
            <input type="file" name="edit_imagen" accept="image/*" class="w-full rounded-lg px-3 py-2 bg-slate-800 text-white mb-2" onchange="previewEditJuegoImg(event)">
            <div class="flex justify-center my-2">
                <img id="preview-edit-juego-img" src="#" alt="Previsualización" style="display:none;max-width:180px;max-height:180px;border-radius:0.75rem;box-shadow:0 0 0.5rem #22d3ee55;" />
            </div>
            <button type="submit" name="edit_juego_submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg w-full">Guardar cambios</button>
            <a href="/admin/juegos" class="absolute top-2 right-4 text-cyan-300 hover:underline text-lg">&times;</a>
        </form>
    </div>
    <script>
    function previewEditJuegoImg(event) {
            const input = event.target;
            const img = document.getElementById('preview-edit-juego-img');
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
    <?php endif; } 
    // Procesar edición de cabecera de juego
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_juego_submit'], $_POST['edit_juego_id'], $_POST['edit_nombre'], $_POST['edit_descripcion'])) {
        $edit_id = intval($_POST['edit_juego_id']);
        $edit_nombre = trim($_POST['edit_nombre']);
        $edit_descripcion = trim($_POST['edit_descripcion']);
        $edit_imagen = null;
        if (isset($_FILES['edit_imagen']) && $_FILES['edit_imagen']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['edit_imagen']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $permitidas)) {
                $dir = '../assets/img/juegos/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $nombre_archivo = uniqid('juego_') . '.' . $ext;
                $destino = $dir . $nombre_archivo;
                if (move_uploaded_file($_FILES['edit_imagen']['tmp_name'], $destino)) {
                    $edit_imagen = 'assets/img/juegos/' . $nombre_archivo;
                }
            }
        }
        if ($edit_imagen) {
            $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=?, imagen=? WHERE id=?");
            $stmt->bind_param('sssi', $edit_nombre, $edit_descripcion, $edit_imagen, $edit_id);
        } else {
            $stmt = $mysqli->prepare("UPDATE juegos SET nombre=?, descripcion=? WHERE id=?");
            $stmt->bind_param('ssi', $edit_nombre, $edit_descripcion, $edit_id);
        }
        $stmt->execute();
        header('Location: /admin/juegos');
        exit;
    }
    ?>
    </ul>
    <a href="/admin/monedas" class="inline-block mt-6 text-cyan-300 hover:underline">Gestionar monedas</a>
</main>
<script>
function addCarField() {
    var cont = document.getElementById('caracteristicas');
    var input = document.createElement('input');
    input.type = 'text';
    input.name = 'caracteristicas[]';
    input.placeholder = 'Característica';
    input.className = 'w-full rounded-lg px-3 py-2 bg-slate-800 text-white placeholder-slate-400 mt-2';
    cont.appendChild(input);
}
function previewImagenJuego(event) {
    const input = event.target;
    const img = document.getElementById('preview-juego-img');
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
<?php include '../includes/footer.php'; ?>