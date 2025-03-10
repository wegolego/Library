<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Handle update, return, and delete actions
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM borrowers WHERE id = ?");
    $stmt->execute([$id]);
    $borrowerToEdit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($borrowerToEdit) {
        $_SESSION['borrower_to_edit'] = $borrowerToEdit;
    }
    echo json_encode($borrowerToEdit);
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $name = $_POST['borrower_name'];
    $number = $_POST['id_number'];
    $classification = $_POST['classification'];
    $book_name = $_POST['book_name'];
    $duedate = $_POST['duedate'];

    $stmt = $pdo->prepare("UPDATE borrowers SET name = ?, number = ?, b_class = ?, b_bookname = ?, duedate = ? WHERE id = ?");
    $stmt->execute([$name, $number, $classification, $book_name, $duedate, $id]);

    $_SESSION['message'] = 'The record has been updated successfully.';
    echo json_encode(['status' => 'success']);
    exit;
} elseif (isset($_GET['return'])) {
    $id = intval($_GET['return']);

    // Fetch the borrowed record to get the quantity and book name
    $stmt = $pdo->prepare("SELECT quantity, b_bookname FROM borrowers WHERE id = ?");
    $stmt->execute([$id]);
    $borrower = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($borrower) {
        // Update the borrow_status and return_date
        $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'returned', return_date = NOW() WHERE id = ?");
        $stmt->execute([$id]);

        // Update the quantity in the books table
        $stmt = $pdo->prepare("UPDATE books SET quantity = quantity + ? WHERE book_name = ?");
        $stmt->execute([$borrower['quantity'], $borrower['b_bookname']]);

        $_SESSION['message'] = 'The book has been marked as returned and inventory updated successfully.';
    } else {
        $_SESSION['message'] = 'Error: Book not found.';
    }

    header("Location: borrowlist.php");
    exit;
} elseif (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM borrowers WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['message'] = 'The record has been deleted successfully.';
    header("Location: borrowlist.php");
    exit;
}

// Update borrow_status for overdue books
// Check if borrow_status should be updated based on the due date
$today = new DateTime();
$todayFormatted = $today->format('Y-m-d');
$tomorrow = (clone $today)->modify('+1 day')->format('Y-m-d');
$dayAfterTomorrow = (clone $today)->modify('+2 days')->format('Y-m-d');

// Update borrow_status for overdue books
$stmt = $pdo->prepare("SELECT id, duedate, borrow_status FROM borrowers WHERE borrow_status != 'returned'");
$stmt->execute();
$borrowers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($borrowers as $borrower) {
    $duedate = new DateTime($borrower['duedate']);
    $duedateFormatted = $duedate->format('Y-m-d');

    if ($duedateFormatted < $todayFormatted && $borrower['borrow_status'] != 'overdue') {
        // Book is overdue
        $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'overdue' WHERE id = ?");
        $stmt->execute([$borrower['id']]);
    } elseif ($duedateFormatted === $todayFormatted && $borrower['borrow_status'] != 'due today') {
        // Book is due today
        $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'due today' WHERE id = ?");
        $stmt->execute([$borrower['id']]);
    } elseif ($duedateFormatted === $tomorrow && $borrower['borrow_status'] != 'due tomorrow') {
        // Book is due tomorrow
        $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'due tomorrow' WHERE id = ?");
        $stmt->execute([$borrower['id']]);
    } elseif ($duedateFormatted === $dayAfterTomorrow && $borrower['borrow_status'] != 'due after tomorrow') {
        // Book is due the day after tomorrow
        $stmt = $pdo->prepare("UPDATE borrowers SET borrow_status = 'due after tomorrow' WHERE id = ?");
        $stmt->execute([$borrower['id']]);
    }
}


// Handle search and sorting
$searchQuery = '';
$searchParams = [];
$orderByDueDates = false;

