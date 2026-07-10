<?php
session_start();
require_once "../config/db.php";

// Accept both logged-in customers and guests
$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer';

// Receive Data
$book_id = isset($_POST['book_id']) ? intval($_POST['book_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($book_id <= 0) {
    header("Location: books.php");
    exit();
}

// Get Book Information
$stmt = $conn->prepare("SELECT * FROM Books WHERE id = ?");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: books.php?error=notfound");
    exit();
}

$book = $result->fetch_assoc();
$price = $book['price'];

// Check Stock
if ($quantity > $book['stock']) {
    header("Location: bookdetail.php?id=" . $book_id . "&error=stock");
    exit();
}

if ($is_logged_in) {
    // Database-based cart for logged-in users
    $user_id = $_SESSION['user_id'];

    $stmt2 = $conn->prepare("SELECT * FROM Cart_item WHERE user_id = ? AND book_id = ?");
    $stmt2->bind_param("ii", $user_id, $book_id);
    $stmt2->execute();
    $cart = $stmt2->get_result();

    if ($cart->num_rows > 0) {
        $row = $cart->fetch_assoc();
        $newQty = $row['quantity'] + $quantity;
        $total = $newQty * $price;

        $update = $conn->prepare("UPDATE Cart_item SET quantity = ?, totalprice = ? WHERE id = ?");
        $update->bind_param("idi", $newQty, $total, $row['id']);
        $update->execute();
    } else {
        $total = $price * $quantity;
        $insert = $conn->prepare("INSERT INTO Cart_item (user_id, book_id, quantity, unit_price, totalprice) VALUES (?, ?, ?, ?, ?)");
        $insert->bind_param("iiidd", $user_id, $book_id, $quantity, $price, $total);
        $insert->execute();
    }

    header("Location: cart.php");
    exit();
} else {
    // Session-based cart for guests
    if (!isset($_SESSION['guest_cart'])) {
        $_SESSION['guest_cart'] = [];
    }

    // Check if book already in guest cart
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

    header("Location: cart.php");
    exit();
}
?>
