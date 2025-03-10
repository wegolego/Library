<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $firstName = $_POST['F_Name'];
    $lastName = $_POST['L_Name'];
    $username = $_POST['Username'];
    $password = $_POST['Password'];

    // Update user in the database
    try {
        if (!empty($password)) {
            // Update with new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE ccai_user SET F_Name = :fname, L_Name = :lname, Username = :username, Password = :password WHERE id = :id");
            $stmt->execute([
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':username' => $username,
                ':password' => $hashedPassword,
                ':id' => $id
            ]);
        } else {
            // Update without changing password
            $stmt = $pdo->prepare("UPDATE ccai_user SET F_Name = :fname, L_Name = :lname, Username = :username WHERE id = :id");
            $stmt->execute([
                ':fname' => $firstName,
                ':lname' => $lastName,
                ':username' => $username,
                ':id' => $id
            ]);
        }

        echo 'success';
    } catch (PDOException $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo 'Invalid request method';
}