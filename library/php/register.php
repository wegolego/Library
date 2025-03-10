<?php
require 'config.php';
$success = $error = '';

// Helper function to validate the username
function isValidUsername($username)
{
    return strlen($username) >= 8;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate the username
    if (!isValidUsername($username)) {
        $error = 'Username must be at least 8 characters long';
    } elseif ($password === $confirm_password) {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ccai_user WHERE Username = ?');
            $stmt->execute([$username]);
            $userExists = $stmt->fetchColumn();

            if ($userExists) {
                $error = 'Username is already in use';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new user into the database
                $stmt = $pdo->prepare('INSERT INTO ccai_user (F_Name, L_Name, Username, Password) VALUES (?, ?, ?, ?)');
                $stmt->execute([$first_name, $last_name, $username, $hashed_password]);

                // Redirect to settings.php after successful registration
                header('Location: settings.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Registration failed: ' . $e->getMessage();
        }
    } else {
        $error = 'Passwords do not match';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Register</title>
    <link rel="icon" href="../img/logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="../css/register.css">
    <!-- Font Awesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="register-container">
        <h2>Register</h2>
        <form method="post" action="">
            <div class="input-group">
                <input type="text" name="first_name" placeholder="First Name" required>
                <i class="fas fa-address-card input-icon"></i>
            </div>
            <div class="input-group">
                <input type="text" name="last_name" placeholder="Last Name" required>
                <i class="fas fa-address-card input-icon"></i>
            </div>
            <div class="input-group">
                <input type="text" id="username" name="username" placeholder="Username" required>
                <i class="fas fa-user input-icon"></i>
                <span id="username-feedback" class="feedback"></span>
            </div>

            <div class="input-group password-wrapper">
                <input type="password" name="password" placeholder="Password" required>
                <i class="fas fa-lock input-icon"></i>
                <i class="fas fa-eye toggle-password"></i>
            </div>
            <button type="submit">Register</button>
            <!-- Back Button -->
            <a href="settings.php" class="back-button"><i class="fa-solid fa-backward"></i></a>
        </form>
        <?php if ($success) : ?>
            <p class="success-message"><?php echo $success; ?></p>
        <?php elseif ($error) : ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
    </div>
    <script src="../JavaScript/password-toggle.js"></script> <!-- Include your JavaScript for toggling password visibility -->
    <script src="../JavaScript/username-validation.js"></script> <!-- Include your JavaScript for real-time validation -->
</body>

</html>
