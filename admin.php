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
                    echo '<thead><tr class="text-cyan-300"><th>ID</th><th>Nombre</th><th>Precio</th><th>Acciones</th></tr></thead><tbody>';
                    foreach ($juegos as $juego) {
                        echo '<tr class="border-b border-gray-700">';
                        echo '<td>' . htmlspecialchars($juego['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($juego['nombre']) . '</td>';
                        echo '<td>Bs. ' . htmlspecialchars($juego['precio']) . '</td>';
                        echo '<td><a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a></td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';
                }
                break;
            case 'pedidos':
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Pedidos</h2>';
                echo '<p class="text-gray-400">Aquí se listarán y gestionarán los pedidos.</p>';
                break;
            default:
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Bienvenido al panel de administración</h2>';
                echo '<p class="text-gray-400">Selecciona una sección para comenzar.</p>';
                break;
        }
        ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
