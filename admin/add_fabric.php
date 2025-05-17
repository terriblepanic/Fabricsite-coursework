<?php
/* -------------------------------------------------
   add_fabric.php – «Добавить ткань» (обновлённая)
   ------------------------------------------------- */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

/* — мини-хелпер безопасного вывода — */
if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
/* — доступ только для admin — */
requireAdmin();

/* — справочники — */
$fabricTypes = $pdo->query('SELECT id,name FROM fabric_types')->fetchAll(PDO::FETCH_ASSOC);
$colors      = $pdo->query('SELECT id,name FROM colors')->fetchAll(PDO::FETCH_ASSOC);

$errors  = [];
$success = false;

/* — обработка формы — */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // собираем данные
    $name        = trim($_POST['name']        ?? '');
    $type_id     = (int) ($_POST['type_id']   ?? 0);
    $color_id    = (int) ($_POST['color_id']  ?? 0);
    $length      = (float)($_POST['length_m'] ?? 0);
    $width       = (int)  ($_POST['width_cm'] ?? 0);
    $price       = (float)($_POST['price_rub']?? 0);
    $has_pattern = isset($_POST['has_pattern']) ? 1 : 0;
    $has_defect  = isset($_POST['has_defect'])  ? 1 : 0;
    $description = trim($_POST['description'] ?? '');
    $available   = isset($_POST['available'])   ? 1 : 0;                  // вес больше не запрашиваем

    // проверка
    if ($name === '')   $errors[] = 'Название обязательно.';
    if (!$type_id)      $errors[] = 'Выберите тип ткани.';
    if (!$color_id)     $errors[] = 'Выберите цвет.';
    if ($price <= 0)    $errors[] = 'Цена должна быть больше нуля.';

    /* — загрузка изображения (опционально) — */
    $imagePath = '';
    if (!empty($_FILES['image']['name'])) {
        $err = $_FILES['image']['error'];
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = 'Ошибка загрузки файла (код ' . $err . ')';
        } else {
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp'];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Допустимы только JPG, PNG, WEBP.';
            } elseif ($_FILES['image']['size'] > 2*1024*1024) {
                $errors[] = 'Максимальный размер 2 МБ.';
            } else {
                $dir = __DIR__.'/../assets/img/';
                if (!is_dir($dir)) mkdir($dir,0755,true);
                if (!is_writable($dir)) {
                    $errors[] = 'Папка «assets/img» недоступна для записи.';
                } else {
                    $file = 'fabric_' . time() . '_' . bin2hex(random_bytes(4)) . $allowed[$mime];
                    if (move_uploaded_file($_FILES['image']['tmp_name'],$dir.$file)) {
                        $imagePath = 'assets/img/' . $file;
                    } else {
                        $errors[] = 'Не удалось сохранить изображение.';
                    }
                }
            }
        }
    }

    /* — запись в БД — */
    if (!$errors) {
        $pdo->prepare(
            'INSERT INTO fabrics
             (name,type_id,color_id,length_m,width_cm,price_rub,
              has_pattern,has_defect,image_path,description,available,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())'
        )->execute([
            $name,$type_id,$color_id,
            $length,$width,$price,
            $has_pattern,$has_defect,$imagePath,$description,$available
        ]);

        $success = true;
        $_POST   = [];             // очистка формы
    }
}

/* — вывoд — */
require_once __DIR__ . '/../includes/header.php';
?>
    <h2>Добавить новую ткань</h2>

<?php if ($success): ?>
    <div class="success">🎉 Ткань успешно добавлена!</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="errors"><ul>
            <?php foreach ($errors as $e) echo '<li>'.h($e).'</li>'; ?>
        </ul></div>
<?php endif; ?>

    <form method="post" enctype="multipart/form-data"
          class="glass-panel centered glass-form">
        <?php csrf_field(); ?>

        <div class="form-grid">

            <!-- 1. Название (на всю ширину) -->
            <div class="form-group full">
                <label>Название
                    <input type="text" name="name" class="glass-input"
                           value="<?= h($_POST['name'] ?? '') ?>" required>
                </label>
            </div>

            <!-- 2. Тип  +  Цвет  (одна строка) -->
            <div class="form-group">
                <label>Тип
                    <select name="type_id" class="glass-select" required>
                        <option value="">— выберите —</option>
                        <?php foreach ($fabricTypes as $t): ?>
                            <option value="<?= $t['id'] ?>"
                                <?= (!empty($_POST['type_id']) && $_POST['type_id']==$t['id']) ? 'selected' : '' ?>>
                                <?= h($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="form-group">
                <label>Цвет
                    <select name="color_id" class="glass-select" required>
                        <option value="">— выберите —</option>
                        <?php foreach ($colors as $c): ?>
                            <option value="<?= $c['id'] ?>"
                                <?= (!empty($_POST['color_id']) && $_POST['color_id']==$c['id']) ? 'selected' : '' ?>>
                                <?= h($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <!-- 3. Чекбоксы: одна строка на всю ширину -->
            <div class="form-group full checkbox-line">
                <label class="checkbox-item">
                    <input type="checkbox" class="glass-checkbox"
                           name="has_pattern" <?= !empty($_POST['has_pattern'])?'checked':'' ?>> С рисунком
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" class="glass-checkbox"
                           name="has_defect" <?= !empty($_POST['has_defect'])?'checked':'' ?>> Есть дефекты
                </label>
                <label class="checkbox-item">
                    <input type="checkbox" class="glass-checkbox"
                           name="available" value="1" <?= !empty($_POST['available'])?'checked':'' ?>> В наличии
                </label>
            </div>

            <!-- 4. Длина  +  Ширина -->
            <div class="form-group">
                <label>Длина (м)
                    <input type="number" step="0.01" name="length_m"
                           class="glass-input" value="<?= h($_POST['length_m'] ?? '') ?>">
                </label>
            </div>

            <div class="form-group">
                <label>Ширина (см)
                    <input type="number" name="width_cm"
                           class="glass-input" value="<?= h($_POST['width_cm'] ?? '') ?>">
                </label>
            </div>

            <!-- 5. Цена  (отдельная строка) -->
            <div class="form-group">
                <label>Цена (₽)
                    <input type="number" step="0.01" name="price_rub" class="glass-input" required
                           value="<?= h($_POST['price_rub'] ?? '') ?>">
                </label>
            </div>

            <!-- 6. Описание (full) -->
            <div class="form-group full">
                <label>Описание
                    <textarea name="description" rows="4" class="glass-input"
                              style="resize:vertical"><?= h($_POST['description'] ?? '') ?></textarea>
                </label>
            </div>

            <!-- 7. Фото (full) -->
            <div class="form-group full">
                <label>Фото ткани
                    <input type="file" name="image" accept="image/*"
                           class="glass-input" style="padding:.45rem;">
                </label>
            </div>

        </div><!-- /.form-grid -->

        <div class="text-center" style="margin-top:1.5rem">
            <button type="submit" class="btn btn-primary">Добавить ткань</button>
        </div>
    </form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
