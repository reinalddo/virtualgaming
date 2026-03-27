<?php
require_once __DIR__ . "/includes/tenant.php";
require_once __DIR__ . "/includes/db_connect.php";

$openLoginModalWithError = static function (string $message, string $emailValue = ''): void {
  tenant_start_session();
  $_SESSION["auth_modal_state"] = [
    "mode" => "login",
    "message" => $message,
    "email" => $emailValue,
  ];

  header("Location: " . app_path('/'));
  exit;
};

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: " . app_path('/'));
  exit;
}

$email = strtolower(trim($_POST["email"] ?? ""));
$password = (string) ($_POST["password"] ?? "");

if ($email === "" || $password === "") {
  $openLoginModalWithError("Completa el correo y la contraseña.", $email);
}

$stmt = $mysqli->prepare("SELECT id, username, password, nombre, email, telefono, rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($user === null || empty($user["password"]) || !password_verify($password, $user["password"])) {
  $openLoginModalWithError("Credenciales inválidas.", $email);
}

tenant_start_session();
session_regenerate_id(true);
unset($_SESSION["auth_modal_state"]);
$_SESSION["auth_user"] = [
  "id" => $user["id"],
  "email" => $user["email"],
  "telefono" => $user["telefono"] ?? '',
  "full_name" => $user["nombre"],
  "username" => $user["username"],
  "rol" => $user["rol"]
];
$_SESSION["auth_flash"] = ["type" => "success", "message" => "Inicio de sesión exitoso."];

if (($user["rol"] ?? "") === "admin") {
  header("Location: " . app_path('/admin/dashboard'));
  exit;
}
if (($user["rol"] ?? "") === "empleado") {
  header("Location: " . app_path('/admin/dashboard'));
  exit;
}
if (($user["rol"] ?? "") === "influencer") {
  header("Location: " . app_path('/admin/cupones') . '?tab=influencers');
  exit;
}
header("Location: " . app_path('/'));
exit;
