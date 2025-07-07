<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require 'condb.php';

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$quarter = $data['quarter'] ?? null;
$month = $data['month'] ?? null;
$role = $data['role'] ?? 'user';

if ($role !== 'admin' && (!$user_id || !$year)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

if (!$year) {
    echo json_encode(['error' => 'Missing year']);
    exit;
}


$sql = "
SELECT price_range, 
       SUM(unit) AS unit, 
       SUM(total_value) AS total_value, 
       SUM(area) AS usable_area,
       CASE 
         WHEN SUM(area) > 0 THEN ROUND(SUM(total_value) / SUM(area), 2)
         ELSE 0 
       END AS price_per_sqm
FROM contract_submission cs
JOIN contract_detail cd ON cs.id = cd.contract_submission_id
WHERE cs.buddhist_year = ?";


$params = [$year];

if ($role !== 'admin' && $role !== 'master') {
    if ($user_id) {
        $sql .= " AND cs.user_id = ?";  
        $params[] = $user_id;
    }
}

if ($quarter) {
    $quarterMap = [
        'ไตรมาสที่ 1' => 1,
        'ไตรมาสที่ 2' => 2,
        'ไตรมาสที่ 3' => 3,
        'ไตรมาสที่ 4' => 4
    ];
    $quarterNumber = $quarterMap[$quarter] ?? null;
    if ($quarterNumber !== null) {
        $sql .= " AND cs.quarter = ?";
        $params[] = $quarterNumber;
    }
}


if ($month) {
    $sql .= " AND MONTH(cs.submitted_at) = ?";
    $params[] = $month;
}

$sql .= " GROUP BY price_range";


$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


$result = [
    'unit' => [],
    'total_value' => [],
    'usable_area' => [],
    'price_per_sqm' => [],
];


foreach ($rows as $row) {
    $range = $row['price_range'];
    $result['unit'][$range] = (int)$row['unit'];
    $result['total_value'][$range] = (int)$row['total_value'];
    $result['usable_area'][$range] = (int)$row['usable_area'];
    $result['price_per_sqm'][$range] = (float)$row['price_per_sqm'];
}


echo json_encode($result, JSON_UNESCAPED_UNICODE);
?>
