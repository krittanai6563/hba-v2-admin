<?php
require 'condb.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$userId = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$month = $data['month_number'] ?? null;
$quarter = $data['quarter'] ?? null;

if (!$userId || !$year || !$month || !$quarter) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// ตรวจสอบว่ามีข้อมูล submission แล้วหรือไม่
$stmt = $conn->prepare("SELECT id FROM contract_submission WHERE user_id=? AND buddhist_year=? AND month_number=? AND quarter=?");
$stmt->execute([$userId, $year, $month, $quarter]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    echo json_encode(['found' => false]);
    exit;
}

$submissionId = $submission['id'];

$stmt = $conn->prepare("SELECT region, price_range, unit, total_value, area FROM contract_detail WHERE contract_submission_id=?");
$stmt->execute([$submissionId]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มข้อมูล
$data = [];
foreach ($details as $row) {
    $region = $row['region'];
    $price = $row['price_range'];
    if (!isset($data[$region])) $data[$region] = [];
    $data[$region][$price] = [
        'unit' => (int)$row['unit'],
        'value' => (int)$row['total_value'],
        'area' => (int)$row['area'],
    ];
}

echo json_encode(['found' => true, 'details' => $data]);
