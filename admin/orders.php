<?php
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
// PAGINATION SETUP FOR ORDERS
// -------------------------------------------------------------------------
$limit = 10; // Number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total orders count
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Orders");
$totalOrders = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($totalOrders / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch paginated orders list from database
$sql = "SELECT Orders.*, Users.name as customer_name, Payment.status as payment_status 
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">

    <!-- SIDEBAR -->
    <?php include '../auth/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- HEADER -->
        <header class="h-16 bg-yellow-300 border-b border-slate-200/80 flex items-center justify-between px-4 md:px-8 z-40 shrink-0">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Orders Management</h1>
            </div>

            <div class="flex items-center space-x-4 relative">
                <!-- Notifications Bell -->
                <div class="relative">
                    <button onclick="toggleNotificationDropdown(event)" id="notiBtn" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition cursor-pointer">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($low_stock_count > 0 || $pending_payments_count > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <div id="notiDropdown" class="hidden absolute right-0 top-12 w-80 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-xs text-slate-700">Notifications</div>
                        <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto no-scrollbar">
                            <?php if ($pending_payments_count > 0): ?>
                                <?php while($payment = mysqli_fetch_assoc($pending_payments_query)): ?>
                                <a href="manage_payment.php" class="block p-3 hover:bg-slate-50 transition">
                                    <p class="text-xs font-bold text-indigo-600 flex items-center"><i class="fa-solid fa-wallet mr-1.5"></i> New Bank Transfer Pending</p>
                                    <p class="text-xxs text-slate-500 mt-0.5">Amount: <?php echo number_format($payment['amount']); ?> MMK awaiting approval.</p>
                                </a>
                                <?php endwhile; ?>
                            <?php endif; ?>

                            <?php if ($low_stock_count > 0): ?>
                                <div class="block p-3 bg-amber-50/40">
                                    <p class="text-xs font-bold text-amber-600 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Critical Stock Warning</p>
                                    <p class="text-xxs text-slate-500 mt-0.5">You have <?php echo $low_stock_count; ?> books currently running low on stock.</p>
                                </div>
                            <?php endif; ?>

                            <?php if ($low_stock_count == 0 && $pending_payments_count == 0): ?>
                                <div class="p-4 text-center text-xs text-slate-400 font-medium">No new operational notifications.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Profile -->
                <div class="relative border-l border-slate-200 pl-4">
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 text-slate-600 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer overflow-hidden">
                        <?php if (!empty($profile_path) && file_exists($profile_path)): ?>
                            <img src="<?= htmlspecialchars($profile_path); ?>" alt="Admin" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-sm"></i>
                        <?php endif; ?>
                    </button>

                    <div id="profileDropdown" class="hidden absolute right-0 top-12 w-48 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-2.5 border-b border-slate-100 bg-slate-50/60">
                            <p class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($admin_name); ?></p>
                            <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($admin_email); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="dashboard.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-chart-pie w-4 text-slate-400"></i><span>Dashboard</span>
                            </a>
                            <a href="adminprofile.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 hover:text-indigo-600 transition">
                                <i class="fa-solid fa-id-card w-4 text-slate-400"></i><span>My Profile</span>
                            </a>
                            <a href="../auth/logout.php" class="flex items-center space-x-2 px-4 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 transition">
                                <i class="fa-solid fa-right-from-bracket w-4 text-rose-500"></i><span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN CANVAS -->
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <div class="flex items-center justify-between gap-3 mb-6">
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
                            <tr class="bg-white text-slate-900 text-[11px] font-bold uppercase tracking-wider border-b border-slate-300">
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
                                            <?= number_format($row['total_amount']); ?> ကျပ်
                                        </td>

                                        <td class="px-5 py-4">
                                            <?php if ($isPayCompleted): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-emerald-800">
                                                    <i class="fa-solid fa-circle-check text-[10px]"></i> Paid
                                                </span>
                                            <?php else: ?>
                                                <a href="manage_payment.php" title="Click to verify payment" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-bold bg-rose-100 text-rose-800 hover:bg-rose-200 transition">
                                                    <i class="fa-solid fa-clock text-[10px]"></i> Unpaid
                                                </a>
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
                                                
                                                <a href="orderdetail.php?id=<?= $row['id']; ?>" class="h-9 inline-flex items-center justify-center px-3 bg-yellow-500 hover:bg-yellow-700 text-slate-700 rounded-lg font-bold transition text-xs gap-1.5 shrink-0">
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

                <!-- PAGINATION CONTROLS CONTAINER -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                            Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalOrders); ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalOrders); ?></span> of <span class="font-bold text-slate-700"><?= $totalOrders; ?></span> entries
                        </p>
                        <div class="flex items-center space-x-1">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="orders.php?page=<?= $page - 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
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
                                    <a href="orders.php?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                        <?= $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="orders.php?page=<?= $page + 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
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