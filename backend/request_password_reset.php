<?php
include 'condb.php'; 
include 'smtp/PHPMailerAutoload.php';

// (!!! 1. ตั้งค่าพื้นฐาน !!!)
// -------------------------------------------------------------------------
$FRONTEND_RESET_URL = "https://uat.hba-sales.org/auth/reset-password"; 

// (!!! 2. ตั้งค่า RATE LIMITING !!!)
// -------------------------------------------------------------------------
$MAX_IP_ATTEMPTS = 5;      // (ตามที่คุณระบุ: 5 ครั้ง)
$IP_TIME_FRAME_MINUTES = 15; // (ตามที่คุณระบุ: 15 นาที)

$MAX_EMAIL_ATTEMPTS = 3;     // (ตามที่คุณระบุ: 3 ครั้ง)
$EMAIL_TIME_FRAME_HOURS = 1; // (ตามที่คุณระบุ: 1 ชั่วโมง)
// -------------------------------------------------------------------------


// รับข้อมูล JSON
$data = json_decode(file_get_contents('php://input'));

if (empty($data->email)) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณากรอกอีเมล']);
    exit;
}

$email = $data->email;
$ip_address = $_SERVER['REMOTE_ADDR']; // (ดึง IP ผู้ใช้)

try {
    
    // (!!! 3. ตรวจสอบ RATE LIMITING !!!)
    // -------------------------------------------------------------------------
    
    // 3.1 ล้าง Log เก่าที่หมดอายุ
    $conn->prepare("DELETE FROM password_reset_logs WHERE attempt_at < (NOW() - INTERVAL ? HOUR)")
         ->execute([$EMAIL_TIME_FRAME_HOURS]); // (ลบ Log ที่เก่ากว่า 1 ชม.)

    // 3.2 ตรวจสอบ IP (5 ครั้ง / 15 นาที)
    $check_ip_stmt = $conn->prepare(
        "SELECT COUNT(*) as attempt_count FROM password_reset_logs 
         WHERE ip_address = ? AND attempt_at > (NOW() - INTERVAL ? MINUTE)"
    );
    $check_ip_stmt->execute([$ip_address, $IP_TIME_FRAME_MINUTES]);
    $ip_attempts = $check_ip_stmt->fetch(PDO::FETCH_ASSOC);

    if ($ip_attempts && $ip_attempts['attempt_count'] >= $MAX_IP_ATTEMPTS) {
        echo json_encode(['status' => 'error', 'message' => 'คุณพยายามบ่อยเกินไป กรุณารออีก 15 นาที']);
        exit;
    }

    // 3.3 ตรวจสอบ Email (3 ครั้ง / 1 ชั่วโมง)
    $check_email_stmt = $conn->prepare(
        "SELECT COUNT(*) as attempt_count FROM password_reset_logs 
         WHERE email_attempted = ? AND attempt_at > (NOW() - INTERVAL ? HOUR)"
    );
    $check_email_stmt->execute([$email, $EMAIL_TIME_FRAME_HOURS]);
    $email_attempts = $check_email_stmt->fetch(PDO::FETCH_ASSOC);

    if ($email_attempts && $email_attempts['attempt_count'] >= $MAX_EMAIL_ATTEMPTS) {
        echo json_encode(['status' => 'error', 'message' => 'อีเมลนี้ถูกขอรีเซ็ตบ่อยเกินไป กรุณารอ 1 ชั่วโมง']);
        exit;
    }

    // 3.4 ถ้าผ่านทั้งหมด ให้ Log การพยายามครั้งนี้ไว้
    $log_stmt = $conn->prepare("INSERT INTO password_reset_logs (ip_address, email_attempted) VALUES (?, ?)");
    $log_stmt->execute([$ip_address, $email]);
    // -------------------------------------------------------------------------
    

    // (!!! 4. ตรวจสอบอีเมล (Logic ใหม่ตามที่คุณต้องการ) !!!)
    // -------------------------------------------------------------------------
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?"); // (เปลี่ยน 'users' เป็นชื่อตารางของคุณ)
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // (แจ้งเตือนตามจริงว่าไม่พบอีเมล)
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบอีเมลนี้ในระบบ กรุณากรอกอีเมลอีกครั้ง']);
        exit; 
    }
    // -------------------------------------------------------------------------


    // (!!! 5. สร้าง Token และส่งอีเมล (Logic เดิม) !!!)
    // -------------------------------------------------------------------------
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600); // 1 ชั่วโมง

    $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?"); // (เปลี่ยน 'users')
    $stmt->execute([$token, $expires, $email]);

    $reset_link = $FRONTEND_RESET_URL . "?token=" . $token;

    $mail = new PHPMailer;
    // $mail->SMTPDebug = 2; // (!!! ต้องปิดไว้เสมอ !!!)

     $mail->isSMTP();
