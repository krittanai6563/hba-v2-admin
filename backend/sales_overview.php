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

$fromYear = (int)$year - 3;
$toYear = (int)$year;

$params = [];
$sql = "
    SELECT s.buddhist_year, d.price_range,
           SUM(d.unit) as unit,
           SUM(d.total_value) as value,
           SUM(d.area) as area
    FROM contract_submission s
    INNER JOIN contract_detail d ON s.id = d.contract_submission_id
    WHERE s.buddhist_year BETWEEN ? AND ?
";
$params[] = $fromYear;
$params[] = $toYear;

if ($role !== 'admin' && $role !== 'master') {
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id for non-admin']);
        exit;
    }
    $sql .= " AND s.user_id = ?";
    $params[] = $userId;
}


$sql .= " GROUP BY s.buddhist_year, d.price_range ORDER BY s.buddhist_year ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);


$data = [];
foreach ($results as $row) {
    $year = $row['buddhist_year'];
    $range = $row['price_range'];

    if (!isset($data[$year])) {
        $data[$year] = [];
    }

    if (!isset($data[$year][$range])) {
        $data[$year][$range] = ['unit' => 0, 'value' => 0, 'area' => 0];
    }

    $data[$year][$range]['unit'] += (int)$row['unit'];
    $data[$year][$range]['value'] += (int)$row['value'];
    $data[$year][$range]['area'] += (int)$row['area'];
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);
