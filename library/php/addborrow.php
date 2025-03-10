<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $borrowerName = $_POST['borrower_name'];
    $idNumber = $_POST['id_number'];
    $classification = $_POST['classification'];
    $bookNames = $_POST['book_name']; // This will be an array
    $quantities = $_POST['quantity']; // This will be an array

    foreach ($bookNames as $index => $bookName) {
        $quantity = isset($quantities[$index]) ? (int)$quantities[$index] : 1;
        if (!empty($bookName) && $quantity > 0) {
            // Check if a record with the same details already exists
            $checkStmt = $pdo->prepare("SELECT id, quantity FROM borrowers WHERE name = ? AND number = ? AND b_class = ? AND b_bookname = ?");
            $checkStmt->execute([$borrowerName, $idNumber, $classification, $bookName]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRecord) {
                // If record exists, update the quantity
                $newQuantity = $existingRecord['quantity'] + $quantity;
                $updateStmt = $pdo->prepare("UPDATE borrowers SET quantity = ? WHERE id = ?");
                $updateStmt->execute([$newQuantity, $existingRecord['id']]);
            } else {
                // If no record exists, insert a new record
                $stmt = $pdo->prepare("INSERT INTO borrowers (name, number, b_class, b_bookname, quantity, borrow_status, borrow_date) VALUES (?, ?, ?, ?, ?, 'borrowed', CURDATE())");
                $stmt->execute([$borrowerName, $idNumber, $classification, $bookName, $quantity]);
            }

            // Update the quantity in the books table
            $updateBooksStmt = $pdo->prepare("UPDATE books SET quantity = quantity - ? WHERE book_name = ? AND quantity >= ?");
            $updateBooksStmt->execute([$quantity, $bookName, $quantity]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'The records have been added successfully.']);
    exit;
}

// Fetch book names for suggestions
$bookNames = [];
$stmt = $pdo->query("SELECT DISTINCT book_name FROM books");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $bookNames[] = $row['book_name'];
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Borrow a Book</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/addborrow.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">
            <h2>Borrow a Book</h2>
        </div>
    </div>
    <?php include 'navbar.php'; ?>
    <div class="container-container">
        <div class="content-container">
            <div class="form-container">
                <form id="borrower-form">
                    <label for="borrower_name">Name Of Borrower:</label>
                    <input type="text" name="borrower_name" placeholder="Name of Borrower" required>

                    <label for="id_number">ID Number:</label>
                    <input type="text" name="id_number" placeholder="ID Number" required maxlength="12">

                    <label for="classification">Classification:</label>
                    <input type="text" name="classification" placeholder="Teacher/ Student/ Others" required>
                </form>

            </div>
            <div class="submit-container">
                <button type="button" id="submitAllForms">Add Record</button>
                <div class="notification" style="display: none;"></div>
            </div>
        </div>
        <div class="content-container-1">
            <div class="form-container">
                <form id="books-form">
                    <label for="book_name">Book Name:</label>
                    <div id="bookFields">
                        <div class="input-container">
                            <input type="text" name="book_name[]" class="with-remove" placeholder="Book Name" required autocomplete="off">
                            <input type="number" name="quantity[]" class="quantity-input" value="1" min="1" placeholder="Quantity" required>
                            <div class="suggestions" style="display: none;"></div>
                        </div>
                    </div>

                    <button type="button" id="addBookButton" class="add-book-button">Add Column</button>
                    <button type="button" id="removeBookButton" class="remove-book-button">Remove Column</button>
                </form>
            </div>
        </div>
        <!-- Single "Add Record" button -->

    </div>

    <script>
        $(document).ready(function() {
            var bookNames = <?php echo json_encode($bookNames); ?>;

            // Handle input for autocomplete
            $(document).on('input', 'input[name="book_name[]"]', function() {
                var input = $(this).val().toLowerCase();
                var suggestions = bookNames.filter(function(name) {
                    return name.toLowerCase().indexOf(input) !== -1;
                });

                var suggestionsContainer = $(this).siblings('.suggestions');
                suggestionsContainer.empty();
                if (input.length > 0) {
                    suggestions.forEach(function(name) {
                        suggestionsContainer.append('<div class="suggestion-item">' + name + '</div>');
                    });
                    suggestionsContainer.show();
                } else {
                    suggestionsContainer.hide();
                }
            });

            $(document).on('click', '.suggestion-item', function() {
                $(this).parent().siblings('input[name="book_name[]"]').val($(this).text());
                $(this).parent().hide();
            });

            $(document).click(function(event) {
                if (!$(event.target).closest('.suggestions').length) {
                    $('.suggestions').hide();
                }
            });

            $('#addBookButton').on('click', function() {
                if ($('#bookFields .input-container').length < 4) {
                    var newField = $('<div class="input-container">' +
                        '<input type="text" name="book_name[]" class="with-remove" placeholder="Book Name" autocomplete="off">' +
                        '<input type="number" name="quantity[]" class="quantity-input" value="1" min="1" placeholder="Quantity">' +
                        '<div class="suggestions" style="display: none;"></div>' +
                        '</div>');
                    $('#bookFields').append(newField);
                    checkRemoveButtonState();
                } else {
                    alert('You have reached the limit.');
                }
            });

            $('#removeBookButton').on('click', function() {
                if ($('#bookFields .input-container').length > 1) {
                    $('#bookFields .input-container').last().remove();
                }
                checkRemoveButtonState();
            });

            function checkRemoveButtonState() {
                if ($('#bookFields .input-container').length <= 1) {
                    $('#removeBookButton').prop('disabled', true);
                } else {
                    $('#removeBookButton').prop('disabled', false);
                }
            }

            // Initialize button state on page load
            checkRemoveButtonState();

            // Function to validate required fields
            function validateForms() {
                var isValid = true;
                $('#borrower-form input[required], #books-form input[required]').each(function() {
                    if ($(this).val().trim() === '') {
                        isValid = false;
                        $(this).css('border-color', 'red'); // Highlight missing input
                    } else {
                        $(this).css('border-color', ''); // Reset input highlight
                    }
                });
                return isValid;
            }

            // Submit both forms using AJAX when "Add Record" button is clicked
            $('#submitAllForms').on('click', function() {
                if (!validateForms()) {
                    alert('Please fill in all required fields.');
                    return;
                }

                var formData = $('#borrower-form').serializeArray().concat($('#books-form').serializeArray());
                formData.push({
                    name: 'ajax',
                    value: true
                });

                $.ajax({
                    url: 'addborrow.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('.notification').text(response.message).fadeIn('slow').delay(4000).fadeOut('slow');
                            // Clear forms if needed
                            $('#borrower-form')[0].reset();
                            $('#books-form')[0].reset();
                        }
                    },
                    error: function() {
                        alert('There was an error processing the request.');
                    }
                });
            });
        });
    </script>

</body>

</html>