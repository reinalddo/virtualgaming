<?php
// Conexión a la base de datos para todos los módulos
$mysqli = new mysqli('localhost', 'root', '', 'tvirtualgaming');
if ($mysqli->connect_errno) {
    die('Error de conexión: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8mb4');
