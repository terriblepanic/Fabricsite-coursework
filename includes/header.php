<?php
if (!isset($_SESSION)) session_start();
require_once __DIR__ . '/csrf.php';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Магазин тканей «Чудотворец»</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">

    <!-- Glassmorphic stylesheets (split) -->
    <link rel="stylesheet" href="/fabricsite/assets/css/00_variables.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/01_base.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/02_layout.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/03_typography.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/04_components.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/05_forms.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/06_cards.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/07_tables.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/08_alerts.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/09_chat.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/10_utilities.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/11_responsive.css">
    <link rel="stylesheet" href="/fabricsite/assets/css/12_animations.css">
    <!-- AOS animations -->
    <link rel="stylesheet" href="/fabricsite/assets/css/aos.css">
    <script src="/fabricsite/assets/js/scroll.js" defer></script>

    <!-- JS для валидации форм -->
    <script src="/fabricsite/assets/js/validate.js" defer></script>
    <link rel="icon" href="/fabricsite/assets/img/favicon.ico">
</head>
<body>
<div class="background"></div>
<header>
    <div class="header-inner">
        <div><a class="logo" href="/fabricsite/index.php">Чудотворец</a></div>
        <nav>
            <a href="/fabricsite/index.php">Каталог</a>
            <?php if (!empty($_SESSION['user'])): ?>
                <a href="/fabricsite/user/cabinet.php">Кабинет</a>
                <?php if ($_SESSION['user']['role']==='admin'): ?>
                    <a href="/fabricsite/admin/dashboard.php">Админка</a>
                    <a href="/fabricsite/admin/add_fabric.php">Добавить ткань</a>
                    <a href="/fabricsite/admin/all_fabrics.php">Ткани</a>
                <?php endif; ?>
                <a href="/fabricsite/auth/logout.php">Выход</a>
            <?php else: ?>
                <a href="/fabricsite/auth/login.php">Вход</a>
                <a href="/fabricsite/auth/register.php">Регистрация</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main>
    