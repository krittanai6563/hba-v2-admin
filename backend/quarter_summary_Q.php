<?php
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
$year = $data['year'] ?? null;
$role = $data['role'] ?? 'user';

if (!$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing year']);
    exit;
}

$endYear = intval($year);
$startYear = $endYear - 4;

$sql = "
    SELECT 
        cs.buddhist_year,
        cd.price_range,
        SUM(cd.unit) AS unit,
        SUM(cd.total_value) AS value,
        SUM(cd.area) AS area
    FROM contract_submission cs
    JOIN contract_detail cd ON cs.id = cd.contract_submission_id
    WHERE cs.buddhist_year BETWEEN :start_year AND :end_year
";

$params = [
    ':start_year' => $startYear,
    ':end_year' => $endYear,
];

if ($role !== 'admin' && $role !=='master') {
    $sql .= " AND cs.user_id = :user_id";
    $params[':user_id'] = $userId;
}

$sql .= "
    GROUP BY cs.buddhist_year, cd.price_range
    ORDER BY cs.buddhist_year ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];

foreach ($rows as $row) {
    $year = $row['buddhist_year'];
    $range = $row['price_range'];

    if (!isset($data[$year])) $data[$year] = [];
    if (!isset($data[$year][$range])) {
        $data[$year][$range] = ['unit' => 0, 'value' => 0, 'area' => 0, 'price_per_sqm' => 0];
    }

    $data[$year][$range]['unit'] += (int)$row['unit'];
    $data[$year][$range]['value'] += (int)$row['value'];
    $data[$year][$range]['area'] += (int)$row['area'];

    // ✅ คำนวณเฉลี่ย/ตร.ม.
    $value = $data[$year][$range]['value'];
    $area = $data[$year][$range]['area'];
    $data[$year][$range]['price_per_sqm'] = $area > 0 ? round($value / $area, 2) : 0;
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>