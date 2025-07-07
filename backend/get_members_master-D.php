<?php
// อนุญาตการเข้าถึงจากทุกต้นทาง
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// ตรวจสอบหากเป็น OPTIONS request, ให้ตอบกลับด้วยสถานะ 200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// รหัสการเชื่อมต่อกับฐานข้อมูล
require 'condb.php';

// รับค่า id, buddhist_year, month_number จาก query string
$id = isset($_GET['id']) ? $_GET['id'] : null;
$buddhist_year = isset($_GET['buddhist_year']) ? $_GET['buddhist_year'] : null;
$month_number = isset($_GET['month_number']) ? $_GET['month_number'] : null;

// ตรวจสอบว่ามีการส่งค่าพารามิเตอร์หรือไม่
if (empty($id) || empty($buddhist_year) || empty($month_number)) {
    http_response_code(400); // ส่งกลับ 400 หากข้อมูลไม่ครบ
    echo json_encode(["message" => "Missing required parameters"]);
    exit();
}

try {
    // SQL query ที่ใช้ดึงข้อมูล
    $sql = "
        SELECT
            u.id,
            u.email,
            u.fullname,
            u.member_type,
            u.role,
            u.profile_image,
            CASE WHEN cs.id IS NULL THEN 'ยังไม่กรอกข้อมูล' ELSE 'กรอกข้อมูลเรียบร้อย' END AS status,
            COALESCE(SUM(cd.total_value), 0) AS contractValue,
            cd.region,
            cd.price_range,
            COALESCE(SUM(cd.unit), 0) AS total_units,  -- ใช้ COALESCE กับ SUM เพื่อป้องกันค่า NULL
            COALESCE(SUM(cd.area), 0) AS total_area,  -- ใช้ COALESCE กับ SUM เพื่อป้องกันค่า NULL
            COALESCE(SUM(cd.total_value), 0) AS total_value,  -- ใช้ COALESCE กับ SUM เพื่อป้องกันค่า NULL
            COALESCE(AVG(cd.price_per_sqm), 0) AS avg_price_per_sqm  -- ใช้ COALESCE กับ AVG เพื่อป้องกันค่า NULL
        FROM
            users u
        LEFT JOIN contract_submission cs ON
            cs.user_id = u.id AND cs.buddhist_year = :buddhist_year AND cs.month_number = :month_number
        LEFT JOIN contract_detail cd ON
            cd.contract_submission_id = cs.id
        WHERE
            u.id = :id
        GROUP BY
            u.id,
            cd.region,
            cd.price_range;
    ";

    // เตรียมการ query และ bind parameter
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':buddhist_year', $buddhist_year);
    $stmt->bindParam(':month_number', $month_number);
    $stmt->bindParam(':id', $id);
    $stmt->execute();

    // ดึงข้อมูลจากฐานข้อมูล
    $memberDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // หากมีข้อมูลสมาชิก
    if (count($memberDetails) > 0) {
        // ข้อมูลสมาชิก
        $userInfo = [
            "id" => $memberDetails[0]['id'],
            "fullname" => $memberDetails[0]['fullname'],
            "email" => $memberDetails[0]['email'],
            "member_type" => $memberDetails[0]['member_type'],
            "role" => $memberDetails[0]['role'],
            "profile_image" => $memberDetails[0]['profile_image'],
            "status" => $memberDetails[0]['status'],
            "contractValue" => $memberDetails[0]['contractValue']
        ];

        // จัดข้อมูลตาม region และ price_range
        $regions = ['กรุงเทพปริมณฑล', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคกลาง', 'ภาคตะวันออก', 'ภาคใต้', 'ภาคตะวันตก'];
        $priceRanges = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

        // จัดกลุ่มข้อมูลตาม region และ price_range
        $groupedData = [];
        foreach ($memberDetails as $row) {
            $region = $row['region'];
            $priceRange = $row['price_range'];

            // หากยังไม่มีข้อมูลใน group นี้
            if (!isset($groupedData[$region])) {
                $groupedData[$region] = [];
            }

            if (!isset($groupedData[$region][$priceRange])) {
                $groupedData[$region][$priceRange] = [
                    'total_units' => 0,
                    'total_value' => 0,
                    'total_area' => 0,
                    'avg_price_per_sqm' => 0
                ];
            }

            // คำนวณข้อมูล
            $groupedData[$region][$priceRange]['total_units'] += $row['total_units'];
            $groupedData[$region][$priceRange]['total_value'] += $row['total_value'];
            $groupedData[$region][$priceRange]['total_area'] += $row['total_area'];
            // คำนวณราคาเฉลี่ยต่อ ตร.ม
            $groupedData[$region][$priceRange]['avg_price_per_sqm'] = $groupedData[$region][$priceRange]['total_area'] > 0 ?
                $groupedData[$region][$priceRange]['total_value'] / $groupedData[$region][$priceRange]['total_area'] : 0;
        }

        // ส่งข้อมูลกลับไป
        $response = [
            'user_info' => $userInfo,
            'data' => $groupedData
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
    } else {
        // หากไม่พบข้อมูล
        echo json_encode(["message" => "No data found for this user"]);
    }

} catch (Exception $e) {
    // หากเกิดข้อผิดพลาด, ส่งกลับสถานะ 500 พร้อมข้อความข้อผิดพลาด
    http_response_code(500);
    echo json_encode(["message" => "Error: " . $e->getMessage()]);
}
?>
