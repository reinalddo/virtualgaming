<?php
require_once __DIR__ . "/includes/tenant.php";
require_once __DIR__ . "/includes/db_connect.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: " . app_path('/'));
  exit;
}

$email = strtolower(trim($_POST["email"] ?? ""));
$password = (string) ($_POST["password"] ?? "");

if ($email === "" || $password === "") {
  tenant_start_session();
  $_SESSION["auth_flash"] = ["type" => "error", "message" => "Completa el correo y la contraseña."];
  header("Location: " . app_path('/'));
  exit;
}

$stmt = $mysqli->prepare("SELECT id, username, password, nombre, email, rol FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if ($user === null || empty($user["password"]) || !password_verify($password, $user["password"])) {
  tenant_start_session();
  $_SESSION["auth_flash"] = ["type" => "error", "message" => "Credenciales inválidas."];
  header("Location: " . app_path('/'));
  exit;
}

tenant_start_session();
$_SESSION["auth_user"] = [
  "id" => $user["id"],
  "email" => $user["email"],
  "full_name" => $user["nombre"],
  "username" => $user["username"],
  "rol" => $user["rol"]
];
$_SESSION["auth_flash"] = ["type" => "success", "message" => "Inicio de sesión exitoso."];

if (($user["rol"] ?? "") === "admin") {
  header("Location: " . app_path('/admin/dashboard'));
  exit;
}
header("Location: " . app_path('/'));
exit;
