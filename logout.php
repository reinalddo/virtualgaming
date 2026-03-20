<?php
require_once __DIR__ . '/includes/tenant.php';
tenant_start_session();
// Destruir la sesión y redirigir al index
session_destroy();
header('Location: ' . app_path('/'));
exit();
