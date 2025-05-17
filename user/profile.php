<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

function h($s) {
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

$user = $_SESSION['user'];
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    csrf_check();

    // Gather inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if ($name === '' || $email === '') {
        $errors[] = 'Имя и Email обязательны.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный Email.';
    }
    // Check if email changed and unique
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email уже занят другим пользователем.';
        }
    }
    // Password change if provided
    $updatePassword = false;
    if ($password !== '' || $confirm !== '') {
        if ($password !== $confirm) {
            $errors[] = 'Пароли не совпадают.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Пароль должен быть не менее 6 символов.';
        } else {
            $updatePassword = true;
            $hash = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    // If no errors, update
    if (empty($errors)) {
        if ($updatePassword) {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?');
            $stmt->execute([$name, $email, $hash, $user['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
            $stmt->execute([$name, $email, $user['id']]);
        }
        // Update session
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $success = true;
    }
}
?>

<h2>Редактирование профиля</h2>

<?php if ($success): ?>
    <p class="success">Профиль обновлён.</p>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="errors"><ul>
            <?php foreach ($errors as $e): ?>
                <li><?= h($e) ?></li>
            <?php endforeach; ?>
        </ul></div>
<?php endif; ?>

<form method="post" data-validate>
    <?php csrf_field(); ?>
    <label>Имя:<br>
        <input type="text" name="name" value="<?= h($_SESSION['user']['name']) ?>" required>
    </label><br>

    <label>Email:<br>
        <input type="email" name="email" value="<?= h($_SESSION['user']['email']) ?>" required>
    </label><br>

    <p>Оставьте поля ниже пустыми, если не хотите менять пароль</p>

    <label>Новый пароль:<br>
        <input type="password" name="password">
    </label><br>

    <label>Подтвердите пароль:<br>
        <input type="password" name="confirm_password">
    </label><br>

    <button type="submit">Сохранить изменения</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
