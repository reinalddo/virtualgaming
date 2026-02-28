<?php
// admin_cupones.php
require_once 'includes/auth.php';
require_once 'includes/db_connect.php';
require_once 'includes/slugify.php';

// Helper para URLs amigables
define('ADMIN_CUPONES_BASE', '/admin/cupones');

function url_cupon($action, $id = null) {
    $base = ADMIN_CUPONES_BASE;
    switch ($action) {
        case 'nuevo': return "$base/nuevo";
        case 'editar': return "$base/editar/" . intval($id);
        case 'eliminar': return "$base/eliminar/" . intval($id);
        case 'activar': return "$base/activar/" . intval($id);
        case 'desactivar': return "$base/desactivar/" . intval($id);
        default: return $base;
    }
}

// Routing básico para CRUD amigable
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
$action = null;
$id = null;

if (preg_match('#^' . ADMIN_CUPONES_BASE . '/(nuevo)$#', $path, $m)) {
    $action = 'nuevo';
} elseif (preg_match('#^' . ADMIN_CUPONES_BASE . '/(editar|eliminar|activar|desactivar)/(\d+)$#', $path, $m)) {
    $action = $m[1];
    $id = (int)$m[2];
} else {
    $action = 'listar';
}

// CRUD
// Salida dinámica para el contenido principal
$contenido = '';

