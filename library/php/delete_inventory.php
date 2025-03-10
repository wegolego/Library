<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$id = $_GET['id'] ?? '';
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$year = $_GET['year'] ?? '';
$newest = $_GET['newest'] ?? '';
$page = $_GET['page'] ?? 1;

if ($id) {
    try {
        // Prepare and execute delete statement
        $stmt = $pdo->prepare("DELETE FROM books WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $deleteSuccess = $stmt->execute();

        // Redirect back to the inventory page with the same search and filter criteria
        $notification = $deleteSuccess ? 'deleted' : 'failure';
        header("Location: inventory.php?search=" . urlencode($search) . "&category=" . urlencode($category) . "&year=" . urlencode($year) . "&newest=" . urlencode($newest) . "&page=" . urlencode($page) . "&notification=delete_success");

        exit;

    } catch (PDOException $e) {
        // Handle any PDO exceptions and redirect with failure notification
        header("Location: inventory.php?search=" . urlencode($search) . "&category=" . urlencode($category) . "&year=" . urlencode($year) . "&newest=" . urlencode($newest) . "&page=" . urlencode($page) . "&notification=failure");
        exit;
    }
} else {
    echo "No ID provided!";
    exit;
}
?>
