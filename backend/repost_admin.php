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
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid year']);
    exit;
}

$whereConditions = [];
$params = [];

// Adding year filtering to the query
$placeholders = implode(',', array_fill(0, count($years), '?'));
$sql = "
   SELECT s.buddhist_year, 
          d.price_range,
          SUM(d.total_value) AS value,
          MONTH(s.submitted_at) AS month,          
          MONTHNAME(s.submitted_at) AS month_name,  
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

// Prepare parameters array with selected years, months, and quarters (if any)
$params = array_merge($params, $years, $months, $quarters);

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
          ORDER BY s.buddhist_year ASC, MONTH(s.submitted_at) ASC, s.quarter ASC, d.price_range ASC";

// Prepare the statement and execute with the correct parameters
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize data for each section
$data = [
    'yearly_data' => [],
    'monthly_data' => [],
    'quarterly_data' => [],
    'quarterly_month_comparison' => [],
    'region_data' => [],
    'membership_data' => [] // This will be populated by the new logic
];

// Process results from the database
foreach ($results as $row) {
    $year = $row['buddhist_year'];
    $month = $row['month']; // Extract month
    $quarter = $row['quarter']; // Extract quarter
    $range = $row['price_range'];
    $value = (float)$row['value'];
    $region = $row['region'];

    // Process yearly data
    if (!isset($data['yearly_data'][$year])) {
        $data['yearly_data'][$year] = [];
    }
    if (!isset($data['yearly_data'][$year][$range])) {
        $data['yearly_data'][$year][$range] = 0;
    }
    $data['yearly_data'][$year][$range] += $value;

    // Process monthly data
    if (!isset($data['monthly_data'][$year])) {
        $data['monthly_data'][$year] = [];
    }
    if (!isset($data['monthly_data'][$year][$month])) {
        $data['monthly_data'][$year][$month] = [];
    }
    if (!isset($data['monthly_data'][$year][$month][$range])) {
        $data['monthly_data'][$year][$month][$range] = 0;
    }
    $data['monthly_data'][$year][$month][$range] += $value;

    // Process quarterly data
    if (!isset($data['quarterly_data'][$year])) {
        $data['quarterly_data'][$year] = [];
    }
    if (!isset($data['quarterly_data'][$year][$quarter])) {
        $data['quarterly_data'][$year][$quarter] = [];
    }
    if (!isset($data['quarterly_data'][$year][$quarter][$range])) {
        $data['quarterly_data'][$year][$quarter][$range] = 0;
    }
    $data['quarterly_data'][$year][$quarter][$range] += $value;

    // Process region data and group by region, year, and quarter
    if (!isset($data['region_data'][$region])) {
        $data['region_data'][$region] = [];
    }
    if (!isset($data['region_data'][$region][$year])) {
        $data['region_data'][$region][$year] = [];
    }
    if (!isset($data['region_data'][$region][$year][$quarter])) {
        $data['region_data'][$region][$year][$quarter] = 0;  // Initialize the value for this region, year, and quarter
    }

    // Add the value for this region, year, and quarter
    $data['region_data'][$region][$year][$quarter] += $value;

    // Process quarterly month comparison data (new logic for monthly data within each quarter)
    if (!isset($data['quarterly_month_comparison'][$year])) {
        $data['quarterly_month_comparison'][$year] = [];
    }
    if (!isset($data['quarterly_month_comparison'][$year][$quarter])) {
        $data['quarterly_month_comparison'][$year][$quarter] = [];
    }

    // Get the months in the selected quarter
    $monthsInQuarter = getMonthsInQuarter($quarter);

    // Process each month within the quarter
    foreach ($monthsInQuarter as $monthIndex) {
        // Ensure that the month is properly set as an array before using it
        if (!isset($data['quarterly_month_comparison'][$year][$quarter][$monthIndex])) {
            $data['quarterly_month_comparison'][$year][$quarter][$monthIndex] = [];
        }

        // Ensure that the range for the month is properly set as an array before using it
        if (!isset($data['quarterly_month_comparison'][$year][$quarter][$monthIndex][$range])) {
            $data['quarterly_month_comparison'][$year][$quarter][$monthIndex][$range] = 0;
        }

        // Add value to the corresponding month and range
        $data['quarterly_month_comparison'][$year][$quarter][$monthIndex][$range] += $value;
    }
}

// If no region data was found, return an empty array
if (empty($data['region_data'])) {
    $data['region_data'] = [];
}


