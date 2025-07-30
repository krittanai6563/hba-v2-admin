<?php

header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'condb.php'; 


$currentYear = date('Y') + 543;

$currentMonth = date('n');


$buddhist_year = isset($_GET['buddhist_year']) ? intval($_GET['buddhist_year']) : $currentYear;
$month_number = isset($_GET['month_number']) ? intval($_GET['month_number']) : $currentMonth;


if ($month_number < 1 || $month_number > 12) {
    $month_number = $currentMonth;
}


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
        WHERE u.role = 'user','admin','master'  
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
