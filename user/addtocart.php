<?php
session_start();
require_once "../config/db.php";

// Customer Login Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != "customer") {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Receive Data
$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($book_id <= 0) {
    header("Location: books.php");
    exit();
}

// Get Book Information
$sql = "SELECT * FROM Books WHERE id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Book not found.");
}

$book = $result->fetch_assoc();
$price = $book['price'];

// Check Stock
if ($quantity > $book['stock']) {
    die("Stock is not enough.");
}

// Check Existing Cart
$sql = "SELECT * FROM Cart_item WHERE user_id=? AND book_id=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $book_id);
$stmt->execute();
$cart = $stmt->get_result();

if ($cart->num_rows > 0) {
    // Update Quantity if item already exists
    $row = $cart->fetch_assoc();
    $newQty = $row['quantity'] + $quantity;
    $total = $newQty * $price;

    $update = $conn->prepare("UPDATE Cart_item SET quantity=?, totalprice=? WHERE id=?");
    $update->bind_param("idi", $newQty, $total, $row['id']);
    $update->execute();
} else {
    // Insert New Cart Item if it doesn't exist
    $total = $price * $quantity;

    $insert = $conn->prepare("INSERT INTO Cart_item (user_id, book_id, quantity, unit_price, totalprice) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("iiidd", $user_id, $book_id, $quantity, $price, $total);
    $insert->execute();
}

// Redirect to cart page
header("Location: cart.php");
exit();
?>