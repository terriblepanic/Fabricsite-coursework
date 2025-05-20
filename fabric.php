<?php
require_once __DIR__.'/config/db.php';
require_once __DIR__.'/includes/header.php';
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);

/* weight_kg больше нет, поэтому просто не выводим его */
$sql = "SELECT f.*, ft.name AS type_name, c.name AS color_name
        FROM fabrics f
        JOIN fabric_types ft ON f.type_id = ft.id
        JOIN colors       c ON f.color_id = c.id
        WHERE f.id = ? AND f.available = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$fabric = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fabric) {
    echo '<h2>Ткань не найдена</h2>';
    require_once __DIR__.'/includes/footer.php';
    exit;
}
?>

    <div class="fabric-detail">
        <img src="<?=h('/fabricsite/'.$fabric['image_path'])?>" alt="<?=h($fabric['name'])?>" width="300">

        <div class="fabric-info">
            <h2><?=h($fabric['name'])?></h2>
            <p><strong>Тип:</strong> <?=h($fabric['type_name'])?></p>
            <p><strong>Цвет:</strong> <?=h($fabric['color_name'])?></p>

            <?php if ($fabric['has_pattern']): ?>
                <p>С&nbsp;рисунком</p>
            <?php endif; ?>

            <?php if ($fabric['has_defect']): ?>
                <p style="color:#b00">Имеются дефекты</p>
            <?php endif; ?>

            <p><strong>Размер:</strong>
                <?= $fabric['length_m'] ? h($fabric['length_m']).' м' : '' ?>
                <?= $fabric['width_cm'] ? ' × '.h($fabric['width_cm']).' см' : '' ?>
            </p>

            <p><strong>Цена:</strong> <span style="font-size:18px"><?=h($fabric['price_rub'])?> ₽</span></p>

            <?php if ($fabric['description']): ?>
                <p><strong>Описание:</strong><br><?=nl2br(h($fabric['description']))?></p>
            <?php endif; ?>

            <?php if (isset($_SESSION['user'])): ?>
                <a href="request.php?id=<?=$fabric['id']?>" class="btn">Оставить заявку</a>
            <?php else: ?>
                <p>Чтобы оформить заявку, <a href="/fabricsite/auth/login.php">войдите</a>.</p>
            <?php endif; ?>
        </div>
    </div>

<?php require_once __DIR__.'/includes/footer.php'; ?>