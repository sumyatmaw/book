<?php
// Initialize session for flash messages and user authentication check
session_start();

// Include database connection file
require_once '../config/db.php';

// Authorization check: Ensure only Admin or Delivery worker roles can access this page
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'delivery'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Retrieve flash messages from session and clear them immediately
$message = $_SESSION['flash_message'] ?? "";
$error = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// ==========================================================
// HANDLE ORDER STATUS UPDATE WITH STRICT PAYMENT VALIDATION
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = isset($_POST['delivery_status']) ? trim($_POST['delivery_status']) : '';

    if (!empty($new_status) && $order_id > 0) {
        
        // Fetch current order's status and payment status from Payment table
        $pay_stmt = $conn->prepare("
            SELECT Orders.status as current_order_status, Payment.status as payment_status 
            FROM Orders 
            LEFT JOIN Payment ON Orders.id = Payment.order_id 
            WHERE Orders.id = ? LIMIT 1
        ");
        $pay_stmt->bind_param("i", $order_id);
        $pay_stmt->execute();
        $pay_res = $pay_stmt->get_result();
        $pay_row = $pay_res->fetch_assoc();
        $pay_stmt->close();

        $payment_status = strtolower($pay_row['payment_status'] ?? 'pending');

        // STRICT CHECK: Prevent changing status to 'completed' if Payment Status is not 'completed'
        if ($new_status === 'completed' && $payment_status !== 'completed') {
            $_SESSION['flash_error'] = "မအောင်မြင်ပါ။ Payment Status မှာ Paid မဖြစ်သေးပါ (လက်ရှိ Payment: " . ucfirst($payment_status) . ")။ ငွေချေမှု ပြီးစီးမှသာ Completed သို့ ပြောင်းလဲနိုင်ပါမည်။";
        } else {
            // Update Orders table status
            $update_stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_status, $order_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['flash_message'] = "Order status ကို အောင်မြင်စွာ ပြောင်းလဲပြီးပါပြီ။";
            } else {
                $_SESSION['flash_error'] = "Status ပြောင်းလဲရာတွင် အမှားအယွင်း ရှိနေပါသည်။";
            }
            $update_stmt->close();
        }

        // Redirect back to same page (PRG Pattern)
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// -------------------------------------------------------------------------
// PAGINATION SETUP FOR DELIVERY ORDERS
// -------------------------------------------------------------------------
$limit = 10; // Number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total delivery orders count
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Orders");
$totalOrders = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($totalOrders / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch paginated delivery orders with associated customer and payment details
$sql = "SELECT Orders.*, Orders.status as delivery_status, Users.name as customer_name, Users.phone as customer_phone, Payment.status as payment_status 
        FROM Orders 
        LEFT JOIN Users ON Orders.user_id = Users.id 
        LEFT JOIN Payment ON Orders.id = Payment.order_id
        ORDER BY Orders.id DESC
        LIMIT ? OFFSET ?";
$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Online Book Shop</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-300 font-sans text-slate-800">

<div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR COMPONENT -->
    <?php include '../auth/sidebar.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- TOP HEADER NAVBAR -->
        <header class="h-16 bg-yellow-300 border-b border-slate-200/80 flex items-center justify-between px-4 md:px-6 shrink-0 z-40">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-base sm:text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-truck-fast text-indigo-600"></i> Delivery Management
                </h1>
            </div>
            <span class="text-xs font-semibold bg-white px-3 py-1.5 rounded-full shadow-sm text-slate-700">
                Role: <?= ucfirst($_SESSION['user_role']); ?>
            </span>
        </header>

        <!-- MAIN PAGE CANVAS -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <!-- PAGE TITLE & DESCRIPTION -->
            <div class="mb-6">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Delivery Orders</h2>
                <p class="text-xs text-slate-900 mt-1">Payment Status (Paid) ဖြစ်မှသာ Order Status ကို Completed ပြောင်းလဲနိုင်ပါမည်။</p>
            </div>

            <!-- SUCCESS FLASH ALERT -->
            <?php if (!empty($message)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- ERROR FLASH ALERT -->
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- ORDERS DELIVERY DATA TABLE CONTAINER -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full no-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead>
                            <tr class="bg-white text-slate-700 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                <th class="px-5 py-4">Order ID</th>
                                <th class="px-5 py-4">Customer</th>
                                <th class="px-5 py-4">Phone</th>
                                <th class="px-5 py-4">Payment Status</th>
                                <th class="px-5 py-4">Order Status</th>
                                <th class="px-5 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php 
                                        $payStatus = strtolower($row['payment_status'] ?? 'pending'); 
                                        $isPayCompleted = ($payStatus === 'completed');
                                        $delStatus = strtolower($row['delivery_status'] ?? 'pending');
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <!-- ORDER ID -->
                                        <td class="px-5 py-4 font-bold text-indigo-600 font-mono">
                                            #<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?>
                                        </td>

                                        <!-- CUSTOMER NAME -->
                                        <td class="px-5 py-4 font-semibold text-slate-900">
                                            <?= htmlspecialchars($row['customer_name'] ?? 'Guest'); ?>
                                        </td>

                                        <!-- PHONE NUMBER -->
                                        <td class="px-5 py-4 text-slate-600">
                                            <i class="fa-solid fa-phone text-slate-400 mr-1"></i>
                                            <?= htmlspecialchars($row['customer_phone'] ?? 'N/A'); ?>
                                        </td>

                                        <!-- PAYMENT STATUS BADGE -->
                                        <td class="px-5 py-4">
                                            <?php if ($isPayCompleted): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                    <i class="fa-solid fa-circle-check"></i> Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-100 text-amber-800">
                                                    <i class="fa-solid fa-clock"></i> Unpaid
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- CURRENT ORDER STATUS BADGE -->
                                        <td class="px-5 py-4">
                                            <?php 
                                            $delBadge = "bg-gray-300 text-slate-600 border-slate-200";
                                            if ($delStatus === 'pending') $delBadge = "bg-amber-50 text-amber-700 border-amber-200";
                                            elseif ($delStatus === 'cancelled') $delBadge = "bg-rose-50 text-rose-700 border-rose-200";
                                            elseif ($delStatus === 'completed') $delBadge = "bg-emerald-50 text-emerald-700 border-emerald-200";
                                            ?>
                                            <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border <?= $delBadge; ?>">
                                                <?= ucfirst($delStatus); ?>
                                            </span>
                                        </td>

                                        <!-- ACTION FORM -->
                                        <td class="px-5 py-4 text-center">
                                            <form method="POST" action="" class="flex items-center justify-center gap-2">
                                                <input type="hidden" name="order_id" value="<?= $row['id']; ?>">
                                                
                                                <select name="delivery_status" class="h-9 w-36 bg-slate-50 text-xs rounded-lg px-2.5 border border-slate-300 outline-none focus:border-indigo-500 font-medium text-slate-700 shrink-0 cursor-pointer">
                                                    <option value="pending" <?= $delStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="cancelled" <?= $delStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    
                                                    <!-- Disable Completed option if payment is unpaid -->
                                                    <option value="completed" 
                                                        <?= $delStatus === 'completed' ? 'selected' : ''; ?> 
                                                        <?= !$isPayCompleted ? 'disabled class="bg-gray-100 text-gray-400"' : ''; ?>>
                                                        Completed <?= !$isPayCompleted ? '(Payment Required)' : ''; ?>
                                                    </option>
                                                </select>
                                                
                                                <button type="submit" name="update_delivery_status" class="h-9 px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-xs transition inline-flex items-center gap-1 shrink-0 cursor-pointer shadow-sm">
                                                    <i class="fa-solid fa-floppy-disk"></i> Save
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-12 text-center text-slate-400 font-semibold">
                                        No delivery orders found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION CONTROLS CONTAINER -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                            Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalOrders); ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalOrders); ?></span> of <span class="font-bold text-slate-700"><?= $totalOrders; ?></span> entries
                        </p>
                        <div class="flex items-center space-x-1">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="delivery.php?page=<?= $page - 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers Loop -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg text-xs font-bold text-white shadow-xs">
                                        <?= $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="delivery.php?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                        <?= $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="delivery.php?page=<?= $page + 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) {
            sidebar.classList.toggle('-translate-x-full');
        }
    }
</script>
</body>
</html>