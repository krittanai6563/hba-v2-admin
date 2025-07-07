

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
           SUM(d.total_value) as value,
           MONTH(s.submitted_at) AS month,          
           MONTHNAME(s.submitted_at) AS month_name,  
           QUARTER(s.submitted_at) AS quarter,      
           d.region                                  
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

// เพิ่มการจัดกลุ่มตามไตรมาส และเดือน
$sql .= " 
GROUP BY s.buddhist_year, d.price_range, MONTH(s.submitted_at), QUARTER(s.submitted_at), d.region
          ORDER BY s.buddhist_year ASC, QUARTER(s.submitted_at) ASC, MONTH(s.submitted_at) ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [
    'yearly_data' => [],
    'quarterly_data' => [],
    'yearly_data_quarterly' => [],
    'yearly_data_quarterly_region' => [],  // เพิ่มตัวแปรสำหรับเก็บข้อมูล quarterly
    'monthly_member_summary' => []  // เพิ่มข้อมูลสมาชิกที่กรอกและไม่กรอก
];

// ฟังก์ชันสำหรับคำนวณไตรมาส
function getQuarter($month) {
    if ($month >= 1 && $month <= 3) {
        return 'Q1';
    } elseif ($month >= 4 && $month <= 6) {
        return 'Q2';
    } elseif ($month >= 7 && $month <= 9) {
        return 'Q3';
    } else {
        return 'Q4';
    }
}

// ฟังก์ชันสำหรับคำนวณข้อมูลรายปีตามช่วงราคา
function updateYearlyData(&$data, $year, $range, $value) {
    if (!isset($data['yearly_data'][$year])) {
        $data['yearly_data'][$year] = [];
    }
    if (!isset($data['yearly_data'][$year][$range])) {
        $data['yearly_data'][$year][$range] = 0;
    }
    $data['yearly_data'][$year][$range] += $value;
}

// ฟังก์ชันสำหรับคำนวณข้อมูลรายไตรมาส
function updateQuarterlyData(&$data, $year, $quarter, $monthName, $value) {
    if (!isset($data['quarterly_data'][$year])) {
        $data['quarterly_data'][$year] = [
            'Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []
        ];
    }

    // เพิ่มมูลค่าของเดือนที่เจาะจง
    if (!isset($data['quarterly_data'][$year][$quarter][$monthName])) {
        $data['quarterly_data'][$year][$quarter][$monthName] = 0; // กำหนดยอดรวมเริ่มต้น
    }
    $data['quarterly_data'][$year][$quarter][$monthName] += $value;
}

// ฟังก์ชันสำหรับคำนวณข้อมูลรายไตรมาส
function updateYearlyDataQuarterly(&$data, $year, $quarter, $range, $value) {
    if (!isset($data['yearly_data_quarterly'][$year])) {
        $data['yearly_data_quarterly'][$year] = [
            'Q1' => [], 'Q2' => [], 'Q3' => [], 'Q4' => []
        ];
    }
    if (!isset($data['yearly_data_quarterly'][$year][$quarter][$range])) {
        $data['yearly_data_quarterly'][$year][$quarter][$range] = 0;
    }
    $data['yearly_data_quarterly'][$year][$quarter][$range] += $value;
}

// ฟังก์ชันสำหรับคำนวณข้อมูลรายไตรมาสตามภูมิภาค
function updateQuarterlyRegionData(&$data, $year, $region, $quarter, $value) {
    // ตรวจสอบว่าภูมิภาคมีข้อมูลหรือไม่ (เช่น NULL หรือค่าว่าง)
    if (empty($region)) {
        return;  // ถ้าไม่มีข้อมูลภูมิภาค (เช่น NULL หรือค่าว่าง) ก็ข้ามไป
    }

    // ตรวจสอบว่า 'yearly_data_quarterly_region' มีข้อมูลสำหรับภูมิภาคและปีนั้นๆ หรือไม่
    if (!isset($data['yearly_data_quarterly_region'][$region][$year])) {
        // ถ้าไม่มี ให้สร้างข้อมูลสำหรับภูมิภาคและปีนั้นๆ
        $data['yearly_data_quarterly_region'][$region][$year] = [
            'Q1' => 0,
            'Q2' => 0,
            'Q3' => 0,
            'Q4' => 0,
            'รวม' => 0,  // เพิ่ม 'รวม' เพื่อคำนวณยอดรวม
        ];
    }

    // เพิ่มมูลค่าของไตรมาสที่เลือก
    $data['yearly_data_quarterly_region'][$region][$year][$quarter] += $value;

    // คำนวณผลรวมสำหรับภูมิภาคนี้ในปีนั้น
    $data['yearly_data_quarterly_region'][$region][$year]['รวม'] = 
        $data['yearly_data_quarterly_region'][$region][$year]['Q1'] +
        $data['yearly_data_quarterly_region'][$region][$year]['Q2'] +
        $data['yearly_data_quarterly_region'][$region][$year]['Q3'] +
        $data['yearly_data_quarterly_region'][$region][$year]['Q4'];

    // คำนวณยอดรวมสำหรับกรุงเทพปริมณฑลและต่างจังหวัดในปีนั้น
    $data['yearly_data_quarterly_region']['รวม'][$year][$quarter] =
        ($data['yearly_data_quarterly_region']['กรุงเทพปริมณฑล'][$year][$quarter] ?? 0) +
        ($data['yearly_data_quarterly_region']['ต่างจังหวัด'][$year][$quarter] ?? 0);

    // คำนวณยอดรวมทั้งหมดในปีนั้น
    $data['yearly_data_quarterly_region']['รวม'][$year]['รวม'] =
        ($data['yearly_data_quarterly_region']['กรุงเทพปริมณฑล'][$year]['รวม'] ?? 0) +
        ($data['yearly_data_quarterly_region']['ต่างจังหวัด'][$year]['รวม'] ?? 0);
}


