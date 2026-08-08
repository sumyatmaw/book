<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

// Route Guard: Ensure only logged-in customers can access this page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/register.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Fetch order master record belonging to the authenticated user
$order_query = $conn->prepare("SELECT o.*, p.status AS payment_status, p.transaction_ref 
                               FROM orders o 
                               LEFT JOIN payment p ON o.id = p.order_id 
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

// Fetch ordered items joined with books table using order_item table
$items_query = $conn->prepare("SELECT oi.*, b.* 
                               FROM order_item oi 
                               JOIN books b ON oi.book_id = b.id 
                               WHERE oi.order_id = ?");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();

// Calculate total book cost directly from items (excluding delivery fees)
$calculated_books_total = 0;
$items_array = [];

if ($items_result && $items_result->num_rows > 0) {
    while ($item = $items_result->fetch_assoc()) {
        $unit_price = $item['unit_price'] ?? $item['price'] ?? 0;
        $subtotal = $item['totalprice'] ?? $item['subtotal'] ?? ($unit_price * $item['quantity']);
        $calculated_books_total += $subtotal;
        $items_array[] = array_merge($item, [
            'calculated_unit_price' => $unit_price,
            'calculated_subtotal' => $subtotal
        ]);
    }
}
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

    <main class="flex-grow max-w-3xl w-full mx-auto px-4 py-4 sm:py-6 md:py-10">
        <div class="space-y-4 sm:space-y-6">
            
            <!-- Top Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-0">
                <a href="../user/dashboard.php" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-indigo-600 transition">
                    <i class="fa-solid fa-arrow-left text-xs"></i> Back to My Orders
                </a>
                <span class="text-xs text-slate-400 font-medium">Ordered on: <?= date('d M Y, h:i A', strtotime($order['created_at'] ?? $order['order_date'] ?? 'now')) ?></span>
            </div>

            <!-- Master Summary Card -->
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-sm border border-slate-100 grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Order ID</span>
                    <span class="text-base sm:text-lg font-extrabold text-slate-800">#<?= $order_id ?></span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Order Status</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 bg-indigo-50 text-indigo-700 capitalize">
                        <?= htmlspecialchars($order['status'] ?? 'pending') ?>
                    </span>
                </div>
                <div>
                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Payment Verification</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mt-1 <?= (($order['payment_status'] ?? '') === 'approved') ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?> capitalize">
                        <?= htmlspecialchars($order['payment_status'] ?? 'Unpaid') ?>
                    </span>
                </div>
            </div>

            <!-- Items Purchased Breakdown -->
            <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-6 shadow-sm border border-slate-100 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b border-slate-100 pb-3">Items Purchased</h3>
                <div class="divide-y divide-slate-100">
                    <?php if (!empty($items_array)): ?>
                        <?php foreach ($items_array as $item): 
                            // Dynamically find image column name from database
                            $image_name = $item['book_image'] ?? $item['cover_image'] ?? $item['image'] ?? $item['photo'] ?? '';
                            
                            // Set full file path based on folder existence
                            if (!empty($image_name) && file_exists("../uploads/" . $image_name)) {
                                $img_path = "../uploads/" . $image_name;
                            } elseif (!empty($image_name) && file_exists("../assets/images/" . $image_name)) {
                                $img_path = "../assets/images/" . $image_name;
                            } elseif (!empty($image_name) && file_exists("../assets/" . $image_name)) {
                                $img_path = "../assets/" . $image_name;
                            } else {
                                $img_path = "https://placehold.co/60x80?text=Book";
                            }
                        ?>
                            <div class="flex items-center gap-3 sm:gap-4 py-3 sm:py-4 first:pt-0 last:pb-0">
                                <img src="<?= htmlspecialchars($img_path) ?>" 
                                     onerror="this.src='https://placehold.co/60x80?text=Book'" 
                                     alt="<?= htmlspecialchars($item['title'] ?? 'Book Image') ?>" 
                                     class="w-12 h-16 object-cover rounded-lg bg-slate-100 border border-slate-100 shadow-sm shrink-0">
                                <div class="flex-grow min-w-0">
                                    <h4 class="text-xs sm:text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($item['title'] ?? 'Unknown Book') ?></h4>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= number_format($item['calculated_unit_price']) ?> ကျပ် × <?= $item['quantity'] ?></p>
                                </div>
                                <span class="text-xs sm:text-sm font-extrabold text-slate-700 shrink-0"><?= number_format($item['calculated_subtotal']) ?> ကျပ်</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-slate-500 text-sm py-2">No items found for this order.</p>
                    <?php endif; ?>
                </div>

                <!-- Aggregate Total Summary (Books Total Only) -->
                <div class="border-t border-slate-100 pt-4 flex items-center justify-between">
                    <span class="text-xs sm:text-sm font-bold text-slate-500">စုစုပေါင်း</span>
                    <span class="text-lg sm:text-xl font-extrabold text-indigo-600"><?= number_format($calculated_books_total) ?> ကျပ်</span>
                </div>
            </div>

        </div>
    </main>

    <?php include "../auth/footer.php"; ?>

</body>
</html>