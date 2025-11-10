<?php

// Define the category order (ราคาบ้าน)
$categoryOrder = ['ไม่เกิน 2.50 ล้านบาท', '2.51 - 5 ล้านบาท', '5.01 - 10 ล้านบาท', '10.01 - 20 ล้านบาท', '20.01 ล้านขึ้นไป'];

// Allow cross-origin requests (CORS)
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

// Include the database connection file
require 'condb.php';

// Get the request data
$data = json_decode(file_get_contents("php://input"), true);
$userId = $data['user_id'] ?? null;
$years = $data['buddhist_year'] ?? null; // Array of selected years
$role = $data['role'] ?? 'user';
$months = $data['months'] ?? [];  // Array of selected months (may be empty)
$quarters = $data['quarters'] ?? []; // Array of selected quarters (may be empty)

if (!$years || count($years) < 1) {
    // กำหนดปีปัจจุบันเป็นค่าเริ่มต้นหากไม่มีการเลือกปี (เหมือนกับ logic ใน Vue)
    $currentYear = (new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('Y') + 543;
    $years = [(string)$currentYear];
}

// 🚀 START: ส่วนที่แก้ไข 1/3
// --- สร้าง Array ของปีที่ต้องดึงข้อมูล
// เราต้องการปีก่อนหน้าด้วยเสมอ เพื่อคำนวณ YoY
$comparisonYears = [];
if ($years) {
    foreach ($years as $year) {
        // ถ้าผู้ใช้เลือกปี 2568, เราต้องการปี 2567 มาเปรียบเทียบด้วย
        $comparisonYears[] = (int)$year - 1;
    }
}
// รวมปีที่เลือก และ ปีที่ต้องใช้เปรียบเทียบ เข้าด้วยกัน (ป้องกันการซ้ำซ้อน)
$allYearsToFetch = array_unique(array_merge($years, $comparisonYears));
// 🚀 END: ส่วนที่แก้ไข 1/3


$whereConditions = [];
$params = [];

// 1. ขยาย Main Query เพื่อรวม metrics ทั้งหมด: total_value, total_units, total_area

// 🚀 START: ส่วนที่แก้ไข 2/3
// --- ใช้ $allYearsToFetch แทน $years
$placeholders = implode(',', array_fill(0, count($allYearsToFetch), '?'));
// 🚀 END: ส่วนที่แก้ไข 2/3

$sql = "
    SELECT s.buddhist_year,
           d.price_range,
           SUM(d.total_value) AS total_value,
           SUM(d.unit) AS total_units,
           SUM(d.area) AS total_area,
           MONTH(s.submitted_at) AS month,
           s.quarter AS quarter,
           d.region
    FROM contract_submission s
    INNER JOIN contract_detail d ON s.id = d.contract_submission_id
    WHERE s.buddhist_year IN ($placeholders)
";

// Add month filtering if months are provided
if (!empty($months)) {
    $monthPlaceholders = implode(',', array_fill(0, count($months), '?'));
    $sql .= " AND MONTH(s.submitted_at) IN ($monthPlaceholders)";
}

// Add quarter filtering if quarters are provided
if (!empty($quarters)) {
    $quarterPlaceholders = implode(',', array_fill(0, count($quarters), '?'));
    $sql .= " AND s.quarter IN ($quarterPlaceholders)";
}

// 🚀 START: ส่วนที่แก้ไข 3/3
// --- ใช้ $allYearsToFetch แทน $years
$params = array_merge($params, $allYearsToFetch, $months, $quarters);
// 🚀 END: ส่วนที่แก้ไข 3/3


// If the user is not an admin or master, filter by user_id
if ($role !== 'admin' && $role !== 'master') {
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user_id for non-admin']);
        exit;
    }
    $sql .= " AND s.user_id = ?";
    $params[] = $userId;
}

$sql .= " GROUP BY s.buddhist_year, MONTH(s.submitted_at), s.quarter, d.price_range, d.region
          ORDER BY s.buddhist_year ASC, MONTH(s.submitted_at) ASC, d.price_range ASC, d.region ASC";

// Prepare the statement and execute with the correct parameters
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize data structure
$data = [
    'yearly_data' => [],
    'monthly_data' => [],
    'region_data' => [],
    'membership_data' => []
];

