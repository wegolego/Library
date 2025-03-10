<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Print $_POST for debugging (remove in production)
    echo '<pre>';
    print_r($_POST);
    echo '</pre>';

    // Ensure all required POST fields are set
    if (isset($_POST['id'], $_POST['quantity'], $_POST['book_category'], $_POST['classification'], $_POST['book_name'], $_POST['author'], $_POST['isbn'], $_POST['year'])) {
        // Retrieve and sanitize POST data
        $id = $_POST['id'];
        $quantity = $_POST['quantity'];
        $book_category = $_POST['book_category'];
        $classification = $_POST['classification'];
        $book_name = $_POST['book_name'];
        $author = $_POST['author'];
        $isbn = $_POST['isbn'];
        $year = $_POST['year'];

        // Ensure all fields are not empty
        if (empty($id) || empty($quantity) || empty($book_category) || empty($classification) || empty($book_name) || empty($author) || empty($isbn) || empty($year)) {
            echo "All fields are required.";
            exit;
        }

        try {
            // Update the book in the database
            $sql = "UPDATE books SET quantity = :quantity, book_category = :book_category, classification = :classification, book_name = :book_name, author = :author, isbn = :isbn, year = :year WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':book_category', $book_category);
            $stmt->bindParam(':classification', $classification);
            $stmt->bindParam(':book_name', $book_name);
            $stmt->bindParam(':author', $author);
            $stmt->bindParam(':isbn', $isbn);
            $stmt->bindParam(':year', $year);
            $stmt->bindParam(':id', $id);

            $updateSuccess = $stmt->execute();

            // Get existing query parameters from the referrer
            $queryParams = [];
            if (isset($_SERVER['HTTP_REFERER'])) {
                $url = parse_url($_SERVER['HTTP_REFERER']);
                parse_str($url['query'] ?? '', $queryParams);
            }

            // Build query string
            $queryString = http_build_query($queryParams);

            // Redirect based on the update result
            if ($updateSuccess) {
                $_SESSION['message'] = 'The book has been updated successfully.';
                header('Location: inventory.php?' . $queryString . '&notification=success');
            } else {
                $_SESSION['message'] = 'Failed to update the book.';
                header('Location: inventory.php?' . $queryString . '&notification=failure');
            }
            exit;

        } catch (PDOException $e) {
            // Handle any PDO exceptions
            $_SESSION['message'] = 'An error occurred: ' . $e->getMessage();
            header('Location: inventory.php?' . $queryString . '&notification=failure');
            exit;
        }
    } else {
        echo "Missing required fields.";
        exit;
    }
}
?>




<!DOCTYPE html>
<html>
<head>
    <title>Update Book</title>
    <link rel="stylesheet" type="text/css" href="../css/inventory.css">
</head>
<body>
    <div class="nav-container">
        <!-- Navigation Content Here -->
    </div>
    <div class="dashboard-container">
        <h1>Update Book</h1>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($book['id']); ?>">

            <label>Quantity:</label>
            <input type="number" name="quantity" value="<?php echo htmlspecialchars($book['quantity']); ?>" required><br>

            <label>Classification:</label>
            <input type="text" name="classification" value="<?php echo htmlspecialchars($book['classification']); ?>" required><br>

            <label>Category:</label>
            <input type="text" name="book_category" value="<?php echo htmlspecialchars($book['book_category']); ?>" required><br>

            <label>Book Name:</label>
            <input type="text" name="book_name" value="<?php echo htmlspecialchars($book['book_name']); ?>" required><br>

            <label>Author:</label>
            <input type="text" name="author" value="<?php echo htmlspecialchars($book['author']); ?>" required><br>

            <label>ISBN:</label>
            <input type="text" name="isbn" value="<?php echo htmlspecialchars($book['isbn']); ?>" required><br>

            <label>Year:</label>
            <input type="text" name="year" value="<?php echo htmlspecialchars($book['year']); ?>" required><br>

            <button type="submit">Update</button>
        </form>
    </div>
</body>
</html>
