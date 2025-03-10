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

$totalReturnedQuery = $pdo->query("SELECT COUNT(*) AS total FROM borrowers WHERE borrow_status = 'returned' ");
$totalReturned = $totalReturnedQuery->fetchColumn();

$totalBorrowQuery = $pdo->query("SELECT COUNT(*) AS total FROM borrowers WHERE borrow_status = 'borrowed' OR 'overdue' ");
$totalBorrow = $totalBorrowQuery->fetchColumn();

$totalUsersQuery = $pdo->query("SELECT COUNT(*) AS total FROM ccai_user");
$totalUsers = $totalUsersQuery->fetchColumn();

// Add notification count query
$notificationCount = $pdo->query("SELECT COUNT(*) FROM borrowers 
    WHERE borrow_status IN ('overdue', 'due today', 'due tomorrow', 'due after tomorrow') 
    AND borrow_status != 'returned'")->fetchColumn();
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
echo "<script>console.log('Notification:', '$notification');</script>";
?>

<!DOCTYPE html>
<html>

<head>
    <title>Home</title>
    <link rel="icon" href="img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/home.css">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
    /* Add notification styles */
    .notification-button {
        position: relative;
        background: #ffffff;
        border: none;
        cursor: pointer;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #333;
        text-decoration: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: all 0.2s ease;
    }

    .notification-button:hover {
        background: #f8f9fa;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .notification-button i {
        font-size: 18px;
        color:rgb(0, 0, 0);
    }

    .notification-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4444;
        color: white;
        border-radius: 50%;
        padding: 3px 6px;
        font-size: 10px;
        min-width: 15px;
        height: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        border: 2px solid #ffffff;
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
        background-color: rgba(138, 129, 129, 0.25);
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

    .notification-details {
        font-size: 0.9em;
        color: rgb(0, 0, 0);
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

    .title-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        padding: 0 20px;
    }

    .header-title {
        margin: 0;
    }

    .header-controls {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-right: 20px;
    }

    .notification-button {
        position: relative;
        background: white;
        border: none;
        cursor: pointer;
        padding: 5px;
        display: flex;
        align-items: center;
        color: #333;
        text-decoration: none;
    }

    .notification-button i {
        font-size: 20px;
        color: #6B6F7B;
    }

    .notification-button:hover i {
        color: #343A40;
    }

    .notification-badge {
        position: absolute;
        top: -3px;
        right: -3px;
        background: #ff4444;
        color: white;
        border-radius: 50%;
        padding: 3px 6px;
        font-size: 10px;
        min-width: 15px;
        height: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .notification-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .notification-header i {
        color: #6B6F7B;
        font-size: 18px;
    }

    .notification-header h3 {
        margin: 0;
        font-size: 16px;
        color: #333;
    }
    </style>
</head>

<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">
            <h2 class="header-title">Dashboard</h2>
            <div class="header-controls">
                <a href="javascript:void(0);" onclick="showNotifications();" class="notification-button" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </a>
                <div id="notificationDropdown" class="notification-dropdown">
                    <div class="notification-header">
                        <i class="fas fa-bell"></i>
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
                                duedate DESC");
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
                                // Format the date to day month year
                                $formattedDate = date('d F Y', strtotime($notif['duedate']));
                                
                                echo "<a href='borrowlist.php?search=" . urlencode($notif['name']) . "' class='notification-item'>";
                                echo "<div class='notification-icon'><i class='fas fa-book' style='color: {$statusColor}'></i></div>";
                                echo "<div class='notification-content'>";
                                echo "<div class='notification-title'><strong>{$notif['name']}</strong></div>";
                                echo "<div class='notification-details'>{$notif['b_bookname']}</div>";
                                echo "<div class='notification-status' style='color: {$statusColor}'>" . ucfirst($notif['borrow_status']) . "</div>";
                                echo "<div class='notification-time'>Due: {$formattedDate}</div>";
                                echo "</div></a>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'navbar.php' ?>
    <div class="dashboard-container">
        <div class="banner-container">

            <!-- banner container 1 -->
            <div class="banner-1">

                <div class="value-container">
                    <div class="banner-value">
                        <p><?php echo htmlspecialchars($totalBooks); ?></p>
                    </div>
                    <div class="banner-title">
                        <p>Books</p>
                    </div>
                </div>
                <div class="icon-container">
                    <i class="fa fa-book" aria-hidden="true"></i>
                </div>
            </div>
            <!-- banner container 2 -->
            <div class="banner-1">

                <div class="value-container">
                    <div class="banner-value">
                        <p><?php echo htmlspecialchars($totalBorrowed); ?></p>
                    </div>
                    <div class="banner-title">
                        <p>Borrowed Books</p>
                    </div>
                </div>
                <div class="icon-container">
                    <i class="fa fa-handshake-o" aria-hidden="true"></i>
                </div>
            </div>
            <!-- banner container 3 -->
            <div class="banner-3">

                <div class="value-container">
                    <div class="banner-value">
                        <p><?php echo htmlspecialchars($totalReturned); ?></p>
                    </div>
                    <div class="banner-title">
                        <p>Returned Books</p>
                    </div>
                </div>
                <div class="icon-container">
                    <i class="fa fa-check" aria-hidden="true"></i>
                </div>

            </div>
            <!-- banner container 4 -->
            <div class="banner-1">

                <div class="value-container">
                    <div class="banner-value">
                        <p><?php echo htmlspecialchars($totalUsers); ?></p>
                    </div>
                    <div class="banner-title">
                        <p>Users</p>
                    </div>
                </div>
                <div class="icon-container">
                    <i class="fa fa-user" aria-hidden="true"></i>
                </div>
            </div>
        </div>
        <!-- 2nd division of the webpage -->
        <div class="table-container">
            <div class="div-1">

                <center>
                    <h2>Inventory Overview</h2>
                </center>
                <div class="content-container">

                    <div class="table-wrapper">
                        <table class="fl-table">
                            <thead>
                                <tr>
                                    <th>Quantity</th>
                                    <th>Category</th>

                                    <th>Book Name</th>

                                    <th>Year</th>

                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($books) : ?>
                                    <?php foreach ($books as $book) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($book['book_category']); ?></td>
                                            <td><?php echo htmlspecialchars($book['book_name']); ?></td>
                                            <td><?php echo htmlspecialchars($book['year']); ?></td>

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
                </div>
                <div class="view-button">
                    <button type="submit">
                        <a href="inventory.php">View Inventory</a></button>
                </div>

            </div>
            <div class="div-3">
                <div class="div-3-title-container">
                    <div class="div-3-title">
                        <h2>DATE TODAY</h2>
                    </div>
                </div>
                <h3 id="current-date"></h3>
                <div class="calendar-container">
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button id="prev-month">&#10094;</button>
                            <div id="month-year"></div>
                            <button id="next-month">&#10095;</button>
                        </div>
                        <div class="calendar-body">
                            <div class="calendar-weekdays">
                                <div>Sun</div>
                                <div>Mon</div>
                                <div>Tue</div>
                                <div>Wed</div>
                                <div>Thu</div>
                                <div>Fri</div>
                                <div>Sat</div>
                            </div>
                            <div class="calendar-days" id="calendar-days"></div>

                        </div>
                    </div>


                </div>
                <div class="view-button">
                    <button type="submit">
                        <a href="borrowlist.php">Borrowed List</a></button>
                </div>
            </div>

        </div>
    </div>

</body>

<script>
    // Make content visible after page load and animations start
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            const elements = document.querySelectorAll('.hidden-before-animation');
            elements.forEach(function(element) {
                element.classList.add('show-content');
            });
        }, 100); // Adjust the delay if needed
    });

    // Update date and time
    function updateDateTime() {
        const dateElement = document.getElementById('current-date');

        const now = new Date();

        // Format date
        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        const currentDate = now.toLocaleDateString(undefined, options);
        dateElement.innerText = currentDate;
    }

    // Initialize with current date and time
    updateDateTime();

    const calendarDays = document.getElementById('calendar-days');
    const monthYear = document.getElementById('month-year');
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();

    function generateCalendar(month, year) {
        const now = new Date();
        const today = now.getDate();
        const thisMonth = now.getMonth();
        const thisYear = now.getFullYear();

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Clear previous calendar days
        calendarDays.innerHTML = '';

        // Update month-year text
        monthYear.innerText = new Date(year, month).toLocaleString('default', {
            month: 'long',
            year: 'numeric'
        });

        // Fill in the days before the 1st day of the month
        for (let i = 0; i < firstDay; i++) {
            calendarDays.innerHTML += `<div></div>`;
        }

        // Add the days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dayDiv = document.createElement('div');
            dayDiv.innerText = day;
            if (day === today && month === thisMonth && year === thisYear) {
                dayDiv.classList.add('today');
            }
            calendarDays.appendChild(dayDiv);
        }
    }

    document.getElementById('prev-month').addEventListener('click', () => {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        generateCalendar(currentMonth, currentYear);
    });

    document.getElementById('next-month').addEventListener('click', () => {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        generateCalendar(currentMonth, currentYear);
    });

    generateCalendar(currentMonth, currentYear);

    // Add notification JavaScript
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
        event.stopPropagation();
    });
</script>


</html>