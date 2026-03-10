<?php
session_start();

// Verificar si el usuario es admin
if (!isset($_SESSION['auth_user']) || ($_SESSION['auth_user']['rol'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}

// Definir la variable $seccion
$seccion = $_GET['seccion'] ?? 'dashboard';
if (isset($_SERVER['REQUEST_URI'])) {
    if (preg_match('#/admin/([a-zA-Z0-9_-]+)#', $_SERVER['REQUEST_URI'], $m)) {
        $seccion = $m[1];
    }
}

function normalize_coupon_code(string $value): string {
    return strtoupper(trim($value));
}

function is_valid_coupon_code(string $value): bool {
    return $value !== '' && preg_match('/^[A-Za-z0-9]+$/', $value) === 1;
}

function admin_set_flash(string $type, string $message): void {
    $_SESSION['auth_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function admin_redirect(string $section, array $query = []): void {
    $target = '/admin/' . $section;
    if (!empty($query)) {
        $target .= '?' . http_build_query($query);
    }
    header('Location: ' . $target);
    exit();
}

switch ($seccion) {
    case 'usuarios':
        require_once __DIR__ . '/includes/db.php';
        if (isset($_GET['borrar_usuario'])) {
            $id = intval($_GET['borrar_usuario']);
            if ($id === 1) {
                admin_set_flash('error', 'No puedes eliminar el admin principal.');
            } else {
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                admin_set_flash('success', 'Usuario eliminado.');
            }
            admin_redirect('usuarios');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
            $id = intval($_POST['id']);
            $nombre = trim($_POST['nombre'] ?? '');
            $rol = $_POST['rol'] ?? 'usuario';
            if ($id && $nombre && in_array($rol, ['usuario', 'admin'], true)) {
                $pdo->prepare('UPDATE usuarios SET nombre = ?, rol = ? WHERE id = ?')->execute([$nombre, $rol, $id]);
                admin_set_flash('success', 'Usuario actualizado.');
            } else {
                admin_set_flash('error', 'Datos inválidos para actualizar el usuario.');
            }
            admin_redirect('usuarios');
        }
        break;

    case 'juegos':
        if (file_exists(__DIR__ . '/includes/db.php')) {
            require_once __DIR__ . '/includes/db.php';
        }
        if (isset($pdo)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_juego'])) {
                $nombre = $_POST['nombre'] ?? '';
                $descripcion = $_POST['descripcion'] ?? '';
                $precio = $_POST['precio'] ?? 0;
                $imagen = $_POST['imagen'] ?? '';
                $stmt = $pdo->prepare('INSERT INTO juegos (nombre, descripcion, precio, imagen) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nombre, $descripcion, $precio, $imagen]);
                admin_set_flash('success', 'Juego agregado correctamente.');
                admin_redirect('juegos');
            }

            if (isset($_GET['borrar_juego'])) {
                $id = intval($_GET['borrar_juego']);
                $pdo->prepare('DELETE FROM juegos WHERE id = ?')->execute([$id]);
                admin_set_flash('success', 'Juego eliminado.');
                admin_redirect('juegos');
            }
        }
        break;

    case 'cupones':
        require_once __DIR__ . '/includes/db.php';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_cupon'])) {
            $codigoInput = trim($_POST['codigo'] ?? '');
            $codigo = normalize_coupon_code($codigoInput);
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
            $limite_usos = ($_POST['limite_usos'] ?? '') !== '' ? intval($_POST['limite_usos']) : null;
            $activo = isset($_POST['activo']) ? 1 : 0;

            if (!is_valid_coupon_code($codigoInput)) {
                admin_set_flash('error', 'El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.');
            } else {
                $stmt_check = $pdo->prepare('SELECT 1 FROM cupones WHERE codigo = ? LIMIT 1');
                $stmt_check->execute([$codigo]);
                if ($stmt_check->fetch()) {
                    admin_set_flash('error', 'Ya existe un cupón con ese código.');
                } elseif ($codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje', 'fijo'], true)) {
                    $stmt = $pdo->prepare('INSERT INTO cupones (codigo, tipo_descuento, valor_descuento, fecha_expiracion, limite_usos, activo) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$codigo, $tipo_descuento, $valor_descuento, $fecha_expiracion !== '' ? $fecha_expiracion : null, $limite_usos, $activo]);
                    admin_set_flash('success', 'Cupón creado correctamente.');
                } else {
                    admin_set_flash('error', 'Datos inválidos para el cupón.');
                }
            }
            admin_redirect('cupones');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cupon'])) {
            $id = intval($_POST['id'] ?? 0);
            $codigoInput = trim($_POST['codigo'] ?? '');
            $codigo = normalize_coupon_code($codigoInput);
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
            $limite_usos = ($_POST['limite_usos'] ?? '') !== '' ? intval($_POST['limite_usos']) : null;
            $activo = isset($_POST['activo']) ? 1 : 0;

            if (!is_valid_coupon_code($codigoInput)) {
                admin_set_flash('error', 'El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.');
            } else {
                $stmt_check = $pdo->prepare('SELECT 1 FROM cupones WHERE codigo = ? AND id <> ? LIMIT 1');
                $stmt_check->execute([$codigo, $id]);
                if ($stmt_check->fetch()) {
                    admin_set_flash('error', 'Ya existe un cupón con ese código.');
                } elseif ($id && $codigo && $valor_descuento > 0 && in_array($tipo_descuento, ['porcentaje', 'fijo'], true)) {
                    $stmt = $pdo->prepare('UPDATE cupones SET codigo=?, tipo_descuento=?, valor_descuento=?, fecha_expiracion=?, limite_usos=?, activo=? WHERE id=?');
                    $stmt->execute([$codigo, $tipo_descuento, $valor_descuento, $fecha_expiracion !== '' ? $fecha_expiracion : null, $limite_usos, $activo, $id]);
                    admin_set_flash('success', 'Cupón actualizado correctamente.');
                } else {
                    admin_set_flash('error', 'Datos inválidos para el cupón.');
                }
            }
            admin_redirect('cupones', ['editar_cupon' => $id]);
        }

        if (isset($_GET['borrar_cupon'])) {
            $id = intval($_GET['borrar_cupon']);
            $pdo->prepare('DELETE FROM cupones WHERE id = ?')->execute([$id]);
            admin_set_flash('success', 'Cupón eliminado.');
            admin_redirect('cupones');
        }

        if (isset($_GET['toggle_cupon'])) {
            $id = intval($_GET['toggle_cupon']);
            $pdo->prepare('UPDATE cupones SET activo = NOT activo WHERE id = ?')->execute([$id]);
            admin_set_flash('success', 'Estado del cupón actualizado.');
            admin_redirect('cupones');
        }
        break;

    case 'configuracion':
        require_once __DIR__ . '/includes/store_config.php';
        require_once __DIR__ . '/includes/home_gallery.php';
        $activeTab = $_GET['tab'] ?? 'correo';
        if (!in_array($activeTab, ['correo', 'cabecera', 'galeria'], true)) {
            $activeTab = 'correo';
        }

        home_gallery_ensure_table();

        if ($activeTab === 'galeria' && isset($_GET['eliminar_galeria'])) {
            $galleryId = intval($_GET['eliminar_galeria']);
            if ($galleryId > 0 && home_gallery_delete($galleryId)) {
                admin_set_flash('success', 'Elemento de galería eliminado.');
            } else {
                admin_set_flash('error', 'No se pudo eliminar el elemento de galería.');
            }
            admin_redirect('configuracion', ['tab' => 'galeria']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $activeTab = $_POST['config_section'] ?? $activeTab;
            if (!in_array($activeTab, ['correo', 'cabecera', 'galeria'], true)) {
                $activeTab = 'correo';
            }

            if ($activeTab === 'correo') {
                $campos = [
                    'correo_corporativo', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'
                ];
                foreach ($campos as $clave) {
                    store_config_upsert($clave, trim((string) ($_POST[$clave] ?? '')));
                }
                admin_set_flash('success', 'Configuración de correo actualizada.');
            }

            if ($activeTab === 'cabecera') {
                $nombrePrefijo = trim((string) ($_POST['nombre_prefijo'] ?? ''));
                $nombreTienda = trim((string) ($_POST['nombre_tienda'] ?? ''));
                $currentLogo = store_config_get('logo_tienda', '');
                $nextLogo = $currentLogo;
                $hasUpload = isset($_FILES['logo_tienda']) && (($_FILES['logo_tienda']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);

                if ($nombrePrefijo === '' || $nombreTienda === '') {
                    admin_set_flash('error', 'Completa el nombre prefijo y el nombre de la tienda.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'cabecera']);
                }

                if ($hasUpload) {
                    $upload = store_config_store_logo_upload($_FILES['logo_tienda']);
                    if (!$upload['success']) {
                        admin_set_flash('error', $upload['message']);
                        define('ADMIN_CONFIG_POST_HANDLED', true);
                        admin_redirect('configuracion', ['tab' => 'cabecera']);
                    }
                    if (!empty($upload['path'])) {
                        $nextLogo = $upload['path'];
                    }
                } elseif (isset($_POST['eliminar_logo_tienda'])) {
                    $nextLogo = '';
                }

                store_config_upsert('nombre_prefijo', $nombrePrefijo);
                store_config_upsert('nombre_tienda', $nombreTienda);
                if ($nextLogo === '') {
                    store_config_delete('logo_tienda');
                } else {
                    store_config_upsert('logo_tienda', $nextLogo);
                }

                if ($currentLogo !== '' && $currentLogo !== $nextLogo) {
                    store_config_delete_logo_file($currentLogo);
                }

                admin_set_flash('success', 'Datos de cabecera actualizados.');
            }

            if ($activeTab === 'galeria') {
                $galleryId = isset($_POST['gallery_id']) ? intval($_POST['gallery_id']) : 0;
                $existingItem = $galleryId > 0 ? home_gallery_find($galleryId) : null;
                if ($galleryId > 0 && $existingItem === null) {
                    admin_set_flash('error', 'El elemento de galería que intentas editar no existe.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'galeria']);
                }

                $validation = home_gallery_validate_form($_POST, $_FILES, $existingItem);
                if (!$validation['is_valid']) {
                    $newImage = (string) ($validation['data']['imagen'] ?? '');
                    $existingImage = (string) ($existingItem['imagen'] ?? '');
                    if ($newImage !== '' && $newImage !== $existingImage) {
                        home_gallery_delete_image_file($newImage);
                    }
                    admin_set_flash('error', implode(' ', $validation['errors']));
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    $query = ['tab' => 'galeria'];
                    if ($galleryId > 0) {
                        $query['editar_galeria'] = $galleryId;
                    }
                    admin_redirect('configuracion', $query);
                }

                $saved = home_gallery_save($validation['data'], $galleryId > 0 ? $galleryId : null);
                if ($saved) {
                    $replacedImage = (string) ($validation['replaced_image'] ?? '');
                    $newImage = (string) ($validation['data']['imagen'] ?? '');
                    if ($replacedImage !== '' && $replacedImage !== $newImage) {
                        home_gallery_delete_image_file($replacedImage);
                    }
                    admin_set_flash('success', $galleryId > 0 ? 'Elemento de galería actualizado.' : 'Elemento de galería creado.');
                    define('ADMIN_CONFIG_POST_HANDLED', true);
                    admin_redirect('configuracion', ['tab' => 'galeria']);
                }

                $newImage = (string) ($validation['data']['imagen'] ?? '');
                $existingImage = (string) ($existingItem['imagen'] ?? '');
                if ($newImage !== '' && $newImage !== $existingImage) {
                    home_gallery_delete_image_file($newImage);
                }
                admin_set_flash('error', 'No se pudo guardar el elemento de galería.');
            }

            define('ADMIN_CONFIG_POST_HANDLED', true);
            admin_redirect('configuracion', ['tab' => $activeTab]);
        }

        define('ADMIN_CONFIG_ACTIVE_TAB', $activeTab);
        define('ADMIN_CONFIG_POST_HANDLED', true);
        break;
}

define('ADMIN_LAYOUT_EMBEDDED', true);

// Header y menú igual al inicio
require_once __DIR__ . '/includes/header.php';
?>
<body class="min-h-screen text-slate-100">
<div class="relative min-h-screen overflow-hidden">

<div class="container-lg min-vh-100 d-flex flex-column align-items-center justify-content-center px-2">
    <div class="w-100 mt-5">
        <?php if ($seccion === 'dashboard'): ?>
        <div class="mb-5 text-center">
            <h1 class="display-4 fw-bold text-info mb-4">Panel de Administración</h1>
            <h2 class="h3 fw-semibold mb-3">Bienvenido al panel de administración</h2>
            <p class="mb-4">Selecciona una sección para comenzar.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="/admin/usuarios" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>👤</span>Usuarios</a>
                <a href="/admin/juegos" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>🎮</span>Juegos</a>
                <a href="/admin/monedas" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>💵</span>Monedas</a>
                <a href="/admin/cupones" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>✏️</span>Cupones</a>
                <a href="/admin/pedidos" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>📋</span>Pedidos</a>
                <a href="/admin/configuracion" class="btn btn-outline-info btn-lg d-flex align-items-center gap-2"><span>⚙️</span>Configuración</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="relative mb-8">
        <?php
        switch ($seccion) {
            case 'usuarios':
                require_once __DIR__ . '/includes/db.php';
                echo '<h2 class="display-6 fw-bold text-info mb-4">Gestión de Usuarios</h2>';
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
                    echo '<div class="text-secondary">No hay usuarios registrados.</div>';
                } else {
                    // Tabla desktop modelo gamer neon sin fondo blanco
                    echo '<div class="table-responsive mb-4 d-none d-md-block" style="background:#10141a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem;">';
                    echo '<table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">';
                    echo '<thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">';
                    echo '<tr>';
                    echo '<th style="color:#00fff7; background:#181f2a;">ID</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Nombre</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Email</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Rol</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Creado</th>';
                    echo '<th style="color:#00fff7; background:#181f2a;">Acciones</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    $rowAlt = false;
                    foreach ($usuarios as $usuario) {
                        $rowStyle = $rowAlt ? 'background:#151a24;' : 'background:#181f2a;';
                        echo '<tr style="' . $rowStyle . ' color:#fff;">';
                        echo '<td style="color:#00fff7; background:#181f2a;">' . htmlspecialchars($usuario['id']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        echo '<form method="POST" class="d-flex gap-2 align-items-center">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="form-control form-control-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                        echo '</td>';
                        echo '<td style="color:#fff; background:#181f2a;">' . htmlspecialchars($usuario['email']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        echo '<select name="rol" class="form-select form-select-sm" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                        foreach (["usuario"=>"Usuario","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '<button type="submit" class="btn btn-info btn-sm ms-2" style="background:#00fff7; color:#222; border:none; box-shadow:0 0 8px #00fff7;">Guardar</button>';
                        echo '</form>';
                        echo '</td>';
                        echo '<td style="color:#00fff7; background:#181f2a;">' . htmlspecialchars($usuario['creado_en']) . '</td>';
                        echo '<td style="background:#181f2a;">';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="btn btn-outline-danger btn-sm" style="border-color:#ff0059; color:#ff0059; background:#181f2a;" onmouseover="this.style.background=\'#ff0059\';this.style.color=\'#fff\'" onmouseout="this.style.background=\'#181f2a\';this.style.color=\'#ff0059\'" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="text-secondary">Admin</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                        $rowAlt = !$rowAlt;
                    }
                    echo '</tbody></table>';
                    echo '</div>';

                    // Cards solo en móvil
                    echo '<div class="d-block d-md-none">';
                    foreach ($usuarios as $usuario) {
                        echo '<div class="card bg-dark text-light mb-3 border-info shadow">';
                        echo '<div class="card-header d-flex justify-content-between align-items-center">';
                        echo '<span class="small text-info">ID: ' . htmlspecialchars($usuario['id']) . '</span>';
                        echo '<span class="small text-secondary">' . htmlspecialchars($usuario['creado_en']) . '</span>';
                        echo '</div>';
                        echo '<div class="card-body">';
                        echo '<form method="POST">';
                        echo '<input type="hidden" name="editar_usuario" value="1">';
                        echo '<input type="hidden" name="id" value="' . htmlspecialchars($usuario['id']) . '">';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Nombre</label>';
                        echo '<input type="text" name="nombre" value="' . htmlspecialchars($usuario['nombre']) . '" class="form-control">';
                        echo '</div>';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Email</label>';
                        echo '<div class="form-control bg-dark text-light">' . htmlspecialchars($usuario['email']) . '</div>';
                        echo '</div>';
                        echo '<div class="mb-2">';
                        echo '<label class="form-label text-info">Rol</label>';
                        echo '<select name="rol" class="form-select">';
                        foreach (["usuario"=>"Usuario","admin"=>"Admin"] as $rolVal=>$rolTxt) {
                            $sel = $usuario['rol']===$rolVal ? 'selected' : '';
                            echo "<option value='$rolVal' $sel>$rolTxt</option>";
                        }
                        echo '</select>';
                        echo '</div>';
                        echo '<div class="d-flex gap-2 mt-2">';
                        echo '<button type="submit" class="btn btn-info flex-fill">Guardar</button>';
                        if ($usuario['id'] != 1) {
                            echo '<a href="?seccion=usuarios&borrar_usuario=' . $usuario['id'] . '" class="btn btn-danger flex-fill" onclick="return confirm(\'¿Eliminar este usuario?\')">Eliminar</a>';
                        } else {
                            echo '<span class="btn btn-secondary flex-fill disabled">Admin</span>';
                        }
                        echo '</div>';
                        echo '</form>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                break;
            case 'juegos':
                echo '<h2 class="text-2xl font-semibold mb-8 text-cyan-300">Gestión de Juegos</h2>';
                if (file_exists(__DIR__ . "/includes/db.php")) {
                    require_once __DIR__ . "/includes/db.php";
                } else {
                    echo '<div class="text-red-400">Error: No se encontró el archivo de conexión a la base de datos (includes/db.php).</div>';
                    break;
                }
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
                echo '<form method="POST" action="/admin/juegos" enctype="multipart/form-data" class="bg-slate-900 rounded-xl p-8 max-w-lg w-full relative mb-8" style="box-shadow:0 0 2rem #22d3ee33;">';
                echo '<h3 class="text-xl font-bold mb-4 text-cyan-300">Registrar juego</h3>';
                echo '<label class="block text-slate-300 font-medium mb-2"><input type="checkbox" name="popular" value="1" class="accent-cyan-600"> Marcar como popular</label>';
                echo '<input type="text" name="nombre" placeholder="Nombre del juego" class="block w-full text-xl px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2" required />';
                echo '<textarea name="descripcion" placeholder="Descripción" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2"></textarea>';
                echo '<label class="block text-slate-300 font-medium mb-2">Imagen principal:';
                echo '<input type="file" name="imagen" accept="image/*" class="block w-full mt-2 mb-2" /></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Imagen común para paquetes:';
                echo '<input type="file" name="imagen_paquete" accept="image/*" class="block w-full mt-2 mb-2" /></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Moneda fija o variable:';
                echo '<select name="moneda" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2">';
                echo '<option value="">Moneda variable (usuario elige)</option>';
                echo '<option value="USD">Dólar estadounidense</option>';
                echo '<option value="VES">Bolívares</option>';
                echo '</select></label>';
                echo '<label class="block text-slate-300 font-medium mb-2">Seleccionar características existentes:';
                echo '<select name="caracteristicas[]" multiple class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2">';
                echo '<option value="Entrega Inmediata">Entrega Inmediata</option>';
                echo '<option value="Global">Global</option>';
                echo '</select></label>';
                echo '<input type="text" name="nueva_caracteristica" placeholder="Nueva característica" class="block w-full text-base px-4 py-3 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-cyan-400 mb-2" />';
                echo '<button type="submit" class="w-full bg-cyan-600 hover:bg-cyan-700 text-white text-xl px-4 py-4 rounded font-semibold transition">Registrar juego</button>';
                echo '</form>';
                if (count($juegos) === 0) {
                    echo '<div class="text-gray-400">No hay juegos registrados.</div>';
                } else {
                    // Tabla para desktop
                    echo '<div class="overflow-x-auto hidden sm:block">';
                    echo '<table class="w-full text-left text-sm min-w-[800px]">';
                    echo '<thead><tr class="text-cyan-300">'
                        .'<th>Imagen</th>'
                        .'<th>Nombre</th>'
                        .'<th>Popular</th>'
                        .'<th>Imagen Paquete</th>'
                        .'<th>Descripción</th>'
                        .'<th>Moneda</th>'
                        .'<th>Características</th>'
                        .'<th>Acciones</th>'
                        .'</tr></thead><tbody>';
                    foreach ($juegos as $juego) {
                        echo '<tr class="border-b border-gray-700">';
                        // Imagen principal
                        echo '<td>';
                        if (!empty($juego['imagen'])) {
                            $imgSrc = '/' . ltrim($juego['imagen'], '/');
                            echo '<img src="'.htmlspecialchars($imgSrc).'" alt="img" class="w-16 h-16 object-cover rounded" />';
                        } else {
                            echo '<span class="text-gray-400">Sin imagen</span>';
                        }
                        echo '</td>';
                        // Nombre
                        echo '<td>' . htmlspecialchars($juego['nombre']) . '</td>';
                        // Popular
                        echo '<td>' . ((isset($juego['popular']) && $juego['popular']) ? '<span class="text-green-400">★</span>' : '—') . '</td>';
                        // Imagen Paquete
                        echo '<td>';
                        if (!empty($juego['imagen_paquete'])) {
                            $imgPaqSrc = '/' . ltrim($juego['imagen_paquete'], '/');
                            echo '<img src="'.htmlspecialchars($imgPaqSrc).'" alt="img" class="w-16 h-16 object-cover rounded" />';
                        } else {
                            echo '<span class="text-gray-400">Sin imagen</span>';
                        }
                        echo '</td>';
                        // Descripción
                        echo '<td>' . htmlspecialchars($juego['descripcion'] ?? '') . '</td>';
                        // Moneda
                        echo '<td>' . htmlspecialchars($juego['moneda'] ?? '') . '</td>';
                        // Características
                        echo '<td>' . htmlspecialchars($juego['caracteristicas'] ?? '') . '</td>';
                        // Acciones
                        echo '<td>';
                        echo '<a href="?seccion=juegos&editar_juego=' . $juego['id'] . '" class="text-green-400 hover:underline mr-2">Editar</a>';
                        echo '<a href="?seccion=paquetes&juego_id=' . $juego['id'] . '" class="text-cyan-400 hover:underline mr-2">Paquetes</a>';
                        echo '<a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    echo '</div>';

                    // Cards para móvil
                    echo '<div class="sm:hidden flex flex-col gap-4">';
                    foreach ($juegos as $juego) {
                        echo '<div class="rounded-xl border border-slate-700 bg-gray-900 p-4 flex flex-col gap-2 shadow">';
                        // Imagen principal
                        if (!empty($juego['imagen'])) {
                            $imgSrc = '/' . ltrim($juego['imagen'], '/');
                            echo '<img src="'.htmlspecialchars($imgSrc).'" alt="img" class="w-full h-32 object-cover rounded mb-2" />';
                        } else {
                            echo '<span class="text-gray-400 mb-2">Sin imagen</span>';
                        }
                        echo '<div class="font-bold text-lg text-cyan-200">' . htmlspecialchars($juego['nombre']) . '</div>';
                        if (isset($juego['popular']) && $juego['popular']) {
                            echo '<div class="text-green-400 text-sm">★ Popular</div>';
                        }
                        // Imagen Paquete
                        if (!empty($juego['imagen_paquete'])) {
                            $imgPaqSrc = '/' . ltrim($juego['imagen_paquete'], '/');
                            echo '<img src="'.htmlspecialchars($imgPaqSrc).'" alt="img" class="w-full h-16 object-cover rounded mb-2" />';
                        } else {
                            echo '<span class="text-gray-400 mb-2">Sin imagen de paquete</span>';
                        }
                        echo '<div class="text-sm text-slate-300"><strong>Descripción:</strong> ' . htmlspecialchars($juego['descripcion'] ?? '') . '</div>';
                        echo '<div class="text-sm text-slate-300"><strong>Moneda:</strong> ' . htmlspecialchars($juego['moneda'] ?? '') . '</div>';
                        echo '<div class="text-sm text-slate-300"><strong>Características:</strong> ' . htmlspecialchars($juego['caracteristicas'] ?? '') . '</div>';
                        echo '<div class="flex gap-4 mt-2">';
                        echo '<a href="?seccion=juegos&editar_juego=' . $juego['id'] . '" class="text-green-400 hover:underline">Editar</a>';
                        echo '<a href="?seccion=paquetes&juego_id=' . $juego['id'] . '" class="text-cyan-400 hover:underline">Paquetes</a>';
                        echo '<a href="?seccion=juegos&borrar_juego=' . $juego['id'] . '" class="text-red-400 hover:underline" onclick="return confirm(\'¿Eliminar este juego?\')">Eliminar</a>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
                break;
            case 'cupones':
                require_once __DIR__ . '/includes/db.php';
                echo '<h2 class="text-center mb-4" style="color:#00fff7;">Gestión de Cupones</h2>';
                // Alta y edición de cupón
                $edit_cupon = null;
                if (isset($_GET['editar_cupon'])) {
                    $edit_id = intval($_GET['editar_cupon']);
                    $cupones = $pdo->query('SELECT * FROM cupones ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cupones as $cupon) {
                        if ($cupon['id'] == $edit_id) {
                            $edit_cupon = $cupon;
                            break;
                        }
                    }
                }
                // Formulario
                echo '<form method="POST" action="" class="row g-3 mb-4" style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:2rem;">';
                if ($edit_cupon) {
                    echo '<input type="hidden" name="editar_cupon" value="1">';
                    echo '<input type="hidden" name="id" value="' . htmlspecialchars($edit_cupon['id']) . '">';
                } else {
                    echo '<input type="hidden" name="nuevo_cupon" value="1">';
                }
                echo '<div class="col-md-4">';
                echo '<label class="form-label" style="color:#00fff7;">Código del cupón</label>';
                echo '<input type="text" name="codigo" value="' . ($edit_cupon ? htmlspecialchars($edit_cupon['codigo']) : '') . '" required pattern="[A-Za-z0-9]+" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false" oninput="this.value=this.value.replace(/[^A-Za-z0-9]/g,\'\').toUpperCase()" title="Solo letras y números, sin espacios ni caracteres especiales." class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-md-4">';
                echo '<label class="form-label" style="color:#00fff7;">Tipo de descuento</label>';
                echo '<select name="tipo_descuento" class="form-select" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '<option value="porcentaje"' . ($edit_cupon && $edit_cupon['tipo_descuento']=='porcentaje' ? ' selected' : '') . '>Porcentaje (%)</option>';
                echo '<option value="fijo"' . ($edit_cupon && $edit_cupon['tipo_descuento']=='fijo' ? ' selected' : '') . '>Monto fijo</option>';
                echo '</select>';
                echo '</div>';
                echo '<div class="col-md-4">';
                echo '<label class="form-label" style="color:#00fff7;">Valor del descuento</label>';
                echo '<input type="number" name="valor_descuento" step="0.01" min="0.01" value="' . ($edit_cupon ? htmlspecialchars($edit_cupon['valor_descuento']) : '') . '" required class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-md-4">';
                echo '<label class="form-label" style="color:#00fff7;">Fecha expiración</label>';
                echo '<input type="datetime-local" name="fecha_expiracion" value="' . ($edit_cupon && $edit_cupon['fecha_expiracion'] ? date('Y-m-d\TH:i', strtotime($edit_cupon['fecha_expiracion'])) : '') . '" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-md-4">';
                echo '<label class="form-label" style="color:#00fff7;">Límite de usos</label>';
                echo '<input type="number" name="limite_usos" min="0" value="' . ($edit_cupon ? htmlspecialchars($edit_cupon['limite_usos']) : '') . '" placeholder="0 = ilimitado" class="form-control" style="background:#222c3a; color:#00fff7; border:1px solid #00fff7;">';
                echo '</div>';
                echo '<div class="col-md-4 d-flex align-items-end">';
                echo '<div class="form-check">';
                echo '<input type="checkbox" name="activo" class="form-check-input" id="activoCheck"' . ($edit_cupon && $edit_cupon['activo'] ? ' checked' : (!$edit_cupon ? ' checked' : '')) . '>';
                echo '<label class="form-check-label" for="activoCheck" style="color:#00fff7;">Cupón activo</label>';
                echo '</div>';
                echo '</div>';
                echo '<div class="col-12">';
                echo '<button type="submit" class="btn btn-info w-100" style="background:#00fff7; color:#222; border:none; box-shadow:0 0 8px #00fff7;">' . ($edit_cupon ? 'Guardar cambios' : 'Crear cupón') . '</button>';
                if ($edit_cupon) echo '<a href="?seccion=cupones" class="btn btn-secondary ms-2">Cancelar</a>';
                echo '</div>';
                echo '</form>';
                // Procesar alta/edición/borrado/activación
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_cupon'])) {
                    $codigoInput = trim($_POST['codigo'] ?? '');
                    $codigo = normalize_coupon_code($codigoInput);
                    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
                    $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
                    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
                    $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
                    $activo = isset($_POST['activo']) ? 1 : 0;
                    if (!is_valid_coupon_code($codigoInput)) {
                        echo '<div class="text-red-400 mb-2">El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.</div>';
                    } else {
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
                }
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_cupon'])) {
                    $id = intval($_POST['id'] ?? 0);
                    $codigoInput = trim($_POST['codigo'] ?? '');
                    $codigo = normalize_coupon_code($codigoInput);
                    $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
                    $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
                    $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
                    $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
                    $activo = isset($_POST['activo']) ? 1 : 0;
                    if (!is_valid_coupon_code($codigoInput)) {
                        echo '<div class="text-red-400 mb-2">El código del cupón solo puede contener letras y números, sin espacios, acentos ni caracteres especiales.</div>';
                    } else {
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
                }
                if (isset($_GET['borrar_cupon'])) {
                    $id = intval($_GET['borrar_cupon']);
                    $pdo->prepare('DELETE FROM cupones WHERE id = ?')->execute([$id]);
                    echo '<div class="text-green-400 mb-2">Cupón eliminado.</div>';
                }
                if (isset($_GET['toggle_cupon'])) {
                    $id = intval($_GET['toggle_cupon']);
                    $pdo->prepare('UPDATE cupones SET activo = NOT activo WHERE id = ?')->execute([$id]);
                }
                $cupones = $pdo->query('SELECT * FROM cupones ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
                echo '<h3 class="text-info mt-5 mb-3">Cupones existentes</h3>';
                echo '<div class="table-responsive d-none d-md-block">';
                echo '<table class="table align-middle" style="background:#181f2a; color:#00fff7; border-radius:12px;">';
                echo '<thead style="background:#181f2a; color:#00fff7; border-bottom:2px solid #00fff7;">';
                echo '<tr>';
                echo '<th style="color:#00fff7; background:#181f2a;">ID</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Código</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Tipo</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Valor</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Expira</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Límite usos</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Usos actuales</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Activo</th>';
                echo '<th style="color:#00fff7; background:#181f2a;">Acciones</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($cupones as $c) {
                    echo '<tr style="background:#181f2a; color:#fff;">';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . $c['id'] . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . htmlspecialchars($c['codigo']) . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . htmlspecialchars($c['tipo_descuento']) . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . htmlspecialchars($c['valor_descuento']) . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . ($c['fecha_expiracion'] ? htmlspecialchars($c['fecha_expiracion']) : '-') . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . ($c['limite_usos'] ?? '-') . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . $c['usos_actuales'] . '</td>';
                    echo '<td style="background:#181f2a; color:#00fff7;">' . ($c['activo'] ? 'Sí' : 'No') . '</td>';
                    echo '<td style="background:#181f2a;">';
                    echo '<a href="?seccion=cupones&editar_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Editar</a>';
                    echo ($c['activo'] ? '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Desactivar</a>' : '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; margin-right:1em;">Activar</a>');
                    echo '<a href="?seccion=cupones&borrar_cupon=' . $c['id'] . '" style="color:#ff0059; text-decoration:underline;" onclick="return confirm(\'¿Eliminar este cupón?\')">Eliminar</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
                // Mobile Cards
                echo '<div class="d-block d-md-none space-y-4">';
                foreach ($cupones as $c) {
                    echo '<div style="background:#181f2a; border-radius:16px; border:2px solid #00fff7; box-shadow:0 0 24px #00fff733; padding:1rem; color:#00fff7; margin-bottom:1.2rem;">';
                    echo '<div style="font-weight:bold; font-size:1.2em; color:#00fff7; display:flex; align-items:center;">' . htmlspecialchars($c['codigo']) . '<span style="font-size:0.9em; color:#b2f6ff; margin-left:0.5em;">ID: ' . $c['id'] . '</span></div>';
                    echo '<div style="margin-top:0.5em; color:#fff;"><span style="color:#00fff7; font-weight:bold;">Tipo:</span> ' . htmlspecialchars($c['tipo_descuento']) . ' | <span style="color:#00fff7; font-weight:bold;">Valor:</span> ' . htmlspecialchars($c['valor_descuento']) . '</div>';
                    echo '<div style="margin-top:0.5em; color:#fff;"><span style="color:#00fff7; font-weight:bold;">Expira:</span> ' . ($c['fecha_expiracion'] ? htmlspecialchars($c['fecha_expiracion']) : '-') . '</div>';
                    echo '<div style="margin-top:0.5em; color:#fff;"><span style="color:#00fff7; font-weight:bold;">Límite usos:</span> ' . ($c['limite_usos'] ?? '-') . ' | <span style="color:#00fff7; font-weight:bold;">Usos actuales:</span> ' . $c['usos_actuales'] . '</div>';
                    echo '<div style="margin-top:0.5em; color:#fff;"><span style="color:#00fff7; font-weight:bold;">Activo:</span> ' . ($c['activo'] ? 'Sí' : 'No') . '</div>';
                    echo '<div style="display:flex; gap:1rem; margin-top:1rem;">';
                    echo '<a href="?seccion=cupones&editar_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; font-weight:bold;">Editar</a>';
                    echo ($c['activo'] ? '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; font-weight:bold;">Desactivar</a>' : '<a href="?seccion=cupones&toggle_cupon=' . $c['id'] . '" style="color:#00fff7; text-decoration:underline; font-weight:bold;">Activar</a>');
                    echo '<a href="?seccion=cupones&borrar_cupon=' . $c['id'] . '" style="color:#ff0059; text-decoration:underline; font-weight:bold;" onclick="return confirm(\'¿Eliminar este cupón?\')">Eliminar</a>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
                break;
            case 'pedidos':
                echo '<h2 class="text-2xl font-semibold mb-4 text-cyan-300">Gestión de Pedidos</h2>';
                echo '<p class="text-gray-400">Aquí se listarán y gestionarán los pedidos.</p>';
                break;
            case 'configuracion':
                require_once __DIR__ . '/admin_configuracion.php';
                break;
        }
        ?>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
