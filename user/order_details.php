<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Route Guard: Ensure only customers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/register.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order master record belonging to the authenticated user
$order_query = $conn->prepare("SELECT o.*, p.status AS payment_status, p.transaction_ref 
                               FROM Orders o 
                               LEFT JOIN Payment p ON o.id = p.order_id 
                               WHERE o.id = ? AND o.user_id = ?");
$order_query->bind_param("ii", $order_id, $_SESSION['user_id']);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();
$order_query->close();

// Redirect back to order history if record is missing
if (!$order) {
    header("Location: myorders.php");
    exit();
}

// FIX: Changed ci.order_id to ci.id based on database diagram mapping
$items_query = $conn->prepare("SELECT ci.*, b.title, b.book_image 
                               FROM Cart_item ci 
                               JOIN Books b ON ci.book_id = b.id 
                               WHERE ci.id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();
$items_query->close();
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?= $order_id ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="text-slate-900 antialiased min-h-screen flex flex-col justify-between">

    <?php include "../auth/header.php"; ?>

    <main class="flex-grow max-w-3xl w-full mx-auto px-4 py-6 md:py-10">
        <div class="space-y-6">
            
            <!-- Top Navigation Banner Links -->
            <div class="flex items-center justify-between">
                <a href="myorders.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-indigo-600 transition">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back to My Orders
                </a>
                <span class="text-xs text-slate-400 font-medium">Ordered on: <?= date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now')) ?></span>
            </div>

            <!-- Master Summary Card Layout Element -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Order ID</span>
                    <span class="text-lg font-extrabold text-slate-800">#<?= $order_id ?></span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Order Status</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 bg-indigo-50 text-indigo-700 capitalize">
                        <?= htmlspecialchars($order['status'] ?? 'pending') ?>
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Payment Verification</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 <?= ($order['payment_status'] === 'approved') ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?> capitalize">
                        <?= htmlspecialchars($order['payment_status'] ?? 'Unpaid') ?>
                    </span>
                </div>
            </div>

            <!-- Items Purchased Breakdown Lists Block -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3">Items Purchased</h3>
                <div class="divide-y divide-slate-100">
                    <?php while ($item = $items_result->fetch_assoc()): ?>
                        <div class="flex items-center gap-4 py-4 first:pt-0 last:pb-0">
                            <img src="../assets/<?= htmlspecialchars($item['book_image'] ?? 'placeholder.png') ?>" onerror="this.src='https://placehold.co/60x80?text=Book'" class="w-12 h-16 object-cover rounded-lg bg-slate-100 border border-slate-100 shadow-sm shrink-0">
                            <div class="flex-grow min-w-0">
                                <h4 class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($item['title']) ?></h4>
                                <p class="text-xs text-slate-400 mt-0.5"><?= number_format($item['unit_price']) ?> ကျပ် × <?= $item['quantity'] ?></p>
                            </div>
                            <span class="text-sm font-extrabold text-slate-700 shrink-0"><?= number_format($item['totalprice']) ?> ကျပ်</span>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Aggregate Total Summary Row Display Details -->
                <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-500">Total Net Cost</span>
                    <span class="text-xl font-extrabold text-indigo-600"><?= number_format($order['total_amount']) ?> ကျပ်</span>
                </div>
            </div>

            <!-- Meta Reference Shipping Logs Block -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3">Shipping & Transaction Data</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div class="space-y-1">
                        <span class="text-xs text-slate-400 font-semibold block uppercase">Delivery Location Address</span>
                        <p class="text-slate-600 font-medium leading-relaxed">Refer to Order System Record</p>
                    </div>
                    <div class="space-y-1">
                        <span class="text-xs text-slate-400 font-semibold block uppercase">Transaction Reference Key</span>
                        <p class="text-slate-600 font-mono font-bold uppercase"><?= htmlspecialchars($order['transaction_ref'] ?? 'N/A') ?></p>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include "../auth/footer.php"; ?>

</body>
</html>