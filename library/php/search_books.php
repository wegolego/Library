<?php
require 'config.php';

if (isset($_GET['query'])) {
    $query = $_GET['query'];
    $stmt = $pdo->prepare("SELECT book_name FROM books WHERE book_name LIKE ? LIMIT 10");
    $stmt->execute(["%$query%"]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($books);
}
?>
