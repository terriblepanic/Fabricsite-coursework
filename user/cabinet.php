<?php
// show errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// includes
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

// helper
function h($s) {
    return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
}

// User profile handling
$user = $_SESSION['user'];
$profileErrors = [];
$profileSuccess = false;

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    csrf_check();

    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validation
    if ($name === '' || $email === '') {
        $profileErrors[] = 'Имя и Email обязательны.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileErrors[] = 'Некорректный Email.';
    }
    
    // Check email uniqueness only if changed
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $user['id']]);
        if ($stmt->fetch()) {
            $profileErrors[] = 'Email уже занят другим пользователем.';
        }
    }

    // Password validation if provided
    $updatePassword = false;
    if ($password !== '' || $confirm !== '') {
        if ($password !== $confirm) {
            $profileErrors[] = 'Пароли не совпадают.';
        } elseif (strlen($password) < 6) {
            $profileErrors[] = 'Пароль должен быть не менее 6 символов.';
        } else {
            $updatePassword = true;
            $hash = password_hash($password, PASSWORD_DEFAULT);
        }
    }

    // Update profile if no errors
    if (empty($profileErrors)) {
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
        
        $profileSuccess = true;
    }
}

// Filters & pagination
$q = trim($_GET['q'] ?? '');
$st = (int)($_GET['status'] ?? 0);
$perPage = 5;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Fetch statuses for filter
$statusList = $pdo
    ->query("SELECT id, name FROM request_statuses ORDER BY id")
    ->fetchAll(PDO::FETCH_ASSOC);

// Build WHERE and params
$where  = ["r.user_id = ?"];
$params = [ $_SESSION['user']['id'] ];

if ($q !== '') {
    $where[]  = "f.name LIKE ?";
    $params[] = "%{$q}%";
}
if ($st) {
    $where[]  = "r.status_id = ?";
    $params[] = $st;
}

$whereSql = implode(' AND ', $where);

// Count total
$cntStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM requests r
    JOIN request_items ri ON ri.request_id = r.id
    JOIN fabrics f       ON ri.fabric_id   = f.id
    WHERE {$whereSql}
");
$cntStmt->execute($params);
$total      = (int)$cntStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

// Fetch paginated data
$stmt = $pdo->prepare("
    SELECT 
      r.id           AS request_id,
      r.created_at,
      rs.name        AS status_name,
      r.status_id,
      f.id           AS fabric_id,
      f.name         AS fabric_name,
      f.price_rub
    FROM requests r
    JOIN request_statuses rs ON r.status_id = rs.id
    JOIN request_items ri    ON ri.request_id = r.id
    JOIN fabrics f           ON ri.fabric_id   = f.id
    WHERE {$whereSql}
    ORDER BY r.created_at DESC
    LIMIT ?, ?
");
// bind filter params
foreach ($params as $i => $v) {
    $stmt->bindValue($i+1, $v);
}
// bind offset & limit
$stmt->bindValue(count($params)+1, $offset,   PDO::PARAM_INT);
$stmt->bindValue(count($params)+2, $perPage, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

    <!-- Filter form -->
    <form method="get" class="cabinet-filter">
        <input
                type="text"
                name="q"
                class="glass-input"
                placeholder="Поиск по названию ткани"
                value="<?= h($q) ?>"
        >

        <select name="status" class="glass-select">
            <option value="0">Все статусы</option>
            <?php foreach ($statusList as $s): ?>
                <option
                        value="<?= $s['id'] ?>"
                    <?= $s['id'] === $st ? 'selected' : ''?>
                >
                    <?= h($s['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit" class="btn btn-primary">Фильтровать</button>
    </form>

<?php
// Count notifications: requests ready for pickup (status_id = 4)
$newReady = 0;
foreach ($requests as $r) {
    if ((int)$r['status_id'] === 4) {
        $newReady++;
    }
}
?>
<?php if ($newReady): ?>
    <div class="notification">
        📢 У вас <?= $newReady ?> заявк<?= $newReady===1?'а':'и' ?> готов<?= $newReady===1?'а':'ы' ?> к выдаче!
    </div>
<?php endif; ?>

    <div class="cabinet-filter">
    <h3>Мои заявки</h3>
    <p><a href="export_pdf.php" target="_blank">📄 Скачать PDF</a></p>

<?php if (empty($requests)): ?>
    <p>Заявок не найдено.</p>
<?php else: ?>
    <?php
    // row background colors by status_id
    $rowColors = [
        1 => 'rgba(208,231,255,0.7)',  // Новая
        2 => 'rgba(255,244,208,0.7)',  // В обработке
        3 => 'rgba(255,208,208,0.7)',  // Отклонена
        4 => 'rgba(208,255,208,0.7)'   // Готова к выдаче
    ];
    ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Дата</th>
            <th>Ткань</th>
            <th>Цена</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
            <tr data-aos="fade-up" style="background-color: <?= $rowColors[$r['status_id']] ?? '' ?>;">
                <td><?= h($r['request_id']) ?></td>
                <td><?= h($r['created_at']) ?></td>
                <td><?= h($r['fabric_name']) ?></td>
                <td><?= h($r['price_rub']) ?> ₽</td>
                <td><?= h($r['status_name']) ?></td>
                <td>
                    <a href="/fabricsite/fabric.php?id=<?= $r['fabric_id'] ?>">
                        Подробнее
                    </a>
                    |
                    <a href="/fabricsite/user/chat.php?request_id=<?= $r['request_id'] ?>">
                        Чат с админом
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a
                        href="?<?= http_build_query(['q'=>$q,'status'=>$st,'page'=>$page-1]) ?>"
                        class="page-link"
                >&laquo; Prev</a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a
                        href="?<?= http_build_query(['q'=>$q,'status'=>$st,'page'=>$p]) ?>"
                        class="page-link<?= $p === $page ? ' active' : '' ?>"
                ><?= $p ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a
                        href="?<?= http_build_query(['q'=>$q,'status'=>$st,'page'=>$page+1]) ?>"
                        class="page-link"
                >Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
    </div>

<div class="cabinet-filter">
    <h3>Редактирование профиля</h3>
    
    <?php if ($profileSuccess): ?>
        <p class="success">Профиль успешно обновлен.</p>
    <?php endif; ?>

    <?php if (!empty($profileErrors)): ?>
        <div class="errors">
            <ul>
                <?php foreach ($profileErrors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" class="profile-form">
        <?php csrf_field(); ?>
        <input type="hidden" name="update_profile" value="1">
        
        <div class="form-group">
            <label>Имя:<br>
                <input type="text" name="name" value="<?= h($_SESSION['user']['name']) ?>" required 
                       class="glass-input">
            </label>
        </div>

        <div class="form-group">
            <label>Email:<br>
                <input type="email" name="email" value="<?= h($_SESSION['user']['email']) ?>" required 
                       class="glass-input">
            </label>
        </div>

        <div class="form-group">
            <p class="form-help">Оставьте поля пароля пустыми, если не хотите его менять</p>
            <label>Новый пароль:<br>
                <input type="password" name="password" class="glass-input">
            </label>
        </div>

        <div class="form-group">
            <label>Подтвердите пароль:<br>
                <input type="password" name="confirm_password" class="glass-input">
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>