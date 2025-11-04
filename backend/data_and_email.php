<?php


error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start(); 

header("Access-Control-Allow-Origin: *");
header("Access-control-allow-headers: content-type");

header("Access-Control-Allow-Methods: POST, OPTIONS"); 
header("Content-Type: application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // ส่งสถานะ 200 OK
    exit(); // สิ้นสุดสคริปต์ทันที
}
// =============================================================

require 'condb.php'; // เรียกใช้การเชื่อมต่อฐานข้อมูล PDO

// รับค่าจาก POST
$data = json_decode(file_get_contents("php://input"), true);

$user_id = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$month_number = $data['month_number'] ?? null;

if (!$user_id || !$year || !$month_number) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    ob_end_flush();
    exit;
}

$sql = "
    SELECT 
        u.id,
        CASE 
            WHEN cs.id IS NULL THEN 'ยังไม่กรอกข้อมูล'
            ELSE 'กรอกข้อมูลเรียบร้อย'
        END AS status,
        COALESCE(SUM(cd.total_value), 0) AS contractValue
    FROM users u
    LEFT JOIN contract_submission cs 
        ON cs.user_id = u.id 
        AND cs.buddhist_year = ? 
        AND cs.month_number = ?
    LEFT JOIN contract_detail cd ON cd.contract_submission_id = cs.id
    WHERE u.id = ?
    GROUP BY u.id
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$year, $month_number, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode([
            'status' => $result['status'],
            'contractValue' => $result['contractValue']
        ]);
    } else {
        echo json_encode(['status' => 'ยังไม่กรอกข้อมูล', 'contractValue' => 0]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

ob_end_flush();
?>