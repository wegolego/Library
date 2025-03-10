<?php
require 'config.php';

if (isset($_GET['username'])) {
    $username = $_GET['username'];
    $response = ['available' => false, 'message' => ''];

    if (strlen($username) < 8) {
        $response['message'] = 'Username must be at least 8 characters long';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM ccai_user WHERE Username = ?');
            $stmt->execute([$username]);
            $userExists = $stmt->fetchColumn();

            if ($userExists) {
                $response['message'] = 'Username is already in use';
            } else {
                $response['available'] = true;
            }
        } catch (PDOException $e) {
            $response['message'] = 'Error: ' . $e->getMessage();
        }
    }

    echo json_encode($response);
}
?>