if ($action === 'nuevo') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = trim($_POST['codigo'] ?? '');
        $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
        $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
        $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
        $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
        $activo = isset($_POST['activo']) ? 1 : 0;

        $errores = [];
        if ($codigo === '') $errores[] = 'El código es obligatorio.';
        if ($valor_descuento <= 0) $errores[] = 'El valor de descuento debe ser mayor a 0.';
        if (!in_array($tipo_descuento, ['porcentaje', 'fijo'])) $errores[] = 'Tipo de descuento inválido.';

        if (empty($errores)) {
            $stmt = $db->prepare("INSERT INTO cupones (codigo, tipo_descuento, valor_descuento, fecha_expiracion, limite_usos, activo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $codigo,
                $tipo_descuento,
                $valor_descuento,
                $fecha_expiracion !== '' ? $fecha_expiracion : null,
                $limite_usos,
                $activo
            ]);
            header('Location: ' . ADMIN_CUPONES_BASE);
            exit;
        }
    }
    $contenido .= '<h2>Nuevo Cupón</h2>';
    if (!empty($errores)) {
        $contenido .= '<div class="errores"><ul><li>' . implode('</li><li>', $errores) . '</li></ul></div>';
    }
    $contenido .= '<form method="post" action="'.url_cupon('nuevo').'">';
    $contenido .= '<label>Código: <input type="text" name="codigo" required></label><br>';
    $contenido .= '<label>Tipo de descuento: <select name="tipo_descuento"><option value="porcentaje">Porcentaje (%)</option><option value="fijo">Monto fijo</option></select></label><br>';
    $contenido .= '<label>Valor descuento: <input type="number" name="valor_descuento" step="0.01" min="0.01" required></label><br>';
    $contenido .= '<label>Fecha expiración: <input type="datetime-local" name="fecha_expiracion"></label><br>';
    $contenido .= '<label>Límite de usos: <input type="number" name="limite_usos" min="0" placeholder="0 = ilimitado"></label><br>';
    $contenido .= '<label><input type="checkbox" name="activo" checked> Cupón activo</label><br>';
    $contenido .= '<button type="submit">Crear cupón</button> ';
    $contenido .= '<a href="'.ADMIN_CUPONES_BASE.'">Cancelar</a>';
    $contenido .= '</form>';
} elseif ($action === 'editar' && $id) {
    $stmt = $db->prepare("SELECT * FROM cupones WHERE id = ?");
    $stmt->execute([$id]);
    $cupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cupon) {
        $contenido .= '<p>Cupón no encontrado.</p>';
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $codigo = trim($_POST['codigo'] ?? '');
            $tipo_descuento = $_POST['tipo_descuento'] ?? 'porcentaje';
            $valor_descuento = floatval($_POST['valor_descuento'] ?? 0);
            $fecha_expiracion = $_POST['fecha_expiracion'] ?? null;
            $limite_usos = $_POST['limite_usos'] !== '' ? intval($_POST['limite_usos']) : null;
            $activo = isset($_POST['activo']) ? 1 : 0;
            $errores = [];
            if ($codigo === '') $errores[] = 'El código es obligatorio.';
            if ($valor_descuento <= 0) $errores[] = 'El valor de descuento debe ser mayor a 0.';
            if (!in_array($tipo_descuento, ['porcentaje', 'fijo'])) $errores[] = 'Tipo de descuento inválido.';
            if (empty($errores)) {
                $stmt2 = $db->prepare("UPDATE cupones SET codigo=?, tipo_descuento=?, valor_descuento=?, fecha_expiracion=?, limite_usos=?, activo=? WHERE id=?");
                $stmt2->execute([
                    $codigo,
                    $tipo_descuento,
                    $valor_descuento,
                    $fecha_expiracion !== '' ? $fecha_expiracion : null,
                    $limite_usos,
                    $activo,
                    $id
                ]);
                header('Location: ' . ADMIN_CUPONES_BASE);
                exit;
            }
        } else {
            $codigo = $cupon['codigo'];
            $tipo_descuento = $cupon['tipo_descuento'];
            $valor_descuento = $cupon['valor_descuento'];
            $fecha_expiracion = $cupon['fecha_expiracion'];
            $limite_usos = $cupon['limite_usos'];
            $activo = $cupon['activo'];
        }
        $contenido .= '<h2>Editar Cupón</h2>';
        if (!empty($errores)) {
            $contenido .= '<div class="errores"><ul><li>' . implode('</li><li>', $errores) . '</li></ul></div>';
        }
        $contenido .= '<form method="post" action="'.url_cupon('editar', $id).'">';
        $contenido .= '<label>Código: <input type="text" name="codigo" value="'.htmlspecialchars($codigo).'" required></label><br>';
        $contenido .= '<label>Tipo de descuento: <select name="tipo_descuento">';
        $contenido .= '<option value="porcentaje"'.($tipo_descuento=='porcentaje'?' selected':'').'>Porcentaje (%)</option>';
        $contenido .= '<option value="fijo"'.($tipo_descuento=='fijo'?' selected':'').'>Monto fijo</option>';
        $contenido .= '</select></label><br>';
        $contenido .= '<label>Valor descuento: <input type="number" name="valor_descuento" step="0.01" min="0.01" value="'.htmlspecialchars($valor_descuento).'" required></label><br>';
        $contenido .= '<label>Fecha expiración: <input type="datetime-local" name="fecha_expiracion" value="'.($fecha_expiracion ? date('Y-m-d\TH:i', strtotime($fecha_expiracion)) : '').'"></label><br>';
        $contenido .= '<label>Límite de usos: <input type="number" name="limite_usos" min="0" value="'.htmlspecialchars($limite_usos).'" placeholder="0 = ilimitado"></label><br>';
        $contenido .= '<label><input type="checkbox" name="activo"'.($activo?' checked':'').'> Cupón activo</label><br>';
        $contenido .= '<button type="submit">Guardar cambios</button> ';
        $contenido .= '<a href="'.ADMIN_CUPONES_BASE.'">Cancelar</a>';
        $contenido .= '</form>';
    }
} elseif ($action === 'eliminar' && $id) {
    $stmt = $db->prepare("DELETE FROM cupones WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: ' . ADMIN_CUPONES_BASE);
    exit;
} elseif ($action === 'activar' && $id) {
    $stmt = $db->prepare("UPDATE cupones SET activo = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: ' . ADMIN_CUPONES_BASE);
    exit;
} elseif ($action === 'desactivar' && $id) {
    $stmt = $db->prepare("UPDATE cupones SET activo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: ' . ADMIN_CUPONES_BASE);
    exit;
} else {
    // Listar cupones
    $stmt = $db->query("SELECT * FROM cupones ORDER BY id DESC");
    $cupones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $contenido .= '<a href="'.url_cupon('nuevo').'" class="btn">Nuevo cupón</a>';
    $contenido .= '<h2>Listado de Cupones</h2>';
    if (empty($cupones)) {
        $contenido .= '<p>No hay cupones registrados.</p>';
    } else {
        $contenido .= '<table border="1" cellpadding="5"><tr><th>ID</th><th>Código</th><th>Tipo</th><th>Valor</th><th>Expira</th><th>Límite usos</th><th>Usos actuales</th><th>Activo</th><th>Acciones</th></tr>';
        foreach ($cupones as $c) {
            $contenido .= '<tr>';
            $contenido .= '<td>'.$c['id'].'</td>';
            $contenido .= '<td>'.$c['codigo'].'</td>';
            $contenido .= '<td>'.$c['tipo_descuento'].'</td>';
            $contenido .= '<td>'.$c['valor_descuento'].'</td>';
            $contenido .= '<td>'.($c['fecha_expiracion'] ? $c['fecha_expiracion'] : '-').'</td>';
            $contenido .= '<td>'.($c['limite_usos'] ?? '-').'</td>';
            $contenido .= '<td>'.$c['usos_actuales'].'</td>';
            $contenido .= '<td>'.($c['activo'] ? 'Sí' : 'No').'</td>';
            $contenido .= '<td>';
            $contenido .= '<a href="'.url_cupon('editar', $c['id']).'">Editar</a> | ';
            if ($c['activo']) {
                $contenido .= '<a href="'.url_cupon('desactivar', $c['id']).'">Desactivar</a> | ';
            } else {
                $contenido .= '<a href="'.url_cupon('activar', $c['id']).'">Activar</a> | ';
            }
            $contenido .= '<a href="'.url_cupon('eliminar', $c['id']).'" onclick="return confirm(\'¿Eliminar este cupón?\')">Eliminar</a>';
            $contenido .= '</td>';
            $contenido .= '</tr>';
        }
        $contenido .= '</table>';
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Administrar Cupones</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
    <h1>Administrar Cupones</h1>
    <!-- Aquí irá el contenido dinámico del CRUD -->
</body>
</html>
