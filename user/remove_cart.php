<?php
/**
 * Online Book Shop — Remove Cart Item
 * Removes item from guest session cart or customer DB cart.
 * Supports GET (index-based for guest, id-based for customer) and POST.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$is_logged_in = isset($_SESSION['user_id']);
$guest_cart   = &$_SESSION['guest_cart'] ?? [];

// Determine which item to remove
// Guest: remove by array index (position in cart)
// Customer: remove by cart_item.id
$remove_index = isset($_GET['index']) ? intval($_GET['index']) : (isset($_POST['index']) ? intval($_POST['index']) : -1);
$remove_id    = isset($_GET['id'])    ? intval($_GET['id'])    : (isset($_POST['id'])    ? intval($_POST['id'])    : -1);

if ($is_logged_in && $remove_id > 0) {
    // Customer: delete from DB
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("DELETE FROM Cart_item WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $remove_id, $uid);
    $stmt->execute();
    $stmt->close();
} elseif (!$is_logged_in && $remove_index >= 0) {
    // Guest: remove from session array by index
    if (isset($guest_cart[$remove_index])) {
        array_splice($_SESSION['guest_cart'], $remove_index, 1);
    }
}

// Redirect back to cart page
header("Location: cart.php");
exit;
