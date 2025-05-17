<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();                                   // <<<

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Введите email и пароль.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT users.*, roles.name AS role_name
            FROM users
            JOIN roles ON users.role_id = roles.id
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);            // <<<
            $_SESSION['user'] = [
                'id'   => $user['id'],
                'name' => $user['name'],
                'email'=> $user['email'],
                'role' => $user['role_name'],
            ];
            header('Location: /fabricsite/index.php');
            exit;
        }
        $errors[] = 'Неверный логин или пароль.';
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2>Вход</h2>

<?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" data-validate data-aos="fade-right">
    <?php csrf_field(); ?>                          <!-- токен -->
    <label>Email: <input type="email" name="email" required></label><br>
    <label>Пароль: <input type="password" name="password" required></label><br>
    <button type="submit">Войти</button>
    <p class="small"><a href="/fabricsite/auth/forgot_password.php">Забыли пароль?</a></p>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
