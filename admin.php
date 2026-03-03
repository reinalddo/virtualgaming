<?php
session_start();

// Verificar si el usuario es admin
if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}


$seccion = $_GET['seccion'] ?? 'dashboard';
// Permitir también URLs amigables: /admin/seccion
if (isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/admin/([a-zA-Z0-9_-]+)#', $_SERVER['REQUEST_URI'], $m)) {
        $seccion = $m[1];
    }
}

function nav_link($nombre, $seccion_actual) {
    $active = $nombre === $seccion_actual ? 'style="font-weight:bold;"' : '';
    // Usar URL amigable
    echo "<a href='/admin/$nombre' $active>".ucfirst($nombre)."</a> | ";
}
?>
<?php
// Header y menú igual al inicio
require_once __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen text-slate-100">
<div class="relative min-h-screen overflow-hidden">

<div class="w-full min-h-screen flex flex-col items-center px-2 sm:px-0">
    <div class="w-[90%] mt-6">
        <h1 class="text-2xl sm:text-3xl font-bold mb-8 text-center text-cyan-400">Panel de Administración</h1>
        <div class="relative mb-8">
        <?php
        switch ($seccion) {
            case 'usuarios':
                require_once __DIR__ . '/includes/db.php';
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Usuarios</h2>';
                // Borrar usuario
                if (isset($_GET['borrar_usuario'])) {
                    $id = intval($_GET['borrar_usuario']);
                    if ($id !== 1) { // No permitir borrar admin principal
                        $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                        echo '<div class="text-green-400 mb-2">Usuario eliminado.</div>';
                    }
                }
                // Edición de usuario (solo nombre y rol)
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
                    $id = intval($_POST['id']);
                    $nombre = trim($_POST['nombre'] ?? '');
                    $rol = $_POST['rol'] ?? 'usuario';
                    if ($id && $nombre && in_array($rol, ['usuario','admin'])) {
                        $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?')->execute([$nombre, $rol, $id]);
                        echo '<div class="text-green-400 mb-2">Usuario actualizado.</div>';
                    }
                }
                // Listado de usuarios
                $usuarios = $pdo->query('SELECT * FROM usuarios ORDER BY creado_en DESC')->fetchAll(PDO::FETCH_ASSOC);
                if (count($usuarios) === 0) {
                    echo '<div class="text-gray-400">No hay usuarios registrados.</div>';
                } else {
                    echo '<div class="overflow-x-auto">';
                    echo '<div class="hidden sm:block">';
                    echo '<table class="w-full text-left text-sm min-w-[600px]">';
                    echo '<thead><tr class="text-cyan-300"><th>ID</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr></thead><tbody>';
                    foreach ($usuarios as $usuario) {
                        echo '<tr class="border-b border-gray-700">';
                        echo '<td>' . htmlspecialchars($usuario['id']) . '</td>';
                        echo '<td>';
                        echo '<form method="POST" class="flex gap-2 items-center">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-white w-32">';
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($usuario['email']) . '</td>';
                        echo '<td>';
                        echo '<select name="rol" class="bg-gray-900 border border-gray-700 rounded px-2 py-1 text-white">';
                        foreach (["usuario"=>"Usuario","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '<button type="submit" class="ml-2 px-2 py-1 rounded bg-cyan-600 text-white hover:bg-cyan-700">Guardar</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($usuario['creado_en']) . '</td>';
                        echo '<td>';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="text-gray-500">Admin</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                    // Cards para móvil
                    echo '<div class="sm:hidden flex flex-col gap-4">';
                    foreach ($usuarios as $usuario) {
                        echo '<div class="rounded-xl border border-slate-700 bg-gray-900 p-4 flex flex-col gap-2 shadow">';
                        echo '<div class="flex justify-between items-center mb-2">';
                        echo '<span class="text-xs text-cyan-300 font-semibold">ID: ' . htmlspecialchars($usuario['id']) . '</span>';
                        echo '<span class="text-xs text-slate-400">' . htmlspecialchars($usuario['creado_en']) . '</span>';
                        echo '</div>';
                        echo '<form method="POST" class="flex flex-col gap-2">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<label class="text-xs text-slate-400">Nombre</label>';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-white">';
                        echo '<label class="text-xs text-slate-400">Email</label>';
                        echo '<div class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-white">' . htmlspecialchars($usuario['email']) . '</div>';
                        echo '<label class="text-xs text-slate-400">Rol</label>';
                        echo '<select name="rol" class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-white">';
                        foreach (["usuario"=>"Usuario","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '<div class="flex gap-2 mt-2">';
                        echo '<button type="submit" class="flex-1 px-2 py-1 rounded bg-cyan-600 text-white hover:bg-cyan-700">Guardar</button>';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="flex-1 text-center px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="flex-1 text-center text-gray-500">Admin</span>';
                        }
                        echo '</div>';
                        echo '</form>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                break;
            case 'juegos':
                echo '<h2 class="text-2xl font-semibold mb-8 text-cyan-300">Gestión de Juegos</h2>';
                require_once __DIR__ . "/includes/db.php";
                // Alta de juego
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_juego'])) {
                    $nombre = $_POST['nombre'] ?? '';
                    $descripcion = $_POST['descripcion'] ?? '';
                    $precio = $_POST['precio'] ?? 0;
                    $imagen = $_POST['imagen'] ?? '';
                    $stmt = $pdo->prepare("INSERT INTO juegos (nombre, descripcion, precio, imagen) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nombre, $descripcion, $precio, $imagen]);
                    echo '<div class="text-green-400 mb-2">Juego agregado correctamente.</div>';
                }
                // Borrado de juego
                if (isset($_GET['borrar_juego'])) {
                    $id = intval($_GET['borrar_juego']);
                    $pdo->prepare("DELETE FROM juegos WHERE id = ?")->execute([$id]);
                    echo '<div class="text-green-400 mb-2">Juego eliminado.</div>';
                }
                // Listado de juegos
                $juegos = $pdo->query("SELECT * FROM juegos ORDER BY creado_en DESC")->fetchAll(PDO::FETCH_ASSOC);
                echo '<form method="POST" class="mb-6 flex flex-col gap-8 bg-gray-900 pt-12 pb-12 px-0 sm:p-4 sm:rounded-lg rounded-none shadow-sm w-full">';
                echo '<input type="hidden" name="nuevo_juego" value="1">';
                echo '<input type="text" name="nombre" placeholder="Nombre del juego" class="block w-full text-2xl px-6 py-10 rounded-none sm:rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required />';
                echo '<input type="text" name="descripcion" placeholder="Descripción" class="block w-full text-2xl px-6 py-10 rounded-none sm:rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                echo '<input type="number" step="0.01" name="precio" placeholder="Precio" class="block w-full text-2xl px-6 py-10 rounded-none sm:rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required />';
                echo '<input type="text" name="imagen" placeholder="URL de imagen" class="block w-full text-2xl px-6 py-10 rounded-none sm:rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                echo '<button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white text-2xl px-6 py-10 rounded-none sm:rounded font-semibold transition">Agregar juego</button>';
                echo '</form>';
                if (count($juegos) === 0) {
                    echo '<div class="text-gray-400">No hay juegos registrados.</div>';
                } else {
                    echo '<div class="overflow-x-auto">';
                    echo '<table class="w-full text-left text-sm min-w-[400px]">';
                    echo '<thead><tr class="text-cyan-300"><th>ID</th><th>Nombre</th><th>Acciones</th></tr></thead><tbody>';
                    foreach ($juegos as $juego) {
                        echo '<tr class="border-b border-gray-700">';
                        echo '<td>' . htmlspecialchars($juego['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($juego['nombre']) . '</td>';
                        echo '<td><a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                }
                break;
            case 'cupones':
                require_once __DIR__ . '/includes/db.php';
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Cupones</h2>';
                // Alta de cupón
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_cupon'])) {
                    $codigo = trim($_POST['codigo'] ?? '');
                    $codigo = strtoupper($codigo);
                    $codigo = preg_replace('/[^A-Z0-9_\-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $codigo));
                    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
                    $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
                    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
                    $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
                    $activo = isset($_POST['activo']) ? 1 : 0;
                    $stmt_check = $pdo->prepare("SELECT 1 FROM cupones WHERE codigo = ? LIMIT 1");
                    $stmt_check->execute([$codigo]);
                    if ($stmt_check->fetch()) {
                        echo '<div class="text-red-400 mb-2">Ya existe un cupón con ese código.</div>';
                    } elseif ($codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje','fijo'])) {
                        $stmt = $pdo->prepare("INSERT INTO cupones (codigo, tipo_descuento, valor_descuento, fecha_expiracion, limite_usos, activo) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$codigo, $tipo_descuento, $valor_descuento, $fecha_expiracion !== '' ? $fecha_expiracion : null, $limite_usos, $activo]);
                        echo '<div class="text-green-400 mb-2">Cupón creado correctamente.</div>';
                    } else {
                        echo '<div class="text-red-400 mb-2">Datos inválidos para el cupón.</div>';
                    }
                }

                // Edición de cupón
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cupon'])) {
                    $id = intval($_POST['id'] ?? 0);
                    $codigo = trim($_POST['codigo'] ?? '');
                    $codigo = strtoupper($codigo);
                    $codigo = preg_replace('/[^A-Z0-9_\-]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $codigo));
                    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
                    $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
                    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
                    $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
                    $activo = isset($_POST['activo']) ? 1 : 0;
                    // Verificar duplicados excepto el actual
                    $stmt_check = $pdo->prepare("SELECT 1 FROM cupones WHERE codigo = ? AND id <> ? LIMIT 1");
                    $stmt_check->execute([$codigo, $id]);
                    if ($stmt_check->fetch()) {
                        echo '<div class="text-red-400 mb-2">Ya existe un cupón con ese código.</div>';
                    } elseif ($id && $codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje','fijo'])) {
                        $stmt = $pdo->prepare("UPDATE cupones SET codigo=?, tipo_descuento=?, valor_descuento=?, fecha_expiracion=?, limite_usos=?, activo=? WHERE id=?");
                        $stmt->execute([$codigo, $tipo_descuento, $valor_descuento, $fecha_expiracion !== '' ? $fecha_expiracion : null, $limite_usos, $activo, $id]);
                        echo '<div class="text-green-400 mb-2">Cupón actualizado correctamente.</div>';
                    } else {
                        echo '<div class="text-red-400 mb-2">Datos inválidos para el cupón.</div>';
                    }
                }
                // Borrado de cupón
                if (isset($_GET['borrar_cupon'])) {
                    $id = intval($_GET['borrar_cupon']);
                    $pdo->prepare('DELETE FROM cupones WHERE id = ?')->execute([$id]);
                    echo '<div class="text-green-400 mb-2">Cupón eliminado.</div>';
                }
                // Activar/desactivar cupón
                if (isset($_GET['toggle_cupon'])) {
                    $id = intval($_GET['toggle_cupon']);
                    $pdo->prepare('UPDATE cupones SET activo = NOT activo WHERE id = ?')->execute([$id]);
                }
                // Obtener cupones
                $cupones = $pdo->query('SELECT * FROM cupones ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

                // Mostrar formulario de edición si corresponde
                $edit_cupon = null;
                if (isset($_GET['editar_cupon'])) {
                    $edit_id = intval($_GET['editar_cupon']);
                    foreach ($cupones as $cupon) {
                        if ($cupon['id'] == $edit_id) {
                            $edit_cupon = $cupon;
                            break;
                        }
                    }
                }

                if ($edit_cupon) {
                    // Formulario de edición
                    echo '<form method="POST" class="mb-6 flex flex-col gap-8 bg-gray-900 pt-12 pb-12 px-0 sm:p-4 sm:rounded-lg rounded-none shadow-sm w-full">';
                    echo '<input type="hidden" name="editar_cupon" value="1">';
                    echo '<input type="hidden" name="id" value="' . htmlspecialchars($edit_cupon['id']) . '">';
                    echo '<input type="text" name="codigo" id="codigo-cupon-input-edit" value="' . htmlspecialchars($edit_cupon['codigo']) . '" placeholder="Código del cupón" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required autocomplete="off" />';
                    echo '<script>';
                    echo 'document.addEventListener("DOMContentLoaded", function() {';
                    echo '  var input = document.getElementById("codigo-cupon-input-edit");';
                    echo '  if (input) {';
                    echo '    input.addEventListener("input", function(e) {';
                    echo '      let val = e.target.value;';
                    echo '      val = val.normalize("NFD").replace(/[\u0300-\u036f]/g, "");';
                    echo '      val = val.replace(/[^A-Z0-9_\-]/gi, "");';
                    echo '      val = val.toUpperCase();';
                    echo '      e.target.value = val;';
                    echo '    });';
                    echo '  }';
                    echo '});';
                    echo '</script>';
                    echo '<select name="tipo_descuento" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400">';
                    echo '<option value="porcentaje"' . ($edit_cupon['tipo_descuento'] == 'porcentaje' ? ' selected' : '') . '>Porcentaje (%)</option>';
                    echo '<option value="fijo"' . ($edit_cupon['tipo_descuento'] == 'fijo' ? ' selected' : '') . '>Monto fijo</option>';
                    echo '</select>';
                    echo '<input type="number" step="0.01" name="valor_descuento" value="' . htmlspecialchars($edit_cupon['valor_descuento']) . '" placeholder="Valor del descuento" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required />';
                    echo '<input type="datetime-local" name="fecha_expiracion" value="' . ($edit_cupon['fecha_expiracion'] ? date('Y-m-d\TH:i', strtotime($edit_cupon['fecha_expiracion'])) : '') . '" placeholder="Fecha de expiración" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                    echo '<input type="number" name="limite_usos" value="' . htmlspecialchars($edit_cupon['limite_usos']) . '" placeholder="Límite de usos (0 = ilimitado)" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                    echo '<label class="flex items-center gap-2"><input type="checkbox" name="activo" class="form-checkbox"' . ($edit_cupon['activo'] ? ' checked' : '') . '> Activo</label>';
                    echo '<button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white text-2xl px-6 py-4 rounded font-semibold transition">Guardar cambios</button>';
                    echo '<a href="?seccion=cupones" class="w-full text-center mt-2 text-cyan-400 hover:underline">Cancelar</a>';
                    echo '</form>';
                } else {
                    // Formulario de alta
                    echo '<form method="POST" class="mb-6 flex flex-col gap-8 bg-gray-900 pt-12 pb-12 px-0 sm:p-4 sm:rounded-lg rounded-none shadow-sm w-full">';
                    echo '<input type="hidden" name="nuevo_cupon" value="1">';
                    echo '<input type="text" name="codigo" id="codigo-cupon-input" placeholder="Código del cupón" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required autocomplete="off" />';
                    echo '<script>';
                    echo 'document.addEventListener("DOMContentLoaded", function() {';
                    echo '  var input = document.getElementById("codigo-cupon-input");';
                    echo '  if (input) {';
                    echo '    input.addEventListener("input", function(e) {';
                    echo '      let val = e.target.value;';
                    echo '      val = val.normalize("NFD").replace(/[\u0300-\u036f]/g, "");';
                    echo '      val = val.replace(/[^A-Z0-9_\-]/gi, "");';
                    echo '      val = val.toUpperCase();';
                    echo '      e.target.value = val;';
                    echo '    });';
                    echo '  }';
                    echo '});';
                    echo '</script>';
                    echo '<select name="tipo_descuento" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400">';
                    echo '<option value="porcentaje">Porcentaje (%)</option>';
                    echo '<option value="fijo">Monto fijo</option>';
                    echo '</select>';
                    echo '<input type="number" step="0.01" name="valor_descuento" placeholder="Valor del descuento" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" required />';
                    echo '<input type="datetime-local" name="fecha_expiracion" placeholder="Fecha de expiración" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                    echo '<input type="number" name="limite_usos" placeholder="Límite de usos (0 = ilimitado)" class="block w-full text-2xl px-6 py-4 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400" />';
                    echo '<label class="flex items-center gap-2"><input type="checkbox" name="activo" checked class="form-checkbox"> Activo</label>';
                    echo '<button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white text-2xl px-6 py-4 rounded font-semibold transition">Crear cupón</button>';
                    echo '</form>';
                }

                // Tabla desktop y cards mobile
                if (count($cupones) === 0) {
                    echo '<div class="text-gray-400">No hay cupones registrados.</div>';
                } else {
                    echo '<div class="overflow-x-auto">';
                    // Desktop table
                    echo '<div class="hidden md:block">';
                    echo '<table class="w-full text-left text-base min-w-[900px]">';
                    echo '<thead><tr class="text-cyan-300"><th>ID</th><th>Código</th><th>Tipo</th><th>Valor</th><th>Expira</th><th>Límite usos</th><th>Usos actuales</th><th>Activo</th><th>Acciones</th></tr></thead><tbody>';
                    foreach ($cupones as $c) {
                        echo '<tr class="border-b border-gray-700">';
                        echo '<td>' . htmlspecialchars($c['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['codigo']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['tipo_descuento']) . '</td>';
                        echo '<td>' . htmlspecialchars($c['valor_descuento']) . '</td>';
                        echo '<td>' . ($c['fecha_expiracion'] ? htmlspecialchars($c['fecha_expiracion']) : '-') . '</td>';
                        echo '<td>' . ($c['limite_usos'] ?? '-') . '</td>';
                        echo '<td>' . $c['usos_actuales'] . '</td>';
                        echo '<td>' . ($c['activo'] ? 'Sí' : 'No') . '</td>';
                        echo '<td>';
                        echo '<a href="?seccion=cupones&editar_cupon=' . $c['id'] . '" class="text-yellow-400 hover:underline">Editar</a> | ';
                        echo '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" class="text-cyan-400 hover:underline">' . ($c['activo'] ? 'Desactivar' : 'Activar') . '</a> | ';
                        echo '<a href="?seccion=cupones&borrar_cupon=' . $c['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este cupón?\')">Eliminar</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                    // Mobile cards
                    echo '<div class="md:hidden flex flex-col gap-4 mt-6">';
                    foreach ($cupones as $c) {
                        echo '<div class="rounded-xl border border-slate-700 bg-gray-900 p-4 flex flex-col gap-2 shadow">';
                        echo '<div class="flex justify-between items-center mb-2">';
                        echo '<span class="text-xs text-cyan-300 font-semibold">ID: ' . htmlspecialchars($c['id']) . '</span>';
                        echo '<span class="text-xs text-slate-400">' . ($c['fecha_expiracion'] ? htmlspecialchars($c['fecha_expiracion']) : '-') . '</span>';
                        echo '</div>';
                        echo '<div class="text-lg font-bold text-cyan-200">' . htmlspecialchars($c['codigo']) . '</div>';
                        echo '<div class="text-sm text-slate-300">Tipo: ' . htmlspecialchars($c['tipo_descuento']) . ' | Valor: ' . htmlspecialchars($c['valor_descuento']) . '</div>';
                        echo '<div class="text-sm text-slate-300">Límite usos: ' . ($c['limite_usos'] ?? '-') . ' | Usos actuales: ' . $c['usos_actuales'] . '</div>';
                        echo '<div class="text-sm text-slate-300">Activo: ' . ($c['activo'] ? 'Sí' : 'No') . '</div>';
                        echo '<div class="flex gap-2 mt-2">';
                        echo '<a href="?seccion=cupones&editar_cupon=' . $c['id'] . '" class="flex-1 text-center px-2 py-1 rounded bg-yellow-600 text-white hover:bg-yellow-700">Editar</a>';
                        echo '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" class="flex-1 text-center px-2 py-1 rounded bg-cyan-600 text-white hover:bg-cyan-700">' . ($c['activo'] ? 'Desactivar' : 'Activar') . '</a>';
                        echo '<a href="?seccion=cupones&borrar_cupon=' . $c['id'] . '" class="flex-1 text-center px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700" onclick="return confirm(\'¿Eliminar este cupón?\')">Eliminar</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                break;
            case 'pedidos':
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Pedidos</h2>';
                echo '<p class="text-gray-400">Aquí se listarán y gestionarán los pedidos.</p>';
                break;
            case 'configuracion':
                require_once __DIR__ . '/admin_configuracion.php';
                break;
            default:
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Bienvenido al panel de administración</h2>';
                echo '<p class="text-gray-400 mb-8">Selecciona una sección para comenzar.</p>';
                echo '<div class="grid grid-cols-1 sm:grid-cols-2 gap-6 max-w-lg mx-auto">';
                echo '<a href="/admin/usuarios" class="flex flex-col items-center justify-center rounded-xl border border-cyan-700 bg-gray-900 p-6 shadow hover:bg-cyan-950/30 transition">';
                echo '<span class="text-3xl mb-2">👤</span><span class="text-lg font-semibold text-cyan-300">Usuarios</span>';
                echo '</a>';
                echo '<a href="/admin/juegos" class="flex flex-col items-center justify-center rounded-xl border border-cyan-700 bg-gray-900 p-6 shadow hover:bg-cyan-950/30 transition">';
                echo '<span class="text-3xl mb-2">🎮</span><span class="text-lg font-semibold text-cyan-300">Juegos</span>';
                echo '</a>';
                echo '<a href="/admin/cupones" class="flex flex-col items-center justify-center rounded-xl border border-cyan-700 bg-gray-900 p-6 shadow hover:bg-cyan-950/30 transition">';
                echo '<span class="text-3xl mb-2">🏷️</span><span class="text-lg font-semibold text-cyan-300">Cupones</span>';
                echo '</a>';
                echo '<a href="/admin/pedidos" class="flex flex-col items-center justify-center rounded-xl border border-cyan-700 bg-gray-900 p-6 shadow hover:bg-cyan-950/30 transition">';
                echo '<span class="text-3xl mb-2">🧾</span><span class="text-lg font-semibold text-cyan-300">Pedidos</span>';
                echo '</a>';
                echo '<a href="/admin/configuracion" class="flex flex-col items-center justify-center rounded-xl border border-emerald-400 bg-gray-900 p-6 shadow hover:bg-emerald-950/30 transition">';
                echo '<span class="text-3xl mb-2">⚙️</span><span class="text-lg font-semibold text-emerald-300">Configuración</span>';
                echo '</a>';
                echo '</div>';
                break;
        }
        ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
