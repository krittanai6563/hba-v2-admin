<?php
// CORS headers (ลบซ้ำ)
// header("Access-Control-Allow-Origin: *"); 
// header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// header("Access-Control-Allow-Headers: Content-Type");

// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

// require 'condb.php'; // ต้องแน่ใจว่าไฟล์นี้สร้าง $conn เป็น PDO instance

// ดึงปีปัจจุบันแบบ พ.ศ. (ปี ค.ศ. + 543)
// $currentYear = date('Y') + 543;

// ดึงเดือนปัจจุบัน (เลข 1-12)
// $currentMonth = date('n');

// กำหนดค่า parameter โดยใช้ค่าใน URL ถ้ามี ไม่งั้นใช้ค่าเริ่มต้นเป็นปีและเดือนปัจจุบัน
// $buddhist_year = isset($_GET['buddhist_year']) ? intval($_GET['buddhist_year']) : $currentYear;
// $month_number = isset($_GET['month_number']) ? intval($_GET['month_number']) : $currentMonth;

// ป้องกันค่าเดือนเกินขอบเขต
// if ($month_number < 1 || $month_number > 12) {
//     $month_number = $currentMonth;
// }

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// ไม่จำเป็นต้องป้องกันปีอีกต่อไป สามารถใช้ปีใดก็ได้จากพารามิเตอร์
// try {
//     $sql = "
//         SELECT 
//             u.id, u.email, u.fullname, u.member_type, u.role, u.profile_image,
//             CASE 
//                 WHEN cs.id IS NULL THEN 'ยังไม่กรอกข้อมูล'
//                 ELSE 'กรอกข้อมูลเรียบร้อย'
//             END AS status,
//             COALESCE(SUM(cd.total_value), 0) AS contractValue
//         FROM users u
//         LEFT JOIN contract_submission cs 
//             ON cs.user_id = u.id 
//             AND cs.buddhist_year = :buddhist_year 
//             AND cs.month_number = :month_number
//         LEFT JOIN contract_detail cd ON cd.contract_submission_id = cs.id
//      WHERE u.role IN ('user', 'admin', 'master')
//         GROUP BY u.id
//         ORDER BY u.id
//         LIMIT 0, 25;
//     ";

//     $stmt = $conn->prepare($sql);
//     $stmt->execute([
//         ':buddhist_year' => $buddhist_year,
//         ':month_number' => $month_number
//     ]);

//     $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     if ($members) {
//         echo json_encode($members);  // Return JSON if the data is found
//     } else {
//         echo json_encode(['message' => 'No members found']);
//     }
// } catch (Exception $e) {
//     error_log('Error executing query: ' . $e->getMessage());  // Log the error message
//     echo json_encode(["message" => "Failed to fetch members: " . $e->getMessage()]);
// }


// CORS headers (ลบซ้ำ)
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'condb.php'; // ต้องแน่ใจว่าไฟล์นี้สร้าง $conn เป็น PDO instance

// ดึงปีปัจจุบันแบบ พ.ศ. (ปี ค.ศ. + 543)
$currentYear = date('Y') + 543;

// ดึงเดือนปัจจุบัน (เลข 1-12)
$currentMonth = date('n');

// กำหนดค่า parameter โดยใช้ค่าใน URL ถ้ามี ไม่งั้นใช้ค่าเริ่มต้นเป็นปีและเดือนปัจจุบัน
$buddhist_year = isset($_GET['buddhist_year']) ? intval($_GET['buddhist_year']) : $currentYear;
$month_number = isset($_GET['month_number']) ? intval($_GET['month_number']) : $currentMonth;

// ป้องกันค่าเดือนเกินขอบเขต
if ($month_number < 1 || $month_number > 12) {
    $month_number = $currentMonth;
}

// ไม่จำเป็นต้องป้องกันปีอีกต่อไป สามารถใช้ปีใดก็ได้จากพารามิเตอร์
try {
    $sql = "
        SELECT 
            u.id, u.email, u.fullname, u.member_type, u.role, u.profile_image,
            CASE 
                WHEN cs.id IS NULL THEN 'ยังไม่กรอกข้อมูล'
                ELSE 'กรอกข้อมูลเรียบร้อย'
            END AS status,
            COALESCE(SUM(cd.total_value), 0) AS contractValue
        FROM users u
        LEFT JOIN contract_submission cs 
            ON cs.user_id = u.id 
            AND cs.buddhist_year = :buddhist_year 
            AND cs.month_number = :month_number
        LEFT JOIN contract_detail cd ON cd.contract_submission_id = cs.id
        WHERE u.role = 'user'  
        GROUP BY u.id
        ORDER BY u.id
        LIMIT 0, 25;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':buddhist_year' => $buddhist_year,
        ':month_number' => $month_number
    ]);

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($members);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Failed to fetch members: " . $e->getMessage()]);
}
?>

