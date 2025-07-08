<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");



require 'condb.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $profileImageUrl = null;


  if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_image']['tmp_name'];
    $fileName = $_FILES['profile_image']['name'];
    $uploadDir = 'uploads/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = uniqid() . '-' . basename($fileName);
    $filePath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $filePath)) {
        $profileImageUrl = $filePath;
    } else {
        $profileImageUrl = null;
    }
}



    // Get data from FormData
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user'; 
    $member_type = $_POST['member_type'] ?? '';

    // Validate if all fields are filled
    if (empty($fullname)) {
        echo json_encode(["message" => "Fullname is required"]);
        exit;
    }

    if (empty($email)) {
        echo json_encode(["message" => "Email is required"]);
        exit;
    }

    if (empty($password)) {
        echo json_encode(["message" => "Password is required"]);
        exit;
    }

    // If all required data is provided
    try {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Prepare the SQL query
        $stmt = $conn->prepare("INSERT INTO users (email, fullname, password, profile_image, role, member_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$email, $fullname, $hashedPassword, $profileImageUrl, $role, $member_type]);

        echo json_encode(["message" => "Registration successful"]);
    } catch (Exception $e) {
        echo json_encode(["message" => "Failed to register: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["message" => "Invalid request method"]);
}
?>