// Add total sums for each section (yearly, monthly, quarterly, and quarterly month comparison)
foreach ($data['yearly_data'] as $year => $rangesData) {
    $total = 0;
    foreach ($rangesData as $range => $value) {
        $total += $value;
    }
    $data['yearly_data'][$year]['รวม'] = $total;
}

foreach ($data['monthly_data'] as $year => $monthsData) {
    foreach ($monthsData as $month => $rangeData) {
        $total = 0;
        foreach ($rangeData as $range => $value) {
            $total += $value;
        }
        $data['monthly_data'][$year][$month]['รวม'] = $total;
    }
}

foreach ($data['quarterly_data'] as $year => $quartersData) {
    foreach ($quartersData as $quarter => $rangeData) {
        $total = 0;
        foreach ($rangeData as $range => $value) {
            $total += $value;
        }
        $data['quarterly_data'][$year][$quarter]['รวม'] = $total;
    }
}

// Add totals for the quarterly month comparison
foreach ($data['quarterly_month_comparison'] as $year => $quartersData) {
    foreach ($quartersData as $quarter => $monthsData) {
        $total = 0;
        foreach ($monthsData as $month => $rangeData) {
            foreach ($rangeData as $range => $value) {
                $total += $value;
            }
        }
        $data['quarterly_month_comparison'][$year][$quarter]['รวม'] = $total;
    }
}

// Ensure that missing months and quarters are returned as 0
foreach ($data['monthly_data'] as $year => $monthsData) {
    foreach ($monthsData as $month => $rangeData) {
        foreach ($categoryOrder as $range) {
            if (!isset($data['monthly_data'][$year][$month][$range])) {
                $data['monthly_data'][$year][$month][$range] = 0;
            }
        }
    }
}

foreach ($data['quarterly_data'] as $year => $quartersData) {
    foreach ($quartersData as $quarter => $rangeData) {
        foreach ($categoryOrder as $range) {
            if (!isset($data['quarterly_data'][$year][$quarter][$range])) {
                $data['quarterly_data'][$year][$quarter][$range] = 0;
            }
        }
    }
}
foreach ($data['quarterly_month_comparison'] as $year => $quartersData) {
    foreach ($quartersData as $quarter => $monthsData) {
        // ตรวจสอบให้แน่ใจว่าแต่ละเดือนในไตรมาสมีการสร้างขึ้น
        $monthsInQuarter = getMonthsInQuarter($quarter);
        foreach ($monthsInQuarter as $monthIndex) {
            // ตรวจสอบว่าเดือนนั้นๆ ถูกสร้างขึ้นแล้วหรือยัง
            if (!isset($data['quarterly_month_comparison'][$year][$quarter][$monthIndex])) {
                $data['quarterly_month_comparison'][$year][$quarter][$monthIndex] = [];
            }

            // คำนวณผลรวมของมูลค่าทั้งหมดในแต่ละเดือน
            $totalMonthValue = 0;

            // ตรวจสอบเพื่อดึงข้อมูลจาก 'monthly_data' สำหรับแต่ละเดือน
            if (isset($data['monthly_data'][$year][$monthIndex])) {
                // คำนวณผลรวมของแต่ละ category (ราคาบ้าน) ในเดือนนั้น
                foreach ($data['monthly_data'][$year][$monthIndex] as $range => $value) {
                    if ($range !== 'รวม') { // ตรวจสอบเพื่อไม่รวม 'รวม' ในการคำนวณ
                        $totalMonthValue += $value;
                    }
                }
            }

            // เก็บแค่ผลรวมในเดือนนั้นๆ
            $data['quarterly_month_comparison'][$year][$quarter][$monthIndex] = [
                'รวม' => $totalMonthValue // เก็บแค่ผลรวม
            ];
        }
    }
}

foreach ($data['region_data'] as $region => $yearsData) {
    foreach ($yearsData as $year => $quartersData) {
        $total = 0;
        foreach ($quartersData as $quarter => $value) {
            $total += $value;
        }
        $data['region_data'][$region][$year]['รวม'] = $total;  // Add totals for region and year
    }
}


// --- START: New Membership Data Processing Logic ---

// 1. Get total count of 'user' role members
$sqlTotalUsers = "SELECT COUNT(id) AS total_users FROM users WHERE role = 'user'";
$stmtTotalUsers = $conn->prepare($sqlTotalUsers);
$stmtTotalUsers->execute();
$totalUsersResult = $stmtTotalUsers->fetch(PDO::FETCH_ASSOC);
$totalUsers = $totalUsersResult['total_users'];

