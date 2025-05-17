<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Проверки
    if ($name === '' || $email === '' || $password === '') {
        $errors[] = "Все поля обязательны.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некорректный email.";
    }
    if ($password !== $confirm) {
        $errors[] = "Пароли не совпадают.";
    }

    // Проверка на существующего пользователя
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким email уже зарегистрирован.";
        }
    }

    // Добавление в БД
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $role_id = 2; // 2 = обычный пользователь

        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hash, $role_id]);

        $_SESSION['user'] = [
            'id'    => $pdo->lastInsertId(),
            'name'  => $name,
            'email' => $email,
            'role'  => 'user'
        ];

        header("Location: /fabricsite/index.php");
        exit;
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<h2>Регистрация</h2>

<?php if (!empty($errors)): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" data-validate data-aos="fade-right">
    <?php csrf_field(); ?>
    <label>Имя: <input type="text" name="name" required></label><br>
    <label>Email: <input type="email" name="email" required></label><br>
    <label>Пароль: <input type="password" name="password" required></label><br>
    <label>Подтвердите пароль: <input type="password" name="confirm_password" required></label><br>
    <button type="submit">Зарегистрироваться</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
