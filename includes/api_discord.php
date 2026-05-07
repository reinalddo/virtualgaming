<?php

require_once __DIR__ . '/store_config.php';

function api_discord_commands_path(): string {
    return __DIR__ . '/api_discord_commands.json';
}

function api_discord_load_commands(): array {
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $path = api_discord_commands_path();
    if (!is_file($path)) {
        $cache = [];
        return $cache;
    }

    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        $cache = [];
        return $cache;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        $cache = [];
        return $cache;
    }

    $commands = [];
    foreach ($decoded as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $key = trim((string) ($entry['key'] ?? ''));
        $template = trim((string) ($entry['template'] ?? ''));
        if ($key === '' || $template === '') {
            continue;
        }

        $entry['key'] = $key;
        $entry['kind'] = trim((string) ($entry['kind'] ?? 'topup'));
        $entry['label'] = trim((string) ($entry['label'] ?? $key));
        $entry['sample'] = trim((string) ($entry['sample'] ?? $template));
        $entry['params'] = is_array($entry['params'] ?? null) ? array_values($entry['params']) : [];
        $commands[] = $entry;
    }

    $cache = $commands;
    return $cache;
}

function api_discord_find_command(string $key): ?array {
    $normalizedKey = trim($key);
    foreach (api_discord_load_commands() as $command) {
        if (($command['key'] ?? '') === $normalizedKey) {
            return $command;
        }
    }

    return null;
}

function api_discord_price_commands(): array {
    return array_values(array_filter(api_discord_load_commands(), static function (array $command): bool {
        return ($command['kind'] ?? '') === 'price';
    }));
}

function api_discord_topup_commands(): array {
    return array_values(array_filter(api_discord_load_commands(), static function (array $command): bool {
        return ($command['kind'] ?? '') === 'topup';
    }));
}

function api_discord_sample_command_text(array $command): string {
    $sample = trim((string) ($command['sample'] ?? ''));
    if ($sample !== '') {
        return $sample;
    }

    return trim((string) ($command['template'] ?? ''));
}

function api_discord_command_placeholders(array $command): array {
    $template = trim((string) ($command['template'] ?? ''));
    if ($template === '') {
        return [];
    }

    if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches) !== 1) {
        return [];
    }

    $placeholders = [];
    foreach ($matches[1] as $placeholder) {
        $normalized = strtolower(trim((string) $placeholder));
        if ($normalized !== '') {
            $placeholders[$normalized] = true;
        }
    }

    return array_keys($placeholders);
}

function api_discord_render_command_text(array $command, array $values): array {
    $template = trim((string) ($command['template'] ?? ''));
    if ($template === '') {
        return [
            'ok' => false,
            'command_text' => '',
            'missing_params' => [],
            'message' => 'El comando Discord no tiene un template configurado.',
        ];
    }

    $normalizedValues = [];
    foreach ($values as $key => $value) {
        $normalizedKey = strtolower(trim((string) $key));
        if ($normalizedKey === '') {
            continue;
        }

        $normalizedValues[$normalizedKey] = trim((string) $value);
    }

    $commandText = $template;
    $missingParams = [];
    if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches) === 1) {
        foreach ($matches[1] as $index => $placeholder) {
            $token = (string) ($matches[0][$index] ?? '');
            $normalizedPlaceholder = strtolower(trim((string) $placeholder));
            $replacement = trim((string) ($normalizedValues[$normalizedPlaceholder] ?? ''));
            if ($replacement === '') {
                $missingParams[$normalizedPlaceholder] = true;
                continue;
            }

            $commandText = str_replace($token, $replacement, $commandText);
        }
    }

    $commandText = trim((string) (preg_replace('/\s+/u', ' ', $commandText) ?? $commandText));
    if (!empty($missingParams)) {
        return [
            'ok' => false,
            'command_text' => $commandText,
            'missing_params' => array_keys($missingParams),
            'message' => 'Faltan parámetros para construir el comando Discord: ' . implode(', ', array_keys($missingParams)) . '.',
        ];
    }

    return [
        'ok' => $commandText !== '',
        'command_text' => $commandText,
        'missing_params' => [],
        'message' => $commandText !== '' ? '' : 'No se pudo construir el comando Discord.',
    ];
}

