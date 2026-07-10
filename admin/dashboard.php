<?php
// Session and Database Connection Validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php'; 

// Restrict access to Admins only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch Admin Details from Session
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// --- 1. Dynamic Analytics Queries ---

// Total Revenue
$rev_query = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM Payment WHERE status IN ('pending', 'paid')");
$total_revenue = mysqli_fetch_assoc($rev_query)['total'];

// Total Quantity of All Books in Stock (Database Total Stock Sum)
$books_query = mysqli_query($conn, "SELECT COALESCE(SUM(stock), 0) as total FROM Books");
$total_books = mysqli_fetch_assoc($books_query)['total'];

// Total Categories
$cat_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Categories");
$total_categories = mysqli_fetch_assoc($cat_query)['total'];

// --- Split Customers into Two Operational Groups ---
// 1. Customers who have actually purchased (at least one order in Orders table)
$purchased_query = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as total FROM Orders");
$purchased_customers = mysqli_fetch_assoc($purchased_query)['total'];

// 2. Registered viewers (role 'customer' but no orders yet in Orders table)
$viewers_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Users WHERE role = 'customer' AND id NOT IN (SELECT DISTINCT user_id FROM Orders)");
$registered_viewers = mysqli_fetch_assoc($viewers_query)['total'];

// Total Registered Customers (Purchased Customers + Registered Viewers)
$total_customers = $purchased_customers + $registered_viewers;

// Total Orders Placed
$order_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Orders");
$total_orders = mysqli_fetch_assoc($order_query)['total'];

// Total Awaiting Deliveries
$del_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Delivery WHERE delivery_status = 'pending'");
$pending_deliveries = mysqli_fetch_assoc($del_query)['total'];

// Fetch Books with Low Stock Levels (Less than 3 copies available)
$low_stock_query = mysqli_query($conn, "SELECT id, title, stock FROM Books WHERE stock < 3 ORDER BY stock ASC");
$low_stock_count = mysqli_num_rows($low_stock_query);

// Fetch Pending Payments (New Bank Transfers) for the Notification Dropdown
$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = mysqli_num_rows($pending_payments_query);

