<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
/* Проверка роли администратора */
requireAdmin();
require_once __DIR__ . '/../includes/header.php';
function h($s){ return htmlspecialchars($s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// Получаем статусы (id=>name)
$statuses = $pdo->query("SELECT id,name FROM request_statuses")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

// Обработка POST (bulk и одиночные действия)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Bulk action
    if (!empty($_POST['bulk_ids']) && !empty($_POST['bulk_action'])) {
        $ids = array_map('intval', $_POST['bulk_ids']);
        if (preg_match('#^status_(\d+)$#', $_POST['bulk_action'], $m)) {
            $newSt = (int)$m[1];
            $pdo->exec("UPDATE requests SET status_id={$newSt} WHERE id IN(".implode(',', $ids).")");
        } elseif ($_POST['bulk_action'] === 'hide') {
            $pdo->exec("UPDATE fabrics SET available=0 WHERE id IN(".implode(',', $ids).")");
        }
    }
    // Одиночный update статуса
    if (isset($_POST['request_id'], $_POST['status_id'])) {
        $pdo->prepare("UPDATE requests SET status_id=? WHERE id=?")
            ->execute([(int)$_POST['status_id'], (int)$_POST['request_id']]);
    }
    // Скрыть ткань
    if (isset($_POST['delete_fabric_id'])) {
        $pdo->prepare("UPDATE fabrics SET available=0 WHERE id=?")
            ->execute([(int)$_POST['delete_fabric_id']]);
    }

    // Логируем действие
    $actionDesc = '';
    if (!empty($_POST['bulk_ids']) && !empty($_POST['bulk_action'])) {
        $ids = array_map('intval', $_POST['bulk_ids']);
        if (preg_match('#^status_(\d+)$#', $_POST['bulk_action'], $m)) {
            $actionDesc = "Bulk status → {$statuses[$m[1]]} for requests: ".implode(',', $ids);
        } else {
            $actionDesc = "Bulk hide fabrics IDs: ".implode(',', $ids);
        }
    } elseif (isset($_POST['request_id'], $_POST['status_id'])) {
        $rid = (int)$_POST['request_id'];
        $sid = (int)$_POST['status_id'];
        $actionDesc = "Request #{$rid} status → {$statuses[$sid]}";
    } elseif (isset($_POST['delete_fabric_id'])) {
        $fid = (int)$_POST['delete_fabric_id'];
        $actionDesc = "Hide fabric #{$fid}";
    }
    $pdo->prepare("INSERT INTO admin_logs(admin_id, action, context) VALUES (?,?,?)")
        ->execute([ $_SESSION['user']['id'], $actionDesc, json_encode($_POST) ]);

    header('Location: dashboard.php?'.$_SERVER['QUERY_STRING']);
    exit;
}

// Фильтры и пагинация
$q        = trim($_GET['q'] ?? '');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to']   ?? '';
$stFilter = (int)($_GET['status'] ?? 0);

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Формируем WHERE
$where  = ["1=1"];
$params = [];
if ($q !== '') {
    $where[]          = "(u.name LIKE :q OR u.email LIKE :q OR f.name LIKE :q)";
    $params['q']      = "%{$q}%";
}
if ($dateFrom !== '') {
    $where[]          = "r.created_at >= :df";
    $params['df']     = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '') {
    $where[]          = "r.created_at <= :dt";
    $params['dt']     = $dateTo . ' 23:59:59';
}
if ($stFilter) {
    $where[]          = "r.status_id = :st";
    $params['st']     = $stFilter;
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Считаем общее число записей
$countSql = "
    SELECT COUNT(*) 
      FROM requests r
      JOIN users u          ON r.user_id=u.id
      JOIN request_items ri ON ri.request_id=r.id
      JOIN fabrics f        ON ri.fabric_id=f.id
    {$whereSql}
";
$totalRows = $pdo->prepare($countSql);
$totalRows->execute($params);
$totalRows = (int)$totalRows->fetchColumn();

// Вычитываем текущую страницу
$sql = "
    SELECT 
      r.id          AS request_id,
      r.created_at,
      u.name        AS user_name,
      u.email       AS user_email,
      rs.id         AS status_id,
      rs.name       AS status_name,
      f.id          AS fabric_id,
      f.name        AS fabric_name
    FROM requests r
    JOIN users u          ON r.user_id=u.id
    JOIN request_statuses rs ON r.status_id=rs.id
    JOIN request_items ri ON ri.request_id=r.id
    JOIN fabrics f        ON ri.fabric_id=f.id
    {$whereSql}
    ORDER BY r.created_at DESC
    LIMIT :off, :pp
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":{$k}", $v);
}
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->bindValue(':pp',  $perPage, PDO::PARAM_INT);
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Логи админ-действий
$logs = $pdo->query("
    SELECT al.created_at, u.name AS admin_name, al.action, al.context
      FROM admin_logs al
      JOIN users u ON al.admin_id=u.id
     ORDER BY al.created_at DESC
     LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<h2>Административная панель</h2>
<p>
    <a href="export_excel.php" class="btn btn-secondary" target="_blank">
        📥 Экспорт в Excel
    </a>
</p>

<!-- Форма фильтрации -->
<form method="get" class="filter-form admin-filter">
    <div class="filter-row">
        <div class="filter-field">
            <input type="text" name="q" class="glass-input" placeholder="Поиск..." value="<?=h($q)?>">
        </div>
        <div class="filter-field">
            <input type="date" name="date_from" class="glass-input" value="<?=h($dateFrom)?>">
        </div>
        <div class="filter-field">
            <input type="date" name="date_to" class="glass-input" value="<?=h($dateTo)?>">
        </div>
        <div class="filter-field">
            <select name="status" class="glass-select">
                <option value="0">Все статусы</option>
                <?php foreach($statuses as $id=>$name): ?>
                    <option value="<?=$id?>" <?=$id==$stFilter?'selected':''?>>
                        <?=h($name)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-field small">
            <button type="submit" class="btn btn-primary">Фильтровать</button>
        </div>
    </div>
</form>

<!-- Bulk-форма и таблица -->
<form method="post" class="checkbox-item">
    <?php csrf_field(); ?>
    <table>
        <thead>
        <tr>
            <th><input type="checkbox" id="bulk-check-all"></th>
            <th>Дата</th>
            <th>Пользователь</th>
            <th>Email</th>
            <th>Ткань</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach($requests as $r): ?>
            <tr>
                <td>
                    <input type="checkbox" class="glass-checkbox" name="bulk_ids[]" value="<?=$r['request_id']?>">
                </td>
                <td><?=h($r['created_at'])?></td>
                <td><?=h($r['user_name'])?></td>
                <td><?=h($r['user_email'])?></td>
                <td><?=h($r['fabric_name'])?></td>
                <td><?=h($r['status_name'])?></td>
                <td class="actions">
                    <input type="hidden" name="request_id" value="<?=$r['request_id']?>">
                    <select name="status_id" class="glass-select">
                        <?php foreach($statuses as $id=>$name): ?>
                            <option value="<?=$id?>" <?=$id==$r['status_id']?'selected':''?>>
                                <?=h($name)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Обновить</button>
                    <input type="hidden" name="delete_fabric_id" value="<?=$r['fabric_id']?>">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Скрыть ткань?')">Удалить</button>
                    <a href="edit_fabric.php?id=<?=$r['fabric_id']?>" class="btn btn-secondary">Редактировать</a>
                    <a href="chat.php?request_id=<?=$r['request_id']?>" class="btn btn-secondary">Чат</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Bulk actions -->
    <div class="bulk-actions">
        <select name="bulk_action" class="glass-select">
            <option value="">— Действие для выделенных —</option>
            <?php foreach($statuses as $id=>$name): ?>
                <option value="status_<?=$id?>">Сменить статус на «<?=h($name)?>»</option>
            <?php endforeach; ?>
            <option value="hide">Скрыть ткани</option>
        </select>
        <button type="submit" class="btn btn-primary">Применить</button>
    </div>
</form>

<!-- Пагинация -->
<?php $totalPages = ceil($totalRows / $perPage); ?>
<?php if ($totalPages > 1): ?>
    <nav class="pagination">
        <?php for ($p=1; $p<=$totalPages; $p++): ?>
            <a href="?<?=http_build_query(array_merge($_GET, ['page'=>$p]))?>"
               class="page-link<?= $p==$page?' active':'' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>

<!-- Логи админ-действий -->
<h3>Журнал админ-действий (последние 10)</h3>
<table>
    <thead>
    <tr><th>Время</th><th>Админ</th><th>Действие</th><th>Подробности</th></tr>
    </thead>
    <tbody>
    <?php foreach($logs as $lg): ?>
        <tr>
            <td><?=h($lg['created_at'])?></td>
            <td><?=h($lg['admin_name'])?></td>
            <td><?=h($lg['action'])?></td>
            <td>
                <details>
                    <summary>Показать</summary>
                    <ul>
                        <?php
                        $ctx = json_decode($lg['context'], true) ?: [];
                        foreach ($ctx as $k => $v) {
                            if ($k==='csrf') continue;
                            echo '<li><strong>'.h($k).':</strong> ';
                            echo is_array($v) ? h(json_encode($v)) : h($v);
                            echo '</li>';
                        }
                        ?>
                    </ul>
                </details>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
    // «Выделить все» чекбоксов
    document.getElementById('bulk-check-all').addEventListener('change', e => {
        document.querySelectorAll('input[name="bulk_ids[]"]').forEach(ch => ch.checked = e.target.checked);
    });
</script>
