<?php
include 'condb.php'; 
$data = json_decode(file_get_contents('php://input'));

if (empty($data->token) || empty($data->password) || empty($data->confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$token = $data->token;
$password = $data->password;
$confirmPassword = $data->confirmPassword;

if ($password !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Token ไม่ถูกต้องหรือหมดอายุการใช้งาน กรุณาขอรีเซ็ตรหัสผ่านใหม่อีกครั้ง']);
        exit;
    }
	
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
    $stmt->execute([$hashed_password, $user['id']]);

    echo json_encode(['status' => 'success', 'message' => 'ตั้งรหัสผ่านใหม่สำเร็จ']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดฝั่งเซิร์ฟเวอร์: ' . $e->getMessage()]);
}
?>