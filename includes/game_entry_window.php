<?php

require_once __DIR__ . '/store_config.php';

if (!function_exists('game_entry_window_feature_default')) {
    function game_entry_window_feature_default(): string {
        return '0';
    }
}

if (!function_exists('game_entry_window_defaults')) {
    function game_entry_window_defaults(): array {
        return [
            'title' => 'ANTES DE CONTINUAR',
            'icon' => game_entry_window_default_icon_path(),
            'copy' => 'Lee la información antes de continuar con la recarga.',
            'check_text' => 'He leído y entiendo las condiciones del servicio',
            'button_text' => 'Aceptar y continuar',
            'modal_background' => '#18101e',
            'title_color' => '#f8b53d',
            'check_text_color' => '#e2e8f0',
            'check_background_color' => '#1e293b',
            'button_text_color' => '#0b0f18',
            'button_background_color' => '#c99712',
            'button_disabled_text_color' => '#0b0f18',
            'button_disabled_background_color' => '#c99712',
        ];
    }
}

if (!function_exists('game_entry_window_default_icon_path')) {
    function game_entry_window_default_icon_path(): string {
        return function_exists('app_path') ? app_path('/assets/img/game-entry-window-default-icon.svg') : '/assets/img/game-entry-window-default-icon.svg';
    }
}

if (!function_exists('game_entry_window_resolve_icon_path')) {
    function game_entry_window_resolve_icon_path(?string $path = null): string {
        $candidate = trim((string) $path);
        if ($candidate !== '') {
            return $candidate;
        }

        return game_entry_window_default_icon_path();
    }
}

if (!function_exists('game_entry_window_config_descriptions')) {
    function game_entry_window_config_descriptions(): array {
        return [
            'ventana_inicio_juego' => 'Activa o desactiva la ventana global que se muestra al entrar a cualquier juego.',
            'ventana_inicio_juego_titulo' => 'Título principal de la ventana global que se muestra al entrar a un juego.',
            'ventana_inicio_juego_icono' => 'Ruta del icono global de la ventana inicial en juegos.',
            'ventana_inicio_juego_descripcion' => 'Texto descriptivo debajo del título principal de la ventana inicial en juegos.',
            'ventana_inicio_juego_check_texto' => 'Texto que aparece junto al check obligatorio para continuar en la ventana inicial en juegos.',
            'ventana_inicio_juego_boton_texto' => 'Texto del botón principal para cerrar la ventana inicial en juegos.',
            'ventana_inicio_juego_modal_background' => 'Color de fondo del modal global que se muestra al entrar a un juego.',
            'ventana_inicio_juego_title_color' => 'Color del título principal de la ventana inicial en juegos.',
            'ventana_inicio_juego_check_text_color' => 'Color del texto del bloque de confirmación de la ventana inicial en juegos.',
            'ventana_inicio_juego_check_background_color' => 'Color de fondo del bloque de confirmación de la ventana inicial en juegos.',
            'ventana_inicio_juego_button_text_color' => 'Color del texto del botón principal de la ventana inicial en juegos.',
            'ventana_inicio_juego_button_background_color' => 'Color de fondo del botón principal de la ventana inicial en juegos.',
            'ventana_inicio_juego_button_disabled_text_color' => 'Color del texto del botón principal inactivo de la ventana inicial en juegos.',
            'ventana_inicio_juego_button_disabled_background_color' => 'Color de fondo del botón principal inactivo de la ventana inicial en juegos.',
        ];
    }
}

