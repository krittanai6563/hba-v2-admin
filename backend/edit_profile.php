<?php
// CORS headers
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);  // Handle preflight request
    exit();
}

require 'condb.php'; // Make sure this file contains the correct PDO connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve POST data
    $id = $_POST['id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $member_type = $_POST['member_type'];

    // Handle file upload (if any)
    $uploadFilePath = null;
    if (isset($_FILES['profile_image'])) {
        $file = $_FILES['profile_image'];
        $uploadDir = 'uploads/';
        $fileName = basename($file['name']);
        $uploadFilePath = $uploadDir . $fileName;
        
        // Move the uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
            // File successfully uploaded
        } else {
            // Handle upload failure (optional)
            http_response_code(500);
            echo json_encode(["message" => "Failed to upload the file"]);
            exit();
        }
    }

    try {
        // SQL Query to update user data
        $sql = "UPDATE users SET fullname = ?, email = ?, role = ?, member_type = ?";
        
        // Add profile image path if uploaded
        if ($uploadFilePath) {
            $sql .= ", profile_image = ?";
        }

        $sql .= " WHERE id = ?";

        // Prepare the SQL statement
        $stmt = $conn->prepare($sql);

        // Bind parameters
        if ($uploadFilePath) {
            $stmt->bindValue(1, $fullname, PDO::PARAM_STR);
            $stmt->bindValue(2, $email, PDO::PARAM_STR);
            $stmt->bindValue(3, $role, PDO::PARAM_STR);
            $stmt->bindValue(4, $member_type, PDO::PARAM_STR);
            $stmt->bindValue(5, $uploadFilePath, PDO::PARAM_STR);
            $stmt->bindValue(6, $id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(1, $fullname, PDO::PARAM_STR);
            $stmt->bindValue(2, $email, PDO::PARAM_STR);
            $stmt->bindValue(3, $role, PDO::PARAM_STR);
            $stmt->bindValue(4, $member_type, PDO::PARAM_STR);
            $stmt->bindValue(5, $id, PDO::PARAM_INT);
        }

        // Execute the query
        if ($stmt->execute()) {
            echo json_encode(["message" => "Profile updated successfully!"]);
        } else {
            echo json_encode(["message" => "Update failed"]);
        }

    } catch (Exception $e) {
        // Handle exceptions (database errors)
        http_response_code(500);
        echo json_encode(["message" => "Error updating profile: " . $e->getMessage()]);
    }

    // Close the statement and connection
    $stmt = null;
    $conn = null;
}
?>
