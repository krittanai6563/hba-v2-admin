<?php

// ป้องกัน preflight fail สำหรับ CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(200);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require 'condb.php';

$data = json_decode(file_get_contents("php://input"), true);

$userId = $data['user_id'] ?? null;
$role = $data['role'] ?? 'user';
$year = $data['year'] ?? null;

if (!$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing year']);
    exit;
}

$sql = '';
$params = [];

if ($role === 'admin' || $role === 'master') {
    // ✅ Admin ดูได้ทุก user
    $sql = "
        SELECT 
        s.quarter,
        d.price_range,
        SUM(d.unit) AS unit,
        SUM(d.total_value) AS value,
        SUM(d.area) AS area,
        SUM(d.price_per_sqm) AS total_price_per_sqm 
    FROM contract_submission s
    JOIN contract_detail d ON s.id = d.contract_submission_id
    WHERE s.buddhist_year = ?
    GROUP BY s.quarter, d.price_range
    ORDER BY s.quarter, d.price_range;
    ";
    $params = [$year];
} else {
    // ✅ เฉพาะ user ตัวเอง
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id']);
        exit;
    }

    $sql = "
        SELECT s.quarter, d.price_range, 
               SUM(d.unit) as unit, 
               SUM(d.total_value) as value, 
               SUM(d.area) as area
        FROM contract_submission s
        JOIN contract_detail d ON s.id = d.contract_submission_id
        WHERE s.user_id = ? AND s.buddhist_year = ?
        GROUP BY s.quarter, d.price_range
    ";
    $params = [$userId, $year];
}

// ✅ ดึงข้อมูล
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ แปลงข้อมูลเป็นโครงสร้าง JSON พร้อมคำนวณราคาเฉลี่ย/ตร.ม.
$data = [];

foreach ($results as $row) {
    $q = 'Q' . $row['quarter'];
    $range = $row['price_range'];

    $unit = (int)$row['unit'];
    $value = (int)$row['value'];
    $area = (int)$row['area'];

    if (!isset($data[$q])) $data[$q] = [];
    if (!isset($data[$q][$range])) {
        $data[$q][$range] = [
            'unit' => 0,
            'value' => 0,
            'area' => 0,
            'price_per_sqm' => 0
        ];
    }

    $data[$q][$range]['unit'] += $unit;
    $data[$q][$range]['value'] += $value;
    $data[$q][$range]['area'] += $area;

    $totalValue = $data[$q][$range]['value'];
    $totalArea = $data[$q][$range]['area'];
    $data[$q][$range]['price_per_sqm'] = $totalArea > 0 ? round($totalValue / $totalArea, 2) : 0;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
