<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Только админ
if ($_SESSION['user']['role'] !== 'admin') {
    die('Доступ запрещён');
}

// Получаем все заявки
$stmt = $pdo->query("
    SELECT r.id AS request_id, r.created_at, u.name AS user_name, u.email,
           rs.name AS status_name,
           f.name AS fabric_name, ri.quantity, f.price_rub
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN request_statuses rs ON r.status_id = rs.id
    JOIN request_items ri ON r.id = ri.request_id
    JOIN fabrics f ON ri.fabric_id = f.id
    ORDER BY r.created_at DESC
");

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Создаём Excel-документ
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Заявки');

// Заголовки
$sheet->fromArray([
    'ID заявки', 'Дата', 'Пользователь', 'Email',
    'Ткань', 'Количество', 'Цена за шт.', 'Сумма', 'Статус'
], NULL, 'A1');

// Данные
$row = 2;
foreach ($requests as $r) {
    $sheet->setCellValue("A$row", $r['request_id']);
    $sheet->setCellValue("B$row", $r['created_at']);
    $sheet->setCellValue("C$row", $r['user_name']);
    $sheet->setCellValue("D$row", $r['email']);
    $sheet->setCellValue("E$row", $r['fabric_name']);
    $sheet->setCellValue("F$row", $r['quantity']);
    $sheet->setCellValue("G$row", $r['price_rub']);
    $sheet->setCellValue("H$row", $r['quantity'] * $r['price_rub']);
    $sheet->setCellValue("I$row", $r['status_name']);
    $row++;
}

// Скачивание
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="requests.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
