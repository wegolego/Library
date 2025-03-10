<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if an ID is provided
if (isset($_POST['id'])) {
    $bookId = intval($_POST['id']); // Sanitize input

    // Prepare and execute query
    try {
        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = :id");
        $stmt->bindValue(':id', $bookId, PDO::PARAM_INT);
        $stmt->execute();
        
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if book data was found
        if ($book) {
            echo json_encode($book);
        } else {
            echo json_encode(['error' => 'Book not found']);
        }
    } catch (PDOException $e) {
        // Log error message instead of exposing it
        error_log('Database error: ' . $e->getMessage());
        echo json_encode(['error' => 'Internal server error']);
    }
} else {
    echo json_encode(['error' => 'No book ID provided']);
}
?>
