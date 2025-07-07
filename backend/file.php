<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'condb.php';  // Database connection file

$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$quarter = $data['quarter'] ?? null;
$role = $data['role'] ?? 'user';  // Default to 'user' if role is not provided

// Validate parameters
if (!$year) {
    echo json_encode(['error' => 'Missing required parameter: buddhist_year']);
    exit;
}

if ($role !== 'admin' && $role !== 'master' && !$user_id) {
    echo json_encode(['error' => 'Missing required parameter: user_id for non-admin']);
    exit;
}

// SQL query to fetch regional data
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

// If role is not 'admin' or 'master', filter by user_id
if ($role !== 'admin' && $role !== 'master') {
    $sql .= " AND cs.user_id = ?";
    $params[] = $user_id;
}

// Handle quarter filter
if (!empty($quarter)) {
    $quarterMap = [
        'ไตรมาส 1' => 1,
        'ไตรมาส 2' => 2,
        'ไตรมาส 3' => 3,
        'ไตรมาส 4' => 4
    ];
    $quarterNum = $quarterMap[$quarter] ?? null;
    if ($quarterNum !== null) {
        $sql .= " AND cs.quarter = ?";
        $params[] = $quarterNum;
    }
}

// Group by region
$sql .= " GROUP BY cd.region";

try {
    // Prepare and execute the SQL query
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the result by region
    $regionResult = [];
    foreach ($rows as $row) {
        $region = $row['region'];
        $regionResult[$region] = [
            'unit' => (int)$row['unit'],
            'total_value' => (int)$row['total_value'],
            'usable_area' => (int)$row['usable_area'],
        ];
    }

    // Fetch the latest month's total value
    $latestSql = "
        SELECT SUM(cd.total_value) AS latest_month_value
        FROM contract_submission cs
        JOIN contract_detail cd ON cs.id = cd.contract_submission_id
        WHERE cs.buddhist_year = ?
    ";

    $latestParams = [$year];
    if ($role !== 'admin' && $role !== 'master') {
        $latestSql .= " AND cs.user_id = ?";
        $latestParams[] = $user_id;
    }

    // Execute the latest query
    $latestStmt = $conn->prepare($latestSql);
    $latestStmt->execute($latestParams);
    $latestRow = $latestStmt->fetch(PDO::FETCH_ASSOC);
    $latestMonthValue = (int)($latestRow['latest_month_value'] ?? 0);

    // Send the response as JSON
    echo json_encode([
        'latest_month_value' => $latestMonthValue,
        'regions' => $regionResult
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}