if (isset($_GET['search'])) {
    $searchQuery = trim($_GET['search']);
    if (!empty($searchQuery)) {
        $searchParams = [
            '%' . $searchQuery . '%',
            '%' . $searchQuery . '%',
            '%' . $searchQuery . '%',
            '%' . $searchQuery . '%'
        ];
    }
}

if (isset($_GET['sort']) && $_GET['sort'] == 'duedates') {
    $orderByDueDates = true;
}

// Pagination setup
$itemsPerPage = 5; // Adjust as needed
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Count total records for pagination
$countSql = "SELECT COUNT(*) FROM borrowers WHERE borrow_status != 'returned'";
if ($searchQuery) {
    $countSql .= " AND (name LIKE ? OR b_class LIKE ? OR b_bookname LIKE ? OR borrow_status LIKE ?)";
}

$countStmt = $pdo->prepare($countSql);
$countStmt->execute($searchParams);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $itemsPerPage);

// Fetch paginated borrowers data
$sql = "SELECT * FROM borrowers WHERE borrow_status != 'returned'";
if ($searchQuery) {
    $sql .= " AND (name LIKE ? OR b_class LIKE ? OR b_bookname LIKE ? OR borrow_status LIKE ?)";
}
$sql .= $orderByDueDates ? " ORDER BY duedate ASC" : " ORDER BY borrow_date DESC";
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

