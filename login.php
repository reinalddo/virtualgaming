<?php
require_once __DIR__ . "/includes/auth.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  auth_redirect_back("/");
}

$tenantSlug = auth_get_tenant_slug();
$email = auth_normalize_email($_POST["email"] ?? "");
$password = (string) ($_POST["password"] ?? "");

if ($email === "" || $password === "") {
  auth_set_flash("error", "Completa el correo y la contraseña.");
  auth_redirect_back("/");
}

$users = auth_load_users($tenantSlug);
$user = auth_find_user_by_email($users, $email);

if ($user === null || empty($user["password_hash"]) || !password_verify($password, $user["password_hash"])) {
  auth_set_flash("error", "Credenciales inválidas.");
  auth_redirect_back("/");
}


if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$_SESSION["auth_user"] = [
  "id" => $user["id"] ?? "",
  "email" => $user["email"] ?? $email,
  "full_name" => $user["full_name"] ?? "",
  "phone" => $user["phone"] ?? "",
  "tenant" => $tenantSlug,
  "rol" => $user["rol"] ?? "usuario"
];

auth_set_flash("success", "Inicio de sesión exitoso.");

// Si es admin, redirigir al panel admin
if (($_SESSION["auth_user"]["rol"] ?? "") === "admin") {
  header("Location: /admin.php");
  exit;
}

// Redirect back to the page where the modal was opened.
auth_redirect_back("/");
