<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Fetch summary data
$totalBooksQuery = $pdo->query("SELECT COUNT(*) AS total FROM books");
$totalBooks = $totalBooksQuery->fetchColumn();

$totalBorrowedQuery = $pdo->query("SELECT COUNT(*) AS total FROM borrowers");
$totalBorrowed = $totalBorrowedQuery->fetchColumn();

$totalUsersQuery = $pdo->query("SELECT COUNT(*) AS total FROM ccai_user");
$totalUsers = $totalUsersQuery->fetchColumn();
?>

<?php
// Initialize $books as an empty array
$books = [];

// Prepare the search query and filters
$searchQuery = $_GET['search'] ?? '';
$selectedCategory = $_GET['category'] ?? '';
$selectedYear = $_GET['year'] ?? '';

// Determine sorting order
$showNewestFirst = isset($_GET['newest']) && $_GET['newest'] === 'true';
$sortingOrder = $showNewestFirst ? "ORDER BY created_at DESC" : "ORDER BY year DESC";

// Pagination variables
$itemsPerPage = 13;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch total number of books for pagination
try {
    $countSql = "SELECT COUNT(*) FROM books WHERE 1=1";
    $countParams = [];

    if ($searchQuery) {
        $countSql .= " AND (book_name LIKE :search OR author LIKE :author OR book_category LIKE :category OR Classification LIKE :classification)";
        $countParams[':search'] = '%' . $searchQuery . '%';
        $countParams[':author'] = '%' . $searchQuery . '%';
        $countParams[':category'] = '%' . $searchQuery . '%';
        $countParams[':classification'] = '%' . $searchQuery . '%';
    }
    if ($selectedCategory && $selectedCategory !== 'Select Category') {
        $countSql .= " AND book_category = :category";
        $countParams[':category'] = $selectedCategory;
    }
    if ($selectedYear) {
        $countSql .= " AND year = :year";
        $countParams[':year'] = $selectedYear;
    }

    $countStmt = $pdo->prepare($countSql);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue($key, $value);
    }

    $countStmt->execute();
    $totalBooks = $countStmt->fetchColumn();
    $totalPages = ceil($totalBooks / $itemsPerPage);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    die;
}

// Fetch books data based on search query and filters
try {
    $sql = "SELECT id, quantity, book_category, Classification, book_name, author, isbn, year FROM books WHERE 1=1";

    if ($searchQuery) {
        $sql .= " AND (book_name LIKE :search OR author LIKE :author OR book_category LIKE :category OR Classification LIKE :classification)";
    }
    if ($selectedCategory && $selectedCategory !== 'Select Category') {
        $sql .= " AND book_category = :category";
    }
    if ($selectedYear) {
        $sql .= " AND year = :year";
    }

    // Add sorting order and pagination
    $sql .= " ORDER BY
                CASE 
                    WHEN year = 'N/A' THEN 1 
                    ELSE 0 
                END,
                year DESC";
    $sql .= " LIMIT :limit OFFSET :offset";

    // Prepare statement with updated SQL
    $stmt = $pdo->prepare($sql);

    // Bind parameters
    if ($searchQuery) {
        $stmt->bindValue(':search', '%' . $searchQuery . '%');
        $stmt->bindValue(':author', '%' . $searchQuery . '%');
        $stmt->bindValue(':category', '%' . $searchQuery . '%');
        $stmt->bindValue(':classification', '%' . $searchQuery . '%');
    }
    if ($selectedCategory && $selectedCategory !== 'Select Category') {
        $stmt->bindValue(':category', $selectedCategory);
    }
    if ($selectedYear) {
        $stmt->bindValue(':year', $selectedYear);
    }

    // Bind pagination parameters
    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    // Execute query
    $stmt->execute();

    // Fetch results
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch categories for dropdown
try {
    $categoriesStmt = $pdo->query("SELECT DISTINCT book_category FROM books ORDER BY book_category");
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch distinct years for the year filter dropdown
try {
    $yearsStmt = $pdo->query("SELECT DISTINCT year FROM books WHERE year IS NOT NULL AND year != '' ORDER BY year DESC");
    $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check for notification parameter
$notification = $_GET['notification'] ?? '';
?>

<!DOCTYPE html>
<html>

<head>
    <title>Inventory</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/inventory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    function confirmDelete(id) {
        var search = encodeURIComponent('<?php echo htmlspecialchars($searchQuery); ?>');
        var category = encodeURIComponent('<?php echo htmlspecialchars($selectedCategory); ?>');
        var year = encodeURIComponent('<?php echo htmlspecialchars($selectedYear); ?>');
        var newest = encodeURIComponent('<?php echo $showNewestFirst ? 'true' : 'false'; ?>');
        var page = '<?php echo $currentPage; ?>';

        if (confirm('Are you sure you want to delete this book?')) {
            // Redirect to delete_inventory.php with notification parameters
            window.location.href = 'delete_inventory.php?id=' + id + 
                '&search=' + search + 
                '&category=' + category + 
                '&year=' + year + 
                '&newest=' + newest + 
                '&page=' + page + 
                '&notification=delete_success';
        }
    }

    $(document).ready(function() {
        // Handle update button click
        $(".update").click(function() {
            var bookId = $(this).data("id");
            $.ajax({
                url: "fetch_book.php",
                type: "POST",
                data: { id: bookId },
                dataType: "json",
                success: function(data) {
                    $("#editId").val(data.id);
                    $("#editQuantity").val(data.quantity);
                    $("#editBookCategory").val(data.book_category);
                    $("#editClassification").val(data.Classification);
                    $("#editBookName").val(data.book_name);
                    $("#editAuthor").val(data.author);
                    $("#editIsbn").val(data.isbn);
                    $("#editYear").val(data.year);
                    $("#editModal").fadeIn();
                }
            });
        });

        // Close modal
        $(".close").click(function() {
            $("#editModal").fadeOut(); // Hide modal
        });

        // Handle form submission via AJAX for updates
        $('#updateForm').on('submit', function(event) {
            event.preventDefault(); // Prevent the default form submission

            $.ajax({
                url: 'update_inventory.php',
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    // Handle success response
                    showNotification('Update successful!', 'success');
                    setTimeout(function() {
                        location.reload(); // Reload the page to reflect changes
                    }, 2000); // Delay reload to show notification
                },
                error: function() {
                    // Handle error response
                    showNotification('Update failed. Please try again.', 'error');
                }
            });
        });

        // Handle filter button click
        $('#filterButton').click(function() {
            var searchQuery = $('#searchInput').val();
            var category = $('#categorySelect').val();
            var year = $('#yearSelect').val();

            $.ajax({
                url: 'filter_inventory.php',
                type: 'GET',
                data: {
                    search: searchQuery,
                    category: category,
                    year: year
                },
                success: function(response) {
                    // Update the table or page content with filtered data
                    $('#inventoryTable').html(response);
                    showNotification('Filter applied successfully!', 'success');
                },
                error: function() {
                    showNotification('Filter failed. Please try again.', 'error');
                }
            });
        });
    });

    function showNotification(message, type) {
        var notification = $('#notification');
        notification.text(message);
        notification.addClass(type);
        notification.fadeIn().delay(3000).fadeOut();
    }
    </script>
    <style>
        #notification {
            display: none;
            padding: 10px;
            border-radius: 5px;
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
        }
        #notification.success {
            background-color: #4CAF50;
            color: white;
        }
        #notification.error {
            background-color: #F44336;
            color: white;
        }
    </style>
