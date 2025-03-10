<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['id'];

    // Delete the user from the database
    $stmt = $pdo->prepare("DELETE FROM ccai_user WHERE id = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo 'success';
    } else {
        echo 'error';
    }
}
?>