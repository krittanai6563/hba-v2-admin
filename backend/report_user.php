<?php
// CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'condb.php'; // เชื่อมต่อฐานข้อมูล

// ดึงปีที่ส่งมาจาก URL (ใช้ปีปัจจุบันหากไม่มี)
$currentYear = date('Y') + 543;
$buddhist_years = isset($_GET['buddhist_years']) ? explode(',', $_GET['buddhist_years']) : [$currentYear];

// ดึงไตรมาสที่ส่งมาจาก URL (ถ้าไม่มีกำหนดเป็น Q2)
$quarters = isset($_GET['quarters']) ? explode(',', $_GET['quarters']) : [2]; // เริ่มต้นที่ Q2

// เตรียม SQL Query
$sql = "
WITH months AS (
    SELECT 1 AS month_number UNION ALL
    SELECT 2 UNION ALL
    SELECT 3 UNION ALL
    SELECT 4 UNION ALL
    SELECT 5 UNION ALL
    SELECT 6 UNION ALL
    SELECT 7 UNION ALL
    SELECT 8 UNION ALL
    SELECT 9 UNION ALL
    SELECT 10 UNION ALL
    SELECT 11 UNION ALL
    SELECT 12
),
quarters AS (
    SELECT 1 AS quarter, 'Q1' AS quarter_name, 1 AS start_month, 3 AS end_month UNION ALL
    SELECT 2, 'Q2', 4, 6 UNION ALL
    SELECT 3, 'Q3', 7, 9 UNION ALL
    SELECT 4, 'Q4', 10, 12
)
SELECT 
    q.quarter_name,
    m.month_number,
    (SELECT COUNT(*) FROM users WHERE role = 'user') AS total_members,  
    COALESCE(COUNT(DISTINCT CASE WHEN cs.id IS NOT NULL THEN u.id END), 0) AS submitted_members,  
    COALESCE((SELECT COUNT(*) FROM users WHERE role = 'user') - COUNT(DISTINCT CASE WHEN cs.id IS NOT NULL THEN u.id END), 0) AS not_submitted_members,  
    COALESCE(COUNT(DISTINCT CASE WHEN cs.buddhist_year = cs.buddhist_year THEN u.id END), 0) AS submitted_members_by_year
FROM months m
JOIN quarters q
    ON m.month_number BETWEEN q.start_month AND q.end_month  -- ฟิลเตอร์เดือนตามไตรมาส
LEFT JOIN contract_submission cs 
    ON cs.month_number = m.month_number 
    AND cs.buddhist_year IN (" . implode(',', array_map('intval', $buddhist_years)) . ")  -- ฟิตเตอร์ปีที่เลือก
LEFT JOIN users u ON u.id = cs.user_id
LEFT JOIN contract_detail cd ON cd.contract_submission_id = cs.id
WHERE u.role = 'user'  
AND q.quarter IN (" . implode(',', array_map('intval', $quarters)) . ")  -- ฟิตเตอร์ไตรมาสที่เลือก
GROUP BY q.quarter_name, m.month_number, cs.buddhist_year
ORDER BY m.month_number;
";

// เชื่อมต่อและดึงข้อมูลจากฐานข้อมูล
try {
    // เตรียมและ execute SQL query
    $stmt = $conn->prepare($sql);
    $stmt->execute(); // ไม่มีพารามิเตอร์เพราะใช้ implode ใน SQL

    // ดึงข้อมูลทั้งหมด
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ส่งข้อมูลเป็น JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($members);

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาดในการดึงข้อมูล
    http_response_code(500);
    echo json_encode(["message" => "Failed to fetch members: " . $e->getMessage()]);
}
?>
