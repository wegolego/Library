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
                window.location.href = 'delete_inventory.php?id=' + id + '&search=' + search + '&category=' + category + '&year=' + year + '&newest=' + newest + '&page=' + page;
            }
        }
        $(document).ready(function() {
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

            $(".close").click(function() {
                $("#editModal").fadeOut(); // Hide modal
            });
        });
    </script>
</head>

<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">

            <h2>Inventory</h2> 
            
        </div>

        <div class="search-filter-section">
            <form action="novelinventory.php" method="get">
                <div class="search-filter-container">
                    <div class="search-bar">
                        <input type="text" name="search" placeholder="Search books.." value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                    </div>

                   <!-- filter -->
                        <div class="filter-bar">
                        <!-- category -->
                        <select name="category" id="category">
                            <option value="Select Category">Select Category</option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- year -->
                        <select name="year">
                            <option value="">Year</option>
                            <?php foreach ($years as $year) : ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="filter-button">Filter</button>
                        </div>
                </div>
            </form>
        </div>
    </div>
                <!-- navigation -->
            <div class="navbar">
                <?php include 'navbar.php'; ?>
            </div>

                <div class="dashboard-container">
                    <!-- User header section -->
                    <div id="notification" class="notification"></div>

                    <form class="tableForm" method="POST">
                        <div class="table-container">
                            <table id="books-table">
                            
                                <thead>
                                    <tr>
                                        <th>Quantity</th>
                                        <th> Subject Category</th>
                                        <th>Dept.</th>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Year</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    <?php if ($books) : ?>
                                        <?php foreach ($books as $book) : ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($book['book_category']); ?></td>
                                                <td><?php echo htmlspecialchars($book['Classification'] ?: 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                                <td><?php echo htmlspecialchars($book['year']); ?></td>
                                                <td>
                                                    <button type="button" class="update" data-id="<?php echo $book['id']; ?>"><i class="fas fa-edit"></i></button>
                                                    <button type="button" class="delete" onclick="confirmDelete(<?php echo $book['id']; ?>)"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <tr>
                                            <td colspan="8">No books found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($totalPages > 0) : ?>
                    <!-- First Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="novelinventory.php?page=1&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>" class="pagination-link">First</a>
                    <?php endif; ?>

                    <!-- Previous Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="novelinventory.php?page=<?php echo $currentPage - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>" class="pagination-link">&laquo; Previous</a>
                    <?php endif; ?>

                    <!-- Page Number Links -->
                    <?php
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);

                    if ($startPage > 1) {
                        echo '<a href="novelinventory.php?page=1&search=' . urlencode($searchQuery) . '&category=' . urlencode($selectedCategory) . '&year=' . urlencode($selectedYear) . '&newest=' . ($showNewestFirst ? 'true' : 'false') . '" class="pagination-link">1</a>';
                        if ($startPage > 2) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                    }

                    for ($page = $startPage; $page <= $endPage; $page++) :
                        $activeClass = $page == $currentPage ? 'active' : '';
                    ?>
                        <a href="novelinventory.php?page=<?php echo $page; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>" class="pagination-link <?php echo $activeClass; ?>"><?php echo $page; ?></a>
                    <?php endfor; ?>

                    <?php if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<span class="pagination-ellipsis">...</span>';
                        }
                        echo '<a href="novelinventory.php?page=' . $totalPages . '&search=' . urlencode($searchQuery) . '&category=' . urlencode($selectedCategory) . '&year=' . urlencode($selectedYear) . '&newest=' . ($showNewestFirst ? 'true' : 'false') . '" class="pagination-link">' . $totalPages . '</a>';
                    } ?>

                    <!-- Next Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="novelinventory.php?page=<?php echo $currentPage + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>" class="pagination-link">Next &raquo;</a>
                    <?php endif; ?>

                    <!-- Last Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="novelinventory.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($searchQuery); ?>&category=<?php echo urlencode($selectedCategory); ?>&year=<?php echo urlencode($selectedYear); ?>&newest=<?php echo $showNewestFirst ? 'true' : 'false'; ?>" class="pagination-link">Last</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </form>

        <!-- Edit Book Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Edit Book</h2>
                <form id="editForm" method="POST" action="update_inventory.php">
                    <!-- Existing hidden input for the book ID -->
                    <input type="hidden" name="id" id="editId">
                    
                    <!-- Add hidden inputs for search, filter, and pagination parameters -->
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($selectedCategory); ?>">
                    <input type="hidden" name="classification" value="<?php echo htmlspecialchars($selectedClassification); ?>">
                    <input type="hidden" name="year" value="<?php echo htmlspecialchars($selectedYear); ?>">
                    <input type="hidden" name="newest" value="<?php echo $showNewestFirst ? 'true' : 'false'; ?>">
                    <input type="hidden" name="page" value="<?php echo $currentPage; ?>">

                    <!-- Existing form fields -->
                    <label for="editQuantity">Quantity:</label>
                    <input type="text" name="quantity" id="editQuantity" required>

                    <label for="editBookCategory">Category:</label>
                    <input type="text" name="book_category" id="editBookCategory" required>

                    <label for="editClassification">Classification:</label>
                    <input type="text" name="classification" id="editClassification" required>

                    <label for="editBookName">Book Name:</label>
                    <input type="text" name="book_name" id="editBookName" required>

                    <label for="editAuthor">Author:</label>
                    <input type="text" name="author" id="editAuthor" required>

                    <label for="editIsbn">ISBN:</label>
                    <input type="text" name="isbn" id="editIsbn" required>

                    <label for="editYear">Year:</label>
                    <input type="text" name="year" id="editYear" required>

                    <button type="submit">Save Changes</button>
                </form>


            </div>
        </div>

    </div>
 <!-- Floating Notification -->
 <div id="notification" class="notification"></div>
</body>

</html>