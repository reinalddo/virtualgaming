<?php
function auth_get_tenant_slug() {
  $tenant = "";
  if (!empty($_POST["tenant"])) {
    $tenant = $_POST["tenant"];
  } elseif (!empty($_GET["tenant"])) {
    $tenant = $_GET["tenant"];
  }
  $tenant = preg_replace("/[^a-zA-Z0-9-_]/", "", (string) $tenant);
  return $tenant !== "" ? $tenant : "default";
}

function auth_users_path($tenantSlug) {
  return __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "tenants" . DIRECTORY_SEPARATOR . $tenantSlug . DIRECTORY_SEPARATOR . "users.json";
}

function auth_load_users($tenantSlug) {
  $path = auth_users_path($tenantSlug);
  if (!file_exists($path)) {
    return [];
  }
  $raw = file_get_contents($path);
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
}

function auth_save_users($tenantSlug, $users) {
  $path = auth_users_path($tenantSlug);
  $dir = dirname($path);
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }
  $encoded = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  file_put_contents($path, $encoded, LOCK_EX);
}

function auth_normalize_email($email) {
  return strtolower(trim((string) $email));
}

function auth_find_user_by_email($users, $email) {
  $email = auth_normalize_email($email);
  foreach ($users as $user) {
    if (!empty($user["email"]) && auth_normalize_email($user["email"]) === $email) {
      return $user;
    }
  }
  return null;
}

function auth_set_flash($type, $message) {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  $_SESSION["auth_flash"] = ["type" => $type, "message" => $message];
}

function auth_redirect_back($fallback = "/") {
  $target = $_SERVER["HTTP_REFERER"] ?? $fallback;
  header("Location: " . $target);
  exit;
}