// Update notification count to include all due statuses
$notificationCount = $pdo->query("SELECT COUNT(*) FROM borrowers 
    WHERE borrow_status IN ('overdue', 'due today', 'due tomorrow', 'due after tomorrow') 
    AND borrow_status != 'returned'")->fetchColumn();

// Check if there is a borrower to edit in the session
$borrowerToEdit = null;
if (isset($_SESSION['borrower_to_edit'])) {
    $borrowerToEdit = $_SESSION['borrower_to_edit'];
    unset($_SESSION['borrower_to_edit']);
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Borrowers List</title>
    <link rel="stylesheet" type="text/css" href="../css/borrowlist.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Populate modal with data
        window.populateUpdateModal = function(borrower) {
            $('#updateModal input[name="borrower_name"]').val(borrower.name);
            $('#updateModal input[name="id_number"]').val(borrower.number);
            $('#updateModal input[name="classification"]').val(borrower.b_class);
            $('#updateModal input[name="quantity"]').val(borrower.quantity); // Populate quantity
            $('#updateModal input[name="book_name"]').val(borrower.b_bookname); // Populate book name
            $('#updateModal input[name="duedate"]').val(borrower.duedate);
            $('#updateModal input[name="id"]').val(borrower.id);
            $('#updateModal').show();
        }

        // Update record via AJAX
        $('#updateModal form').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                url: 'borrowlist.php',
                type: 'POST',
                data: $(this).serialize() + '&action=update',
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.status === 'success') {
                        alert('Record updated successfully!');
                        $('#updateModal').hide();
                        location.reload(); // Reload the page to reflect changes
                    } else {
                        alert('Error updating record.');
                    }
                }
            });
        });

        // Return confirmation
        window.confirmReturn = function(id) {
            if (confirm('Are you sure you want to mark this book as returned?')) {
                window.location.href = 'borrowlist.php?return=' + id;
            }
        }

        // Delete confirmation
        window.confirmDelete = function(id) {
            if (confirm('Are you sure you want to delete this record?')) {
                window.location.href = 'borrowlist.php?delete=' + id;
            }
        }

        // Close modal
        $('.close').on('click', function() {
            $('#updateModal').hide();
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
            <h2>List of Borrowers</h2>
        </div>
        <div class="search-container">
            <form method="GET" action="borrowlist.php" class="search-form">
                <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search..">
                <button type="submit" class="search-button"><i class="fas fa-search"></i></button>
                <a href="javascript:void(0);" onclick="showNotifications();" class="due-dates-button notification-button" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </a>
                <div id="notificationDropdown" class="notification-dropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                    </div>
                    <div class="notification-list">
                        <?php
                        $notifQuery = $pdo->query("SELECT id, name, b_bookname, borrow_status, duedate 
                            FROM borrowers 
                            WHERE borrow_status IN ('overdue', 'due today', 'due tomorrow', 'due after tomorrow') 
                            AND borrow_status != 'returned'
                            ORDER BY 
                                CASE borrow_status
                                    WHEN 'due today' THEN 1
                                    WHEN 'due tomorrow' THEN 2
                                    WHEN 'due after tomorrow' THEN 3
                                    WHEN 'overdue' THEN 4
                                END,
                                duedate DESC"); // Changed to DESC for latest dates first
                        $notifications = $notifQuery->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($notifications)) {
                            echo "<div class='no-notifications'>No notifications</div>";
                        } else {
                            foreach ($notifications as $notif) {
                                $statusColor = '#498CD2'; // Default blue for due after tomorrow
                                switch($notif['borrow_status']) {
                                    case 'overdue':
                                        $statusColor = '#fa0000'; // Red
                                        break;
                                    case 'due today':
                                        $statusColor = '#d48a00'; // Orange
                                        break;
                                    case 'due tomorrow':
                                        $statusColor = '#0d700a'; // Green
                                        break;
                                }
                                echo "<a href='borrowlist.php?search=" . urlencode($notif['name']) . "' class='notification-item'>";
                                echo "<div class='notification-icon'><i class='fas fa-book' style='color: {$statusColor}'></i></div>";
                                echo "<div class='notification-content'>";
                                echo "<div class='notification-title'><strong>{$notif['name']}</strong></div>";
                                echo "<div class='notification-details'>{$notif['b_bookname']}</div>";
                                echo "<div class='notification-status' style='color: {$statusColor}'>" . ucfirst($notif['borrow_status']) . "</div>";
                                echo "<div class='notification-time'>Due: " . date('d F Y', strtotime($notif['duedate'])) . "</div>";
                                echo "</div></a>";
                            }
                        }
                        ?>
                    </div>
                </div>
                <a href="borrowlist.php?sort=duedates" class="due-dates-button" title="Due Dates">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="due-dates-text">Due Dates</span>
                </a>
            </form>
        </div>
    </div>
    <?php include 'navbar.php'; ?>
    <div class="content-container">
        <div class="header-container">
            <?php if ($searchQuery || $orderByDueDates) : ?>
                <a href="borrowlist.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <?php if ($orderByDueDates) : ?>
                <ul style="list-style-type: disc; padding-left: 20px; padding-bottom: 10px; display: flex; gap: 50px; font-weight:bold">
                    <li style="color: #fa0000;">OVER DUE</li>
                    <li style="color: #d48a00;">DUE TODAY</li>
                    <li style="color: #0d700a;">DUE TOMORROW</li>
                    <li style="color: #498CD2;">DUE AFTER TOMORROW</li>
                </ul>
            <?php endif; ?>
            <?php if (empty($borrowers)) : ?>
                <p>No records found</p>
            <?php else : ?>
                <table>
                    <thead>
                        <tr>
                            <th class="id-header">Name of Borrower</th>
                            <th class="id-header">ID Number</th>
                            <th class="class-header">Classification</th>
                            <th class="quantity-header">QTY</th>
                            <th>Book Name</th>
                            <th class="dborrow-header">Status</th>
                            <th class="dborrow-header">Date Borrowed</th>
                            <th class="dborrow-header">Due Date</th>
                            <th class="Action-header">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $today = new DateTime();
                        $todayFormatted = $today->format('Y-m-d');
                        $tomorrow = (clone $today)->modify('+1 day')->format('Y-m-d');
                        $dayAfterTomorrow = (clone $today)->modify('+2 days')->format('Y-m-d');

                        foreach ($borrowers as $index => $borrower) {
                            $duedate = new DateTime($borrower['duedate']);
                            $duedateFormatted = $duedate->format('Y-m-d');

                            // Default status is "Borrowed"
                            $textColor = '#000000'; // Default color for borrowed items
                            $borrowerStatus = 'Borrowed';

                            // Change status and color based on due date filter
                            if ($orderByDueDates && strtolower($borrower['borrow_status']) !== 'returned') {
                                if ($borrower['borrow_status'] === 'returned') {
                                    $textColor = '#000000'; // Default color for returned items
                                    $borrowerStatus = 'Returned';
                                } elseif ($duedateFormatted < $todayFormatted) {
                                    // Book is overdue and not returned
                                    $textColor = '#fa0000'; // Red color for overdue books
                                    $borrowerStatus = 'Overdue';
                                } elseif ($duedateFormatted === $todayFormatted) {
                                    // Book is due today
                                    $textColor = '#d48a00'; // Orange color for due today
                                    $borrowerStatus = 'Due Today';
                                } elseif ($duedateFormatted === $tomorrow) {
                                    // Book is due tomorrow
                                    $textColor = '#0d700a'; // Green color for due tomorrow
                                    $borrowerStatus = 'Due Tomorrow';
                                } elseif ($duedateFormatted === $dayAfterTomorrow) {
                                    // Book is due the day after tomorrow
                                    $textColor = '#498CD2'; // Blue color for due after tomorrow
                                    $borrowerStatus = 'Due After Tomorrow';
                                }
                            }

                            if ($orderByDueDates && strtolower($borrower['borrow_status']) !== 'returned') {
                                // Apply color styling to the borrower status if sorting by due dates
                                $borrowerStatus = "<span style='color: {$textColor};'>{$borrowerStatus}</span>";
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($borrower['name']); ?></td>
                            <td><?php echo htmlspecialchars($borrower['number']); ?></td>
                            <td><?php echo htmlspecialchars($borrower['b_class']); ?></td>
                            <td><?php echo htmlspecialchars($borrower['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($borrower['b_bookname']); ?></td>
                            <td><?php echo $borrowerStatus; ?></td>
                            <td><?php echo date('d F Y', strtotime($borrower['borrow_date'])); ?></td>
                            <td><?php echo date('d F Y', strtotime($borrower['duedate'])); ?></td>
                            <td>
                                <a href="javascript:void(0);" onclick="confirmReturn(<?php echo $borrower['id']; ?>);" class="action-icon" title="Mark as Return">
                                    <i class="fas fa-undo"></i>
                                </a>
                                <a href="javascript:void(0);" onclick="populateUpdateModal(<?php echo htmlspecialchars(json_encode($borrower), ENT_QUOTES, 'UTF-8'); ?>);" class="action-icon" title="Update">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $borrower['id']; ?>);" class="action-icon delete-icon" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Pagination controls -->
            <div class="pagination">
                <?php if ($totalPages > 0) : ?>
                    <!-- First Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="borrowlist.php?page=1&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($orderByDueDates ? 'duedates' : ''); ?>" class="pagination-link">First</a>
                    <?php endif; ?>

                    <!-- Previous Page Link -->
                    <?php if ($currentPage > 1) : ?>
                        <a href="borrowlist.php?page=<?php echo ($currentPage - 1); ?>&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($orderByDueDates ? 'duedates' : ''); ?>" class="pagination-link">Previous</a>
                    <?php endif; ?>

                    <!-- Page Number Links -->
                    <?php for ($page = 1; $page <= $totalPages; $page++) : ?>
                        <a href="borrowlist.php?page=<?php echo $page; ?>&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($orderByDueDates ? 'duedates' : ''); ?>" class="pagination-link <?php echo ($page == $currentPage) ? 'active' : ''; ?>">
                            <?php echo $page; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="borrowlist.php?page=<?php echo ($currentPage + 1); ?>&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($orderByDueDates ? 'duedates' : ''); ?>" class="pagination-link">Next</a>
                    <?php endif; ?>

                    <!-- Last Page Link -->
                    <?php if ($currentPage < $totalPages) : ?>
                        <a href="borrowlist.php?page=<?php echo $totalPages; ?>&search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo urlencode($orderByDueDates ? 'duedates' : ''); ?>" class="pagination-link">Last</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>UPDATE RECORD</h3>
                <span class="close">&times;</span>
            </div>
            <form method="POST" action="borrowlist.php" class="modal-form">
                <input type="hidden" name="id" value="">

                <label for="borrower_name">Name of Borrower:</label>
                <input type="text" name="borrower_name" required>

                <label for="id_number">ID Number:</label>
                <input type="text" name="id_number" required>

                <label for="classification">Classification:</label>
                <input type="text" name="classification" required>

                <label for="quantity">Quantity:</label>
                <input type="text" name="quantity" readonly>

                <label for="book_name">Book Name:</label>
                <input type="text" name="book_name" readonly>

                <label for="duedate">Due Date:</label>
                <input type="date" name="duedate" required>

                <button type="submit" class="modal-submit">Update</button>
            </form>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: black;">NOTIFICATIONS</h3>
                <span class="close" onclick="closeNotificationModal()">&times;</span>
            </div>
            <div class="notification-list">
                <?php
                $notifQuery = $pdo->query("SELECT name, b_bookname, borrow_status, duedate FROM borrowers 
                                         WHERE borrow_status IN ('overdue', 'due today') 
                                         AND borrow_status != 'returned'
                                         ORDER BY duedate ASC");
                $notifications = $notifQuery->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($notifications)) {
                    echo "<p>No notifications</p>";
                } else {
                    foreach ($notifications as $notif) {
                        $statusColor = $notif['borrow_status'] === 'overdue' ? '#fa0000' : '#d48a00';
                        $formattedDate = date('d F Y', strtotime($notif['duedate']));
                        echo "<div class='notification-item' style='color: {$statusColor}'>";
                        echo "<p><strong>{$notif['name']}</strong> - {$notif['b_bookname']}<br>";
                        echo "Status: " . ucfirst($notif['borrow_status']) . "<br>";
                        echo "Due Date: {$formattedDate}</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <style>
    .notification-button {
        position: relative;
        margin: 0 10px;
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4444;
        color: white;
        border-radius: 50%;
        padding: 2px 6px;
        font-size: 12px;
        min-width: 18px;
        text-align: center;
    }

    .notification-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        width: 360px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 1000;
        margin-top: 10px;
    }

    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
        font-weight: bold;
    }

    .notification-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .notification-item {
        display: flex;
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background-color 0.2s;
        text-decoration: none;
        color: inherit;
    }

    .notification-item:hover {
        background-color:rgba(165, 145, 145, 0.25);
        text-decoration: none;
    }

    .notification-icon {
        margin-right: 12px;
        width: 40px;
        height: 40px;
        background: #f0f2f5;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-weight: bold;
        margin-bottom: 3px;
        color: black;
    }
    .notification-header h3{
        color: black;
    }

    .notification-details {
        font-size: 0.9em;
        color:rgb(0, 0, 0);
        margin-bottom: 3px;
    }

    .notification-status {
        font-size: 0.85em;
        font-weight: bold;
    }

    .notification-time {
        font-size: 0.8em;
        color: #65676b;
        margin-top: 3px;
    }

    .no-notifications {
        padding: 20px;
        text-align: center;
        color: #65676b;
    }
    </style>

    <script>
    // Add this to your existing JavaScript
    function showNotifications() {
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('notificationDropdown');
        const notificationButton = document.querySelector('.notification-button');
        
        if (!dropdown.contains(event.target) && !notificationButton.contains(event.target)) {
            dropdown.style.display = 'none';
        }
    });

    // Prevent dropdown from closing when clicking inside it
    document.getElementById('notificationDropdown').addEventListener('click', function(event) {
        // Only stop propagation, don't prevent default
        event.stopPropagation();
    });
    </script>
</body>
</html>

