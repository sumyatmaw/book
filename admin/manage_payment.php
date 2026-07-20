<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';

// Fetch current admin profile image from session or database (Default: placeholder)
$admin_image = $_SESSION['user_image'] ?? ''; 
if (empty($admin_image)) {
    // Optional fallback: Fetch from Users table if you store it there
    $admin_query = mysqli_query($conn, "SELECT image FROM Users WHERE id = $admin_id");
    if ($admin_query && mysqli_num_rows($admin_query) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_query);
        $admin_image = $admin_row['image'] ?? '';
    }
}
// Set standard folder path for profile images
$profile_path = !empty($admin_image) ? "../uploads/profile/" . $admin_image : "";

// Handle Approve/Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment'])) {
        $payment_id = intval($_POST['payment_id']);
        $stmt = $conn->prepare("UPDATE Payment SET status = 'paid' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            // Get payment and order info
            $pay_info_stmt = $conn->prepare("SELECT Payment.order_id, Orders.user_id FROM Payment JOIN Orders ON Payment.order_id = Orders.id WHERE Payment.id = ?");
            $pay_info_stmt->bind_param("i", $payment_id);
            $pay_info_stmt->execute();
            $pay_info = $pay_info_stmt->get_result()->fetch_assoc();
            $pay_info_stmt->close();

            if ($pay_info) {
                // Update order status to completed
                $order_upd = $conn->prepare("UPDATE Orders SET status = 'completed' WHERE id = ?");
                $order_upd->bind_param("i", $pay_info['order_id']);
                $order_upd->execute();
                $order_upd->close();

                // Auto-create Delivery record if it doesn't exist
                $del_check = $conn->prepare("SELECT id FROM Delivery WHERE payment_id = ? LIMIT 1");
                $del_check->bind_param("i", $payment_id);
                $del_check->execute();
                $del_exists = $del_check->get_result()->num_rows > 0;
                $del_check->close();

                if (!$del_exists) {
                    // Get customer info for delivery
                    $user_stmt = $conn->prepare("SELECT name, phone, address FROM Users WHERE id = ?");
                    $user_stmt->bind_param("i", $pay_info['user_id']);
                    $user_stmt->execute();
                    $user_data = $user_stmt->get_result()->fetch_assoc();
                    $user_stmt->close();

                    if ($user_data) {
                        $current_time = date('Y-m-d H:i:s');
                        $del_ins = $conn->prepare("INSERT INTO Delivery(payment_id, receiver_name, receiver_phone, address_details, city, delivery_status, delivery_cost, shipped_at) VALUES(?, ?, ?, ?, ?, 'pending', 0.00, ?)");
                        $city = '';
                        $del_ins->bind_param("isssss", $payment_id, $user_data['name'], $user_data['phone'], $user_data['address'], $city, $current_time);
                        $del_ins->execute();
                        $del_ins->close();
                    }
                }
            }
            $message = "Payment approved successfully! Order marked as completed.";
        } else {
            $error = "Failed to approve payment.";
        }
        $stmt->close();
        header("Location: manage_payment.php");
        exit();
    }

    if (isset($_POST['reject_payment'])) {
        $payment_id = intval($_POST['payment_id']);
        $stmt = $conn->prepare("UPDATE Payment SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            $message = "Payment rejected. Order remains pending.";
        } else {
            $error = "Failed to reject payment.";
        }
        $stmt->close();
        header("Location: manage_payment.php");
        exit();
    }
}

// Fetch all payments with payment method name
$sql = "SELECT Payment.*, Orders.order_number, Users.name as customer_name, payment_method.method_name
        FROM Payment
        LEFT JOIN Orders ON Payment.order_id = Orders.id
        LEFT JOIN Users ON Orders.user_id = Users.id
        LEFT JOIN payment_method ON Payment.payment_method_id = payment_method.id
        ORDER BY Payment.id DESC";
$result = $conn->query($sql);
$totalPayments = $result ? $result->num_rows : 0;