// 2. ปรับปรุง Data Processing Loop (จัดโครงสร้างและคำนวณ Metrics)
foreach ($results as $row) {
    $year = $row['buddhist_year'];
    $month = (int)$row['month'];
    $range = $row['price_range'];
    $region = $row['region'];
    
    // Extract raw metrics
    $total_value = (float)$row['total_value'];
    $total_units = (int)$row['total_units'];
    $total_area = (float)$row['total_area'];
    
    // Calculate derived metric
    $average_price_per_sqm = ($total_area > 0) ? round($total_value / $total_area, 2) : 0.00;

    $metrics = [
        'total_value' => $total_value,
        'total_area' => $total_area,
        'total_units' => $total_units,
        'average_price_per_sqm' => $average_price_per_sqm,
    ];

    // --- 1. Process for Monthly Data ---
    if (!isset($data['monthly_data'][$year])) $data['monthly_data'][$year] = [];
    if (!isset($data['monthly_data'][$year][$month])) $data['monthly_data'][$year][$month] = [];
    
    // 🚀 START: แก้ไข Bug (monthly_data)
    //  
    // Bug เดิม: $data['monthly_data'][$year][$month][$range] = $metrics; (เป็นการเขียนทับ)
    // แก้ไข: ต้องบวกสะสม (+=) เพื่อให้ได้ยอดรวมของทุกภูมิภาค
    //
    if (!isset($data['monthly_data'][$year][$month][$range])) {
        $data['monthly_data'][$year][$month][$range] = ['total_value' => 0, 'total_area' => 0, 'total_units' => 0, 'average_price_per_sqm' => 0];
    }
    $data['monthly_data'][$year][$month][$range]['total_value'] += $total_value;
    $data['monthly_data'][$year][$month][$range]['total_area'] += $total_area;
    $data['monthly_data'][$year][$month][$range]['total_units'] += $total_units;
    // (average_price_per_sqm จะถูกคำนวณใหม่ใน Loop 3.2 หลังจากรวมยอดเสร็จ)
    // 🚀 END: แก้ไข Bug (monthly_data)


    // --- 2. Process for Yearly Aggregation ---
    if (!isset($data['yearly_data'][$year])) $data['yearly_data'][$year] = [];

    // 🚀 START: แก้ไข Bug (yearly_data)
    //  
    // Bug เดิม: โค้ดที่คล้ายกันนี้ ทำให้ยอดรายปีถูกเขียนทับด้วยข้อมูลภูมิภาคสุดท้าย
    // แก้ไข: ต้องบวกสะสม (+=)
    //
    if (!isset($data['yearly_data'][$year][$range])) {
        $data['yearly_data'][$year][$range] = ['total_value' => 0, 'total_area' => 0, 'total_units' => 0, 'monthly_count' => 0];
    }
    // Aggregate annual sums
    $data['yearly_data'][$year][$range]['total_value'] += $total_value;
    $data['yearly_data'][$year][$range]['total_area'] += $total_area;
    $data['yearly_data'][$year][$range]['total_units'] += $total_units;
    // 🚀 END: แก้ไข Bug (yearly_data)


    // --- 3. Process for Regional Data (Key for the Vue FE regional tables) ---
    // (ส่วนนี้ถูกต้องอยู่แล้ว ไม่ต้องแก้ไข)
    // Structure: region_data[year][month][region][price_range] = Metrics
    if (!isset($data['region_data'][$year])) $data['region_data'][$year] = [];
    if (!isset($data['region_data'][$year][$month])) $data['region_data'][$year][$month] = [];
    if (!isset($data['region_data'][$year][$month][$region])) $data['region_data'][$year][$month][$region] = [];
    $data['region_data'][$year][$month][$region][$range] = $metrics;
}


// --- 3. Final Aggregation and Total Calculation ---

// 3.1 Process Yearly Totals (Aggregates price ranges and calculates final average)
foreach ($data['yearly_data'] as $year => $rangesData) {
    $total_value_sum = 0;
    $total_area_sum = 0;
    $total_units_sum = 0;
    
    foreach ($categoryOrder as $range) {
        $metrics = $rangesData[$range] ?? ['total_value' => 0, 'total_area' => 0, 'total_units' => 0];
        
        // Calculate average for non-'รวม' categories
        $average_price_per_sqm = ($metrics['total_area'] > 0) ? round($metrics['total_value'] / $metrics['total_area'], 2) : 0.00;
        $data['yearly_data'][$year][$range]['average_price_per_sqm'] = $average_price_per_sqm;

        $total_value_sum += $metrics['total_value'];
        $total_area_sum += $metrics['total_area'];
        $total_units_sum += $metrics['total_units'];
        
        // Ensure all categories exist in yearly data
        if (!isset($data['yearly_data'][$year][$range])) {
             $data['yearly_data'][$year][$range] = ['total_value' => 0.00, 'total_area' => 0.00, 'total_units' => 0, 'average_price_per_sqm' => 0.00];
        }
    }
    
    // Calculate 'รวม' (Grand Total by Price Range for the Year)
    $total_avg_price_per_sqm = ($total_area_sum > 0) ? round($total_value_sum / $total_area_sum, 2) : 0.00;
    $data['yearly_data'][$year]['รวม'] = [
        'total_value' => $total_value_sum,
        'total_area' => $total_area_sum,
        'total_units' => $total_units_sum,
        'average_price_per_sqm' => $total_avg_price_per_sqm,
    ];
}

