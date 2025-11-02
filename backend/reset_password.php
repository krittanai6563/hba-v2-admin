<?php


header('Content-Type: application/json');
include('condb.php');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->token) || !isset($data->new_password) || empty($data->email) || empty($data->token) || empty($data->new_password)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit();
}

$email = mysqli_real_escape_string($con, $data->email);
$token = mysqli_real_escape_string($con, $data->token);
$new_password = $data->new_password;

// 1. ตรวจสอบ Token และเวลาหมดอายุ
$current_time = date("Y-m-d H:i:s");
$sql_validate = "SELECT m_id FROM members WHERE m_email = '$email' AND reset_token = '$token' AND token_expiry > '$current_time' LIMIT 1";
$result_validate = mysqli_query($con, $sql_validate);

if (mysqli_num_rows($result_validate) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'ลิงก์เปลี่ยนรหัสผ่านไม่ถูกต้อง หรือหมดอายุแล้ว']);
    exit();
}

// 2. Hash รหัสผ่านใหม่อย่างปลอดภัย
// ตารางของคุณน่าจะใช้คอลัมน์ m_pass สำหรับเก็บรหัสผ่าน
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// 3. อัปเดตฐานข้อมูล (เปลี่ยนรหัสผ่าน และ ล้าง Token)
$sql_update = "UPDATE members SET m_pass = '$hashed_password', reset_token = NULL, token_expiry = NULL WHERE m_email = '$email'";
$result_update = mysqli_query($con, $sql_update);

if ($result_update) {
    echo json_encode(['status' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จแล้ว']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน']);
}

mysqli_close($con);
?>