// Count by status and system counters
$pending = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='pending'")->fetch_assoc()['t'] ?? 0;
$paid = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
$rejected = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='rejected'")->fetch_assoc()['t'] ?? 0;
$totalAmount = $conn->query("SELECT SUM(amount) as t FROM Payment WHERE status='paid'")->fetch_assoc()['t'] ?? 0;

// Fetch Live Alert Badge & Dropdown Notifications (Synced with categories.php layout)
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
    <title>Manage Payments - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">
    
    <!-- SIDEBAR CONTAINER -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-slate-400 flex flex-col justify-between transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0 border-r border-slate-800 shrink-0">
        <div class="p-6 overflow-y-auto no-scrollbar flex-1">
            <div class="flex items-center justify-between mb-8 px-2">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-600/30">
                        <i class="fa-solid fa-book-open text-sm"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">BookShop</span>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-white cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <nav class="space-y-1.5">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-chart-pie w-5"></i><span>Dashboard</span>
                </a>
                <a href="books.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-book w-5"></i><span>Manage Books</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-tags w-5"></i><span>Categories</span>
                </a>
                <a href="orders.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-cart-shopping w-5"></i><span>Orders</span>
                </a>
                <a href="manage_payment.php" class="flex items-center space-x-3 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-sm shadow-indigo-600/10">
                    <i class="fa-solid fa-credit-card w-5 text-indigo-200"></i><span>Payments</span>
                </a>
                <a href="delivery.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-truck w-5"></i><span>Deliveries</span>
                </a>
                <a href="customers.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-users w-5"></i><span>Customers</span>
                </a>
            </nav>
        </div>
        
        <div class="p-4 border-t border-slate-800 bg-slate-950/30">
            <a href="../auth/logout.php" class="flex items-center justify-center space-x-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-rose-600/20 group">
                <i class="fa-solid fa-right-from-bracket group-hover:transform group-hover:translate-x-0.5 transition"></i><span>Sign Out</span>
            </a>
        </div>
    </aside>

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 hidden transition-opacity duration-300"></div>

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- TOP NAVIGATION BAR -->
        <header class="h-16 bg-white border-b border-slate-200/80 flex items-center justify-between px-4 md:px-8 z-40 shrink-0">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Payments Management</h1>
            </div>

            <div class="flex items-center space-x-4 relative">
                <!-- Notifications Bell Button -->
                <div class="relative">
                    <button onclick="toggleNotificationDropdown(event)" id="notiBtn" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-slate-50 rounded-xl transition cursor-pointer">
                        <i class="fa-solid fa-bell"></i>
                        <?php if ($low_stock_count > 0 || $pending_payments_count > 0): ?>
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>

                    <!-- Notifications Dropdown (Populates live operational details upon clicking) -->
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
                
                <!-- Admin Profile Menu Button (Updated with Profile Image View directly matching orders.php) -->
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
                            <p class="text-xs font-bold text-slate-800 truncate"><?php echo htmlspecialchars($admin_name); ?></p>
                            <p class="text-[10px] text-slate-400 truncate"><?php echo htmlspecialchars($admin_email); ?></p>
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
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto space-y-6">
            
            <!-- Page Header -->
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-credit-card text-indigo-600"></i> Payments
                </h1>
                <p class="text-xs text-gray-400 mt-1"><?= $totalPayments; ?> payment records</p>
            </div>

            <!-- Flash messages -->
            <?php if (!empty($message)): ?>
                <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-clock text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $pending; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Pending</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-check-circle text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $paid; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Paid</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-red-50 text-red-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-times-circle text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $rejected; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Rejected</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 shadow-sm col-span-2 lg:col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-wallet text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-gray-900"> <?= number_format($totalAmount); ?>ကျပ်</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total Collected</p>
                </div>
            </div>

            <!-- Desktop table -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase text-slate-500 tracking-wider">
                                <th class="py-4 px-6">Order ID</th>
                                <th class="py-4 px-6">Customer</th>
                                <th class="py-4 px-6">Method</th>
                                <th class="py-4 px-6">Ref ID</th>
                                <th class="py-4 px-6">Slip</th>
                                <th class="py-4 px-6">Status</th>
                                <th class="py-4 px-6 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="py-4 px-6 font-bold text-slate-900">#<?= htmlspecialchars($row['order_id']); ?></td>
                                        <td class="py-4 px-6 font-medium"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></td>
                                        <td class="py-4 px-6"><span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold uppercase"><?= htmlspecialchars($row['method_name'] ?? 'Online'); ?></span></td>
                                        <td class="py-4 px-6 font-mono text-xs tracking-wide text-slate-500"><?= htmlspecialchars($row['transaction_ref']); ?></td>
                                        <td class="py-4 px-6">
                                            <?php if (!empty($row['payment_slip'])): ?>
                                                <button type="button" onclick="openSlipModal('../assets/<?= htmlspecialchars($row['payment_slip']); ?>')" class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 transition bg-indigo-50 hover:bg-indigo-100 py-1.5 px-3 rounded-lg cursor-pointer">
                                                    <i class="fa-regular fa-image"></i> View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">No slip</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <?php 
                                            $status = strtolower($row['status']);
                                            if ($status === 'paid') echo '<span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold">Paid</span>';
                                            elseif ($status === 'rejected') echo '<span class="px-2.5 py-1 bg-rose-50 text-rose-700 rounded-full text-xs font-bold">Rejected</span>';
                                            else echo '<span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold">Pending</span>';
                                            ?>
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="flex items-center justify-center gap-2">
                                                <?php if ($status === 'pending'): ?>
                                                    <form action="" method="POST" onsubmit="return confirm('Approve this payment?');">
                                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="approve_payment" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm cursor-pointer">Approve</button>
                                                    </form>
                                                    <form action="" method="POST" onsubmit="return confirm('Reject this payment?');">
                                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                        <button type="submit" name="reject_payment" class="bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold py-1.5 px-3 rounded-lg transition shadow-sm cursor-pointer">Reject</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400 italic">Processed</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-sm text-slate-400 font-medium">No payment history discovered.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ================= IMAGE POPUP MODAL ================= -->