if (!function_exists('game_entry_window_ensure_config_defaults')) {
    function game_entry_window_ensure_config_defaults(): void {
        $config = store_config_all();
        $defaults = game_entry_window_defaults();
        $descriptions = game_entry_window_config_descriptions();
        $values = [
            'ventana_inicio_juego' => game_entry_window_feature_default(),
            'ventana_inicio_juego_titulo' => $defaults['title'],
            'ventana_inicio_juego_icono' => $defaults['icon'],
            'ventana_inicio_juego_descripcion' => $defaults['copy'],
            'ventana_inicio_juego_check_texto' => $defaults['check_text'],
            'ventana_inicio_juego_boton_texto' => $defaults['button_text'],
            'ventana_inicio_juego_modal_background' => $defaults['modal_background'],
            'ventana_inicio_juego_title_color' => $defaults['title_color'],
            'ventana_inicio_juego_check_text_color' => $defaults['check_text_color'],
            'ventana_inicio_juego_check_background_color' => $defaults['check_background_color'],
            'ventana_inicio_juego_button_text_color' => $defaults['button_text_color'],
            'ventana_inicio_juego_button_background_color' => $defaults['button_background_color'],
            'ventana_inicio_juego_button_disabled_text_color' => $defaults['button_disabled_text_color'],
            'ventana_inicio_juego_button_disabled_background_color' => $defaults['button_disabled_background_color'],
        ];

        foreach ($values as $key => $value) {
            if (array_key_exists($key, $config)) {
                continue;
            }

            store_config_upsert($key, (string) $value, $descriptions[$key] ?? null);
        }
    }
}

if (!function_exists('game_entry_window_enabled')) {
    function game_entry_window_enabled(): bool {
        game_entry_window_ensure_config_defaults();
        return trim((string) store_config_get('ventana_inicio_juego', game_entry_window_feature_default())) === '1';
    }
}

if (!function_exists('game_entry_window_table_name')) {
    function game_entry_window_table_name(): string {
        return 'ventana_inicio_juego_tarjetas';
    }
}

if (!function_exists('game_entry_window_default_card_template')) {
    function game_entry_window_default_card_template(int $order = 1): array {
        return [
            'id' => 0,
            'activo' => 1,
            'orden' => $order,
            'color' => '#233A73',
            'background_color' => '#121a2f',
            'content_html' => '<p><strong>🧾 ¿Qué es este servicio?</strong></p><p>Esta página ofrece <strong>recargas automáticas</strong> para los juegos disponibles. Todo el proceso se valida desde el sistema.</p>',
        ];
    }
}

