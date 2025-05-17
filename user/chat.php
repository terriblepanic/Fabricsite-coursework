<?php
// user/chat.php
// Simple chat interface between user and admin for a specific request

// 1. Bootstrap and dependencies
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

// Helper for HTML-escaping
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// 2. Validate and fetch request ID
$requestId = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
if ($requestId < 1) {
    echo '<p class="errors">Неверный идентификатор заявки.</p>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// 3. Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check(); // Validate CSRF token

    if (!empty($_POST['message'])) {
        $message = trim($_POST['message']);
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (request_id, user_id, message)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $requestId,
            $_SESSION['user']['id'],
            $message
        ]);
    }
    // Redirect to avoid resubmission
    header('Location: chat.php?request_id=' . $requestId);
    exit;
}

// 4. Load all messages for this request
$stmt = $pdo->prepare("
    SELECT 
        cm.id,
        cm.user_id,
        cm.message,
        cm.created_at,
        u.name AS user_name,
        u.role_id
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    WHERE cm.request_id = ?
    ORDER BY cm.created_at ASC
");
$stmt->execute([$requestId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2 class="mt-2">Чат по заявке #<?= h((string)$requestId) ?></h2>
<div class="chat-box" style="max-width: 800px; margin-bottom: 1.5rem;">
    <?php if (empty($messages)): ?>
        <p>Сообщений ещё нет. Начните диалог:</p>
    <?php endif; ?>

    <?php foreach ($messages as $msg): ?>
        <?php
            $isAdmin = ($msg['role_id'] === 1);
            $author = $isAdmin ? 'Администратор' : h($msg['user_name']);
            $bgColor = $isAdmin ? '#f0f8ff' : '#e8ffe8';
        ?>
        <div style="
            background: <?= $bgColor ?>;
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
        ">
            <div style="font-weight: bold; color: var(--color-primary);">
                <?= $author ?>
                <small style="color: var(--color-muted); font-size: 0.85em;">
                    [<?= h($msg['created_at']) ?>]
                </small>
            </div>
            <p style="margin: 0.5rem 0;"><?= nl2br(h($msg['message'])) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" class="filter-form">
    <?php csrf_field(); ?>
    <textarea name="message" rows="3" required
        style="flex: 1; max-width: 100%; padding:0.75rem; border:1px solid var(--color-border); border-radius:var(--radius);"
        placeholder="Введите сообщение..."></textarea>
    <button type="submit" class="btn btn-primary">Отправить</button>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>