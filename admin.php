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
    echo "<a href='admin.php?seccion=$nombre' $active>".ucfirst($nombre)."</a> | ";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 min-h-screen text-gray-100">
    <div class="w-full min-h-screen flex flex-col items-center px-2 sm:px-0">
        <div class="w-[90%] mt-6">
            <h1 class="text-2xl sm:text-3xl font-bold mb-8 text-center text-cyan-400">Panel de Administración</h1>
            <div class="relative mb-8">
                <button id="admin-menu-btn" class="block sm:hidden flex items-center px-4 py-2 bg-cyan-700 text-white rounded focus:outline-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    Menú
                </button>
                <nav id="admin-menu" class="hidden fixed top-0 left-0 w-full h-full z-50 bg-gray-900 bg-opacity-95 flex-col items-center justify-center sm:static sm:flex sm:flex-row sm:bg-transparent sm:w-auto sm:h-auto sm:z-auto sm:items-start sm:justify-center sm:gap-4 p-8 sm:p-0">
                    <button id="admin-menu-close" class="block sm:hidden absolute top-4 right-4 text-white text-2xl">&times;</button>
                    <a href="/admin/dashboard" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-cyan-800/40 transition-all duration-150 text-xl sm:text-base<?php if($seccion==='dashboard') echo ' bg-cyan-700 text-white'; ?>">Dashboard</a>
                    <a href="/admin/usuarios" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-cyan-800/40 transition-all duration-150 text-xl sm:text-base<?php if($seccion==='usuarios') echo ' bg-cyan-700 text-white'; ?>">Usuarios</a>
                    <a href="/admin/juegos" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-cyan-800/40 transition-all duration-150 text-xl sm:text-base<?php if($seccion==='juegos') echo ' bg-cyan-700 text-white'; ?>">Juegos</a>
                    <a href="/admin/pedidos" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-cyan-800/40 transition-all duration-150 text-xl sm:text-base<?php if($seccion==='pedidos') echo ' bg-cyan-700 text-white'; ?>">Pedidos</a>
                    <hr class="my-4 border-gray-700 w-2/3 sm:hidden">
                    <a href="/" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-gray-700 transition-all duration-150 text-xl sm:text-base">Volver al inicio</a>
                    <a href="logout.php" class="block px-6 py-4 sm:px-4 sm:py-2 rounded hover:bg-red-700 transition-all duration-150 text-xl sm:text-base">Cerrar sesión</a>
                </nav>
                                <script>
                                const menuBtn = document.getElementById('admin-menu-btn');
                                const menu = document.getElementById('admin-menu');
                                const menuClose = document.getElementById('admin-menu-close');
                                if(menuBtn && menu && menuClose) {
                                    menuBtn.addEventListener('click', () => { menu.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); });
                                    menuClose.addEventListener('click', () => { menu.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); });
                                }
                                // Cerrar menú al hacer click fuera en móvil
                                menu.addEventListener('click', function(e) {
                                    if(e.target === menu) { menu.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
                                });
                                </script>
            </div>
            <div class="bg-gray-800 shadow p-2 sm:p-6 min-h-[300px] w-full sm:mx-auto sm:rounded-lg rounded-none mx-[-0.5rem] sm:mx-0 flex flex-col justify-center" style="min-height:400px;">
            <?php
            switch ($seccion) {
                case 'usuarios':
                    echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Usuarios</h2>';
                    echo '<p class="text-gray-400">Aquí se listarán y gestionarán los usuarios.</p>';
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
</body>
</html>
