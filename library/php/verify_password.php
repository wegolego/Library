<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get user input
$password = $_POST['password'];
$actionType = $_POST['action_type'];
$recordId = isset($_POST['record_id']) ? intval($_POST['record_id']) : null;

// Get the current user's password hash from the database
$stmt = $pdo->prepare("SELECT password FROM ccai_user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verify password
if (password_verify($password, $user['password'])) {
    if ($actionType === 'delete_one' && $recordId) {
        // Delete the specific record
        $stmt = $pdo->prepare("DELETE FROM borrowers WHERE id = ?");
        $stmt->execute([$recordId]);
        $_SESSION['message'] = 'The record has been deleted successfully.';
    } elseif ($actionType === 'delete_all') {
        // Delete all data
        $stmt = $pdo->prepare("DELETE FROM borrowers WHERE borrow_status IN ('returned', 'returned late')");
        $stmt->execute();
        $_SESSION['message'] = 'All records have been deleted successfully.';
    }
} else {
    $_SESSION['message'] = 'Incorrect password. Please try again.';
}

// Redirect back to the borrow history page
header("Location: borrowhis.php");
exit;
?>
