<?php
header('Content-Type: application/json');
// Manejo global de errores de conexión
try {
    require_once __DIR__ . '/../includes/db_connect.php';
    if (!isset($mysqli) || $mysqli->connect_errno) {
        throw new Exception('Error de conexión a la base de datos: ' . ($mysqli->connect_error ?? 'Desconocido'));
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$pack_price = isset($_POST['pack_price']) ? floatval($_POST['pack_price']) : 0;

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Cupón vacío.']);
    exit;
}

// LOG TEMPORAL PARA DEPURAR
file_put_contents(__DIR__ . '/log_cupon.txt', date('Y-m-d H:i:s') . " | code: $code | pack_price: $pack_price\n", FILE_APPEND);

$stmt = $mysqli->prepare('SELECT * FROM cupones WHERE codigo = ? LIMIT 1');
$stmt->bind_param('s', $code);
$stmt->execute();
$res = $stmt->get_result();
$cupon = $res->fetch_assoc();
$stmt->close();

if (!$cupon) {
    echo json_encode(['success' => false, 'message' => 'Cupón inexistente.']);
    exit;
}
if ($cupon['activo'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Cupón inactivo.']);
    exit;
}
if (!is_null($cupon['fecha_expiracion']) && strtotime($cupon['fecha_expiracion']) < time()) {
    echo json_encode(['success' => false, 'message' => 'Cupón expirado.']);
    exit;
}
if (!is_null($cupon['limite_usos']) && $cupon['limite_usos'] > 0 && $cupon['usos_actuales'] >= $cupon['limite_usos']) {
    echo json_encode(['success' => false, 'message' => 'Cupón agotado.']);
    exit;
}

$descuento = 0;
if ($cupon['tipo_descuento'] === 'porcentaje') {
    $descuento = $pack_price * ($cupon['valor_descuento'] / 100);
} else {
    $descuento = floatval($cupon['valor_descuento']);
}

$nuevo_total = max(0, $pack_price - $descuento);

echo json_encode([
    'success' => true,
    'message' => 'Cupón aplicado correctamente.',
    'descuento' => $descuento,
    'nuevo_total' => $nuevo_total,
    'tipo_descuento' => $cupon['tipo_descuento'],
    'valor_descuento' => $cupon['valor_descuento']
]);
exit;