<div id="slipModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4 transition-opacity duration-300 opacity-0">
    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl border border-slate-100 transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh]">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-receipt text-indigo-600"></i> Customer Payment Slip
            </h3>
            <button onclick="closeSlipModal()" class="w-7 h-7 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 transition cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>
        <div class="p-4 bg-slate-100/50 overflow-y-auto flex items-center justify-center flex-1 min-h-[300px]">
            <img id="modalSlipImage" src="" alt="Payment Slip Screenshot" class="max-w-full max-h-[60vh] object-contain rounded-lg shadow-sm border border-slate-200">
        </div>
        <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 text-right">
            <button onclick="closeSlipModal()" class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs py-2 px-4 rounded-xl transition cursor-pointer">Close</button>
        </div>
    </div>
</div>

<script>
    // Sidebar Toggles
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    // Header Popups Configs 
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        document.getElementById('notiDropdown').classList.toggle('hidden');
        document.getElementById('profileDropdown').classList.add('hidden');
    }
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        document.getElementById('profileDropdown').classList.toggle('hidden');
        document.getElementById('notiDropdown').classList.add('hidden');
    }
    document.addEventListener('click', () => {
        document.getElementById('notiDropdown').classList.add('hidden');
        document.getElementById('profileDropdown').classList.add('hidden');
    });

    // Modal Operations Scripts
    function openSlipModal(imageSrc) {
        const modal = document.getElementById('slipModal');
        const img = document.getElementById('modalSlipImage');
        
        img.src = imageSrc;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('.transform').classList.remove('scale-95');
        }, 10);
    }

    function closeSlipModal() {
        const modal = document.getElementById('slipModal');
        
        modal.classList.add('opacity-0');
        modal.querySelector('.transform').classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
</script>
</body>
</html>