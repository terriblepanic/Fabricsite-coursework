<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Только для администратора
requireAdmin();
require_once __DIR__ . '/../includes/header.php';

// Обработка удаления и восстановления
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $pdo->prepare("UPDATE fabrics SET available = 0 WHERE id = ?")->execute([$id]);
    }
    if (isset($_POST['restore_id'])) {
        $id = (int)$_POST['restore_id'];
        $pdo->prepare("UPDATE fabrics SET available = 1 WHERE id = ?")->execute([$id]);
    }
}

// Получаем все ткани
$stmt = $pdo->query("
    SELECT f.*, ft.name AS type_name
    FROM fabrics f
    JOIN fabric_types ft ON f.type_id = ft.id
    ORDER BY f.created_at DESC
");
$fabrics = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>Все ткани (админ)</h2>

<?php if (empty($fabrics)): ?>
    <p>Тканей пока нет.</p>
<?php else: ?>
    <table border="1" cellpadding="8" cellspacing="0">
        <tr>
            <th>Название</th>
            <th>Тип</th>
            <th>Цена</th>
            <th>Наличие</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($fabrics as $f): ?>
            <tr data-aos="fade-up">
                <td><?= htmlspecialchars($f['name']) ?></td>
                <td><?= htmlspecialchars($f['type_name']) ?></td>
                <td><?= htmlspecialchars($f['price_rub']) ?> ₽</td>
                <td><?= $f['available'] ? 'В наличии' : 'Скрыта' ?></td>
                <td>
                    <a href="edit_fabric.php?id=<?= $f['id'] ?>">Редактировать</a>

                    <?php if ($f['available']): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Скрыть ткань?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                            <button type="submit">Скрыть</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Восстановить ткань?')">
                            <?php csrf_field(); ?>
                            <input type="hidden" name="restore_id" value="<?= $f['id'] ?>">
                            <button type="submit">Восстановить</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
