<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'condb.php'; // เรียกการเชื่อมต่อฐานข้อมูล
include('smtp/PHPMailerAutoload.php');

// รับค่าจาก POST
$data = json_decode(file_get_contents("php://input"), true);

// รับ user_id จาก frontend
$user_id = $data['user_id'] ?? null;
$year = $data['buddhist_year'] ?? null;
$month_number = $data['month_number'] ?? null;

if (!$user_id || !$year || !$month_number) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

// ตรวจสอบว่าอยู่ในช่วงวันที่ 5-10 หรือไม่
$currentDay = date('j'); // วันในเดือนนี้
if ($currentDay < 1 || $currentDay > 10) {
    echo json_encode(['error' => 'Today is not within the 5th to 10th.']);
    exit;
}

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
    WHERE u.role IN ('user', 'admin', 'master')
    AND u.id = :user_id
    GROUP BY u.id
";

try {
    // เตรียม statement และ bind parameter
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':buddhist_year', $year);
    $stmt->bindParam(':month_number', $month_number);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // ส่งอีเมลถ้าสถานะยังไม่กรอกข้อมูล
        if ($result['status'] === 'ยังไม่กรอกข้อมูล') {
            sendEmail($result['email'], $result['fullname']);
        }

        // ส่งข้อมูลกลับไปที่ Frontend
        echo json_encode([
            'status' => $result['status'],
            'contractValue' => $result['contractValue']
        ]);
    } else {
        echo json_encode(['error' => 'No data found for the user']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

// ฟังก์ชันส่งอีเมล
function sendEmail($to, $fullname) {
    // กำหนดหัวข้ออีเมล
    $subject = "กรุณากรอกข้อมูลให้ครบถ้วน";

    // กำหนดข้อความในอีเมล
    $currentYear = date("Y") + 543;  // ปีพุทธศักราช
    $currentMonth = date("n");  // เดือนปัจจุบัน
    $currentMonthName = getMonthName($currentMonth);  // ชื่อเดือน
    $message = "
    <html>
    <head>
        <title>แจ้งเตือนการกรอกข้อมูล</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                color: #333;
                line-height: 1.6;
            }
            .header {
                background-color: #f4f4f4;
                padding: 20px;
                text-align: center;
                border-bottom: 2px solid #ddd;
            }
            .content {
                padding: 20px;
            }
            .footer {
                background-color: #f4f4f4;
                padding: 10px;
                text-align: center;
                font-size: 0.9em;
                color: #555;
                margin-top: 20px;
            }
            .important {
                font-weight: bold;
                color: #ff6600;
            }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>แจ้งเตือนการกรอกข้อมูล</h2>
        </div>

        <div class='content'>
            <p>เรียนคุณ $fullname,</p>
            <p>ขอแจ้งเตือนว่าท่านยังไม่ได้กรอกข้อมูลในระบบของเรา กรุณากรอกข้อมูลให้ครบถ้วนเพื่อให้สามารถดำเนินการต่อไปได้</p>
            <p class='important'>กรุณากรอกข้อมูลให้เสร็จสมบูรณ์ภายในวันที่ 10 เดือน $currentMonthName ปี $currentYear</p>
            <p>หากท่านมีข้อสงสัยหรือไม่สามารถกรอกข้อมูลได้ กรุณาติดต่อทีมงานของเราที่อีเมลนี้</p>
        </div>

        <div class='footer'>
            <p>ขอแสดงความนับถือ,</p>
            <p>สมาคมธุรกิจรับสร้างบ้าน</p>
        </div>
    </body>
    </html>
    ";

    // ตั้งค่า PHPMailer
    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls';
    $mail->Host = "smtp.gmail.com";
    $mail->Port = 587;
    $mail->IsHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Username = "hbasales2025@gmail.com"; 
    $mail->Password = "hhvs dzjf ipcv gcpn";  // ระบุรหัสผ่าน
    $mail->SetFrom("hbasales2025@gmail.com");
    $mail->Subject = $subject;
    $mail->Body = $message;
    $mail->AddAddress($to);

    // ตัวเลือก SMTP
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => false
        )
    );

    // ส่งอีเมล
    if (!$mail->Send()) {
        echo json_encode(['error' => "Error: " . $mail->ErrorInfo]);
    } else {
        echo json_encode(['message' => "Email sent successfully."]);
    }
}

// ฟังก์ชันเพื่อแปลงหมายเลขเดือนเป็นชื่อเดือนภาษาไทย
function getMonthName($month) {
    $months = [
        1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 5 => "พฤษภาคม", 6 => "มิถุนายน",
        7 => "กรกฎาคม", 8 => "สิงหาคม", 9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
    ];

    return $months[$month];
}
?>


