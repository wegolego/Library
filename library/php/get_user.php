<?php
// Include the database configuration file
require 'config.php';

// Check if the user ID is provided in the GET request
if (isset($_GET['id'])) {
    // Get the user ID from the request
    $user_id = $_GET['id'];

    try {
        // Prepare the SQL statement to fetch the user data
        $stmt = $pdo->prepare("SELECT id, F_Name, L_Name, Username FROM ccai_user WHERE id = :id");
        $stmt->bindValue(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        // Fetch the user data
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user data is found
        if ($user) {
            // Return the user data as a JSON response
            echo json_encode($user);
        } else {
            // Return an error if user not found
            echo json_encode(['error' => 'User not found']);
        }
    } catch (PDOException $e) {
        // Return an error message in case of an exception
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    // Return an error if no user ID is provided
    echo json_encode(['error' => 'No user ID provided']);
}
