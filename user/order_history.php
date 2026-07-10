<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all orders for the logged-in user with payment and delivery status
// Uses LEFT JOIN to include orders that may not have payment or delivery records yet
$stmt = $conn->prepare("
    SELECT 
        o.id AS order_id,
        o.order_number,
        o.total_amount,
        o.status AS order_status,
        o.created_at,
        pm.method_name AS payment_method,
        p.status AS payment_status,
        d.delivery_status,
        d.receiver_name,
        d.shipped_at
    FROM Orders o
    LEFT JOIN Payment p ON o.id = p.order_id
    LEFT JOIN payment_method pm ON p.payment_method_id = pm.id
    LEFT JOIN Delivery d ON p.id = d.payment_id
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

// Determine overall status for the timeline based on payment and delivery states
function getOverallStatus($order_status, $payment_status, $delivery_status) {
    if ($order_status === 'cancelled') {
        return 'Cancelled';
    }
    if ($delivery_status === 'delivered') {
        return 'Delivered';
    }
    if ($delivery_status === 'shipping') {
        return 'Shipped';
    }
    if ($payment_status === 'paid') {
        return 'Paid';
    }
    return 'Pending';
}

// Map overall status to a numeric step level for the timeline
function getStatusStep($status) {
    $steps = [
        'Pending' => 1,
        'Paid' => 2,
        'Shipped' => 3,
        'Delivered' => 4,
        'Cancelled' => 0
    ];
    return isset($steps[$status]) ? $steps[$status] : 1;
}

// Fetch order items for all orders in one query to avoid N+1
$order_ids = [];
$orders_data = [];
while ($row = $orders_result->fetch_assoc()) {
    $orders_data[] = $row;
    $order_ids[] = $row['order_id'];
}

$order_items_map = [];
if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $item_types = str_repeat('i', count($order_ids));
    
    $item_stmt = $conn->prepare("
        SELECT oi.order_id, b.title, oi.quantity
        FROM Order_item oi
        JOIN Books b ON oi.book_id = b.id
        WHERE oi.order_id IN ($placeholders)
    ");
    $item_stmt->bind_param($item_types, ...$order_ids);
    $item_stmt->execute();
    $items_result = $item_stmt->get_result();
    
    while ($item_row = $items_result->fetch_assoc()) {
        $oid = $item_row['order_id'];
        if (!isset($order_items_map[$oid])) {
            $order_items_map[$oid] = [];
        }
        $order_items_map[$oid][] = $item_row['title'] . ' (x' . $item_row['quantity'] . ')';
    }
    $item_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History & Tracking - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans min-h-screen">

    <nav class="bg-white shadow-sm border-b border-gray-200 p-4">
        <div class="max-w-4xl mx-auto flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold text-indigo-600">📚 BookShop</a>
            <span class="text-gray-600 text-sm">Order History</span>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">

        <?php if (isset($_GET['status']) && $_GET['status'] == 'submitted'): ?>
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-2xl flex items-center gap-3 shadow-sm">
                <span class="text-xl">✅</span>
                <div>
                    <strong class="block text-sm">Payment slip submitted successfully!</strong>
                    <span class="text-xs text-emerald-600">Once the admin verifies your payment, the status will update automatically.</span>
                </div>
            </div>
        <?php endif; ?>

        <h1 class="text-2xl font-extrabold text-gray-900 mb-6">📦 Your Orders & Delivery Status</h1>

        <?php if (empty($orders_data)): ?>
            <div class="bg-white text-center p-12 rounded-2xl border border-gray-200 shadow-sm">
                <p class="text-gray-500 mb-4">You have no orders yet.</p>
                <a href="../index.php" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-bold">Browse Books</a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders_data as $order):
                    $overall = getOverallStatus($order['order_status'], $order['payment_status'], $order['delivery_status']);
                    $step_level = getStatusStep($overall);
                    $items_text = isset($order_items_map[$order['order_id']]) ? implode(', ', $order_items_map[$order['order_id']]) : 'N/A';
                    $display_id = !empty($order['order_number']) ? $order['order_number'] : '#' . $order['order_id'];
                    $date = date('Y-m-d', strtotime($order['created_at']));
                    $payment_display = !empty($order['payment_method']) ? $order['payment_method'] : 'N/A';
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                        <div class="bg-gray-50/70 p-4 border-b border-gray-100 flex flex-wrap justify-between items-center gap-2">
                            <div>
                                <span class="text-xs text-gray-400 block font-medium">ORDER ID</span>
                                <span class="font-mono font-bold text-gray-800"><?= htmlspecialchars($display_id) ?></span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 block font-medium">Order Date</span>
                                <span class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($date) ?></span>
                            </div>
                            <div>
                                <span class="text-xs text-gray-400 block font-medium">Total Amount</span>
                                <span class="text-sm font-bold text-indigo-600"><?= number_format($order['total_amount']) ?> MMK (<?= htmlspecialchars($payment_display) ?>)</span>
                            </div>
                            <div>
                                <span class="px-3 py-1 text-xs font-bold rounded-full
                                    <?= $overall === 'Delivered' ? 'bg-green-100 text-green-700' : ($overall === 'Pending' ? 'bg-amber-100 text-amber-700' : ($overall === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700')) ?>">
                                    <?= htmlspecialchars($overall) ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            <div>
                                <span class="text-xs text-gray-400 block font-medium mb-1">Items Purchased</span>
                                <p class="text-sm text-gray-700 font-medium"><?= htmlspecialchars($items_text) ?></p>
                            </div>

                            <?php if ($overall === 'Cancelled'): ?>
                                <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                                    <p class="text-sm font-bold text-red-700">This order has been cancelled.</p>
                                </div>
                            <?php else: ?>
                            <div class="relative pt-4">
                                <div class="flex items-center justify-between text-xs md:text-sm">

                                    <div class="flex flex-col items-center z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 <?= $step_level >= 1 ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-400' ?>">1</div>
                                        <span class="mt-2 font-semibold <?= $step_level >= 1 ? 'text-indigo-600' : 'text-gray-400' ?>">Ordered</span>
                                        <span class="text-[10px] text-gray-400">Placed</span>
                                    </div>

                                    <div class="flex-1 h-1 mx-[-4px] mb-7 <?= $step_level >= 2 ? 'bg-indigo-600' : 'bg-gray-200' ?>"></div>

                                    <div class="flex flex-col items-center z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 <?= $step_level >= 2 ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-400' ?>">2</div>
                                        <span class="mt-2 font-semibold <?= $step_level >= 2 ? 'text-indigo-600' : 'text-gray-400' ?>">Paid</span>
                                        <span class="text-[10px] text-gray-400">Confirmed</span>
                                    </div>

                                    <div class="flex-1 h-1 mx-[-4px] mb-7 <?= $step_level >= 3 ? 'bg-indigo-600' : 'bg-gray-200' ?>"></div>

                                    <div class="flex flex-col items-center z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 <?= $step_level >= 3 ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-gray-300 text-gray-400' ?>">3</div>
                                        <span class="mt-2 font-semibold <?= $step_level >= 3 ? 'text-indigo-600' : 'text-gray-400' ?>">Shipped</span>
                                        <span class="text-[10px] text-gray-400">In Transit</span>
                                    </div>

                                    <div class="flex-1 h-1 mx-[-4px] mb-7 <?= $step_level >= 4 ? 'bg-emerald-600' : 'bg-gray-200' ?>"></div>

                                    <div class="flex flex-col items-center z-10">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold border-2 <?= $step_level >= 4 ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-400' ?>">4</div>
                                        <span class="mt-2 font-semibold <?= $step_level >= 4 ? 'text-emerald-600' : 'text-gray-400' ?>">Delivered</span>
                                        <span class="text-[10px] text-gray-400">Received</span>
                                    </div>

                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="mt-8 text-center">
            <a href="../index.php" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">📚 Back to Book Shop</a>
        </div>
    </main>

</body>
</html>
