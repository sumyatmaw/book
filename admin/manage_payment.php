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
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';

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

$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 4");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;
$pending_payments_count = $pending;
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
        
        <!-- Premium Red Sign Out Button in Sidebar -->
        <div class="p-4 border-t border-slate-800 bg-slate-950/30">
            <a href="../auth/logout.php" class="flex items-center justify-center space-x-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-rose-600/20 group">
                <i class="fa-solid fa-right-from-bracket group-hover:transform group-hover:translate-x-0.5 transition"></i><span>Sign Out</span>
            </a>
        </div>
    </aside>

    <!-- Overlay background for Mobile Sidebar -->
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

                    <!-- Notifications Dropdown -->
                    <div id="notiDropdown" class="hidden absolute right-0 top-12 w-80 bg-white border border-slate-200 shadow-xl rounded-2xl overflow-hidden z-50">
                        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-xs text-slate-700">System Alerts</div>
                        <div class="p-4 text-center text-xs text-slate-400 font-semibold">
                            <?php echo ($low_stock_count + $pending_payments_count > 0) ? "You have system alerts pending." : "No new notifications."; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Admin Profile Menu -->
                <div class="relative border-l border-slate-200 pl-4">
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-user text-sm"></i>
                    </button>

                    <!-- Admin Profile Dropdown Menu -->
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
                    <h3 class="text-lg sm:text-xl font-black text-gray-900">MMK <?= number_format($totalAmount); ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total Collected</p>
                </div>
            </div>

            <!-- Desktop table -->
            <div class="hidden lg:block bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-indigo-500"></i> All Transactions
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/70 text-gray-400 uppercase text-[11px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Order</th>
                                <th class="px-5 py-3 text-left font-semibold">Customer</th>
                                <th class="px-5 py-3 text-left font-semibold">Method</th>
                                <th class="px-5 py-3 text-left font-semibold">Amount</th>
                                <th class="px-5 py-3 text-left font-semibold">Ref</th>
                                <th class="px-5 py-3 text-left font-semibold">Slip</th>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()):
                                    $st = $row['status'];
                                    $st_class = match(strtolower($st)) {
                                        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
                                        'rejected' => 'bg-red-50 text-red-700 border-red-200/60',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200/40'
                                    };
                                ?>
                                <tr class="hover:bg-slate-50/40 transition">
                                    <td class="px-5 py-3">
                                        <a href="orderdetail.php?id=<?= $row['order_id']; ?>" class="font-bold text-indigo-600 hover:text-indigo-700">
                                            #<?= htmlspecialchars($row['order_number'] ?? $row['order_id']); ?>
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-slate-900"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></td>
                                    <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars($row['method_name'] ?? 'N/A'); ?></td>
                                    <td class="px-5 py-3 font-black text-slate-900">MMK <?= number_format($row['amount']); ?></td>
                                    <td class="px-5 py-3 font-mono text-[11px] text-gray-500 max-w-[120px] truncate"><?= htmlspecialchars($row['transaction_ref'] ?? '-'); ?></td>
                                    <td class="px-5 py-3">
                                        <?php if (!empty($row['payment_slip'])): ?>
                                            <a href="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="text-indigo-500 hover:text-indigo-600 font-semibold inline-flex items-center gap-1">
                                                <i class="fa-solid fa-image"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-300">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 whitespace-nowrap"><?= date('M d, Y H:i', strtotime($row['payment_date'])); ?></td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-0.5 text-[11px] font-bold rounded-lg border <?= $st_class; ?>"><?= ucfirst($st); ?></span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <?php if (strtolower($st) === 'pending'): ?>
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="approve_payment"
                                                            class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-sm shadow-indigo-600/10 cursor-pointer flex items-center gap-1">
                                                        <i class="fa-solid fa-check text-[10px]"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                                    <button type="submit" name="reject_payment"
                                                            class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-sm shadow-rose-600/10 cursor-pointer flex items-center gap-1">
                                                        <i class="fa-solid fa-times text-[10px]"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="py-12 text-center text-gray-400 font-semibold">No payment records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile card list -->
            <div class="lg:hidden space-y-3">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()):
                        $st = $row['status'];
                        $st_class = match(strtolower($st)) {
                            'paid' => 'bg-emerald-50 text-emerald-700 border border-emerald-200/40',
                            'rejected' => 'bg-rose-50 text-rose-700 border border-rose-200/40',
                            default => 'bg-amber-50 text-amber-700 border border-amber-200/40'
                        };
                    ?>
                    <div class="bg-white p-4 rounded-xl border border-slate-200/60 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <a href="orderdetail.php?id=<?= $row['order_id']; ?>" class="font-bold text-indigo-600 text-sm">
                                    #<?= htmlspecialchars($row['order_number'] ?? $row['order_id']); ?>
                                </a>
                                p class="text-xs text-slate-900 font-semibold mt-0.5"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></p>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-lg <?= $st_class; ?> shrink-0"><?= ucfirst($st); ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs mb-3 font-medium">
                            <div>
                                <span class="text-gray-400">Amount</span>
                                <p class="font-black text-slate-900">MMK <?= number_format($row['amount']); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Method</span>
                                <p class="text-slate-700 font-semibold"><?= htmlspecialchars($row['method_name'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Ref</span>
                                <p class="font-mono text-gray-600 truncate"><?= htmlspecialchars($row['transaction_ref'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Date</span>
                                <p class="text-gray-600"><?= date('M d, H:i', strtotime($row['payment_date'])); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <?php if (!empty($row['payment_slip'])): ?>
                                <a href="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="text-indigo-500 hover:text-indigo-600 font-semibold text-xs inline-flex items-center gap-1">
                                    <i class="fa-solid fa-image"></i> View Slip
                                </a>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">No slip</span>
                            <?php endif; ?>
                            <?php if (strtolower($st) === 'pending'): ?>
                                <div class="flex items-center gap-1.5">
                                    <form method="POST" action="">
                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                        <button type="submit" name="approve_payment"
                                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition shadow-sm cursor-pointer flex items-center gap-1">
                                            <i class="fa-solid fa-check text-[9px]"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="">
                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                        <button type="submit" name="reject_payment"
                                                class="bg-rose-600 hover:bg-rose-700 text-white px-2.5 py-1.5 rounded-xl text-[11px] font-bold transition shadow-sm cursor-pointer flex items-center gap-1">
                                            <i class="fa-solid fa-times text-[9px]"></i> Reject
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-12 rounded-xl border border-slate-200/60 shadow-sm text-center">
                        <i class="fa-solid fa-receipt text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400 font-semibold text-sm">No payment records found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<script>
    // Sidebar & Dropdown Navigation UI Controller
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        if(overlay) overlay.classList.toggle('hidden');
    }
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
    window.addEventListener('click', function(e) {
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        if (notiDropdown && !notiDropdown.contains(e.target) && !document.getElementById('notiBtn').contains(e.target)) {
            notiDropdown.classList.add('hidden');
        }
        if (profileDropdown && !profileDropdown.contains(e.target) && !document.getElementById('profileBtn').contains(e.target)) {
            profileDropdown.classList.add('hidden');
        }
    });
</script>
</body>
</html>