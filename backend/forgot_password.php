<?php

header('Content-Type: application/json');
include('condb.php');
require_once('smtp/PHPMailerAutoload.php'); 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$data = json_decode(file_get_contents("php://input"));
if (!isset($data->email) || empty($data->email)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกอีเมล']);
    exit();
}

$email = mysqli_real_escape_string($con, $data->email);


$sql_check = "SELECT m_id FROM members WHERE m_email = '$email' LIMIT 1";
$result_check = mysqli_query($con, $sql_check);

if (mysqli_num_rows($result_check) == 0) {
   
    echo json_encode(['status' => 'success', 'message' => 'หากมีอีเมลนี้ในระบบ ลิงก์สำหรับเปลี่ยนรหัสผ่านจะถูกส่งไป']);
    exit();
}


$reset_token = bin2hex(random_bytes(32)); 
$token_expiry = date("Y-m-d H:i:s", time() + 3600); 

// 3. อัปเดตฐานข้อมูล
$sql_update = "UPDATE members SET reset_token = '$reset_token', token_expiry = '$token_expiry' WHERE m_email = '$email'";
mysqli_query($con, $sql_update);

// 4. สร้างลิงก์รีเซ็ต - **ต้องแก้ไข URL ให้เป็นโดเมนจริงของคุณ!**
// URL ต้องชี้ไปยังหน้า ResetPassword.vue ที่คุณจะสร้าง
$reset_link = "http://yourwebsite.com/auth/reset-password?token=" . urlencode($reset_token) . "&email=" . urlencode($email);

// 5. ส่งอีเมลด้วย PHPMailer
$mail = new PHPMailer(true);
try {
    // **ตั้งค่า SMTP: ต้องใช้ข้อมูลที่ถูกต้องของคุณ**
    $mail->IsSMTP();
    $mail->SMTPDebug = 0;
    $mail->SMTPAuth = true;
    $mail->SMTPSecure = 'tls'; // หรือ 'ssl'
    $mail->Host = "smtp.example.com"; // แก้ไข
    $mail->Port = 587; // แก้ไข
    $mail->Username = "your_email@example.com"; // แก้ไข
    $mail->Password = "your_email_password"; // แก้ไข

    $mail->SetFrom('no-reply@yourwebsite.com', 'System Admin');
    $mail->AddAddress($email);
    $mail->IsHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Reset Password Request - ' . $email;
    $mail->Body    = "คุณได้ทำการขอเปลี่ยนรหัสผ่าน กรุณาคลิกที่ลิงก์ด้านล่างเพื่อดำเนินการต่อ:<br><br>" .
                     "<a href=\"$reset_link\">คลิกที่นี่เพื่อเปลี่ยนรหัสผ่าน</a><br><br>" .
                     "ลิงก์นี้จะหมดอายุภายใน 1 ชั่วโมง หากคุณไม่ได้ร้องขอการเปลี่ยนรหัสผ่านนี้ โปรดละเว้นอีเมลนี้";
    $mail->AltBody = "กรุณาใช้ลิงก์นี้เพื่อเปลี่ยนรหัสผ่าน: " . $reset_link;

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'ลิงก์สำหรับเปลี่ยนรหัสผ่านได้ถูกส่งไปยังอีเมลของคุณแล้ว']);

} catch (Exception $e) {
    // ส่งข้อความสำเร็จทั่วไป แม้จะส่งอีเมลไม่สำเร็จ เพื่อป้องกันการคาดเดาอีเมล
    error_log("Mailer Error: " . $mail->ErrorInfo);
    echo json_encode(['status' => 'success', 'message' => 'ลิงก์สำหรับเปลี่ยนรหัสผ่านได้ถูกส่งไปยังอีเมลของคุณแล้ว']);
}

mysqli_close($con);
?>