<?php
ini_set('session.gc_maxlifetime', 18000); 

session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require 'condb.php';

$data = json_decode(file_get_contents("php://input"));

define('BASE_UPLOAD_URL', 'https://c44d-2405-9800-b861-96e-d38-cc71-74cd-d0c1.ngrok-free.app/package/backend/uploads/');

if ($data && isset($data->email) && isset($data->password)) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data->email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data->password, $user['password'])) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'fullname' => $user['fullname'],
                'email' => $user['email'],
                'role' => $user['role'] ?? 'user',
                'profile_image' => $user['profile_image'] ?? ''
            ];

            $profile_image = trim($user['profile_image'] ?? '');

        if (!empty($profile_image)) {
    if (preg_match('/^https?:\/\//', $profile_image)) {
        $profileImageUrl = $profile_image;
    } else {
        $cleanedPath = preg_replace('#^(uploads/)+#', '', $profile_image);
        $profileImageUrl = BASE_UPLOAD_URL . $cleanedPath;
    }
} else {
    $profileImageUrl = null;  // หรือ '' แทนการส่ง URL รูป default
}


            echo json_encode([
                "message" => "Login successful",
                "user" => [
                    "id" => $user['id'],
                    "fullname" => $user['fullname'],
                    "email" => $user['email'],
                    "role" => $user['role'] ?? 'user',
                    "profile_image" => $profileImageUrl
                ]
            ]);
            exit();
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
            exit();
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Database error", "error" => $e->getMessage()]);
        exit();
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Invalid request"]);
    exit();
}
