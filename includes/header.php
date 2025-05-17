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

    <!-- Glassmorphic stylesheet -->
    <link rel="stylesheet" href="/fabricsite/assets/css/styles.css">
    <!-- AOS animations -->
    <link rel="stylesheet" href="/fabricsite/assets/css/aos.css">

    <!-- JS для валидации форм -->
    <script src="/fabricsite/assets/js/validate.js" defer></script>
    <link rel="icon" href="/fabricsite/assets/img/favicon.ico">
</head>
<body>
<header>
    <div class="header-inner">
        <div class="logo"><a href="/fabricsite/index.php">Чудотворец</a></div>
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