// 2. Determine target months for iteration based on input
// 2. Determine target months for iteration based on input
$targetMonths = [];
if (!empty($quarters)) { // <-- ตรงนี้คือการตรวจสอบว่ามีการส่งค่าไตรมาสมาหรือไม่
    foreach ($quarters as $quarterNum) {
        // ถ้ามีการส่งค่าไตรมาสมา จะแปลงไตรมาสนั้นๆ ให้เป็นเดือน
        $targetMonths = array_merge($targetMonths, getMonthsInQuarter($quarterNum));
    }
    $targetMonths = array_unique($targetMonths); // ลบเดือนที่ซ้ำกัน
    sort($targetMonths); // จัดเรียงเดือน
} elseif (!empty($months)) { // ถ้าไม่มีไตรมาส แต่มีเดือน ก็ใช้เดือนที่ระบุ
    $targetMonths = $months;
} else { // ถ้าไม่มีทั้งไตรมาสและเดือน ก็ใช้ทั้ง 12 เดือน
    $targetMonths = range(1, 12); 
}
// 3. Fetch distinct user_ids who submitted for each selected year and month
$submittedUserIdsPerMonthYear = [];
if (!empty($years)) { // Only query if years are selected
    $yearPlaceholdersForSubmitted = implode(',', array_fill(0, count($years), '?'));
    
    $submittedSql = "
        SELECT DISTINCT user_id, buddhist_year, MONTH(submitted_at) AS month_num
        FROM contract_submission
        WHERE buddhist_year IN ($yearPlaceholdersForSubmitted)
    ";

    $submittedParams = $years;
if (!empty($targetMonths)) {
        $monthPlaceholdersForSubmitted = implode(',', array_fill(0, count($targetMonths), '?'));
        $submittedSql .= " AND MONTH(submitted_at) IN ($monthPlaceholdersForSubmitted)"; // <-- กรองตามเดือนที่ได้จากไตรมาส
        $submittedParams = array_merge($submittedParams, $targetMonths);
    }

    $stmtSubmitted = $conn->prepare($submittedSql);
    $stmtSubmitted->execute($submittedParams);
    $submittedResults = $stmtSubmitted->fetchAll(PDO::FETCH_ASSOC);

    foreach ($submittedResults as $row) {
        $y = $row['buddhist_year'];
        $m = $row['month_num'];
        $uid = $row['user_id'];
        if (!isset($submittedUserIdsPerMonthYear[$y])) $submittedUserIdsPerMonthYear[$y] = [];
        if (!isset($submittedUserIdsPerMonthYear[$y][$m])) $submittedUserIdsPerMonthYear[$y][$m] = [];
        $submittedUserIdsPerMonthYear[$y][$m][$uid] = true; // Use associative array for O(1) lookup
    }
}

// 4. Initialize and populate membership data
$membershipData = [
    'monthly_membership_data' => [],
    'overall_total_users' => $totalUsers // Total users with 'user' role
];

foreach ($years as $year) {
    if (!isset($membershipData['monthly_membership_data'][$year])) {
        $membershipData['monthly_membership_data'][$year] = [];
    }
    foreach ($targetMonths as $month) {
        $filledCount = isset($submittedUserIdsPerMonthYear[$year][$month]) ? count($submittedUserIdsPerMonthYear[$year][$month]) : 0;
        $notFilledCount = $totalUsers - $filledCount;

        $membershipData['monthly_membership_data'][$year][$month] = [
            'filled_count' => $filledCount,
            'not_filled_count' => $notFilledCount,
            'total_users_for_month' => $totalUsers // Total users remains constant for each month
        ];
    }
}

// Add the membership data to the final response
$data['membership_data'] = $membershipData;

// --- END: New Membership Data Processing Logic ---


// Return the data as JSON
echo json_encode($data, JSON_UNESCAPED_UNICODE);


// Helper function to get months in a given quarter
function getMonthsInQuarter($quarter) {
    switch ($quarter) {
        case 1:
            return [1, 2, 3];  // January, February, March
        case 2:
            return [4, 5, 6];  // April, May, June
        case 3:
            return [7, 8, 9];  // July, August, September
        case 4:
            return [10, 11, 12];  // October, November, December
        default:
            return [];  // Return an empty array if an invalid quarter is provided
    }
}

?>