</head>

<body>
    <div id="notification"></div>
    <div class="container">
        <h1>Inventory</h1>
        <div class="summary">
            <div class="summary-item">
                <strong>Total Books:</strong> <?php echo $totalBooks; ?>
            </div>
            <div class="summary-item">
                <strong>Total Borrowed:</strong> <?php echo $totalBorrowed; ?>
            </div>
            <div class="summary-item">
                <strong>Total Users:</strong> <?php echo $totalUsers; ?>
            </div>
        </div>

        <form id="filterForm">
            <input type="text" id="searchInput" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            <select id="categorySelect" name="category">
                <option value="">Select Category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category === $selectedCategory ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select id="yearSelect" name="year">
                <option value="">Select Year</option>
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $year === $selectedYear ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($year); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" id="filterButton">Filter</button>
        </form>

        <table id="inventoryTable">
            <thead>
                <tr>
                    <th>Book Name</th>
                    <th>Author</th>
                    <th>Category</th>
                    <th>Classification</th>
                    <th>ISBN</th>
                    <th>Year</th>
                    <th>Quantity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($books)): ?>
                    <?php foreach ($books as $book): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['book_category']); ?></td>
                            <td><?php echo htmlspecialchars($book['Classification']); ?></td>
                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td><?php echo htmlspecialchars($book['year']); ?></td>
                            <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                            <td>
                                <button class="update" data-id="<?php echo htmlspecialchars($book['id']); ?>">Update</button>
                                <button class="delete" onclick="confirmDelete(<?php echo htmlspecialchars($book['id']); ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>">Previous</a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>">Next</a>
            <?php endif; ?>
        </div>

        <!-- Update Modal -->
        <div id="editModal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Update Book</h2>
                <form id="updateForm">
                    <input type="hidden" id="editId" name="id">
                    <label>Quantity:</label>
                    <input type="number" id="editQuantity" name="quantity" required>
                    <label>Category:</label>
                    <input type="text" id="editBookCategory" name="book_category" required>
                    <label>Classification:</label>
                    <input type="text" id="editClassification" name="Classification" required>
                    <label>Book Name:</label>
                    <input type="text" id="editBookName" name="book_name" required>
                    <label>Author:</label>
                    <input type="text" id="editAuthor" name="author" required>
                    <label>ISBN:</label>
                    <input type="text" id="editIsbn" name="isbn" required>
                    <label>Year:</label>
                    <input type="text" id="editYear" name="year" required>
                    <button type="submit">Update</button>
                </form>
            </div>
        </div>
    </div>
</body>

</html>
