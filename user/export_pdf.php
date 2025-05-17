<?php
// Export user requests to PDF using mPDF

// Show errors for debugging
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

// 1. Install mPDF via Composer if not already:
//    composer require mpdf/mpdf

// 2. Autoload and bootstrap
require_once __DIR__ . '/../vendor/autoload.php';
// Ensure mPDF is installed
if (!class_exists('Mpdf\Mpdf')) {
    die('Ошибка: библиотека mPDF не найдена. Выполните в корне проекта: composer require mpdf/mpdf');
}

// Use project tmp directory for mPDF (create manually with write permissions)
$tmpDir = __DIR__ . '/../tmp';
if (!is_dir($tmpDir)) {
    die('Ошибка: создайте папку "tmp" в каталоге проекта и установите права на запись.');
}
if (!is_writable($tmpDir)) {
    die('Ошибка: папка "tmp" в проекте должна быть доступна для записи (chmod 0777 tmp).');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

use Mpdf\Mpdf;

// 3. Fetch user requests
$userId = $_SESSION['user']['id'];
$stmt = $pdo->prepare("
    SELECT 
        r.id AS request_id,
        DATE_FORMAT(r.created_at, '%d.%m.%Y %H:%i') AS created_at,
        f.name    AS fabric_name,
        f.price_rub,
        rs.name   AS status_name
    FROM requests r
    JOIN request_items ri ON ri.request_id = r.id
    JOIN fabrics f        ON f.id = ri.fabric_id
    JOIN request_statuses rs ON rs.id = r.status_id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$userId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Build HTML
$html = '<h1 style="text-align:center;">История ваших заявок</h1>';
if (empty($rows)) {
    $html .= '<p style="text-align:center;">У вас нет заявок.</p>';
} else {
    $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="6" style="border-collapse:collapse;font-size:12px;">
        <thead style="background:#f0f0f0;">
            <tr>
                <th>ID</th><th>Дата</th><th>Ткань</th><th>Цена (₽)</th><th>Статус</th>
            </tr>
        </thead>
        <tbody>';
    foreach ($rows as $r) {
        $html .= '<tr>'
            . '<td>' . $r['request_id']   . '</td>'
            . '<td>' . $r['created_at']   . '</td>'
            . '<td>' . htmlspecialchars($r['fabric_name'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . number_format($r['price_rub'], 2, '.', ' ') . '</td>'
            . '<td>' . htmlspecialchars($r['status_name'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
}

// 5. Initialize mPDF with custom tempDir
$mpdf = new Mpdf([
    'mode'    => 'utf-8',
    'format'  => 'A4',
    'tempDir' => $tmpDir
]);

// 6. Write and output PDF
$mpdf->WriteHTML($html);
$mpdf->Output("requests_user_{$userId}.pdf", \Mpdf\Output\Destination::DOWNLOAD);
exit;
