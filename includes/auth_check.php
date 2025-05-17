<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/csrf.php';

csrf_check();                       // ⬅ проверяем токен для POST-запросов

if (empty($_SESSION['user'])) {
    header('Location: /fabricsite/auth/login.php');
    exit;
}