function api_discord_validate_webhook_url(string $url): bool {
    $normalized = trim($url);
    if ($normalized === '') {
        return false;
    }

    return preg_match('~^https://(?:canary\.|ptb\.)?discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9._\-]+$~i', $normalized) === 1;
}

function api_discord_normalize_timeout($value): int {
    $timeout = is_numeric($value) ? (int) $value : 10;
    if ($timeout < 3) {
        return 3;
    }
    if ($timeout > 30) {
        return 30;
    }

    return $timeout;
}

function api_discord_is_ssl_certificate_issue(string $message): bool {
    $normalized = trim($message);
    if ($normalized === '') {
        return false;
    }

    return stripos($normalized, 'SSL certificate problem') !== false
        || stripos($normalized, 'unable to get local issuer certificate') !== false
        || stripos($normalized, 'certificate verify failed') !== false;
}

function api_discord_normalize_username(string $username): string {
    $normalized = trim($username);
    if ($normalized === '') {
        return '';
    }

    if (stripos($normalized, 'discord') !== false) {
        return '';
    }

    return $normalized;
}

function api_discord_normalize_listener_token(string $token): string {
    $normalized = trim($token);
    if ($normalized === '') {
        return '';
    }

    $normalized = preg_replace('/[^A-Za-z0-9_\-]+/', '', $normalized) ?? '';
    if ($normalized === '') {
        return '';
    }

    if (strlen($normalized) < 12) {
        return '';
    }

    return substr($normalized, 0, 80);
}

function api_discord_generate_listener_token(): string {
    try {
        return bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        return substr(sha1(uniqid('api_discord_listener_', true)), 0, 32);
    }
}

function api_discord_config(): array {
    return [
        'enabled' => trim((string) store_config_get('api_discord', '0')) === '1',
        'webhook_url' => trim((string) store_config_get('api_discord_webhook_url', '')),
        'timeout' => api_discord_normalize_timeout(store_config_get('api_discord_timeout', '10')),
        'username' => trim((string) store_config_get('api_discord_username', '')),
        'avatar_url' => trim((string) store_config_get('api_discord_avatar_url', '')),
        'dry_run' => trim((string) store_config_get('api_discord_dry_run', '1')) === '1',
        'probe_command' => trim((string) store_config_get('api_discord_probe_command', 'mobile_legends_price')),
        'listener_token' => api_discord_normalize_listener_token((string) store_config_get('api_discord_listener_token', '')),
    ];
}

function api_discord_webhook_request_url(string $webhookUrl, bool $wait = false): string {
    if (!$wait) {
        return $webhookUrl;
    }

    return $webhookUrl . (strpos($webhookUrl, '?') === false ? '?' : '&') . 'wait=true';
}

function api_discord_extract_message_id(array $response): string {
    $body = trim((string) ($response['body'] ?? ''));
    if ($body === '') {
        return '';
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return '';
    }

    return trim((string) ($decoded['id'] ?? ''));
}

