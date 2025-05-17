<?php
$host = "localhost";
$dbname = "fabric_catalog";
$username = "root";
$password = ""; // Убедись, что пароль действительно "root" в XAMPP на Mac

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>
