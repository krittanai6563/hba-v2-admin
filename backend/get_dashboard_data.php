<?php
include 'condb.php';
// (!!! 1. เพิ่ม header นี้ หลังจากที่เราย้ายออกจาก condb.php !!!)
header('Content-Type: application/json');


// (!!! 2. นี่คือจุดที่แก้ไข (สำคัญที่สุด) !!!)
// เราจะใช้ 'created_at' จากตาราง 'd' (contract_detail)
// -------------------------------------------------------------------------
$SUBMISSION_TABLE = 'contract_submission'; // ตารางแม่ (aliased as c)
$DETAIL_TABLE = 'contract_detail';         // ตารางลูก (aliased as d)

$DATE_TABLE_ALIAS = 'd';                   // (!!! แก้ไข: บอกว่าวันที่อยู่ในตาราง d !!!)
$DATE_COLUMN = 'created_at';         // (!!! ยืนยันใช้ created_at !!!)

$VALUE_COLUMN = 'total_value';       
$AREA_COLUMN = 'area';               
$UNIT_COLUMN = 'unit';               
// -------------------------------------------------------------------------


// รับข้อมูล JSON จาก Vue
$data = json_decode(file_get_contents('php://input'));

if (empty($data->year) || empty($data->months) || !is_array($data->months)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลปีหรือเดือนไม่ถูกต้อง']);
    exit;
}

$year = (int)$data->year;
$months = $data->months; 

// สร้าง placeholders
$placeholders = implode(',', array_fill(0, count($months), '?'));
$params = array_merge([$year], $months);

// เตรียมอาร์เรย์ผลลัพธ์
$response = [
    'summary' => [
        'total_units' => 0,
        'total_value' => 0,
        'total_area' => 0,
        'value_per_sqm' => 0
    ],
    'monthly_data' => []
];

// สร้าง SQL JOIN
$SQL_JOIN = " FROM $DETAIL_TABLE d
              JOIN $SUBMISSION_TABLE c ON d.contract_submission_id = c.id ";

// (!!! 3. สร้าง SQL WHERE (แก้ไข) !!!)
// (ตอนนี้จะใช้ d.created_at)
$SQL_WHERE = " WHERE YEAR($DATE_TABLE_ALIAS.$DATE_COLUMN) = ? 
               AND MONTH($DATE_TABLE_ALIAS.$DATE_COLUMN) IN ($placeholders) ";

try {
    // --- Query ที่ 1: ดึงผลรวมทั้งหมด (สำหรับ 4 การ์ด) ---
    $sql_summary = "SELECT 
                        SUM(d.$UNIT_COLUMN) as total_units,
                        SUM(d.$VALUE_COLUMN) as total_value,
                        SUM(d.$AREA_COLUMN) as total_area
                    $SQL_JOIN
                    $SQL_WHERE";
    
    $stmt_summary = $conn->prepare($sql_summary);
    $stmt_summary->execute($params);
    $summary_result = $stmt_summary->fetch(PDO::FETCH_ASSOC);

    if ($summary_result) {
        $response['summary']['total_units'] = (int)$summary_result['total_units'];
        $response['summary']['total_value'] = (float)$summary_result['total_value'];
        $response['summary']['total_area'] = (float)$summary_result['total_area'];
        
        if ($response['summary']['total_area'] > 0) {
            $response['summary']['value_per_sqm'] = $response['summary']['total_value'] / $response['summary']['total_area'];
        }
    }

    // --- Query ที่ 2: ดึงข้อมูลแยกรายเดือน (สำหรับ 4 กราฟ) ---
    $sql_monthly = "SELECT 
                        MONTH($DATE_TABLE_ALIAS.$DATE_COLUMN) as month,
                        SUM(d.$UNIT_COLUMN) as monthly_units,
                        SUM(d.$VALUE_COLUMN) as monthly_value,
                        SUM(d.$AREA_COLUMN) as monthly_area
                    $SQL_JOIN
                    $SQL_WHERE
                    GROUP BY MONTH($DATE_TABLE_ALIAS.$DATE_COLUMN)
                    ORDER BY month ASC";
                    
    $stmt_monthly = $conn->prepare($sql_monthly);
    $stmt_monthly->execute($params);
    $monthly_results = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);

    // --- จัดการข้อมูลรายเดือนให้ครบ (เฉพาะเดือนที่เลือก) ---
    $month_map = [];
    foreach ($monthly_results as $row) {
        $month_map[(int)$row['month']] = $row;
    }

    $final_monthly_data = [
        'labels' => [],
        'units' => [],
        'value' => [],
        'area' => [],
        'valuePerSqm' => []
    ];

    $month_names_th = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."]; 

    foreach ($months as $month_num) {
        $final_monthly_data['labels'][] = $month_names_th[$month_num];

        if (isset($month_map[$month_num])) {
            $row = $month_map[$month_num];
            $monthly_units = (int)$row['monthly_units'];
            $monthly_value = (float)$row['monthly_value'];
            $monthly_area = (float)$row['monthly_area'];
            
            $final_monthly_data['units'][] = $monthly_units;
            $final_monthly_data['value'][] = $monthly_value;
            $final_monthly_data['area'][] = $monthly_area;
            
            $final_monthly_data['valuePerSqm'][] = ($monthly_area > 0) ? ($monthly_value / $monthly_area) : 0;
        } else {
            $final_monthly_data['units'][] = 0;
            $final_monthly_data['value'][] = 0;
            $final_monthly_data['area'][] = 0;
            $final_monthly_data['valuePerSqm'][] = 0;
        }
    }
    
    $response['monthly_data'] = $final_monthly_data;

    echo json_encode(['status' => 'success', 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage()]);
}
?>