function api_discord_send_webhook_message(string $webhookUrl, string $content, array $options = []): array {
    $payload = [
        'content' => $content,
    ];

    $username = api_discord_normalize_username((string) ($options['username'] ?? ''));
    if ($username !== '') {
        $payload['username'] = $username;
    }

    $avatarUrl = trim((string) ($options['avatar_url'] ?? ''));
    if ($avatarUrl !== '') {
        $payload['avatar_url'] = $avatarUrl;
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($jsonPayload) || $jsonPayload === '') {
        return [
            'ok' => false,
            'status' => 0,
            'body' => '',
            'error' => 'No se pudo serializar el payload de API Discord.',
        ];
    }

    $timeout = api_discord_normalize_timeout($options['timeout'] ?? 10);
    $requestUrl = api_discord_webhook_request_url($webhookUrl, !empty($options['wait']));

    $sendRequest = static function (bool $verifySsl) use ($requestUrl, $jsonPayload, $timeout): array {
        if (function_exists('curl_init')) {
            $ch = curl_init($requestUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);

            $body = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'body' => is_string($body) ? $body : '',
                'error' => $curlError,
                'ssl_verify' => $verifySsl,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json; charset=utf-8\r\n",
                'content' => $jsonPayload,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $verifySsl,
                'verify_peer_name' => $verifySsl,
            ],
        ]);

        $body = @file_get_contents($requestUrl, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $headerLine) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $headerLine, $matches) === 1) {
                $status = (int) ($matches[1] ?? 0);
                break;
            }
        }

        $lastError = error_get_last();
        $streamError = '';
        if ($body === false && is_array($lastError)) {
            $streamError = trim((string) ($lastError['message'] ?? ''));
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($body) ? $body : '',
            'error' => $body === false ? ($streamError !== '' ? $streamError : 'No se pudo enviar la petición HTTP al webhook.') : '',
            'ssl_verify' => $verifySsl,
        ];
    };

    $response = $sendRequest(true);
    if (!$response['ok'] && api_discord_is_ssl_certificate_issue((string) ($response['error'] ?? ''))) {
        $response = $sendRequest(false);
        $response['ssl_fallback_used'] = true;
    }

    return $response;
}

function api_discord_run_probe(?string $commandKey = null): array {
    $config = api_discord_config();
    if ($config['webhook_url'] === '') {
        return [
            'ok' => false,
            'message' => 'Debes guardar primero un webhook de Discord antes de ejecutar la prueba.',
        ];
    }

    if (!api_discord_validate_webhook_url($config['webhook_url'])) {
        return [
            'ok' => false,
            'message' => 'El webhook guardado no tiene un formato válido.',
        ];
    }

    $resolvedKey = trim((string) ($commandKey ?? $config['probe_command']));
    $command = api_discord_find_command($resolvedKey);
    if (!$command || ($command['kind'] ?? '') !== 'price') {
        return [
            'ok' => false,
            'message' => 'La prueba webhook solo permite comandos seguros de precio.',
        ];
    }

    $sample = api_discord_sample_command_text($command);
    if ($sample === '') {
        return [
            'ok' => false,
            'message' => 'El comando de prueba seleccionado no tiene un sample utilizable.',
        ];
    }

    $response = api_discord_send_webhook_message($config['webhook_url'], $sample, [
        'timeout' => $config['timeout'],
        'username' => $config['username'],
        'avatar_url' => $config['avatar_url'],
    ]);

    if ($response['ok']) {
        $fallbackNote = !empty($response['ssl_fallback_used'])
            ? ' Discord aceptó la petición tras reintentar sin validación SSL local; conviene instalar el CA bundle de PHP antes de pasar esto a producción.'
            : '';
        $usernameNote = api_discord_normalize_username((string) ($config['username'] ?? '')) === '' && trim((string) ($config['username'] ?? '')) !== ''
            ? ' El nombre visible configurado se omitió porque Discord no permite publicar webhooks con la palabra Discord en el username.'
            : '';

        return [
            'ok' => true,
            'message' => 'Prueba enviada a Discord con el comando ' . $sample . '. HTTP ' . (int) ($response['status'] ?? 0) . '. Ahora confirma en Discord si Mobentas respondió.' . $fallbackNote . $usernameNote,
        ];
    }

    $errorText = trim((string) ($response['error'] ?? ''));
    $bodyText = trim((string) ($response['body'] ?? ''));
    $detail = $errorText !== '' ? $errorText : $bodyText;
    if ($detail === '') {
        $detail = 'Sin detalle adicional.';
    }

    return [
        'ok' => false,
        'message' => 'La prueba webhook falló con HTTP ' . (int) ($response['status'] ?? 0) . '. ' . $detail,
    ];
}