if (!function_exists('game_entry_window_ensure_table')) {
    function game_entry_window_ensure_table(mysqli $mysqli): void {
        $table = game_entry_window_table_name();
        $mysqli->query(
            "CREATE TABLE IF NOT EXISTS {$table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_html LONGTEXT NOT NULL,
                color VARCHAR(7) NOT NULL DEFAULT '#233A73',
                background_color VARCHAR(7) NOT NULL DEFAULT '#121a2f',
                activo TINYINT(1) NOT NULL DEFAULT 1,
                orden INT NOT NULL DEFAULT 1,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_activo_orden (activo, orden)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $existing = [];
        $columnResult = $mysqli->query("SHOW COLUMNS FROM {$table}");
        if ($columnResult instanceof mysqli_result) {
            while ($row = $columnResult->fetch_assoc()) {
                $existing[$row['Field']] = true;
            }
        }

        $requiredColumns = [
            'content_html' => "ALTER TABLE {$table} ADD COLUMN content_html LONGTEXT NOT NULL AFTER id",
            'color' => "ALTER TABLE {$table} ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '#233A73' AFTER content_html",
            'background_color' => "ALTER TABLE {$table} ADD COLUMN background_color VARCHAR(7) NOT NULL DEFAULT '#121a2f' AFTER color",
            'activo' => "ALTER TABLE {$table} ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER background_color",
            'orden' => "ALTER TABLE {$table} ADD COLUMN orden INT NOT NULL DEFAULT 1 AFTER activo",
            'creado_en' => "ALTER TABLE {$table} ADD COLUMN creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER orden",
            'actualizado_en' => "ALTER TABLE {$table} ADD COLUMN actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en",
        ];

        foreach ($requiredColumns as $column => $sql) {
            if (!isset($existing[$column])) {
                $mysqli->query($sql);
            }
        }

        $indexResult = $mysqli->query("SHOW INDEX FROM {$table} WHERE Key_name = 'idx_activo_orden'");
        if (!($indexResult instanceof mysqli_result) || $indexResult->num_rows === 0) {
            $mysqli->query("ALTER TABLE {$table} ADD INDEX idx_activo_orden (activo, orden)");
        }

        game_entry_window_seed_default_card($mysqli);
    }
}

if (!function_exists('game_entry_window_seed_default_card')) {
    function game_entry_window_seed_default_card(mysqli $mysqli): void {
        $table = game_entry_window_table_name();
        $result = $mysqli->query("SELECT id FROM {$table} LIMIT 1");
        if ($result instanceof mysqli_result && $result->fetch_assoc()) {
            return;
        }

        $defaultCard = game_entry_window_default_card_template();
        $stmt = $mysqli->prepare("INSERT INTO {$table} (content_html, color, background_color, activo, orden) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            return;
        }

        $contentHtml = (string) $defaultCard['content_html'];
        $color = (string) $defaultCard['color'];
        $backgroundColor = (string) $defaultCard['background_color'];
        $active = (int) $defaultCard['activo'];
        $order = (int) $defaultCard['orden'];
        $stmt->bind_param('sssii', $contentHtml, $color, $backgroundColor, $active, $order);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('game_entry_window_fetch_cards')) {
    function game_entry_window_fetch_cards(mysqli $mysqli, bool $onlyActive = true): array {
        game_entry_window_ensure_table($mysqli);
        $table = game_entry_window_table_name();
        $sql = "SELECT id, content_html, color, background_color, activo, orden FROM {$table}";
        if ($onlyActive) {
            $sql .= ' WHERE activo = 1';
        }
        $sql .= ' ORDER BY orden ASC, id ASC';

        $cards = [];
        $result = $mysqli->query($sql);
        if (!($result instanceof mysqli_result)) {
            return $cards;
        }

        while ($row = $result->fetch_assoc()) {
            $cards[] = [
                'id' => (int) ($row['id'] ?? 0),
                'content_html' => trim((string) ($row['content_html'] ?? '')),
                'color' => store_config_normalize_hex_color((string) ($row['color'] ?? '#233A73'), '#233A73'),
                'background_color' => store_config_normalize_hex_color((string) ($row['background_color'] ?? '#121a2f'), '#121a2f'),
                'activo' => !empty($row['activo']) ? 1 : 0,
                'orden' => max(1, (int) ($row['orden'] ?? 1)),
            ];
        }

        return $cards;
    }
}

if (!function_exists('game_entry_window_public_payload')) {
    function game_entry_window_public_payload(mysqli $mysqli): array {
        game_entry_window_ensure_config_defaults();
        $defaults = game_entry_window_defaults();
        $cards = game_entry_window_fetch_cards($mysqli, true);

        return [
            'enabled' => game_entry_window_enabled() && $cards !== [],
            'title' => trim((string) store_config_get('ventana_inicio_juego_titulo', $defaults['title'])) ?: $defaults['title'],
            'icon' => game_entry_window_resolve_icon_path(store_config_get('ventana_inicio_juego_icono', $defaults['icon'])),
            'copy_text' => trim((string) store_config_get('ventana_inicio_juego_descripcion', $defaults['copy'])) ?: $defaults['copy'],
            'check_text' => trim((string) store_config_get('ventana_inicio_juego_check_texto', $defaults['check_text'])) ?: $defaults['check_text'],
            'button_text' => trim((string) store_config_get('ventana_inicio_juego_boton_texto', $defaults['button_text'])) ?: $defaults['button_text'],
            'modal_background' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_modal_background', $defaults['modal_background']), $defaults['modal_background']),
            'title_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_title_color', $defaults['title_color']), $defaults['title_color']),
            'check_text_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_check_text_color', $defaults['check_text_color']), $defaults['check_text_color']),
            'check_background_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_check_background_color', $defaults['check_background_color']), $defaults['check_background_color']),
            'button_text_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_button_text_color', $defaults['button_text_color']), $defaults['button_text_color']),
            'button_background_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_button_background_color', $defaults['button_background_color']), $defaults['button_background_color']),
            'button_disabled_text_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_button_disabled_text_color', $defaults['button_disabled_text_color']), $defaults['button_disabled_text_color']),
            'button_disabled_background_color' => store_config_normalize_hex_color((string) store_config_get('ventana_inicio_juego_button_disabled_background_color', $defaults['button_disabled_background_color']), $defaults['button_disabled_background_color']),
            'cards' => $cards,
        ];
    }
}

if (!function_exists('game_entry_window_delete_icon')) {
    function game_entry_window_delete_icon(string $relativePath): void {
        if (trim($relativePath) === '' || trim($relativePath) === game_entry_window_default_icon_path()) {
            return;
        }

        store_config_delete_logo_file($relativePath);
    }
}

