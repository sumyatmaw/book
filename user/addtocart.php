<?php
session_start();
require_once "../config/db.php";

// Check if user is logged in as a customer
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer';

// Receive Book ID and Quantity from POST
$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

// Redirect if no book ID is provided
if ($book_id <= 0) {
    header("Location: userdashboard.php");
    exit();
}

// Get Book Information from Database
$stmt = $conn->prepare("SELECT * FROM Books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: userdashboard.php?error=notfound");
    exit();
}

$book = $result->fetch_assoc();
$price = $book['price'];
$available_stock = intval($book['stock']);

// Check Input Quantity against Stock Availability
if ($quantity > $available_stock) {
    header("Location: userdashboard.php?error=stock");
    exit();
}

// Logic for Logged-in Users
if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];

    $stmt2 = $conn->prepare("SELECT * FROM Cart_item WHERE user_id = ? AND book_id = ?");
    $stmt2->bind_param("ii", $user_id, $book_id);
    $stmt2->execute();
    $cart = $stmt2->get_result();

    if ($cart->num_rows > 0) {
        $row = $cart->fetch_assoc();
        $newQty = $row['quantity'] + $quantity;
        $total = $newQty * $price;

        // Update Cart Item Quantity
        $update = $conn->prepare("UPDATE Cart_item SET quantity = ?, totalprice = ? WHERE id = ?");
        $update->bind_param("idi", $newQty, $total, $row['id']);
        $update->execute();
    } else {
        $total = $price * $quantity;
        // Insert New Item into Cart
        $insert = $conn->prepare("INSERT INTO Cart_item (user_id, book_id, quantity, unit_price, totalprice) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiidd", $user_id, $book_id, $quantity, $price, $total);
        $insert->execute();
    }

    // Deduct stock immediately from Books table
    // $update_stock_stmt = $conn->prepare("UPDATE Books SET stock = stock - ? WHERE id = ?");
    // $update_stock_stmt->bind_param("ii", $quantity, $book_id);
    // $update_stock_stmt->execute();
    // $update_stock_stmt->close();

    header("Location: cart.php");
    exit();
} else {
    // Logic for Guest Users (Session-based cart)
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }

    $found = false;
    foreach ($_SESSION['guest_cart'] as &$item) {
        if ($item['book_id'] == $book_id) {
            $item['quantity'] += $quantity;
            $item['totalprice'] = $item['quantity'] * $item['unit_price'];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $_SESSION['guest_cart'][] = [
            'book_id' => $book_id,
            'title' => $book['title'],
            'book_image' => $book['book_image'],
            'quantity' => $quantity,
            'unit_price' => $price,
            'totalprice' => $price * $quantity
        ];
    }

    // Deduct stock immediately from Books table for guest user
    // $update_stock_stmt = $conn->prepare("UPDATE Books SET stock = stock - ? WHERE id = ?");
    // $update_stock_stmt->bind_param("ii", $quantity, $book_id);
    // $update_stock_stmt->execute();
    // $update_stock_stmt->close();

    header("Location: cart.php");
    exit();
}
?>