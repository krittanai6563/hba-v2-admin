<?php

// Handle preflight request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    http_response_code(200);
    exit;
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

require 'condb.php'; 

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;
$role = $data['role'] ?? 'user';
$year = $data['buddhist_year'] ?? null;
$quarter = $data['quarter'] ?? null;

// Check if year is missing
if (!$year) {
    echo json_encode(['error' => 'Missing buddhist_year']);
    exit;
}

// Ensure user_id is provided for non-admin or non-master roles
if (($role !== 'admin' && $role !== 'master') && !$user_id) {
    echo json_encode(['error' => 'Missing user_id']);
    exit;
}

// Build the SQL query
$sql = "
  SELECT cd.region,
         SUM(cd.unit) AS unit,
         SUM(cd.total_value) AS total_value,
         SUM(cd.area) AS usable_area
  FROM contract_submission cs
  JOIN contract_detail cd ON cs.id = cd.contract_submission_id
  WHERE cs.buddhist_year = ?
";
$params = [$year];

// Filter by user_id if role is not admin or master
if ($role !== 'admin' && $role !== 'master') {
    $sql .= " AND cs.user_id = ?";
    $params[] = $user_id;
}

// Filter by quarter if provided
if (!empty($quarter)) {
    $quarterMap = [
        'ไตรมาสที่ 1' => 1,
        'ไตรมาสที่ 2' => 2,
        'ไตรมาสที่ 3' => 3,
        'ไตรมาสที่ 4' => 4
    ];
    $quarterNum = $quarterMap[$quarter] ?? null;
    if ($quarterNum !== null) {
        $sql .= " AND cs.quarter = ?";
        $params[] = $quarterNum;
    }
}

// Filter by month if provided
$month = $data['month'] ?? null;
if ($month) {
    $sql .= " AND MONTH(cs.submitted_at) = ?"; 
    $params[] = $month;
}

$sql .= " GROUP BY cd.region";

try {
    // Prepare and execute SQL query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $region = $row['region'];
        $unit = (int)$row['unit'];
        $value = (int)$row['total_value'];
        $area = (int)$row['usable_area'];
        
        $price_per_sqm = $area > 0 ? round($value / $area, 2) : 0;

        $result[$region] = [
            'unit' => $unit,
            'total_value' => $value,
            'usable_area' => $area,
            'price_per_sqm' => $price_per_sqm
        ];
    }

    // Return the result as JSON
    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
