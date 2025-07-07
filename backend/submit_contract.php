<?php

require 'condb.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$userId = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$month = $data['month_number'] ?? null;
$quarter = $data['quarter'] ?? null;
$regions = $data['regions'] ?? [];
$role = $data['role'] ?? 'user';

$loggedInUserId = $userId; 

if ($role !== 'admin' && $userId != $loggedInUserId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$userId || !$year || !$month || !$quarter) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

try {

    $stmt = $conn->prepare("SELECT id FROM contract_submission WHERE user_id=? AND buddhist_year=? AND month_number=? AND quarter=?");
    $stmt->execute([$userId, $year, $month, $quarter]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $submissionId = $existing['id'];

        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM contract_detail WHERE contract_submission_id=? AND region=? AND price_range=?");

       $updateStmt = $conn->prepare("UPDATE contract_detail 
    SET unit=?, total_value=?, area=?, price_per_sqm=? 
    WHERE contract_submission_id=? AND region=? AND price_range=?");


        $insertStmt = $conn->prepare("INSERT INTO contract_detail (contract_submission_id, region, price_range, unit, total_value, area)
                                      VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($regions as $region) {
            $regionName = $region['name'];
            foreach ($region['rows'] as $row) {
                $label = $row['label'];
                $unit = (int)$row['unit'];
                $value = (float)$row['value'];
                $area = (float)$row['area'];

                // ตรวจว่ามีแถวนี้ใน DB แล้วหรือยัง
                $checkStmt->execute([$submissionId, $regionName, $label]);
                $exists = $checkStmt->fetchColumn() > 0;

                if ($exists) {
                  $pricePerSqm = $area > 0 ? round($value / $area, 2) : 0;
$updateStmt->execute([$unit, $value, $area, $pricePerSqm, $submissionId, $regionName, $label]);

                } else {
                    $insertStmt->execute([$submissionId, $regionName, $label, $unit, $value, $area]);
                }
            }
        }

        echo json_encode(['message' => 'Updated existing submission']);
    } else {
        
        $stmt = $conn->prepare("INSERT INTO contract_submission (user_id, buddhist_year, month_number, quarter)
                                VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $year, $month, $quarter]);
        $submissionId = $conn->lastInsertId();

       $insertStmt = $conn->prepare("INSERT INTO contract_detail (contract_submission_id, region, price_range, unit, total_value, area, price_per_sqm)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");


        foreach ($regions as $region) {
            $regionName = $region['name'];
            foreach ($region['rows'] as $row) {
                $label = $row['label'];
                $unit = (int)$row['unit'];
                $value = (float)$row['value'];
                $area = (float)$row['area'];

              $pricePerSqm = $area > 0 ? round($value / $area, 2) : 0;
$insertStmt->execute([$submissionId, $regionName, $label, $unit, $value, $area, $pricePerSqm]);

            }
        }

        echo json_encode(['message' => 'Inserted new submission']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'detail' => $e->getMessage()]);
}



// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     header("Access-Control-Allow-Origin: *");
//     header("Access-Control-Allow-Headers: Content-Type, Authorization");
//     header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
//     http_response_code(200);
//     exit();
// }

// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Headers: Content-Type, Authorization");
// header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
// header('Content-Type: application/json');

// require 'condb.php';
// header('Content-Type: application/json');

// $data = json_decode(file_get_contents("php://input"), true);


// if (!$data || !isset($data['user_id'], $data['buddhist_year'], $data['month_number'], $data['quarter'], $data['regions'])) {
//     http_response_code(400);
//     echo json_encode(["message" => "Invalid input"]);
//     exit;
// }

// $user_id = (int) $data['user_id'];
// $buddhist_year = (int) $data['buddhist_year'];
// $month_number = (int) $data['month_number'];
// $quarter = (int) $data['quarter'];
// $regions = $data['regions'];

// try {
  
//     $stmt1 = $conn->prepare("INSERT INTO contract_submission (user_id, buddhist_year, month_number, quarter) VALUES (?, ?, ?, ?)");
//     $stmt1->execute([$user_id, $buddhist_year, $month_number, $quarter]);
//     $submission_id = $conn->lastInsertId();

  
//   $stmt2 = $conn->prepare("
//   INSERT INTO contract_detail (contract_submission_id, region, price_range, unit, total_value, area)
//   VALUES (?, ?, ?, ?, ?, ?)
// ");

// foreach ($regions as $region) {
//     $regionName = $region['name'];
//     $rows = $region['rows'];

//     foreach ($rows as $row) {
//         $label = $row['label'];
//         $unit = (int) $row['unit'];
//         $value = (float) $row['value'];
//         $area = (float) $row['area'];

       
//         if ($region['contractStatus'] === 'none') {
//             $unit = 0;
//             $value = 0;
//             $area = 0;
//         }

//         $stmt2->execute([$submission_id, $regionName, $label, $unit, $value, $area]);
//     }
// }



//     echo json_encode(["message" => "Success"]);
// } catch (PDOException $e) {
//     http_response_code(500);
//     echo json_encode(["message" => "Database error", "error" => $e->getMessage()]);
// }
// เพิ่มเติมข้อมูล
