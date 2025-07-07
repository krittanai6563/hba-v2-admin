<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
require 'condb.php';

$data = json_decode(file_get_contents("php://input"), true);
$user_id = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;

if (!$user_id || !$year) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$sql = "
    SELECT 
        CASE
            WHEN cd.total_value <= 2500000 THEN 'ไม่เกิน 2.50 ล้านบาท'
            WHEN cd.total_value BETWEEN 2500001 AND 5000000 THEN '2.51 - 5 ล้านบาท'
            WHEN cd.total_value BETWEEN 5000001 AND 10000000 THEN '5.01 - 10 ล้านบาท'
            WHEN cd.total_value BETWEEN 10000001 AND 20000000 THEN '10.01 - 20 ล้านบาท'
            ELSE '20.01 ล้านขึ้นไป'
        END AS price_range,
        COUNT(*) AS count
    FROM contract_submission cs
    JOIN contract_detail cd ON cs.id = cd.contract_submission_id
    WHERE cs.user_id = ? AND cs.buddhist_year = ?
    GROUP BY price_range
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id, $year]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูลให้ง่ายสำหรับ frontend
    $response = [
        'labels' => [],
        'series' => []
    ];

    foreach ($rows as $row) {
        $response['labels'][] = $row['price_range'];
        $response['series'][] = (int)$row['count'];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