// 3.2 Process Monthly Totals (Aggregates price ranges and ensures all keys exist)
foreach ($data['monthly_data'] as $year => $monthsData) {
    for ($month = 1; $month <= 12; $month++) {
        if (!isset($data['monthly_data'][$year][$month])) {
            $data['monthly_data'][$year][$month] = [];
        }
        
        $rangesData = $data['monthly_data'][$year][$month];
        $total_value_sum = 0;
        $total_area_sum = 0;
        $total_units_sum = 0;

        foreach ($categoryOrder as $range) {
            $metrics = $rangesData[$range] ?? ['total_value' => 0, 'total_area' => 0, 'total_units' => 0, 'average_price_per_sqm' => 0];

            // 🚀 START: แก้ไข Bug (monthly_data)
            // คำนวณ average_price_per_sqm ใหม่
            // จากยอดรวม (total_value, total_area) ที่เราบวกสะสม (+=) มาจาก Loop 2
            $average_price_per_sqm = ($metrics['total_area'] > 0) ? round($metrics['total_value'] / $metrics['total_area'], 2) : 0.00;
            // 🚀 END: แก้ไข Bug
            
            // Ensure all non-submitted price ranges exist with zero values
            if (!isset($data['monthly_data'][$year][$month][$range])) {
                 $data['monthly_data'][$year][$month][$range] = ['total_value' => 0.00, 'total_area' => 0.00, 'total_units' => 0, 'average_price_per_sqm' => 0.00];
            }
            
            // 🚀 START: แก้ไข Bug (monthly_data)
            // บันทึกค่า average ที่คำนวณใหม่
            $data['monthly_data'][$year][$month][$range]['average_price_per_sqm'] = $average_price_per_sqm;
            // 🚀 END: แก้ไข Bug

            $total_value_sum += $metrics['total_value'];
            $total_area_sum += $metrics['total_area'];
            $total_units_sum += $metrics['total_units'];
        }
        
        // Calculate 'รวม' (Grand Total by Price Range for the Month)
        $total_avg_price_per_sqm = ($total_area_sum > 0) ? round($total_value_sum / $total_area_sum, 2) : 0.00;
        $data['monthly_data'][$year][$month]['รวม'] = [
            'total_value' => $total_value_sum,
            'total_area' => $total_area_sum,
            'total_units' => $total_units_sum,
            'average_price_per_sqm' => $total_avg_price_per_sqm,
        ];
    }
}


// 3.3 Process Regional Totals (Aggregates price ranges and regions)
$regionCategories = ['ภาคกลาง', 'ภาคเหนือ', 'ภาคตะวันออกเฉียงเหนือ', 'ภาคใต้', 'ภาคตะวันออก', 'ภาคตะวันตก', 'กรุงเทพปริมณฑล'];

foreach ($data['region_data'] as $year => $monthsData) {
    for ($month = 1; $month <= 12; $month++) { // ⚠️ แก้ไข: วน Loop 12 เดือนเพื่อให้แน่ใจว่ามีข้อมูลครบ
        if (!isset($data['region_data'][$year][$month])) {
            $data['region_data'][$year][$month] = [];
        }
        $regionsData = $data['region_data'][$year][$month];
        
        $nationalTotals = ['total_value' => 0, 'total_area' => 0, 'total_units' => 0];

        foreach ($regionCategories as $region) {
            // Aggregate price ranges within the current region/month/year to get the region's 'รวม' total
            $regionTotals = ['total_value' => 0, 'total_area' => 0, 'total_units' => 0];
            $currentRegionData = $regionsData[$region] ?? [];

            foreach ($categoryOrder as $range) {
                $metrics = $currentRegionData[$range] ?? ['total_value' => 0, 'total_area' => 0, 'total_units' => 0, 'average_price_per_sqm' => 0];
                
                $regionTotals['total_value'] += $metrics['total_value'];
                $regionTotals['total_area'] += $metrics['total_area'];
                $regionTotals['total_units'] += $metrics['total_units'];

                // Ensure non-submitted price ranges exist with zero values for the combined table
                if (!isset($data['region_data'][$year][$month][$region][$range])) {
                     $data['region_data'][$year][$month][$region][$range] = $metrics;
                }
            }
            
            // Calculate Region 'รวม' metric
            $regionTotals['average_price_per_sqm'] = ($regionTotals['total_area'] > 0) ? round($regionTotals['total_value'] / $regionTotals['total_area'], 2) : 0.00;
            
            // Add the region's total metrics under the 'รวม' price range category
            $data['region_data'][$year][$month][$region]['รวม'] = $regionTotals;

            // Accumulate for National Totals
            $nationalTotals['total_value'] += $regionTotals['total_value'];
            $nationalTotals['total_area'] += $regionTotals['total_area'];
            $nationalTotals['total_units'] += $regionTotals['total_units'];
            
             // Ensure all regions exist in the month's data
            if (!isset($data['region_data'][$year][$month][$region])) {
                $data['region_data'][$year][$month][$region] = [];
            }
        }
        
        // Calculate National 'รวมทั่วประเทศ' metrics
        $nationalTotals['average_price_per_sqm'] = ($nationalTotals['total_area'] > 0) ? round($nationalTotals['total_value'] / $nationalTotals['total_area'], 2) : 0.00;
        
        // Store National Totals under 'รวมทั่วประเทศ' with 'รวม' price range category
        $data['region_data'][$year][$month]['รวมทั่วประเทศ']['รวม'] = $nationalTotals;
    }
}