$mail->Host = 'hba-sales.org';    
$mail->SMTPAuth = true;
$mail->Username = 'info@hba-sales.org'; 
$mail->Password = '@hba-sales.org';      
$mail->SMTPSecure = 'ssl'; 
$mail->Port = 465;       // หรือ 465 ถ้าเป็น ssl
$mail->setFrom('info@hba-sales.org', 'HBA Sales');
   // ... (โค้ดส่วนสร้าง $reset_link อยู่ด้านบน) ...

    $mail->addAddress($email);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    // (!!! เริ่มส่วนที่แก้ไขถ้อยคำให้เป็นทางการ !!!)

    // 1. ตั้งค่า Subject (หัวเรื่อง)
    $mail->Subject = '[HBA Sales] คำร้องขอตั้งรหัสผ่านใหม่สำหรับบัญชีผู้ใช้งาน';

    // (สร้างคำทักทาย)
    $greeting = "เรียน ท่านผู้ใช้งาน,";

    // 2. ตั้งค่า Body (เนื้อหาอีเมลแบบ HTML)
    $mail->Body = '
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>คำร้องขอตั้งรหัสผ่านใหม่</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333333;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #cccccc; margin-top: 30px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <tr>
            <td align="center" bgcolor="#ffffff" style="padding: 30px 0 20px 0; background-color: #ffffff;">
                <img src="https://uat.hba-sales.org/assets/img-logo_0-DG1q1sBC.png" alt="HBA Sales Logo" width="180" style="display: block; border: 0;">
            </td>
        </tr>
        
        <tr>
            <td bgcolor="#ffffff" style="padding: 20px 30px 40px 30px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td style="font-size: 20px; font-weight: bold; padding-bottom: 20px; color: #004a99; text-align: center;">
                            แจ้งเตือนการตั้งรหัสผ่านใหม่
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 16px; padding-bottom: 25px;">
                            ' . $greeting . '<br><br>
                            ระบบได้รับคำร้องขอตั้งรหัสผ่านใหม่สำหรับบัญชีของท่าน (อีเมล: ' . htmlspecialchars($email) . ')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 25px;">
                            หากท่านเป็นผู้ดำเนินการส่งคำร้องขอนี้ กรุณาดำเนินการต่อโดยคลิกปุ่มด้านล่างเพื่อตั้งรหัสผ่านใหม่:
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding-bottom: 25px;">
                            <a href="' . $reset_link . '" target="_blank" style="background-color: #0056b3; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 5px; font-size: 16px; font-weight: bold; display: inline-block;">
                                ดำเนินการตั้งรหัสผ่านใหม่
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; color: #555555; padding-bottom: 25px;">
                            หากลิงก์ในปุ่มไม่ทำงาน กรุณาคัดลอก URL นี้ไปวางในเบราว์เซอร์ของท่าน:<br>
                            <span style="font-size: 12px; color: #888888; word-break: break-all;">' . $reset_link . '</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 14px; color: #555555; border-top: 1px solid #eeeeee; padding-top: 20px;">
                            ลิงก์นี้จะหมดอายุภายใน <b>1 ชั่วโมง</b>
                            <br><br>
                            <b style="color: #c00;">ข้อควรทราบ:</b> หากท่านไม่ได้เป็นผู้ดำเนินการร้องขอนี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้ บัญชีของท่านยังคงปลอดภัย
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        
        <tr>
            <td bgcolor="#f4f4f4" style="padding: 30px 30px 30px 30px; color: #888888; font-size: 12px; text-align: center; line-height: 1.5;">
                <b>สมาคมธุรกิจรับสร้างบ้าน</b><br>
                2 ซอย ลาดปลาเค้า 10 Lat Phrao, Bangkok 10230<br>
                เบอร์โทร: 02 570 0153 | อีเมล: info@hba-sales.org<br><br>
                
                อีเมลนี้เป็นอีเมลอัตโนมัติจากระบบ กรุณาอย่าตอบกลับ<br>
                &copy; ' . date("Y") . ' HBA Sales. All rights reserved.
            </td>
        </tr>
    </table>
</body>
</html>
';

    $mail->AltBody = "เรียน ท่านผู้ใช้งาน,\n\n"
                   . "ระบบได้รับคำร้องขอตั้งรหัสผ่านใหม่สำหรับบัญชีของท่าน (อีเมล: " . $email . ")\n\n"
                   . "กรุณาเปิดลิงก์นี้ในเบราว์เซอร์เพื่อตั้งรหัสผ่านใหม่:\n"
                   . $reset_link . "\n\n"
                   . "ลิงก์นี้จะหมดอายุใน 1 ชั่วโมง\n\n"
                   . "หากท่านไม่ได้เป็นผู้ดำเนินการร้องขอนี้ กรุณาอย่าตอบกลับต่ออีเมลฉบับนี้\n\n"
                   . "ขอแสดงความนับถือ,\n"
                   . "ฝ่ายดูแลระบบ HBA Sales\n\n"
                   . "--- ข้อมูลติดต่อ ---\n" 
                   . "สมาคมธุรกิจรับสร้างบ้าน\n"
                   . "เบอร์โทร: 02-570-0153 | อีเมล: info@hba-sales.org";

    if(!$mail->send()) {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการส่งอีเมล: ' . $mail->ErrorInfo]);
    } else {
        // (ส่งสำเร็จจริง)
        echo json_encode(['status' => 'success', 'message' => 'ส่งลิงก์รีเซ็ตสำเร็จ']);
    }

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage()]);
}
?>
