<?php
/**
 * Orders Management Script
 * Manages customer order statuses, inventory deductions upon completion,
 * dynamic pagination limits, and payment validations without delivery fees.
 */

session_start();
require_once '../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';

// Fetch current admin profile image
$admin_image = $_SESSION['user_image'] ?? ''; 
if (empty($admin_image)) {
    $admin_query = mysqli_query($conn, "SELECT image FROM Users WHERE id = $admin_id");
    if ($admin_query && mysqli_num_rows($admin_query) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_query);
        $admin_image = $admin_row['image'] ?? '';
    }
}
$profile_path = !empty($admin_image) ? "../uploads/profile/" . $admin_image : "";

// ==========================================================
// HANDLE ORDER STATUS UPDATE WITH PAYMENT VALIDATION
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = trim($_POST['status']);

    if (!empty($new_status)) {
        
        // Check current payment status for this order from Payment table
        $pay_chk = $conn->prepare("SELECT status FROM Payment WHERE order_id = ? LIMIT 1");
        $pay_chk->bind_param("i", $order_id);
        $pay_chk->execute();
        $pay_res = $pay_chk->get_result();
        $payment_row = $pay_res->fetch_assoc();
        $pay_chk->close();

        $payment_status = strtolower($payment_row['status'] ?? 'pending');

        // Prevent setting order to 'completed' if payment is NOT completed
        if ($new_status === 'completed' && $payment_status !== 'completed') {
            $error = "မအောင်မြင်ပါ။ Payment မပြည့်စုံသေးပါ (Payment status: " . ucfirst($payment_status) . ")။ Payment ကို Completed ပြောင်းပြီးမှသာ Order ကို Complete လုပ်နိုင်ပါမည်။";
        } else {
            
            // Start Database Transaction
            $conn->begin_transaction();

            try {
                if ($new_status === 'completed') {
                    // Check stock availability before completing order
                    $stock_chk = $conn->prepare("SELECT Order_item.book_id, Order_item.quantity, Books.title, Books.stock 
                                                 FROM Order_item 
                                                 JOIN Books ON Order_item.book_id = Books.id 
                                                 WHERE Order_item.order_id = ?");
                    $stock_chk->bind_param("i", $order_id);
                    $stock_chk->execute();
                    $stock_res = $stock_chk->get_result();

                    $insufficient = [];
                    $items = [];
                    while ($r = $stock_res->fetch_assoc()) {
                        if ($r['stock'] < $r['quantity']) {
                            $insufficient[] = $r['title'] . " (Stock: " . $r['stock'] . ", Order: " . $r['quantity'] . ")";
                        }
                        $items[] = $r;
                    }
                    $stock_chk->close();

                    if (!empty($insufficient)) {
                        throw new Exception("Stock မလောက်ပါ - " . implode(", ", $insufficient));
                    }

                    // Deduct stock from Books table
                    $u_stock = $conn->prepare("UPDATE Books SET stock = stock - ? WHERE id = ?");
                    foreach ($items as $item) {
                        $u_stock->bind_param("ii", $item['quantity'], $item['book_id']);
                        $u_stock->execute();
                    }
                    $u_stock->close();
                }

                // Update Order Status
                $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE id = ?");
                $stmt->bind_param("si", $new_status, $order_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                $message = "Order status updated successfully!";

            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// DYNAMIC LIMIT & SMART PAGINATION SETUP
// -------------------------------------------------------------------------
// Handle items-per-page limit selector
$allowed_limits = [10, 20, 30, 50, 100];
$limit = isset($_GET['limit']) && in_array(intval($_GET['limit']), $allowed_limits) ? intval($_GET['limit']) : 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total orders count
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Orders");
$totalOrders = $total_result ? $total_result->fetch_assoc()['total'] : 0;

$total_pages = max(1, ceil($totalOrders / $limit));
if ($page > $total_pages) $page = $total_pages;

// Calculate Showing entries boundaries
$showing_from = $totalOrders > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $limit, $totalOrders);

// Fetch paginated orders list (Calculate item subtotal directly from Order_item table)
$sql = "SELECT Orders.*, 
               COALESCE(
                   (SELECT SUM(price * quantity) FROM Order_item WHERE order_id = Orders.id), 
                   Orders.total_amount, 
                   0
               ) AS calculated_total,
               Users.name as customer_name, 
               Payment.status as payment_status 
        FROM Orders 
        LEFT JOIN Users ON Orders.user_id = Users.id 
        LEFT JOIN Payment ON Orders.id = Payment.order_id
        ORDER BY Orders.id DESC
        LIMIT ? OFFSET ?";
$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();

// Notifications
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = mysqli_num_rows($pending_payments_query);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
    /* Active navigation link highlight */
    .header-nav a.active,
    .header-nav button.active {
        font-weight: 700;
        color: #1e293b !important;
    }

    /* Desktop category dropdown opens on hover */
    @media (min-width: 768px) {
        .cat-dropdown:hover>.cat-dropdown-menu {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Mobile menu slide animation */
    #mobileMenu {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-in-out;
    }

    #mobileMenu.open {
        max-height: 85vh;
        overflow-y: auto;
    }

    /* Category dropdown styling */
    .cat-dropdown-menu {
        display: none;
        opacity: 0;
        transform: translateY(-2px);
        transition: opacity 0.15s ease;
    }

    .cat-dropdown.open>.cat-dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Search bar styling */
    .header-search {
        background-color: #ffffff !important;
        box-shadow: none !important;
    }

    .header-search:focus {
        outline: none !important;
        box-shadow: none !important;
    }

    .header-search::placeholder {
        color: #94a3b8;
    }

    /* Hamburger menu bar animation */
    .hamburger-bar {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    /* Custom scrollbar width */
    ::-webkit-scrollbar {
        width: 5px;
        height: 5px;
    }

    /* Custom scrollbar track */
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    /* Custom scrollbar thumb */
    ::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 10px;
    }

    /* Custom scrollbar thumb hover effect */
    ::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800 h-full overflow-hidden">

<div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <?php include '../auth/sidebar.php'; ?>

    <!-- WORKSPACE WRAPPER -->
    <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full">
        
        <!-- Header Navigation Component Include -->
        <?php 
            $page_title = "Orders Management";
            include '../auth/nav.php'; 
        ?>

        <!-- MAIN CANVAS -->
        <main class="p-3 sm:p-5 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-cart-shopping text-indigo-600"></i> Customer Orders
                    </h1>
                    <p class="text-xs text-slate-700 mt-1"><?= $totalOrders; ?> total orders received</p>
                </div>
            </div>

            <!-- Flash messages -->
            <?php if (!empty($message)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Table Card Container -->
            <div class="w-full bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="overflow-x-auto w-full no-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-900 text-[11px] font-bold uppercase tracking-wider border-b border-slate-300">
                                <th class="px-5 py-4 w-16 text-center">ID</th>
                                <th class="px-5 py-4">Order Number</th>
                                <th class="px-5 py-4">Customer Name</th>
                                <th class="px-5 py-4">Total Amount</th>
                                <th class="px-5 py-4">Payment</th>
                                <th class="px-5 py-4">Date</th>
                                <th class="px-5 py-4 text-center">Status</th>
                                <th class="px-5 py-4 text-center w-80">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php 
                                        $payStatus = strtolower($row['payment_status'] ?? 'pending'); 
                                        $isPayCompleted = ($payStatus === 'completed');
                                        // Display subtotal calculated directly from Order_item query
                                        $displayTotal = floatval($row['calculated_total'] ?? $row['total_amount'] ?? 0);
                                    ?>
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-5 py-4 text-center text-slate-900 font-bold"><?= $row['id']; ?></td>
                                        
                                        <td class="px-5 py-4 text-indigo-600 font-bold font-mono">
                                            <?= htmlspecialchars($row['order_number'] ?? 'N/A'); ?>
                                        </td>
                                        
                                        <td class="px-5 py-4 text-slate-900 font-semibold">
                                            <?= htmlspecialchars($row['customer_name'] ?? 'Unknown User'); ?>
                                        </td>
                                        
                                        <td class="px-5 py-4 font-bold text-slate-900">
                                            <?= number_format($displayTotal); ?> ကျပ်
                                        </td>

                                        <td class="px-5 py-4">
                                            <?php if ($isPayCompleted): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-100 text-rose-800 hover:bg-rose-200 transition">
                                                    <i class="fa-solid fa-clock text-[10px]"></i> Unpaid
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td class="px-5 py-4 text-slate-600 font-normal text-[11px]">
                                            <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                        </td>
                                        
                                        <td class="px-5 py-4 text-center">
                                            <?php 
                                            $status = $row['status'];
                                            $statusLower = strtolower($status);
                                            $badgeColor = "bg-slate-100 text-slate-600 border-slate-200"; 
                                            if ($statusLower === 'pending') $badgeColor = "bg-amber-50 text-amber-700 border-amber-200";
                                            elseif ($statusLower === 'completed') $badgeColor = "bg-emerald-50 text-emerald-700 border-emerald-200";
                                            elseif ($statusLower === 'cancelled') $badgeColor = "bg-rose-50 text-rose-700 border-rose-200";
                                            ?>
                                            <span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border <?= $badgeColor; ?>">
                                                <?= ucfirst(htmlspecialchars($status ?: 'pending')); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- ALIGNED ACTION COLUMN -->
                                        <td class="px-5 py-4 text-center">
                                            <form method="POST" action="" class="flex items-center justify-center gap-2 w-full">
                                                <input type="hidden" name="order_id" value="<?= $row['id']; ?>">
                                                
                                                <select name="status" class="h-9 w-32 bg-slate-50 text-xs rounded-lg px-2.5 border border-slate-300 outline-none focus:border-indigo-500 font-medium text-slate-700 transition cursor-pointer shrink-0">
                                                    <option value="pending" <?= $statusLower === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="completed" <?= $statusLower === 'completed' ? 'selected' : ''; ?> <?= !$isPayCompleted ? 'disabled class="bg-gray-100 text-gray-400"' : ''; ?>>
                                                        Completed <?= !$isPayCompleted ? '(Pay First)' : ''; ?>
                                                    </option>
                                                    <option value="cancelled" <?= $statusLower === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                
                                                <button type="submit" name="update_status" class="h-9 inline-flex items-center justify-center px-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold transition shadow-sm text-xs cursor-pointer gap-1.5 shrink-0">
                                                    <i class="fa-solid fa-arrows-rotate text-[11px]"></i>
                                                    <span>Update</span>
                                                </button>
                                                
                                                <a href="orderdetail.php?id=<?= $row['id']; ?>" class="h-9 inline-flex items-center justify-center px-3 bg-amber-500 hover:bg-amber-600 text-white rounded-lg font-bold transition text-xs gap-1.5 shrink-0 shadow-xs">
                                                    <i class="fa-solid fa-eye text-[11px]"></i>
                                                    <span>Detail</span>
                                                </a>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-slate-400 font-semibold">
                                        No orders found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINATION CONTROLS CONTAINER WITH DYNAMIC LIMIT DROP-DOWN -->
                <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-center justify-between gap-4">
                    
                    <!-- SHOWING ENTRIES TEXT & DYNAMIC SELECT LIMIT DROPDOWN -->
                    <div class="flex items-center gap-2 text-xs text-slate-600 font-medium">
                        <span class="whitespace-nowrap">Showing</span>
                        <form method="GET" action="orders.php" class="inline-block">
                            <input type="hidden" name="page" value="1">
                            <select name="limit" onchange="this.form.submit()" class="bg-white border border-slate-300 text-slate-800 font-bold rounded-lg px-2 py-1 outline-none focus:border-indigo-500 cursor-pointer shadow-2xs">
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

                    <!-- ADVANCED SMART PAGINATION LINKS -->
                    <?php if ($total_pages > 1): ?>
                        <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-1.5 text-xs">
                            
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="orders.php?page=<?= $page - 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1 shadow-2xs">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-2.5 sm:px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg font-bold text-slate-400 cursor-not-allowed flex items-center gap-1">
                                    <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Always display Page 1 -->
                            <?php if ($page == 1): ?>
                                <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs">1</span>
                            <?php else: ?>
                                <a href="orders.php?page=1&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">1</a>
                            <?php endif; ?>

                            <!-- Front Ellipsis -->
                            <?php if ($page > 3): ?>
                                <span class="px-1.5 py-1 text-slate-400 font-bold select-none">...</span>
                            <?php endif; ?>

                            <!-- Middle Page Numbers -->
                            <?php 
                            $start = max(2, $page - 1);
                            $end = min($total_pages - 1, $page + 1);

                            for ($i = $start; $i <= $end; $i++): 
                                if ($i == 1 || $i == $total_pages) continue;
                            ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs"><?= $i; ?></span>
                                <?php else: ?>
                                    <a href="orders.php?page=<?= $i; ?>&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?= $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Back Ellipsis -->
                            <?php if ($page < $total_pages - 2): ?>
                                <span class="px-1.5 py-1 text-slate-400 font-bold select-none">...</span>
                            <?php endif; ?>

                            <!-- Always display Last Page -->
                            <?php if ($total_pages > 1): ?>
                                <?php if ($page == $total_pages): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs"><?= $total_pages; ?></span>
                                <?php else: ?>
                                    <a href="orders.php?page=<?= $total_pages; ?>&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?= $total_pages; ?></a>
                                <?php endif; ?>
                            <?php endif; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="orders.php?page=<?= $page + 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1 shadow-2xs">
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
        sidebar.classList.toggle('-translate-x-full');
    }

    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        notiDropdown.classList.toggle('hidden');
        profileDropdown.classList.add('hidden'); 
    }

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        profileDropdown.classList.toggle('hidden');
        notiDropdown.classList.add('hidden'); 
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