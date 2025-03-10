<?php
// export_inventory.php

// Database connection
$servername = "localhost";
$username = "root"; // Change to your database username
$password = ""; // Change to your database password
$dbname = "ccai"; // Change to your database name

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Export to Excel functionality
$filename = 'inventory_data.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fwrite($output, "ID\tQuantity\tBook Category\tClassification\tBook Name\tAuthor\tISBN\tYear\tDate Added\tTemp ID\n");

$sql = "SELECT id, quantity, book_category, Classification, book_name, author, isbn, year, date_added, temp_id FROM books";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fwrite($output, implode("\t", $row) . "\n");
    }
}

fclose($output);
$conn->close();
exit;
?>