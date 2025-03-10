<?php
session_start();
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = intval($_POST['id']);
    $name = trim($_POST['borrower_name']);
    $number = trim($_POST['id_number']);
    $classification = trim($_POST['classification']);
    $bookName = trim($_POST['book_name']);

    if (!empty($id) && !empty($name) && !empty($number) && !empty($classification) && !empty($bookName)) {
        $stmt = $pdo->prepare("UPDATE borrowers SET name = ?, number = ?, b_class = ?, b_bookname = ? WHERE id = ?");
        if ($stmt->execute([$name, $number, $classification, $bookName, $id])) {
            $_SESSION['message'] = 'The borrower details have been updated successfully.';
        } else {
            $_SESSION['message'] = 'Failed to update borrower details.';
        }
    } else {
        $_SESSION['message'] = 'All fields are required.';
    }
    header('Location: borrowlist.php');
    exit;
} else {
    header('Location: borrowlist.php');
    exit;
}
