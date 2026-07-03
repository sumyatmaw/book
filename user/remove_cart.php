<?php
session_start();
require_once "../config/db.php";

// Customer Login Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != "customer") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Cart ID စစ်
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: cart.php");
    exit();
}

$cart_id = intval($_GET['id']);

// Customer ရဲ့ Cart Item ကိုသာ ဖျက်မယ်
$stmt = $conn->prepare("DELETE FROM Cart_item WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $cart_id, $user_id);

if ($stmt->execute()) {
    header("Location: cart.php?msg=removed");
    exit();
} else {
    echo "Failed to remove item.";
}

$stmt->close();
$conn->close();
?>