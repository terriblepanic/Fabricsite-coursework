<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';
function h($s) { return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// Определяем, нужно ли открыть фильтр сразу
$filterOpen = false;
foreach (['search','type','color','has_pattern','no_defects','price_min','price_max','len_min','len_max'] as $p) {
    if (!empty($_GET[$p]) && trim((string)$_GET[$p]) !== '' && $_GET[$p] !== '0') {
        $filterOpen = true;
        break;
    }
}

// Значения фильтра
$search     = trim($_GET['search'] ?? '');
$typeId     = (int)($_GET['type'] ?? 0);
$colorId    = (int)($_GET['color'] ?? 0);
$hasPattern = isset($_GET['has_pattern']);
$noDefects  = isset($_GET['no_defects']);

// Пагинация и сортировка
$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;
$sort    = $_GET['sort'] ?? 'date_desc';
$allowedSorts = [
    'date_desc'  => 'f.created_at DESC',
    'date_asc'   => 'f.created_at ASC',
    'price_asc'  => 'f.price_rub ASC',
    'price_desc' => 'f.price_rub DESC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['date_desc'];

// Справочники
$types  = $pdo->query('SELECT id, name FROM fabric_types')->fetchAll(PDO::FETCH_ASSOC);
$colors = $pdo->query('SELECT id, name FROM colors')->fetchAll(PDO::FETCH_ASSOC);

// Построение основного SQL
$sql = "
  SELECT f.*, ft.name AS type_name, c.name AS color_name
  FROM fabrics f
  JOIN fabric_types ft ON f.type_id = ft.id
  JOIN colors c       ON f.color_id  = c.id
  WHERE f.available = 1
";
$params = [];
if ($search !== '')    { $sql .= " AND f.name LIKE :search";  $params['search']   = "%{$search}%"; }
if ($typeId)           { $sql .= " AND f.type_id = :type";    $params['type']     = $typeId; }
if ($colorId)          { $sql .= " AND f.color_id = :color";   $params['color']    = $colorId; }
if ($hasPattern)       { $sql .= " AND f.has_pattern = 1"; }
if ($noDefects)        { $sql .= " AND f.has_defect  = 0"; }
foreach (['price_min'=>'price_rub>=:pmin','price_max'=>'price_rub<=:pmax',
             'len_min'=>'length_m>=:lmin','len_max'=>'length_m<=:lmax'] as $k=>$cond) {
    if (!empty($_GET[$k])) {
        $short = substr($k,0,4);
        $sql   .= " AND {$cond}";
        $params[$short] = $_GET[$k];
    }
}
$sql .= " ORDER BY {$orderBy} LIMIT :offset, :limit";

$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) {
    $type = is_numeric($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue(":{$k}", $v, $type);
}
$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$stmt->bindValue(':limit',$perPage,PDO::PARAM_INT);
$stmt->execute();
$fabrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Считаем общее число для пагинации
$countSql = "
  SELECT COUNT(*) 
  FROM fabrics f
  WHERE f.available = 1
";
if ($search !== '')    { $countSql .= " AND f.name LIKE :search"; }
if ($typeId)           { $countSql .= " AND f.type_id = :type"; }
if ($colorId)          { $countSql .= " AND f.color_id = :color"; }
if ($hasPattern)       { $countSql .= " AND f.has_pattern = 1"; }
if ($noDefects)        { $countSql .= " AND f.has_defect  = 0"; }
foreach (['price_min'=>'price_rub>=:pmin','price_max'=>'price_rub<=:pmax',
             'len_min'=>'length_m>=:lmin','len_max'=>'length_m<=:lmax'] as $k=>$cond) {
    if (!empty($_GET[$k])) {
        $countSql .= " AND {$cond}";
    }
}
$countStmt = $pdo->prepare($countSql);
// биндим те же параметры
foreach ($params as $k=>$v) {
    $type = is_numeric($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $countStmt->bindValue(":{$k}", $v, $type);
}
$countStmt->execute();
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalRows / $perPage);
?>
<h2>Каталог тканей</h2>

<!-- Кнопка-тоггл -->
<div class="filter-toggle-wrapper">
    <button id="filter-toggle" type="button" class="btn btn-primary">
        <?= $filterOpen ? 'Скрыть фильтр' : 'Показать фильтр' ?>
    </button>
</div>

<!-- Панель фильтра -->
<div id="filter-wrapper" class="filter-wrapper<?= $filterOpen ? ' open' : '' ?>">
    <form method="get" class="filter-form">
        <div class="filter-row">
            <div class="filter-field">
                <input type="text" name="search" placeholder="Поиск по названию"
                       class="glass-input" value="<?= h($search) ?>">
            </div>
            <div class="filter-field">
                <select name="type" class="glass-select">
                    <option value="0">Любой тип</option>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id']==$typeId?'selected':''?>>
                            <?= h($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <select name="color" class="glass-select">
                    <option value="0">Любой цвет</option>
                    <?php foreach ($colors as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $c['id']==$colorId?'selected':''?>>
                            <?= h($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="filter-row filter-checkboxes">
            <div class="checkbox-item">
                <input type="checkbox" class="glass-checkbox" id="has_pattern" name="has_pattern" <?= $hasPattern?'checked':''?>>
                <label for="has_pattern">С рисунком</label>
            </div>
            <div class="checkbox-item">
                <input type="checkbox" class="glass-checkbox" id="no_defects" name="no_defects" <?= $noDefects?'checked':''?>>
                <label for="no_defects">Без дефектов</label>
            </div>
        </div>

        <div class="filter-row">
            <div class="filter-field small">
                <label>Цена</label>
                <div class="range">
                    <input type="number" name="price_min" placeholder="от"
                           class="glass-input" value="<?= h($_GET['price_min'] ?? '') ?>">
                    <span>—</span>
                    <input type="number" name="price_max" placeholder="до"
                           class="glass-input" value="<?= h($_GET['price_max'] ?? '') ?>">
                </div>
            </div>
            <div class="filter-field small">
                <label>Длина</label>
                <div class="range">
                    <input type="number" step="0.01" name="len_min" placeholder="от"
                           class="glass-input" value="<?= h($_GET['len_min'] ?? '') ?>">
                    <span>—</span>
                    <input type="number" step="0.01" name="len_max" placeholder="до"
                           class="glass-input" value="<?= h($_GET['len_max'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="filter-row actions">
            <button type="submit" class="btn btn-primary">Найти</button>
            <div class="filter-field small">
                <select name="sort" class="glass-select sort-select">
                    <option value="date_desc"  <?= $sort=='date_desc'?'selected':''?>>Сначала новые</option>
                    <option value="date_asc"   <?= $sort=='date_asc'?'selected':''?>>Сначала старые</option>
                    <option value="price_asc"  <?= $sort=='price_asc'?'selected':''?>>Цена ↑</option>
                    <option value="price_desc" <?= $sort=='price_desc'?'selected':''?>>Цена ↓</option>
                </select>
            </div>
        </div>
    </form>
</div>

<?php if (!$fabrics): ?>
    <p>Ничего не найдено.</p>
<?php else: ?>
    <div class="catalog">
        <?php foreach ($fabrics as $f): ?>
            <div class="fabric-card" data-aos="fade-up">
                <img src="<?= h($f['image_path'])?>" alt="<?=h($f['name'])?>">
                <div class="fabric-card-content">
                    <h3><?=h($f['name'])?></h3>
                    <p><?=h($f['type_name'])?>, <?=h($f['color_name'])?></p>
                    <?php if ($f['has_pattern']): ?><p>С рисунком</p><?php endif;?>
                    <?php if ($f['has_defect']): ?><p style="color:#b00">Дефекты</p><?php endif;?>
                    <p>Цена: <?=h($f['price_rub'])?> ₽</p>
                    <a href="<?= isset($_SESSION['user'])
                        ? "fabric.php?id={$f['id']}"
                        : "auth/login.php" ?>">
                        <?= isset($_SESSION['user']) ? 'Подробнее' : 'Войти для заявки'?>
                    </a>
                </div>
            </div>
        <?php endforeach;?>
    </div>
<?php endif; ?>

<!-- Пагинация -->
<?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php if ($page > 1): ?>
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>">
                &laquo; Prev
            </a>
        <?php endif; ?>
        <?php for ($i=1; $i <= $totalPages; $i++): ?>
            <a class="page-link<?= $i===$page ? ' active' : '' ?>"
               href="?<?= http_build_query(array_merge($_GET, ['page'=>$i])) ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>">
                Next &raquo;
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn  = document.getElementById('filter-toggle');
        const wrap = document.getElementById('filter-wrapper');
        btn.addEventListener('click', () => {
            wrap.classList.toggle('open');
            btn.textContent = wrap.classList.contains('open')
                ? 'Скрыть фильтр'
                : 'Показать фильтр';
        });
    });
</script>