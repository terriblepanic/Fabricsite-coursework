<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/email_helper.php';
session_start();
date_default_timezone_set('Europe/Moscow');

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $stmt  = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) {
        $error = 'Пользователь с таким email не найден.';
    } else {
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
            ->execute([$email]);

        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)")
            ->execute([$email, $token, $expires_at]);

        $link    = "http://localhost/fabricsite/auth/reset_password.php?token=$token";
        try {
            sendEmail($email, 'Чудотворец: Сброс пароля',
                "Перейдите по ссылке для сброса пароля:\n\n$link\n\nСсылка действует 1 час.");
            $message = 'Ссылка для сброса отправлена на вашу почту.';
        } catch (Exception $e) {
            $error = 'Ошибка отправки письма: ' . $e->getMessage();
        }
    }
}
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

    <section class="glass-container aos-init" data-aos="fade-up">
        <h2>Восстановление пароля</h2>

        <?php if ($message): ?>
            <div class="alert success"><?= htmlspecialchars($message) ?></div>
        <?php elseif ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <label>
                <span>Email</span>
                <input type="email" name="email" required placeholder="ваш@почта.ру">
            </label>
            <button type="submit">Отправить ссылку</button>
        </form>
    </section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>