<?php
require 'condb.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// ✅ รับค่า JSON
$data = json_decode(file_get_contents("php://input"), true);
$role = $data['role'] ?? 'user';
$buddhist_year = $data['buddhist_year'] ?? null;
$month_number = $data['month_number'] ?? null;
$quarter = $data['quarter'] ?? null;

$userId = null;

// ✅ ตรวจ param สำคัญ
if (is_null($buddhist_year) || is_null($month_number) || is_null($quarter)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

if ($role !== 'admin' && $role !== 'master') {
    $userId = $data['user_id'] ?? null;

    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id']);
        exit;
    }
}

// ✅ Debug log
file_put_contents('debug_contract_data.log', json_encode([
    'INPUT' => $data,
    'FINAL_userId' => $userId,
    'FINAL_buddhist_year' => $buddhist_year,
    'FINAL_month_number' => $month_number,
    'FINAL_quarter' => $quarter,
    'session_id' => session_id(),
    'session_user_id' => $_SESSION['user_id'] ?? null
], JSON_PRETTY_PRINT));


$regions = ['กรุงเทพปริมณฑล', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคกลาง', 'ภาคตะวันออก', 'ภาคใต้', 'ภาคตะวันตก'];
$priceRanges = [
    'ไม่เกิน 2.50 ล้านบาท',
    '2.51 - 5 ล้านบาท',
    '5.01 - 10 ล้านบาท',
    '10.01 - 20 ล้านบาท',
    '20.01 ล้านขึ้นไป'
];


if ($role === 'admin' || $role === 'master') {
    $stmt = $conn->prepare("
        SELECT d.region, d.price_range, d.unit, d.total_value, d.area
        FROM contract_submission s
        JOIN contract_detail d ON s.id = d.contract_submission_id
        WHERE s.buddhist_year = ? AND s.month_number = ? AND s.quarter = ?
    ");
    $stmt->execute([$buddhist_year, $month_number, $quarter]);
} else {
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT d.region, d.price_range, d.unit, d.total_value, d.area
        FROM contract_submission s
        JOIN contract_detail d ON s.id = d.contract_submission_id
        WHERE s.user_id = ? AND s.buddhist_year = ? AND s.month_number = ? AND s.quarter = ?
    ");
    $stmt->execute([$userId, $buddhist_year, $month_number, $quarter]);
}


$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ จัดข้อมูลสำหรับ table
$response = [];
foreach ($regions as $region) {
    $response[$region] = [];
    foreach ($priceRanges as $range) {
        $match = array_filter($details, function($d) use ($region, $range) {
            return strtolower(trim($d['region'])) === strtolower(trim($region)) &&
                   strtolower(trim($d['price_range'])) === strtolower(trim($range));
        });

       
        $sumUnit = 0;
        $sumValue = 0;
        $sumArea = 0;

        foreach ($match as $m) {
            $sumUnit += (int)($m['unit'] ?? 0);
            $sumValue += (int)($m['total_value'] ?? 0);
            $sumArea += (int)($m['area'] ?? 0);
        }

        $response[$region][$range] = [
            'unit' => $sumUnit,
            'value' => $sumValue,
            'area' => $sumArea,
            'price_per_sqm' => $sumArea > 0 ? round($sumValue / $sumArea, 2) : 0
        ];
    }
}


echo json_encode($response);
exit;
?>
