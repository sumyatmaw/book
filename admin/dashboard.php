<?php
session_start();
include '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Metrics
$total_books = $conn->query("SELECT COUNT(*) as t FROM Books")->fetch_assoc()['t'] ?? 0;
$total_categories = $conn->query("SELECT COUNT(*) as t FROM Categories")->fetch_assoc()['t'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(*) as t FROM Orders")->fetch_assoc()['t'] ?? 0;
$total_payments = $conn->query("SELECT SUM(amount) as t FROM Payment WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
$pending_orders = $conn->query("SELECT COUNT(*) as t FROM Orders WHERE status='Pending'")->fetch_assoc()['t'] ?? 0;
$pending_payments = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='pending'")->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Sidebar toggle for mobile */
        #adminSidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @media (max-width: 1023px) {
            #adminSidebar {
                transform: translateX(-100%);
                position: fixed;
                top: 0; left: 0; bottom: 0;
                z-index: 40;
            }
            #adminSidebar.open {
                transform: translateX(0);
            }
            #sidebarOverlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.4);
                z-index: 35;
            }
            #sidebarOverlay.open { display: block; }
        }
        /* Active sidebar link */
        .sidebar-link.active {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }
        .sidebar-link.active i { color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

    <!-- Sidebar overlay (mobile) -->
    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex flex-1">

        <!-- Sidebar -->
        <aside id="adminSidebar" class="w-64 bg-[#0a1128] text-gray-300 flex flex-col justify-between border-r border-slate-800 shrink-0 lg:relative lg:translate-x-0">
            <div class="p-4 space-y-2">
                <!-- Sidebar header -->
                <div class="px-4 py-3 mb-2">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Admin Panel</h2>
                </div>

                <nav class="space-y-0.5">
                    <a href="dashboard.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200">
                        <i class="fa-solid fa-chart-pie text-sm w-5 text-center"></i> Dashboard
                    </a>
                    <a href="categories.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-tags text-sm w-5 text-center text-slate-500"></i> Categories
                    </a>
                    <a href="books.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-book text-sm w-5 text-center text-slate-500"></i> Books
                    </a>
                    <a href="orders.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-shopping-bag text-sm w-5 text-center text-slate-500"></i> Orders
                    </a>
                    <a href="payments.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-credit-card text-sm w-5 text-center text-slate-500"></i> Payments
                    </a>
                    <a href="customer.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-users text-sm w-5 text-center text-slate-500"></i> Customers
                    </a>

                    <hr class="border-slate-800 my-2">

                    <a href="adminprofile.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-user-gear text-sm w-5 text-center text-slate-500"></i> My Profile
                    </a>
                    <a href="../index.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-store text-sm w-5 text-center text-slate-500"></i> View Store
                    </a>
                </nav>
            </div>

            <!-- Sidebar footer -->
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <span class="w-8 h-8 bg-amber-500 rounded-lg flex items-center justify-center text-xs font-bold text-slate-900">A</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-white truncate">Admin</p>
                        <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 min-w-0">

            <!-- Mobile sidebar toggle -->
            <div class="lg:hidden flex items-center gap-3 mb-2">
                <button onclick="toggleSidebar()" class="w-10 h-10 bg-white rounded-xl border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition shadow-sm">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 class="text-lg font-black text-slate-900">Dashboard</h1>
            </div>

            <!-- Welcome banner -->
            <div class="bg-gradient-to-r from-[#0a1128] to-slate-800 rounded-2xl p-6 sm:p-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-amber-500/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="absolute bottom-0 left-1/2 w-24 h-24 bg-amber-500/5 rounded-full translate-y-1/2"></div>
                <div class="relative">
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight">Dashboard Overview</h1>
                    <p class="text-sm text-gray-400 mt-1">Real-time store metrics and transactional records.</p>
                    <div class="flex items-center gap-4 mt-4 text-xs">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span> System Online</span>
                        <span class="text-gray-500"><?= date('l, F j, Y'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Metric cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <!-- Books -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-book text-sm"></i>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $total_books; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Books in store</p>
                </div>

                <!-- Categories -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-tags text-sm"></i>
                        </div>
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total</span>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $total_categories; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Categories</p>
                </div>

                <!-- Orders -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-shopping-bag text-sm"></i>
                        </div>
                        <?php if ($pending_orders > 0): ?>
                            <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $pending_orders; ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $total_orders; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total orders</p>
                </div>

                <!-- Revenue -->
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-wallet text-sm"></i>
                        </div>
                        <?php if ($pending_payments > 0): ?>
                            <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-1.5 py-0.5 rounded-full"><?= $pending_payments; ?> unverified</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-black text-gray-900">MMK <?= number_format($total_payments); ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total revenue</p>
                </div>
            </div>

            <!-- Tables grid -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 sm:gap-6">

                <!-- Recent Orders (spans 2 cols) -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-gray-100 shadow-sm xl:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-clock text-amber-500"></i> Recent Orders
                        </h3>
                        <a href="orders.php" class="text-[11px] font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg transition-colors">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-400 border-b text-[11px] uppercase tracking-wider">
                                    <th class="pb-3 font-semibold">Order</th>
                                    <th class="pb-3 font-semibold">Customer</th>
                                    <th class="pb-3 font-semibold hidden sm:table-cell">Amount</th>
                                    <th class="pb-3 font-semibold">Status</th>
                                    <th class="pb-3 font-semibold hidden md:table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600">
                                <?php
                                $orders_res = $conn->query("SELECT o.*, u.name as customer_name FROM Orders o JOIN Users u ON o.user_id = u.id ORDER BY o.id DESC LIMIT 5");
                                if ($orders_res && $orders_res->num_rows > 0) {
                                    while ($order = $orders_res->fetch_assoc()) {
                                        $status = strtolower($order['status']);
                                        $status_class = match($status) {
                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default => 'bg-amber-50 text-amber-700 border-amber-200'
                                        };
                                ?>
                                <tr class="border-b last:border-0 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 font-bold text-amber-600">#<?= htmlspecialchars($order['order_number'] ?? $order['id']); ?></td>
                                    <td class="py-3 font-medium text-gray-800 truncate max-w-[120px]"><?= htmlspecialchars($order['customer_name']); ?></td>
                                    <td class="py-3 font-bold text-gray-900 hidden sm:table-cell">MMK <?= number_format($order['total_amount']); ?></td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border <?= $status_class; ?>">
                                            <?= ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-gray-400 text-xs hidden md:table-cell"><?= date('M d, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php }
                                } else { ?>
                                <tr><td colspan="5" class="py-8 text-center text-gray-400 text-xs">No orders yet.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-triangle-exclamation text-rose-500"></i> Low Stock
                        </h3>
                        <a href="books.php" class="text-[11px] font-semibold text-rose-600 hover:text-rose-700 bg-rose-50 px-3 py-1.5 rounded-lg transition-colors">Manage</a>
                    </div>
                    <div class="overflow-y-auto max-h-[280px] space-y-2">
                        <?php
                        $low_stock = $conn->query("SELECT title, stock, book_image FROM Books WHERE stock <= 3 ORDER BY stock ASC");
                        if ($low_stock && $low_stock->num_rows > 0) {
                            while ($bk = $low_stock->fetch_assoc()) {
                        ?>
                        <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 transition-colors">
                            <img src="../uploads/<?= htmlspecialchars($bk['book_image'] ?? 'default.jpg'); ?>" class="w-9 h-12 object-cover rounded-lg border border-gray-100" alt="">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-gray-800 truncate"><?= htmlspecialchars($bk['title']); ?></p>
                                <p class="text-[10px] text-gray-400"><?= $bk['stock']; ?> left</p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $bk['stock'] == 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'; ?>">
                                <?= $bk['stock'] == 0 ? 'Out' : $bk['stock']; ?>
                            </span>
                        </div>
                        <?php }
                        } else { ?>
                        <div class="py-8 text-center text-gray-400 text-xs">All stock levels healthy.</div>
                        <?php } ?>
                    </div>
                </div>

                <!-- Recent Payments (full width) -->
                <div class="bg-white p-5 sm:p-6 rounded-2xl border border-gray-100 shadow-sm xl:col-span-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2 text-sm">
                            <i class="fa-solid fa-receipt text-amber-500"></i> Recent Payments
                        </h3>
                        <a href="payments.php" class="text-[11px] font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg transition-colors">View All</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="text-gray-400 border-b text-[11px] uppercase tracking-wider">
                                    <th class="pb-3 font-semibold">Ref</th>
                                    <th class="pb-3 font-semibold">Order</th>
                                    <th class="pb-3 font-semibold">Amount</th>
                                    <th class="pb-3 font-semibold hidden sm:table-cell">Slip</th>
                                    <th class="pb-3 font-semibold">Status</th>
                                    <th class="pb-3 font-semibold hidden md:table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-600">
                                <?php
                                $pay_res = $conn->query("SELECT * FROM Payment ORDER BY id DESC LIMIT 5");
                                if ($pay_res && $pay_res->num_rows > 0) {
                                    while ($pay = $pay_res->fetch_assoc()) {
                                        $ps = strtolower($pay['status']);
                                        $ps_class = match($ps) {
                                            'paid' => 'bg-emerald-100 text-emerald-800',
                                            'rejected' => 'bg-rose-100 text-rose-800',
                                            default => 'bg-amber-100 text-amber-800'
                                        };
                                ?>
                                <tr class="border-b last:border-0 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 font-mono text-xs text-gray-500"><?= htmlspecialchars($pay['transaction_ref']); ?></td>
                                    <td class="py-3 font-bold text-amber-600">#<?= $pay['order_id']; ?></td>
                                    <td class="py-3 font-black text-gray-900">MMK <?= number_format($pay['amount']); ?></td>
                                    <td class="py-3 hidden sm:table-cell">
                                        <?php if (!empty($pay['payment_slip'])): ?>
                                            <a href="../uploads/slips/<?= htmlspecialchars($pay['payment_slip']); ?>" target="_blank" class="text-amber-500 hover:text-amber-600 text-xs font-medium inline-flex items-center gap-1">
                                                <i class="fa-solid fa-image"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded <?= $ps_class; ?>"><?= ucfirst($pay['status']); ?></span>
                                    </td>
                                    <td class="py-3 text-gray-400 text-xs hidden md:table-cell"><?= date('M d, H:i', strtotime($pay['payment_date'])); ?></td>
                                </tr>
                                <?php }
                                } else { ?>
                                <tr><td colspan="6" class="py-8 text-center text-gray-400 text-xs">No payment records yet.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include '../auth/footer.php'; ?>

    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
    </script>
</body>
</html>
