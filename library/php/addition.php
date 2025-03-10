<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Initialize message variables
$message = '';
$messageType = '';

// Handle book addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['books'])) {
    $books = $_POST['books'];

    try {
        $pdo->beginTransaction();

        foreach ($books as $index => $book) {
            $quantity = intval(trim($book['quantity']));
            $category = strtoupper(trim($book['category']));
            $classification = strtoupper(trim($book['classification']));
            $book_name = strtoupper(trim($book['book_name']));
            $author = strtoupper(trim($book['author']));
            $isbn = strtoupper(trim($book['isbn']));
            $year = strtoupper(trim($book['year']));

            // Check if the book with the same ISBN and year already exists
            $stmt = $pdo->prepare("SELECT id, quantity FROM books WHERE isbn = ? AND year = ?");
            $stmt->execute([$isbn, $year]);
            $existingBook = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingBook) {
                // Update quantity if book exists
                $stmt = $pdo->prepare("
                    UPDATE books 
                    SET quantity = quantity + ?, 
                        book_category = ?, 
                        classification = ?, 
                        book_name = ?, 
                        author = ?, 
                        date_added = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$quantity, $category, $classification, $book_name, $author, $existingBook['id']]);
            } else {
                // Insert new book record
                $stmt = $pdo->prepare("
                    INSERT INTO books (quantity, book_category, classification, book_name, author, isbn, year, date_added) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $result = $stmt->execute([$quantity, $category, $classification, $book_name, $author, $isbn, $year]);
            }

            if (!$result) {
                throw new Exception("Failed to insert or update data: " . implode(", ", $stmt->errorInfo()));
            }
        }

        $pdo->commit();
        $message = "Books added/updated successfully.";
        $messageType = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error adding/updating books: " . $e->getMessage();
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Books</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="../css/addition.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">
            <h2>Add Books</h2>
        </div>
    </div>

    <?php include 'navbar.php'; ?>

    <div class="content-container">
        <div class="addition-form">
            <div class="header-container">
                <h2>Add Books</h2>
                <div class="header-controls">
                    <button type="button" id="add-row"><i class="fas fa-plus"></i> Add Row</button>
                    <button type="button" id="remove-row"><i class="fas fa-minus"></i> Remove Row</button>
                </div>
            </div>
            <div id="notification-container"></div>

            <div class="row-count">
                Total Rows: <span id="row-count">1</span>
            </div>

            <form method="POST">
                <table id="books-table">
                    <thead>
                        <tr>
                            <th>Quantity</th>
                            <th>Subject Category</th>
                            <th>Dept / Genre</th>
                            <th>Book Name</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Year</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><input type="text" name="books[0][quantity]" required></td>
                            <td><input type="text" name="books[0][category]" required></td>
                            <td><input type="text" name="books[0][classification]"></td>
                            <td><input type="text" name="books[0][book_name]" required></td>
                            <td><input type="text" name="books[0][author]" required></td>
                            <td><input type="text" name="books[0][isbn]"></td>
                            <td><input type="text" name="books[0][year]" required></td>
                        </tr>
                    </tbody>
                </table>
                <br>
                <br>
                <br>
                <button type="submit">Add Book/s</button>
            </form>
        </div>
    </div>

    <script>
        let rowIndex = 1;
        const maxRows = 15;

        function updateRowCount() {
            const rowCount = document.querySelector('#books-table tbody').rows.length;
            document.getElementById('row-count').textContent = rowCount;
            document.getElementById('remove-row').disabled = rowCount <= 1;
        }

        function getRowData(row) {
            return {
                quantity: row.querySelector('input[name$="[quantity]"]').value.trim(),
                category: row.querySelector('input[name$="[category]"]').value.trim().toUpperCase(),
                classification: row.querySelector('input[name$="[classification]"]').value.trim().toUpperCase(),
                book_name: row.querySelector('input[name$="[book_name]"]').value.trim().toUpperCase(),
                author: row.querySelector('input[name$="[author]"]').value.trim().toUpperCase(),
                isbn: row.querySelector('input[name$="[isbn]"]').value.trim().toUpperCase(),
                year: row.querySelector('input[name$="[year]"]').value.trim().toUpperCase()
            };
        }

        function findDuplicateRow(data) {
            const rows = document.querySelectorAll('#books-table tbody tr');
            for (const row of rows) {
                const rowData = getRowData(row);
                if (Object.keys(data).every(key => rowData[key] === data[key])) {
                    return row;
                }
            }
            return null;
        }

        document.getElementById('add-row').addEventListener('click', function() {
            const tableBody = document.querySelector('#books-table tbody');
            const rowCount = tableBody.rows.length;

            if (rowCount < maxRows) {
                const newRowIndex = rowIndex++;
                const newRow = document.createElement('tr');
                newRow.innerHTML = `
                    <td><input type="text" name="books[${newRowIndex}][quantity]" required></td>
                    <td><input type="text" name="books[${newRowIndex}][category]" required></td>
                    <td><input type="text" name="books[${newRowIndex}][classification]"></td>
                    <td><input type="text" name="books[${newRowIndex}][book_name]" required></td>
                    <td><input type="text" name="books[${newRowIndex}][author]" required></td>
                    <td><input type="text" name="books[${newRowIndex}][isbn]"></td>
                    <td><input type="text" name="books[${newRowIndex}][year]" required></td>
                `;
                
                // Append new row to the table
                tableBody.appendChild(newRow);
                updateRowCount();
            } else {
                alert("You've already reached the limit of adding rows.");
            }
        });

        document.getElementById('remove-row').addEventListener('click', function() {
            const tableBody = document.querySelector('#books-table tbody');
            if (tableBody.rows.length > 1) {
                tableBody.deleteRow(-1);
                rowIndex--;
                updateRowCount();
            }
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.getElementById('notification-container').appendChild(notification);

            // Ensure the notification is displayed
            notification.style.display = 'flex';

            setTimeout(() => {
                notification.remove();
            }, 3000); // Notification disappears after 3 seconds
        }

        // Display PHP messages
        <?php if ($message) echo "showNotification('$message', '$messageType');"; ?>

        // Initialize row count on page load
        updateRowCount();
    </script>
</body>
</html>
