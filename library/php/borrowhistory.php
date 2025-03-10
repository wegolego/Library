<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle delete action
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM borrowers WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = 'The record has been deleted successfully.';
    header("Location: borrowhistory.php");
    exit;
}

// Handle return action
if (isset($_GET['return'])) {
    $id = intval($_GET['return']);
    $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'returned', return_date = NOW() WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = 'The return date has been updated successfully.';
    header("Location: borrowhistory.php");
    exit;
}

// Handle search
$searchQuery = '';
$searchParams = [];
if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    if (!empty($searchQuery)) {
        // Prepare search parameters
        $searchParams = ['%' . $searchQuery . '%', '%' . $searchQuery . '%', '%' . $searchQuery . '%'];
    }
}

// SQL query to fetch only returned or returned late books, with sorting by borrow_date DESC
$sql = "SELECT *, 
        CASE 
            WHEN borrow_status = 'returned' AND return_date > duedate THEN 'Returned Late'
            ELSE borrow_status 
        END AS borrow_status 
        FROM borrowers 
        WHERE borrow_status IN ('returned', 'returned late')";

if ($searchQuery) {
    // Add search filter to the SQL query
    $sql .= " AND (name LIKE ? OR b_class LIKE ? OR b_bookname LIKE ?)";
}

$sql .= " ORDER BY borrow_date DESC"; // Sort records by borrow_date in descending order

// Prepare and execute the statement
$stmt = $pdo->prepare($sql);
$stmt->execute($searchParams);
$borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there is a message in the session
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}// Pagination setup
$itemsPerPage = 5; // Adjust as needed
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM borrowers WHERE borrow_status IN ('returned', 'returned late')";
if ($searchQuery) {
    $countSql .= " AND (name LIKE ? OR b_class LIKE ? OR b_bookname LIKE ?)";
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($searchParams);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $itemsPerPage);

// Fetch paginated borrowers data
$sql = "SELECT * FROM borrowers WHERE borrow_status IN ('returned', 'returned late')";
if ($searchQuery) {
    $sql .= " AND (name LIKE ? OR b_class LIKE ? OR b_bookname LIKE ?)";
}
$sql .= " LIMIT ? OFFSET ?";

$params = array_merge($searchParams, [$itemsPerPage, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if there is a message in the session
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Borrow History</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/borrowhistory.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Delete confirmation
            window.confirmDelete = function(id) {
                if (confirm('Are you sure you want to delete this record?')) {
                    window.location.href = 'borrowhistory.php?delete=' + id;
                }
            }

            // Return confirmation
            window.confirmReturn = function(id) {
                if (confirm('Are you sure you want to mark this book as returned?')) {
                    window.location.href = 'borrowhistory.php?return=' + id;
                }
            }
        });
    </script>
</head>
<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">
            <h2>List of Returned Books</h2>
        </div>
        <div class="search-container">
            <form method="GET" action="borrowhistory.php" class="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search..">
                <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
            </form>
        </div>
    </div>
    <?php include 'navbar.php'; ?>
    <div class="content-container">
        <div class="header-container">
            <?php if ($searchQuery) : ?>
                <a href="borrowhistory.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <?php if (empty($borrowers)) : ?>
                <p>No records found</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th class="name-header">Name of Borrower</th>
                            <th class="id-header">ID Number</th>
                            <th class="class-header">Classification</th>
                            <th class="quantity-header">QTY</th>
                            <th class="bookname-header">Book Name</th>
                            <th class="status-header">Status</th>
                            <th class="dborrow-header">Borrowed</th>
                            <th class="dborrow-header">Due</th>
                            <th class="dborrow-header">Return</th> <!-- Column for Return Date -->
                            <th class="Action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($borrowers as $borrower) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($borrower['name']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['number']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['b_class']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['b_bookname']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['borrow_status']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['borrow_date']); ?></td>
                                <td><?php echo htmlspecialchars($borrower['duedate'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($borrower['return_date'] ?? '-'); ?></td> <!-- Display return date -->
                                <td>
                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $borrower['id']; ?>);" class="action-icon delete-icon" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <!-- Pagination -->
            <div class="pagination">
                <?php if ($totalPages > 0) : ?>
                    <!-- First Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="borrowhistory.php?page=1&search=<?php echo urlencode($searchQuery); ?>" class="pagination-link">First</a>
                    <?php endif; ?>

                    <!-- Previous Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="borrowhistory.php?page=<?php echo ($currentPage - 1); ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-link">Previous</a>
                    <?php endif; ?>

                    <!-- Page Number Links -->
                    <?php for ($page = 1; $page <= $totalPages; $page++) : ?>
                        <a href="borrowhistory.php?page=<?php echo $page; ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-link <?php echo ($page == $currentPage) ? 'active' : ''; ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="borrowhistory.php?page=<?php echo ($currentPage + 1); ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-link">Next</a>
                    <?php endif; ?>

                    <!-- Last Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="borrowhistory.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($searchQuery); ?>" class="pagination-link">Last</a>
                    <?php endif; ?>
                <?php endif; ?>

            </div>
            <div class="delete-all-container">
                <button class="delete-all-button" onclick="confirmDeleteAll()">DELETE ALL</button>
            </div>

            <div id="passwordModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>CONFIRM DELETE</h3>
                        <span class="close">&times;</span>
                    </div>
                    <div class="modal-body">
                        <form id="passwordForm" method="POST" action="verify_password.php">
                            <input type="hidden" name="action_type" id="actionType">
                            <input type="hidden" name="record_id" id="recordId">
                            <label for="password">Password:</label>
                            <input type="password" name="password" id="password" placeholder="Enter password" required>
                            <button type="submit" class="verify-button">Verify</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Modal close event
                $('.close').on('click', function() {
                    $('#passwordModal').hide();
                });

                // Confirm delete one record
                window.confirmDelete = function(id) {
                    $('#actionType').val('delete_one');
                    $('#recordId').val(id);
                    $('#passwordModal').show();
                };

                // Confirm delete all data
                window.confirmDeleteAll = function() {
                    $('#actionType').val('delete_all');
                    $('#passwordModal').show();
                };
            });
        </script>
    </div>
</body>
</html>
