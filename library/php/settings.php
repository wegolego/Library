<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Fetch user information from the database
$stmt = $pdo->prepare("SELECT F_Name, L_Name, Username FROM ccai_user WHERE id = :id");
$stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize variables with default values
$firstName = isset($user['F_Name']) ? htmlspecialchars($user['F_Name']) : 'N/A';
$lastName = isset($user['L_Name']) ? htmlspecialchars($user['L_Name']) : 'N/A';
$username = isset($user['Username']) ? htmlspecialchars($user['Username']) : 'N/A';

// Fetch all users from the database
$users = [];
try {
    $stmt = $pdo->query("SELECT id, F_Name, L_Name, Username FROM ccai_user");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check for notification parameter
$notification = $_GET['notification'] ?? '';
?>

<!DOCTYPE html>
<html>

<head>
    <title>User Settings</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/settings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            var modal = document.getElementById("updateUserModal");
            var span = document.getElementsByClassName("close")[0];

            $(document).on('click', '.update', function(e) {
                e.preventDefault();
                var userId = $(this).data('id');

                $.ajax({
                    url: 'get_user.php',
                    type: 'GET',
                    data: {
                        id: userId
                    },
                    success: function(response) {
                        try {
                            var user = JSON.parse(response);
                            $('#updateUserId').val(user.id);
                            $('#updateFirstName').val(user.F_Name);
                            $('#updateLastName').val(user.L_Name);
                            $('#updateUsername').val(user.Username);
                            $('#updatePassword').val(''); // Clear password field
                            modal.style.display = "block";
                        } catch (e) {
                            console.error("Error parsing response: ", e);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", xhr.responseText);
                    }
                });
            });

            span.onclick = function() {
                modal.style.display = "none";
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }

            $('#updateUserForm').submit(function(e) {
                e.preventDefault();
                $.ajax({
                    url: 'update_user.php',
                    type: 'POST',
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response === 'success') {
                            window.location.href = 'settings.php?notification=success';
                        } else {
                            alert("Failed to update user.");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", xhr.responseText);
                    }
                });
            });
        });

        function confirmDelete(userId) {
            if (confirm("Are you sure you want to delete this user?")) {
                $.ajax({
                    url: 'delete_user.php',
                    type: 'POST',
                    data: {
                        id: userId
                    },
                    success: function(response) {
                        if (response === 'success') {
                            window.location.href = 'settings.php?notification=deleted';
                        } else {
                            alert("Failed to delete user.");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error: ", xhr.responseText);
                    }
                });
            }
        }
    </script>
</head>

<body>
    <div class="header">
        <div class="sidebar-header">
            <img src="../img/logo.png" alt="Logo" class="nav-logo">
        </div>
        <div class="title-container">
            <h2>Settings</h2>
        </div>
    </div>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-container">
        <div id="notification">
            <div id="updateSuccess" class="success-message" style="display: none;">
                User updated successfully!
            </div>
            <div id="deleteSuccess" class="success-message" style="display: none;">
                User deleted successfully!
            </div>
        </div>

        <div class="settings-table-container">
            <table id="users-table" class="settings-table">
                <thead>
                    <tr>
                        <th>FIRST NAME</th>
                        <th>LAST NAME</th>
                        <th>USERNAME</th>
                        <th>ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users) : ?>
                        <?php foreach ($users as $user) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['F_Name']); ?></td>
                                <td><?php echo htmlspecialchars($user['L_Name']); ?></td>
                                <td><?php echo htmlspecialchars($user['Username']); ?></td>
                                <td class="action-icons">
                                    <a href="#" class="icon update" data-id="<?php echo $user['id']; ?>" title="Update"><i class="fas fa-edit"></i></a>
                                    <div class="icon delete" title="Delete" onclick="confirmDelete(<?php echo $user['id']; ?>)"><i class="fas fa-trash"></i></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Update User Modal -->
        <div id="updateUserModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <div class="modal-header">
                    <h2>Update User Details</h2>
                </div>
                <form id="updateUserForm" action="update_user.php" method="post" class="modal-form">
                    <input type="hidden" name="id" id="updateUserId">
                    <label for="updateFirstName">First Name</label>
                    <div class="form-group">
                        <input type="text" id="updateFirstName" name="F_Name" required>
                    </div>
                    <label for="updateLastName">Last Name</label>
                    <div class="form-group">
                        <input type="text" id="updateLastName" name="L_Name" required>
                    </div>
                    <label for="updateUsername">Username</label>
                    <div class="form-group">
                        <input type="text" id="updateUsername" name="Username" required>
                    </div>
                    <label for="updatePassword">New Password</label>
                    <div class="form-group">
                        <input type="password" id="updatePassword" name="Password" placeholder="Leave blank if not changing">
                    </div>
                    <button type="submit" class="updateButton">Update</button>
                </form>
            </div>
        </div>

        <!-- Create Account Button -->
        <div class="create-account-container">
            <a href="register.php" class="create-account-button">Create an Account</a>
        </div>

        <!-- User Manual Button -->

    </div>

    <script>
        $(document).ready(function() {
            var urlParams = new URLSearchParams(window.location.search);
            var notification = urlParams.get('notification');

            if (notification === 'success') {
                $('#updateSuccess').show().delay(5000).fadeOut();
            } else if (notification === 'deleted') {
                $('#deleteSuccess').show().delay(5000).fadeOut();
            }
        });
    </script>
</body>

</html>