if (!function_exists('game_entry_window_store_icon_upload')) {
    function game_entry_window_store_icon_upload(array $file): array {
        return store_config_store_named_logo_upload($file, 'game-entry-window-icon');
    }
}

if (!function_exists('game_entry_window_save_from_request')) {
    function game_entry_window_save_from_request(array $post, array $files = []): array {
        $mysqli = store_config_db();
        game_entry_window_ensure_config_defaults();
        game_entry_window_ensure_table($mysqli);

        $defaults = game_entry_window_defaults();
        $enabled = !empty($post['ventana_inicio_juego_activa']) ? '1' : '0';
        $title = trim((string) ($post['ventana_inicio_juego_titulo'] ?? $defaults['title']));
        $copyText = trim((string) ($post['ventana_inicio_juego_descripcion'] ?? $defaults['copy']));
        $checkText = trim((string) ($post['ventana_inicio_juego_check_texto'] ?? $defaults['check_text']));
        $buttonText = trim((string) ($post['ventana_inicio_juego_boton_texto'] ?? $defaults['button_text']));
        $modalBackground = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_modal_background'] ?? $defaults['modal_background']), $defaults['modal_background']);
        $titleColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_title_color'] ?? $defaults['title_color']), $defaults['title_color']);
        $checkTextColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_check_text_color'] ?? $defaults['check_text_color']), $defaults['check_text_color']);
        $checkBackgroundColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_check_background_color'] ?? $defaults['check_background_color']), $defaults['check_background_color']);
        $buttonTextColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_button_text_color'] ?? $defaults['button_text_color']), $defaults['button_text_color']);
        $buttonBackgroundColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_button_background_color'] ?? $defaults['button_background_color']), $defaults['button_background_color']);
        $buttonDisabledTextColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_button_disabled_text_color'] ?? $defaults['button_disabled_text_color']), $defaults['button_disabled_text_color']);
        $buttonDisabledBackgroundColor = store_config_normalize_hex_color((string) ($post['ventana_inicio_juego_button_disabled_background_color'] ?? $defaults['button_disabled_background_color']), $defaults['button_disabled_background_color']);
        $currentIcon = trim((string) store_config_get('ventana_inicio_juego_icono', ''));
        $defaultIconPath = game_entry_window_default_icon_path();

        if (!empty($post['ventana_inicio_juego_icono_default'])) {
            if ($currentIcon !== '' && $currentIcon !== $defaultIconPath) {
                game_entry_window_delete_icon($currentIcon);
            }
            $currentIcon = $defaultIconPath;
        } elseif (!empty($post['ventana_inicio_juego_icono_eliminar']) && $currentIcon !== '') {
            game_entry_window_delete_icon($currentIcon);
            $currentIcon = $defaultIconPath;
        }

        if (isset($files['ventana_inicio_juego_icono']) && is_array($files['ventana_inicio_juego_icono'])) {
            $upload = game_entry_window_store_icon_upload($files['ventana_inicio_juego_icono']);
            if (empty($upload['success'])) {
                return [
                    'success' => false,
                    'message' => (string) ($upload['message'] ?? 'No se pudo cargar el icono de la ventana inicial.'),
                ];
            }
            $uploadedPath = trim((string) ($upload['path'] ?? ''));
            if ($uploadedPath !== '') {
                if ($currentIcon !== '' && $currentIcon !== $uploadedPath && $currentIcon !== $defaultIconPath) {
                    game_entry_window_delete_icon($currentIcon);
                }
                $currentIcon = $uploadedPath;
            }
        }

        $cardsInput = is_array($post['cards'] ?? null) ? array_values($post['cards']) : [];
        $existingCards = game_entry_window_fetch_cards($mysqli, false);
        $existingById = [];
        foreach ($existingCards as $card) {
            $existingById[(int) $card['id']] = $card;
        }

        $table = game_entry_window_table_name();
        $keptIds = [];
        $savedCards = 0;
        foreach ($cardsInput as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cardId = (int) ($row['id'] ?? 0);
            $contentHtml = trim((string) ($row['content_html'] ?? ''));
            $color = store_config_normalize_hex_color((string) ($row['color'] ?? '#233A73'), '#233A73');
            $backgroundColor = store_config_normalize_hex_color((string) ($row['background_color'] ?? '#121a2f'), '#121a2f');
            $active = !empty($row['active']) ? 1 : 0;
            $order = max(1, (int) ($row['order'] ?? ($index + 1)));

            if ($contentHtml === '') {
                continue;
            }

            if ($cardId > 0 && isset($existingById[$cardId])) {
                $stmt = $mysqli->prepare("UPDATE {$table} SET content_html = ?, color = ?, background_color = ?, activo = ?, orden = ? WHERE id = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param('sssiii', $contentHtml, $color, $backgroundColor, $active, $order, $cardId);
                    $stmt->execute();
                    $stmt->close();
                }
                $keptIds[] = $cardId;
            } else {
                $stmt = $mysqli->prepare("INSERT INTO {$table} (content_html, color, background_color, activo, orden) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('sssii', $contentHtml, $color, $backgroundColor, $active, $order);
                    $stmt->execute();
                    $keptIds[] = (int) $mysqli->insert_id;
                    $stmt->close();
                }
            }

            $savedCards++;
        }

        foreach ($existingById as $existingId => $_unusedCard) {
            if (in_array($existingId, $keptIds, true)) {
                continue;
            }

            $stmt = $mysqli->prepare("DELETE FROM {$table} WHERE id = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('i', $existingId);
                $stmt->execute();
                $stmt->close();
            }
        }

        if ($savedCards === 0) {
            game_entry_window_seed_default_card($mysqli);
        }

        $descriptions = game_entry_window_config_descriptions();
        $ok = true;
        $ok = store_config_upsert('ventana_inicio_juego', $enabled, $descriptions['ventana_inicio_juego'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_titulo', $title !== '' ? $title : $defaults['title'], $descriptions['ventana_inicio_juego_titulo'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_icono', $currentIcon, $descriptions['ventana_inicio_juego_icono'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_descripcion', $copyText !== '' ? $copyText : $defaults['copy'], $descriptions['ventana_inicio_juego_descripcion'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_check_texto', $checkText !== '' ? $checkText : $defaults['check_text'], $descriptions['ventana_inicio_juego_check_texto'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_boton_texto', $buttonText !== '' ? $buttonText : $defaults['button_text'], $descriptions['ventana_inicio_juego_boton_texto'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_modal_background', $modalBackground, $descriptions['ventana_inicio_juego_modal_background'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_title_color', $titleColor, $descriptions['ventana_inicio_juego_title_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_check_text_color', $checkTextColor, $descriptions['ventana_inicio_juego_check_text_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_check_background_color', $checkBackgroundColor, $descriptions['ventana_inicio_juego_check_background_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_button_text_color', $buttonTextColor, $descriptions['ventana_inicio_juego_button_text_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_button_background_color', $buttonBackgroundColor, $descriptions['ventana_inicio_juego_button_background_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_button_disabled_text_color', $buttonDisabledTextColor, $descriptions['ventana_inicio_juego_button_disabled_text_color'] ?? null) && $ok;
        $ok = store_config_upsert('ventana_inicio_juego_button_disabled_background_color', $buttonDisabledBackgroundColor, $descriptions['ventana_inicio_juego_button_disabled_background_color'] ?? null) && $ok;

        if (!$ok) {
            return [
                'success' => false,
                'message' => 'No se pudo guardar la ventana inicial en juegos.',
            ];
        }

        return [
            'success' => true,
            'message' => 'La ventana inicial en juegos fue actualizada correctamente.',
        ];
    }
}

if (!function_exists('game_entry_window_hex_to_rgba')) {
    function game_entry_window_hex_to_rgba(string $hex, float $alpha = 1): string {
        $normalized = ltrim(store_config_normalize_hex_color($hex, '#233A73'), '#');
        if (strlen($normalized) !== 6) {
            $normalized = '233A73';
        }

        $red = hexdec(substr($normalized, 0, 2));
        $green = hexdec(substr($normalized, 2, 2));
        $blue = hexdec(substr($normalized, 4, 2));
        $resolvedAlpha = max(0, min(1, $alpha));

        return 'rgba(' . $red . ', ' . $green . ', ' . $blue . ', ' . $resolvedAlpha . ')';
    }
}