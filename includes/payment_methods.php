<?php

function payment_methods_db(): mysqli {
    global $mysqli;

    if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
        require_once __DIR__ . '/db_connect.php';
    }

    return $mysqli;
}

function payment_methods_ensure_table(): void {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $mysqli = payment_methods_db();
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(160) NOT NULL,
    datos TEXT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_methods_activo (activo),
    INDEX idx_payment_methods_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    $mysqli->query($sql);
    $initialized = true;
}

function payment_methods_all(): array {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $items = [];
    $res = $mysqli->query('SELECT * FROM payment_methods ORDER BY activo DESC, nombre ASC, id DESC');
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['activo'] = (int) $row['activo'];
            $items[] = $row;
        }
    }

    return $items;
}

function payment_methods_find(int $id): ?array {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('SELECT * FROM payment_methods WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $item = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$item) {
        return null;
    }

    $item['id'] = (int) $item['id'];
    $item['activo'] = (int) $item['activo'];
    return $item;
}

function payment_methods_validate_form(array $input): array {
    $nombre = trim((string) ($input['nombre_metodo_pago'] ?? ''));
    $datos = trim((string) ($input['datos_metodo_pago'] ?? ''));
    $activo = isset($input['activo_metodo_pago']) ? 1 : 0;
    $errors = [];

    if ($nombre === '') {
        $errors[] = 'El nombre del método de pago es obligatorio.';
    }
    if ($datos === '') {
        $errors[] = 'Los datos del método de pago son obligatorios.';
    }

    return [
        'is_valid' => empty($errors),
        'errors' => $errors,
        'data' => [
            'nombre' => $nombre,
            'datos' => $datos,
            'activo' => $activo,
        ],
    ];
}

function payment_methods_save(array $data, ?int $id = null): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    if ($id === null) {
        $stmt = $mysqli->prepare('INSERT INTO payment_methods (nombre, datos, activo) VALUES (?, ?, ?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssi', $data['nombre'], $data['datos'], $data['activo']);
    } else {
        $stmt = $mysqli->prepare('UPDATE payment_methods SET nombre = ?, datos = ?, activo = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssii', $data['nombre'], $data['datos'], $data['activo'], $id);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function payment_methods_delete(int $id): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('DELETE FROM payment_methods WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function payment_methods_toggle(int $id): bool {
    payment_methods_ensure_table();

    $mysqli = payment_methods_db();
    $stmt = $mysqli->prepare('UPDATE payment_methods SET activo = NOT activo WHERE id = ?');
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}