// Recent Transactions Queue (Latest 5 orders)
$recent_orders_query = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name 
    FROM Orders o 
    JOIN Users u ON o.user_id = u.id 
    ORDER BY o.id DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BookShop Admin</title>
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
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-sm shadow-indigo-600/10">
                    <i class="fa-solid fa-chart-pie w-5 text-indigo-200"></i><span>Dashboard</span>
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
                <a href="manage_payment.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-credit-card w-5"></i><span>Payments</span>
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

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- TOP NAVIGATION BAR -->
        <header class="h-16 bg-white border-b border-slate-200/80 flex items-center justify-between px-4 md:px-8 z-40 shrink-0">
            <div class="flex items-center space-x-3">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-50 md:hidden transition cursor-pointer">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <!-- Added "Dashboard" header in navigation bar -->
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Dashboard</h1>
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
        <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 md:space-y-8 max-w-[1600px] w-full mx-auto">
            
            <?php if ($low_stock_count > 0): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-2xl p-4 md:p-5 shadow-sm space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                            <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-amber-900">Critical Stock Alert</h4>
                            <p class="text-xs text-amber-700/80">The following catalog inventory items have dipped below 3 operational units</p>
                        </div>
                    </div>
                    <span class="w-fit px-2.5 py-1 text-xs font-extrabold bg-amber-100 text-amber-800 rounded-lg border border-amber-300/40">
                        <?php echo $low_stock_count; ?> Items Low
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2 border-t border-amber-200/50">
                    <?php while($low_book = mysqli_fetch_assoc($low_stock_query)): ?>
                    <div class="bg-white/60 hover:bg-white border border-amber-200/40 rounded-xl p-3 flex justify-between items-center text-xs transition">
                        <span class="font-semibold text-slate-700 truncate max-w-[160px]"><?php echo htmlspecialchars($low_book['title']); ?></span>
                        <span class="font-bold text-rose-600 px-2 py-0.5 bg-rose-50 border border-rose-100 rounded-md shrink-0">
                            Only <?php echo $low_book['stock']; ?> left
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- STATS CARDS GRID -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                <!-- 1. Total Revenue -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex items-center justify-between shadow-sm hover:border-slate-300 transition group">
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">စုစုပေါင်း ဝင်ငွေ</p>
                        <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_revenue); ?> ကျပ်</h3>
                    </div>
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                </div>

                <!-- 2. Total Orders -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex items-center justify-between shadow-sm hover:border-slate-300 transition group">
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">စုစုပေါင်း အော်ဒါအရေအတွက်</p>
                        <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo $total_orders; ?> ခု</h3>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 group-hover:bg-blue-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>

                <!-- 3. Pending Deliveries -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex items-center justify-between shadow-sm hover:border-slate-300 transition group">
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">ပို့ဆောင်ရန် ကျန်ရှိသည့် ပါဆယ်</p>
                        <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo $pending_deliveries; ?> ခု</h3>
                    </div>
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 group-hover:bg-amber-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                </div>

                <!-- 4. Total Books -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex items-center justify-between shadow-sm hover:border-slate-300 transition group">
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">စုစုပေါင်း စာအုပ်</p>
                        <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_books); ?> အုပ်</h3>
                    </div>
                    <div class="w-12 h-12 bg-indigo-50 text-indigo-600 group-hover:bg-indigo-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                        <i class="fa-solid fa-book"></i>
                    </div>
                </div>

                <!-- 5. Categories -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex items-center justify-between shadow-sm hover:border-slate-300 transition group">
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">စာအုပ်အမျိုးအစားများ</p>
                        <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo $total_categories; ?> မျိုး</h3>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 group-hover:bg-purple-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                </div>

                <!-- 6. Customers Card (Updated with requested Myanmar translation and responsive layout) -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 flex flex-col justify-between shadow-sm hover:border-slate-300 transition group space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">အကောင့်ဖွင့်သူစုစုပေါင်း</p>
                            <h3 class="text-xl md:text-2xl font-extrabold text-slate-950 tracking-tight"><?php echo $total_customers; ?> ယောက်</h3>
                        </div>
                        <div class="w-12 h-12 bg-rose-50 text-rose-600 group-hover:bg-rose-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300">
                            <i class="fa-solid fa-users"></i>
                        </div>
                    </div>
                    
                    <!-- Sub Breakdown Layout for Customers -->
                    <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-100 text-xs">
                        <div class="bg-emerald-50/60 p-2 rounded-xl border border-emerald-100">
                            <p class="text-slate-500 font-medium">စာအုပ်ဝယ်ယူသူ</p>
                            <p class="text-base font-bold text-emerald-700 mt-0.5"><?php echo $purchased_customers; ?> ယောက်</p>
                        </div>
                        <div class="bg-slate-50 p-2 rounded-xl border border-slate-200/60">
                            <p class="text-slate-500 font-medium">အကောင့်ဖွင့်ကြည့်သူ</p>
                            <p class="text-base font-bold text-slate-700 mt-0.5"><?php echo $registered_viewers; ?> ယောက်</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RECENT ORDERS TABLE -->
            <div class="bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="px-6 md:px-8 py-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-slate-50/50">
                    <div class="space-y-0.5">
                        <h3 class="font-bold text-slate-900 flex items-center text-base">
                            <i class="fa-solid fa-clock-rotate-left mr-2.5 text-indigo-500 text-sm"></i>Recent Orders
                        </h3>
                        <p class="text-xs text-slate-400 font-medium">Latest 5 incoming store purchases</p>
                    </div>
                    <a href="orders.php" class="w-full sm:w-auto text-center inline-flex items-center justify-center text-xs font-bold text-indigo-600 hover:text-indigo-800 transition bg-indigo-50 hover:bg-indigo-100/80 px-3 py-2 rounded-xl">
                        View All Orders <i class="fa-solid fa-arrow-right ml-1.5 text-[10px]"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto w-full no-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-slate-50/70 text-slate-500 text-xs font-bold uppercase tracking-wider border-b border-slate-100">
                                <th class="px-6 md:px-8 py-4">Order Number</th>
                                <th class="px-6 md:px-8 py-4">Customer</th>
                                <th class="px-6 md:px-8 py-4">Total Amount</th>
                                <th class="px-6 md:px-8 py-4">Status</th>
                                <th class="px-6 md:px-8 py-4">Date</th>
                                <th class="px-6 md:px-8 py-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-700 font-medium">
                            <?php 
                            if (mysqli_num_rows($recent_orders_query) > 0) {
                                while ($order = mysqli_fetch_assoc($recent_orders_query)) {
                                    $status = strtolower($order['status']);
                                    $status_class = "bg-amber-50 text-amber-700 border-amber-200/60"; 
                                    
                                    if ($status === 'completed') {
                                        $status_class = "bg-emerald-50 text-emerald-700 border-emerald-200/60";
                                    } elseif ($status === 'cancelled') {
                                        $status_class = "bg-rose-50 text-rose-700 border-rose-200/60";
                                    }
                            ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-6 md:px-8 py-4 font-mono font-bold text-slate-900 text-xs">
                                    #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 text-slate-900 font-semibold">
                                    <?php echo htmlspecialchars($order['customer_name']); ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 font-bold text-slate-900">
                                    <?php echo number_format($order['total_amount']); ?> ကျပ်
                                </td>
                                <td class="px-6 md:px-8 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border <?php echo $status_class; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 md:px-8 py-4 text-slate-400 font-normal text-xs">
                                    <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 md:px-8 py-4 text-center">
                                    <a href="orderdetail.php?id=<?php echo $order['id']; ?>" class="inline-flex items-center justify-center px-3 py-2 bg-slate-100 text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 rounded-xl text-xs font-bold transition">
                                        <i class="fa-solid fa-eye mr-1.5"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo '<tr><td colspan="6" class="px-8 py-12 text-center text-slate-400 font-semibold">No recent orders found.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Sidebar Toggle Logic
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Notification Dropdown Toggle Logic
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        const notiDropdown = document.getElementById('notiDropdown');
        const profileDropdown = document.getElementById('profileDropdown');
        
        notiDropdown.classList.toggle('hidden');
        profileDropdown.classList.add('hidden'); 
    }

    // Profile Dropdown Toggle Logic
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        const profileDropdown = document.getElementById('profileDropdown');
        const notiDropdown = document.getElementById('notiDropdown');
        
        profileDropdown.classList.toggle('hidden');
        notiDropdown.classList.add('hidden'); 
    }

    // Global click listener to close dropdowns when clicking outside
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