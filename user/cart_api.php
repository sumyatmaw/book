<?php
/**
 * Online Book Shop — Cart API Endpoint
 * Returns JSON with cart count and total amount.
 * Works for both guests (session cart) and logged-in users (DB cart).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$is_logged_in = isset($_SESSION['user_id']);
$cart_count   = 0;
$cart_total   = 0;

if ($is_logged_in) {
    // Database cart for logged-in users
    require_once __DIR__ . '/../config/db.php';
    $uid = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(totalprice),0) AS total FROM Cart_item WHERE user_id = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $cart_count = intval($row['qty'] ?? 0);
    $cart_total = floatval($row['total'] ?? 0);
    $stmt->close();
} elseif (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    // Session cart for guests
    foreach ($_SESSION['guest_cart'] as $gc) {
        $cart_count += intval($gc['quantity']);
        $cart_total += floatval($gc['totalprice']);
    }
}

echo json_encode([
    'count' => $cart_count,
    'total' => round($cart_total, 2)
]);