$monthNames = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

function updateYearlyAndMonthlyData(&$data, $year, $month, $range, $value) {
    // ตรวจสอบว่าเดือนที่ส่งมามีค่าระหว่าง 1 ถึง 12 หรือไม่
    $monthNames = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    $monthName = isset($monthNames[$month]) ? $monthNames[$month] : 'Unknown';

    // ตรวจสอบข้อมูลปีและช่วงราคา
    if (!isset($data['yearly_data'][$year])) {
        $data['yearly_data'][$year] = [];
    }
    if (!isset($data['yearly_data'][$year][$range])) {
        $data['yearly_data'][$year][$range] = 0;
    }
    // เพิ่มมูลค่าการขายตามปีและช่วงราคา
    $data['yearly_data'][$year][$range] += $value;

    // ตรวจสอบข้อมูลเดือนและช่วงราคา
    if (!isset($data['monthly_data'][$year])) {
        $data['monthly_data'][$year] = [];
    }
    if (!isset($data['monthly_data'][$year][$monthName])) {
        $data['monthly_data'][$year][$monthName] = [];
    }
    if (!isset($data['monthly_data'][$year][$monthName][$range])) {
        $data['monthly_data'][$year][$monthName][$range] = 0;
    }
    // เพิ่มมูลค่าการขายตามเดือนและช่วงราคา
    $data['monthly_data'][$year][$monthName][$range] += $value;

}

// ส่วนการดึงข้อมูลจากฐานข้อมูลและการคำนวณยอดรวมตามไตรมาสและเดือน
foreach ($results as $row) {
    $year = $row['buddhist_year'];
    $range = $row['price_range'];
    $region = $row['region'];  // รับข้อมูลภูมิภาคจากฐานข้อมูล
    $month = (int)$row['month'];              // หมายเลขเดือน
    $monthName = $row['month_name'];          // ชื่อเดือน
    $value = (float)$row['value'];            // มูลค่าของการขาย

    // เรียกใช้ฟังก์ชันคำนวณข้อมูลรายปีและรายเดือนตามช่วงราคา
    updateYearlyAndMonthlyData($data, $year, $month, $range, $value);

    // คำนวณไตรมาส
    $quarter = getQuarter($month);

    // เรียกใช้ฟังก์ชันคำนวณข้อมูลรายไตรมาส
    updateQuarterlyData($data, $year, $quarter, $monthName, $value);

    // เรียกใช้ฟังก์ชันคำนวณข้อมูลรายไตรมาส
    updateYearlyDataQuarterly($data, $year, $quarter, $range, $value);

    // เรียกใช้ฟังก์ชันคำนวณข้อมูลรายไตรมาสตามภูมิภาค
    updateQuarterlyRegionData($data, $year, $region, $quarter, $value);
}
// ฟังก์ชันแปลงปีจาก พ.ศ. เป็น ค.ศ.
$year_in_gregorian = $year - 543;

// การดึงข้อมูลสมาชิกที่กรอกข้อมูล
$memberSummarySql = "
    SELECT 
        MONTH(s.submitted_at) AS month,
        COUNT(DISTINCT s.user_id) AS submitted_members
    FROM contract_submission s
    WHERE s.buddhist_year = ?  -- ปีที่เลือกจากตัวแปร $year
    GROUP BY MONTH(s.submitted_at)
";
$memberStmt = $conn->prepare($memberSummarySql);
$memberStmt->execute([$year]);
$memberResults = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลจำนวนสมาชิกทั้งหมดในปีที่เลือก
$totalMembersSql = "
    SELECT 
        COUNT(DISTINCT u.id) AS total_members
    FROM users u
    WHERE YEAR(u.created_at) = ?  -- ปีที่เลือกจากตัวแปร $year (ในคริสต์ศักราช)
";
$totalStmt = $conn->prepare($totalMembersSql);
$totalStmt->execute([$year_in_gregorian]);  // ใช้ปีในคริสต์ศักราช
$totalResults = $totalStmt->fetch(PDO::FETCH_ASSOC);

