<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/csrf.php';

// Проверка роли администратора
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (empty($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
            require_once __DIR__ . '/header.php';
            echo '<p class="errors">Доступ запрещён</p>';
            require_once __DIR__ . '/footer.php';
            exit;
        }
    }
}

csrf_check();                       // ⬅ проверяем токен для POST-запросов

if (empty($_SESSION['user'])) {
    header('Location: /fabricsite/auth/login.php');
    exit;
}