// --- 4. Comprehensive Membership Data Processing Logic ---

// 1. Fetch all users (user, admin, master)
$sqlAllUsers = "
    SELECT id, email, fullname, role
    FROM users
    ORDER BY role DESC, fullname ASC";
$stmtAllUsers = $conn->prepare($sqlAllUsers);
$stmtAllUsers->execute();
$allUsers = $stmtAllUsers->fetchAll(PDO::FETCH_ASSOC);

$userIds = [];
if (!empty($allUsers)) {
    $userIds = array_column($allUsers, 'id');
} else {
    // ไม่มี user ในระบบ, ส่งข้อมูลว่าง
    $data['membership_data'] = [];
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


// 2. Fetch all contract submissions for these users
$userIdPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
$sqlSubmissions = "
    SELECT user_id, buddhist_year, month_number
    FROM contract_submission
    WHERE user_id IN ($userIdPlaceholders)
    ORDER BY user_id, buddhist_year, month_number";
$stmtSubmissions = $conn->prepare($sqlSubmissions);
$stmtSubmissions->execute($userIds);
$submissions = $stmtSubmissions->fetchAll(PDO::FETCH_ASSOC);

// 3. Process data into the structure expected by Vue's MemberSubmission interface
$membershipData = [];

// Initialize the structure for all users
foreach ($allUsers as $user) {
    $membershipData[$user['id']] = [
        'member_id' => (string)$user['id'],
        'name' => $user['fullname'] ?? $user['email'],
        'role' => $user['role'],
        'total_submitted_count' => 0,
        'submissions_by_year' => new stdClass(), // ใช้ stdClass() เพื่อให้เป็น {}
        'submissions_in_period' => new stdClass(), // ใช้ stdClass() เพื่อให้เป็น {}
    ];
}

// Populate the submission data
foreach ($submissions as $sub) {
    $uid = (int)$sub['user_id'];
    $year = (string)$sub['buddhist_year'];
    $month = (int)$sub['month_number'];
    
    if (isset($membershipData[$uid])) {
        // Total submitted count (lifetime: assuming each row in contract_submission is one submission)
        $membershipData[$uid]['total_submitted_count'] += 1;
        
        // Submissions by year
        if (!isset($membershipData[$uid]['submissions_by_year']->{$year})) {
            $membershipData[$uid]['submissions_by_year']->{$year} = 0;
        }
        $membershipData[$uid]['submissions_by_year']->{$year} += 1;
        
        // Submissions in period (months submitted by year)
        if (!isset($membershipData[$uid]['submissions_in_period']->{$year})) {
            $membershipData[$uid]['submissions_in_period']->{$year} = [];
        }
        // Only add unique month number to the array
        if (!in_array($month, $membershipData[$uid]['submissions_in_period']->{$year})) {
             $membershipData[$uid]['submissions_in_period']->{$year}[] = $month;
        }
    }
}

// Final array conversion (only the array of users is needed)
$data['membership_data'] = array_values($membershipData);

// Return the data as JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);

// Helper function to get months in a given quarter
function getMonthsInQuarter($quarter) {
    switch ($quarter) {
        case 1:
            return [1, 2, 3];
        case 2:
            return [4, 5, 6];
        case 3:
            return [7, 8, 9];
        case 4:
            return [10, 11, 12];
        default:
            return [];
    }
}

?>