<?php
session_start();
require 'php/config.php'; // Include your database connection configuration

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trim input to remove any leading/trailing spaces
    $username = trim($_POST['username']);
    $Password = trim($_POST['Password']);

    // Prepare and execute the query to fetch user data based on the username
    $stmt = $pdo->prepare('SELECT * FROM ccai_user WHERE Username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Debugging: Output fetched user data (optional)
    // error_log(print_r($user, true));
    // error_log('Entered Password: ' . $Password);
    // error_log('Stored Hash: ' . $user['Password']);
    // error_log('Password match result: ' . password_verify($Password, $user['Password']));


    if ($user) {
        // Check if the password matches the stored hash
        if (password_verify($Password, $user['Password'])) {
            // If password matches, set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_label'] = $user['Label']; // Uncomment if you need this session variable
            header('Location: php/home.php');
            exit;
        } else {
            // If password does not match, set error message
            $error = 'Incorrect password';
        }
    } else {
        // If username does not exist, set error message
        $error = 'Username does not exist';
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
    <link rel="icon" href="../logo.png" type="image/x-icon" />
    <link rel="stylesheet" type="text/css" href="css/index.css">
    <!-- Font Awesome CDN for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <div class="login-container">
        <h2>Login</h2>
        <form method="post" action="">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
                <i class="fas fa-user input-icon"></i>
            </div>
            <div class="input-group password-wrapper">
                <input type="password" name="Password" placeholder="Password" required>
                <i class="fas fa-lock input-icon"></i>
                <i class="fas fa-eye toggle-password"></i>
            </div>

            <button type="submit" class="loginButton">Login</button>
        </form>

        <!-- Display error message if set -->
        <?php if (isset($error)) : ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>
        
    </div>
    <script src="JavaScript/password-toggle.js"></script>
</body>

</html>
