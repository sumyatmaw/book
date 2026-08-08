<?php
/**
 * Delivery Management Script
 * Manages order delivery status updates linked directly with `delivery` table
 * using payment_id and delivery_status columns.
 */

session_start();
require_once '../config/db.php';

// Authorization check
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'delivery'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = $_SESSION['flash_message'] ?? "";
$error = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// ==========================================================
// HANDLE DELIVERY STATUS UPDATE
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_status'])) {
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_delivery_status = isset($_POST['delivery_status']) ? trim($_POST['delivery_status']) : '';

    if (!empty($new_delivery_status) && $order_id > 0) {
        
        // Fetch payment status and payment_id for the given order
        $pay_stmt = $conn->prepare("
            SELECT Orders.status as current_order_status, Payment.id as payment_id, Payment.status as payment_status 
            FROM Orders 
            LEFT JOIN Payment ON Orders.id = Payment.order_id 
            WHERE Orders.id = ? LIMIT 1
        ");
        $pay_stmt->bind_param("i", $order_id);
        $pay_stmt->execute();
        $pay_res = $pay_stmt->get_result();
        $pay_row = $pay_res->fetch_assoc();
        $pay_stmt->close();

        $payment_id = $pay_row['payment_id'] ?? null;
        $payment_status = strtolower($pay_row['payment_status'] ?? 'pending');

        // STRICT CHECK: Prevent changing delivery status to 'delivered' if Payment Status is not 'completed' or 'paid'
        if ($new_delivery_status === 'delivered' && !in_array($payment_status, ['completed', 'paid'])) {
            $_SESSION['flash_error'] = "မအောင်မြင်ပါ။ Payment Status မှာ Paid မဖြစ်သေးပါ (လက်ရှိ Payment: " . ucfirst($payment_status) . ")။ ငွေချေမှု ပြီးစီးမှသာ Complete/Delivered သို့ ပြောင်းလဲနိုင်ပါမည်။";
        } else {
            if ($payment_id) {
                // Check if delivery record exists for this payment_id
                $chk_del = $conn->prepare("SELECT id FROM delivery WHERE payment_id = ? LIMIT 1");
                $chk_del->bind_param("i", $payment_id);
                $chk_del->execute();
                $del_exists = $chk_del->get_result()->num_rows > 0;
                $chk_del->close();

                if ($del_exists) {
                    // Update existing record in delivery table using delivery_status column
                    $update_stmt = $conn->prepare("UPDATE delivery SET delivery_status = ? WHERE payment_id = ?");
                    $update_stmt->bind_param("si", $new_delivery_status, $payment_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                } else {
                    // Insert new delivery record if not exists
                    $ins_stmt = $conn->prepare("INSERT INTO delivery (payment_id, delivery_status) VALUES (?, ?)");
                    $ins_stmt->bind_param("is", $payment_id, $new_delivery_status);
                    $ins_stmt->execute();
                    $ins_stmt->close();
                }
            }

            // Auto mark main order status as completed if delivery status is set to delivered
            if ($new_delivery_status === 'delivered') {
                $auto_complete = $conn->prepare("UPDATE Orders SET status = 'completed' WHERE id = ?");
                $auto_complete->bind_param("i", $order_id);
                $auto_complete->execute();
                $auto_complete->close();
            }

            $_SESSION['flash_message'] = "Delivery status ကို အောင်မြင်စွာ ပြောင်းလဲပြီးပါပြီ။";
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// -------------------------------------------------------------------------
// DYNAMIC LIMIT & SMART PAGINATION SETUP
// -------------------------------------------------------------------------
$allowed_limits = [10, 20, 30, 50, 100];
$limit = isset($_GET['limit']) && in_array(intval($_GET['limit']), $allowed_limits) ? intval($_GET['limit']) : 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total orders
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Orders");
$totalOrders = $total_result ? $total_result->fetch_assoc()['total'] : 0;

$total_pages = max(1, ceil($totalOrders / $limit));
if ($page > $total_pages) $page = $total_pages;

$showing_from = $totalOrders > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $limit, $totalOrders);

// JOIN Orders -> Payment -> delivery
$sql = "SELECT Orders.*, 
               COALESCE(delivery.delivery_status, 'pending') as delivery_status, 
               Users.name as customer_name, 
               Users.phone as customer_phone, 
               Payment.status as payment_status 
        FROM Orders 
        LEFT JOIN Users ON Orders.user_id = Users.id 
        LEFT JOIN Payment ON Orders.id = Payment.order_id
        LEFT JOIN delivery ON Payment.id = delivery.payment_id
        ORDER BY Orders.id DESC
        LIMIT ? OFFSET ?";
$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }
        * { scrollbar-width: thin; scrollbar-color: #94a3b8 transparent; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800 h-full overflow-hidden">

<div class="flex h-screen overflow-hidden">

    <!-- Sidebar Include -->
    <?php include '../auth/sidebar.php'; ?>

    <!-- Main Wrapper -->
    <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full">

        <!-- Navigation Bar -->
        <?php 
            $page_title = "Delivery Management";
            include '../auth/nav.php'; 
        ?>

        <main class="p-3 sm:p-5 md:p-8 space-y-6 max-w-[1600px] w-full mx-auto">
            
            <div class="mb-4 sm:mb-6">
                <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Delivery Orders</h2>
                <p class="text-xs text-slate-900 mt-1">Payment Status (Paid) ဖြစ်မှသာ Order Status ကို Completed ပြောင်းလဲနိုင်ပါမည်။</p>
            </div>

            <!-- Flash Alerts -->
            <?php if (!empty($message)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Table Container -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full no-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-700 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                <th class="px-5 py-4">Order ID</th>
                                <th class="px-5 py-4">Customer</th>
                                <th class="px-5 py-4">Phone</th>
                                <th class="px-5 py-4">Payment Status</th>
                                <th class="px-5 py-4">Delivery Status</th>
                                <th class="px-5 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php 
                                        $payStatus = strtolower($row['payment_status'] ?? 'pending'); 
                                        $isPayCompleted = ($payStatus === 'completed' || $payStatus === 'paid');
                                        $delStatus = strtolower($row['delivery_status'] ?? 'pending');
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-5 py-4 font-bold text-indigo-600 font-mono">
                                            #<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?>
                                        </td>

                                        <td class="px-5 py-4 font-semibold text-slate-900">
                                            <?= htmlspecialchars($row['customer_name'] ?? 'Guest'); ?>
                                        </td>

                                        <td class="px-5 py-4 text-slate-600">
                                            <i class="fa-solid fa-phone text-slate-400 mr-1"></i>
                                            <?= htmlspecialchars($row['customer_phone'] ?? 'N/A'); ?>
                                        </td>

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

                                        <td class="px-5 py-4">
                                            <?php 
                                            $delBadge = "bg-gray-100 text-slate-600 border-slate-200";
                                            if ($delStatus === 'pending') $delBadge = "bg-amber-50 text-amber-700 border-amber-200";
                                            elseif ($delStatus === 'shipped') $delBadge = "bg-blue-50 text-blue-700 border-blue-200";
                                            elseif ($delStatus === 'out_for_delivery') $delBadge = "bg-purple-50 text-purple-700 border-purple-200";
                                            elseif ($delStatus === 'delivered') $delBadge = "bg-emerald-50 text-emerald-700 border-emerald-200";
                                            elseif ($delStatus === 'returned') $delBadge = "bg-rose-50 text-rose-700 border-rose-200";
                                            ?>
                                            <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border <?= $delBadge; ?>">
                                                <?= ucfirst(str_replace('_', ' ', $delStatus)); ?>
                                            </span>
                                        </td>

                                        <td class="px-5 py-4 text-center">
                                            <form method="POST" action="" class="flex items-center justify-center gap-2">
                                                <input type="hidden" name="order_id" value="<?= $row['id']; ?>">
                                                
                                                <select name="delivery_status" class="h-9 w-40 bg-slate-50 text-xs rounded-lg px-2.5 border border-slate-300 outline-none focus:border-indigo-500 font-medium text-slate-700 shrink-0 cursor-pointer">
                                                    <option value="pending" <?= $delStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="shipped" <?= $delStatus === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="out_for_delivery" <?= $delStatus === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                                    <option value="delivered" 
                                                        <?= $delStatus === 'delivered' ? 'selected' : ''; ?> 
                                                        <?= !$isPayCompleted ? 'disabled class="bg-gray-100 text-gray-400"' : ''; ?>>
                                                        Delivered <?= !$isPayCompleted ? '(Payment Required)' : ''; ?>
                                                    </option>
                                                    <option value="returned" <?= $delStatus === 'returned' ? 'selected' : ''; ?>>Returned</option>
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

                <!-- Pagination -->
                <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs text-slate-600 font-medium">
                        <span class="whitespace-nowrap">Showing</span>
                        <form method="GET" action="delivery.php" class="inline-block">
                            <input type="hidden" name="page" value="1">
                            <select name="limit" onchange="this.form.submit()" class="bg-white border border-slate-300 text-slate-800 font-bold rounded-lg px-2 py-1 outline-none focus:border-indigo-500 cursor-pointer">
                                <option value="10" <?= $limit == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : ''; ?>>20</option>
                                <option value="30" <?= $limit == 30 ? 'selected' : ''; ?>>30</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?= $limit == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                        </form>
                        <span class="whitespace-nowrap">
                            (<?= $showing_from; ?>–<?= $showing_to; ?> of <?= $totalOrders; ?> entries)
                        </span>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-1.5 text-xs">
                            <?php if ($page > 1): ?>
                                <a href="delivery.php?page=<?= $page - 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-2.5 sm:px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg font-bold text-slate-400 cursor-not-allowed flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </span>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white"><?= $i; ?></span>
                                <?php else: ?>
                                    <a href="delivery.php?page=<?= $i; ?>&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?= $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="delivery.php?page=<?= $page + 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1">
                                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-2.5 sm:px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg font-bold text-slate-400 cursor-not-allowed flex items-center gap-1">
                                    Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.toggle('-translate-x-full');
    }

    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        if (notiDropdown) notiDropdown.classList.toggle('hidden');
        if (profileDropdown) profileDropdown.classList.add('hidden');
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        if (profileDropdown) profileDropdown.classList.toggle('hidden');
        if (notiDropdown) notiDropdown.classList.add('hidden');
    }

    window.addEventListener('click', function(e) {
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        const notiBtn = document.getElementById('notiBtn');
        const profileBtn = document.getElementById('profileBtn');

        if (notiDropdown && !notiDropdown.contains(e.target) && notiBtn && !notiBtn.contains(e.target)) {
            notiDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.contains(e.target) && profileBtn && !profileBtn.contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
</script>
</body>
</html>