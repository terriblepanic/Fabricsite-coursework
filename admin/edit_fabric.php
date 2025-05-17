<?php
require_once __DIR__.'/../config/db.php';
require_once __DIR__.'/../includes/auth_check.php';
require_once __DIR__.'/../includes/header.php';

/* доступ только админу */
if ($_SESSION['user']['role'] !== 'admin') {
    echo '<p class="errors">Доступ запрещён</p>';
    require_once __DIR__.'/../includes/footer.php';
    exit;
}

/* получаем ткань */
$fabricId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM fabrics WHERE id = ?');
$stmt->execute([$fabricId]);
$fabric = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fabric) {
    echo '<p class="errors">Ткань не найдена</p>';
    require_once __DIR__.'/../includes/footer.php';
    exit;
}

/* справочники */
$types  = $pdo->query('SELECT id,name FROM fabric_types')->fetchAll(PDO::FETCH_ASSOC);
$colors = $pdo->query('SELECT id,name FROM colors')->fetchAll(PDO::FETCH_ASSOC);

$errors  = [];
$success = false;
$imagePath = $fabric['image_path'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* собираем данные */
    $name   = trim($_POST['name']);
    $type   = (int)$_POST['type_id'];
    $color  = (int)$_POST['color_id'];
    $len    = (float)$_POST['length_m'];
    $wid    = (int)$_POST['width_cm'];
    $price  = (float)$_POST['price_rub'];
    $descr  = trim($_POST['description']);
    $pat    = isset($_POST['has_pattern']) ? 1 : 0;
    $def    = isset($_POST['has_defect'])  ? 1 : 0;
    $avail  = isset($_POST['available'])   ? 1 : 0;

    /* изображение (по желанию) */
    if (!empty($_FILES['image']['name'])) {
        $mime  = mime_content_type($_FILES['image']['tmp_name']);
        $allow = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];

        if (!isset($allow[$mime]))                $errors[] = 'JPG / PNG / WEBP только';
        elseif ($_FILES['image']['size']>2*1024*1024) $errors[] = 'Размер > 2 МБ';
        else {
            $dir = __DIR__.'/../assets/img/';
            if (!is_dir($dir)) mkdir($dir,0755,true);

            $fname = uniqid('fabric_', true) . $allow[$mime];
            if (move_uploaded_file($_FILES['image']['tmp_name'], $dir.$fname)) {
                $imagePath = 'assets/img/'.$fname;
            } else {
                $errors[] = 'Не удалось сохранить файл';
            }
        }
    }

    /* если ошибок нет — апдейт */
    if (!$errors) {
        $sql = 'UPDATE fabrics SET
                  name = ?, type_id = ?, color_id = ?,
                  length_m = ?, width_cm = ?, price_rub = ?,
                  image_path = ?, description = ?,
                  has_pattern = ?, has_defect = ?, available = ?
                WHERE id = ?';
        $pdo->prepare($sql)->execute([
            $name, $type, $color,
            $len, $wid, $price,
            $imagePath, $descr,
            $pat, $def, $avail,
            $fabricId
        ]);

        /* обновляем локальный массив для повторного показа формы */
        $fabric = array_merge($fabric, [
            'name'=>$name, 'type_id'=>$type, 'color_id'=>$color,
            'length_m'=>$len, 'width_cm'=>$wid, 'price_rub'=>$price,
            'image_path'=>$imagePath, 'description'=>$descr,
            'has_pattern'=>$pat, 'has_defect'=>$def, 'available'=>$avail
        ]);

        $success = true;
    }
}

/* helper */
function h($s){ return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>

    <h2>Редактировать ткань</h2>

<?php if ($success): ?>
    <p class="success">Изменения сохранены.</p>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="errors"><ul>
            <?php foreach ($errors as $e) echo '<li>'.h($e).'</li>'; ?>
        </ul></div>
<?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="glass-panel centered">
        <?php csrf_field(); ?>

        <label>Название
            <input type="text" name="name" class="glass-input" value="<?=h($fabric['name'])?>" required>
        </label>

        <label>Тип
            <select name="type_id" class="glass-select" required>
                <?php foreach ($types as $t): ?>
                    <option value="<?=$t['id']?>" <?=$t['id']==$fabric['type_id']?'selected':''?>>
                        <?=h($t['name'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Цвет
            <select name="color_id" class="glass-select" required>
                <?php foreach ($colors as $c): ?>
                    <option value="<?=$c['id']?>" <?=$c['id']==$fabric['color_id']?'selected':''?>>
                        <?=h($c['name'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="checkbox-line">
            <label class="checkbox-item">
                <input type="checkbox" class="glass-checkbox" name="has_pattern"
                    <?=$fabric['has_pattern']?'checked':''?>> С&nbsp;рисунком
            </label>
            <label class="checkbox-item">
                <input type="checkbox" class="glass-checkbox" name="has_defect"
                    <?=$fabric['has_defect']?'checked':''?>> Есть&nbsp;дефекты
            </label>
            <label class="checkbox-item">
                <input type="checkbox" class="glass-checkbox" name="available" value="1"
                    <?=$fabric['available']?'checked':''?>> В&nbsp;наличии
            </label>
        </div>

        <label>Длина (м)
            <input type="number" step="0.01" name="length_m" class="glass-input"
                   value="<?=h($fabric['length_m'])?>">
        </label>

        <label>Ширина (см)
            <input type="number" name="width_cm" class="glass-input"
                   value="<?=h($fabric['width_cm'])?>">
        </label>

        <label>Цена (₽)
            <input type="number" step="0.01" name="price_rub" class="glass-input"
                   value="<?=h($fabric['price_rub'])?>" required>
        </label>

        <label>Описание
            <textarea name="description" rows="4" class="glass-input"
                      style="resize:vertical"><?=h($fabric['description'])?></textarea>
        </label>

        <label>Фото (заменить)
            <input type="file" name="image" accept="image/*" class="glass-input" style="padding:.45rem">
        </label>

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>

<?php require_once __DIR__.'/../includes/footer.php'; ?>