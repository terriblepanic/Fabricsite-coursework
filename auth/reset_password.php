<?php
require_once __DIR__ . '/../config/db.php';
session_start();
date_default_timezone_set('Europe/Moscow');

$token    = $_GET['token'] ?? '';
$message  = '';
$error    = '';

if (!$token) {
    exit('Неверный запрос.');
}

$now      = date('Y-m-d H:i:s');
$stmt     = $pdo->prepare("
    SELECT * FROM password_resets 
    WHERE token = ? AND expires_at > ?
");
$stmt->execute([$token, $now]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    exit('Ссылка недействительна или истекла.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    if (strlen($pass) < 6) {
        $error = 'Пароль должен быть не менее 6 символов.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
            ->execute([$hash, $resetRow['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE token = ?")
            ->execute([$token]);
        $message = 'Пароль успешно обновлён. <a href="/fabricsite/auth/login.php">Войти</a>';
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

    <section class="glass-container aos-init" data-aos="fade-up">
        <h2>Новый пароль</h2>

        <?php if ($message): ?>
            <div class="alert success"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <label>
                <span>Новый пароль</span>
                <input type="password" name="password" required placeholder="••••••••">
            </label>
            <button type="submit">Обновить пароль</button>
        </form>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>