// จำนวนสมาชิกทั้งหมด
$totalMembers = $totalResults['total_members'] ?? 0;  // กำหนดค่า default เป็น 0 ถ้าไม่มีข้อมูล

// สร้าง array สำหรับข้อมูลสรุปรายเดือน
$monthlySummary = [];
for ($i = 1; $i <= 12; $i++) {
    $submitted = 0;

    // หาจำนวนสมาชิกที่กรอกข้อมูลในเดือนนั้น
    foreach ($memberResults as $row) {
        if ((int)$row['month'] === $i) {
            $submitted = (int)$row['submitted_members'];
            break;
        }
    }

    // ปรับให้ไม่ให้ `not_submitted_members` เป็นค่าติดลบ
    $notSubmittedMembers = $totalMembers - $submitted >= 0 ? $totalMembers - $submitted : 0;

    // เพิ่มข้อมูลสำหรับแต่ละเดือน
    $monthlySummary[] = [
        'month_number' => $i,
        'total_members' => $totalMembers,  // จำนวนสมาชิกทั้งหมดที่ดึงจากตาราง users
        'submitted_members' => $submitted,  // จำนวนสมาชิกที่กรอกข้อมูลจาก contract_submission
        'not_submitted_members' => $notSubmittedMembers,  // จำนวนสมาชิกที่ยังไม่กรอก
        'year' => $year  // ปีที่เลือก
    ];
}

// เปรียบเทียบข้อมูลปีที่สอง (เช่น ปี 2568 กับ ปี 2567)
$memberSummarySqlCompareYear = "
    SELECT 
        MONTH(s.submitted_at) AS month,
        COUNT(DISTINCT s.user_id) AS submitted_members
    FROM contract_submission s
    WHERE s.buddhist_year = ?  -- ปีที่เปรียบเทียบ (2568)
    GROUP BY MONTH(s.submitted_at)
";
$memberStmtCompareYear = $conn->prepare($memberSummarySqlCompareYear);
$memberStmtCompareYear->execute([$year + 1]);  // เพิ่มปี 1 เพื่อใช้ปีถัดไป (เช่น 2568)
$memberResultsCompareYear = $memberStmtCompareYear->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลจำนวนสมาชิกทั้งหมดในปีที่เปรียบเทียบ
$totalMembersCompareYearSql = "
    SELECT 
        COUNT(DISTINCT u.id) AS total_members
    FROM users u
    WHERE YEAR(u.created_at) = ?  -- ปีที่เปรียบเทียบ (ในคริสต์ศักราช)
";
$totalStmtCompareYear = $conn->prepare($totalMembersCompareYearSql);
$totalStmtCompareYear->execute([($year + 1) - 543]);  // ใช้ปีในคริสต์ศักราช
$totalResultsCompareYear = $totalStmtCompareYear->fetch(PDO::FETCH_ASSOC);

// จำนวนสมาชิกทั้งหมดในปีที่เปรียบเทียบ
$totalMembersCompareYear = $totalResultsCompareYear['total_members'] ?? 0;  // กำหนดค่า default เป็น 0 ถ้าไม่มีข้อมูล

// เพิ่มข้อมูลสมาชิกที่กรอกข้อมูลสำหรับปีที่เปรียบเทียบ
$monthlySummaryCompareYear = [];
for ($i = 1; $i <= 12; $i++) {
    $submitted = 0;

    // หาจำนวนสมาชิกที่กรอกข้อมูลในเดือนนั้น
    foreach ($memberResultsCompareYear as $row) {
        if ((int)$row['month'] === $i) {
            $submitted = (int)$row['submitted_members'];
            break;
        }
    }

    // ปรับให้ไม่ให้ `not_submitted_members` เป็นค่าติดลบ
    $notSubmittedMembers = $totalMembersCompareYear - $submitted >= 0 ? $totalMembersCompareYear - $submitted : 0;

    // เพิ่มข้อมูลสำหรับแต่ละเดือนในปีที่เปรียบเทียบ
    $monthlySummaryCompareYear[] = [
        'month_number' => $i,
        'total_members' => $totalMembersCompareYear,  // จำนวนสมาชิกทั้งหมดในปีที่เปรียบเทียบ
        'submitted_members' => $submitted,  // จำนวนสมาชิกที่กรอกข้อมูลจาก contract_submission
        'not_submitted_members' => $notSubmittedMembers,  // จำนวนสมาชิกที่ยังไม่กรอก
        'year' => $year + 1  // ปีที่เปรียบเทียบ
    ];
}

// เพิ่มข้อมูลที่เปรียบเทียบปีเข้าไปในผลลัพธ์หลัก
$data['monthly_member_summary_compare_year'] = $monthlySummaryCompareYear;
$data['monthly_member_summary'] = $monthlySummary;

echo json_encode($data, JSON_UNESCAPED_UNICODE);

?>
