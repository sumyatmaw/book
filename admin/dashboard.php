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

// Sidebar Active Link 
$current_page = 'dashboard';

// Fetch Admin Details from Session
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Sync Profile Image from Database if not available in current session
if (!isset($_SESSION['user_image']) && isset($conn) && isset($_SESSION['user_id'])) {
    $uid = mysqli_real_escape_string($conn, $_SESSION['user_id']);
    $u_query = mysqli_query($conn, "SELECT profile_image FROM Users WHERE id = '$uid'");
    if ($u_query && $u_row = mysqli_fetch_assoc($u_query)) {
        $_SESSION['user_image'] = $u_row['profile_image'];
    }
}

// --- Dynamic Analytics Queries ---
$rev_query = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM Payment WHERE status IN ('pending', 'paid')");
$total_revenue = $rev_query ? mysqli_fetch_assoc($rev_query)['total'] : 0;

$books_query = mysqli_query($conn, "SELECT COALESCE(SUM(stock), 0) as total FROM Books");
$total_books = $books_query ? mysqli_fetch_assoc($books_query)['total'] : 0;

$cat_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Categories");
$total_categories = $cat_query ? mysqli_fetch_assoc($cat_query)['total'] : 0;

$purchased_query = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as total FROM Orders");
$purchased_customers = $purchased_query ? mysqli_fetch_assoc($purchased_query)['total'] : 0;

$viewers_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Users WHERE role = 'customer' AND id NOT IN (SELECT DISTINCT user_id FROM Orders WHERE user_id IS NOT NULL)");
$registered_viewers = $viewers_query ? mysqli_fetch_assoc($viewers_query)['total'] : 0;

$total_customers = $purchased_customers + $registered_viewers;

$order_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Orders");
$total_orders = $order_query ? mysqli_fetch_assoc($order_query)['total'] : 0;

$del_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Delivery WHERE delivery_status = 'pending'");
$pending_deliveries = $del_query ? mysqli_fetch_assoc($del_query)['total'] : 0;

$low_stock_query = mysqli_query($conn, "SELECT id, title, stock FROM Books WHERE stock < 3 ORDER BY stock ASC");
$low_stock_count = $low_stock_query ? mysqli_num_rows($low_stock_query) : 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = $pending_payments_query ? mysqli_num_rows($pending_payments_query) : 0;

$recent_orders_query = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name 
    FROM Orders o 
    LEFT JOIN Users u ON o.user_id = u.id 
    ORDER BY o.id DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard BookShop Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>

<body class="bg-gray-300 font-sans antialiased text-slate-800">

    <div class="flex h-screen overflow-hidden bg-white">

        <!-- Dynamic Sidebar Include -->
        <?php include '../auth/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden w-full bg-white">

            <!-- Dynamic Header Navigation Component Include -->
            <?php
            $page_title = "Dashboard Managrement";
            include '../auth/nav.php';
            ?>

            <!-- Main Content Area -->
            <main class="flex-1 overflow-y-auto p-4 md:p-8 space-y-6 md:space-y-8 max-w-[1600px] w-full mx-auto bg-gray-300">

                <!-- Low Stock Warning Banner -->
                <?php if ($low_stock_count > 0 && $low_stock_query): ?>
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
                            <?php while ($low_book = mysqli_fetch_assoc($low_stock_query)): ?>
                                <div class="bg-white  border border-amber-200/60 rounded-xl p-3 flex justify-between items-center text-xs transition">
                                    <span class="font-semibold text-slate-700 truncate max-w-[160px]"><?php echo htmlspecialchars($low_book['title']); ?></span>
                                    <span class="font-bold text-rose-600 px-2 py-0.5 bg-rose-50 border border-rose-100 rounded-md shrink-0">
                                        Only <?php echo $low_book['stock']; ?> left
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                    <!-- 1. Total Revenue -->
                    <div class="bg-amber-100 p-6 rounded-2xl border border-amber-200/60 flex items-center justify-between shadow-sm hover:shadow transition group">
                        <div class="space-y-2">
                            <p class="text-lg md:text-xl font-bold text-amber-800 uppercase tracking-wider">စုစုပေါင်း ဝင်ငွေ</p>
                            <h3 class="text-xs md:text-sm font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_revenue); ?> ကျပ်</h3>
                        </div>
                        <div class="w-12 h-12 bg-white text-amber-600 group-hover:bg-amber-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>

                    <!-- 2. Total Orders -->
                    <div class="bg-blue-100 p-6 rounded-2xl border border-blue-200/60 flex items-center justify-between shadow-sm hover:shadow transition group">
                        <div class="space-y-2">
                            <p class="text-lg md:text-xl font-bold text-blue-800 uppercase tracking-wider">စုစုပေါင်း အော်ဒါအရေအတွက်</p>
                            <h3 class="text-xs md:text-sm font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_orders); ?> ခု</h3>
                        </div>
                        <div class="w-12 h-12 bg-white text-blue-600 group-hover:bg-blue-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                            <i class="fa-solid fa-cart-shopping"></i>
                        </div>
                    </div>

                    <!-- 3. Pending Deliveries -->
                    <div class="bg-purple-100 p-6 rounded-2xl border border-purple-200/60 flex items-center justify-between shadow-sm hover:shadow transition group">
                        <div class="space-y-2">
                            <p class="text-xs md:text-sm font-bold text-purple-800 uppercase tracking-wider">ပို့ဆောင်ရန် ကျန်ရှိသည့် ပါဆယ်</p>
                            <h3 class="text-lg md:text-xl font-extrabold text-slate-950 tracking-tight"><?php echo number_format($pending_deliveries); ?> ခု</h3>
                        </div>
                        <div class="w-12 h-12 bg-white text-purple-600 group-hover:bg-purple-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                            <i class="fa-solid fa-truck-ramp-box"></i>
                        </div>
                    </div>

                    <!-- 4. Total Books -->
                    <div class="bg-emerald-100 p-6 rounded-2xl border border-emerald-200/60 flex items-center justify-between shadow-sm hover:shadow transition group">
                        <div class="space-y-2">
                            <p class="text-lg md:text-xl font-bold text-emerald-800 uppercase tracking-wider">စုစုပေါင်း စာအုပ်</p>
                            <h3 class="text-xs md:text-sm font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_books); ?> အုပ်</h3>
                        </div>
                        <div class="w-12 h-12 bg-white text-emerald-600 group-hover:bg-emerald-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                            <i class="fa-solid fa-book"></i>
                        </div>
                    </div>

                    <!-- 5. Categories -->
                    <div class="bg-sky-100 p-6 rounded-2xl border border-sky-200/60 flex items-center justify-between shadow-sm hover:shadow transition group">
                        <div class="space-y-2">
                            <p class="text-lg md:text-xl font-bold text-sky-800 uppercase tracking-wider">စာအုပ်အမျိုးအစားများ</p>
                            <h3 class="text-xs md:text-sm font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_categories); ?> မျိုး</h3>
                        </div>
                        <div class="w-12 h-12 bg-white text-sky-600 group-hover:bg-sky-500 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                            <i class="fa-solid fa-tags"></i>
                        </div>
                    </div>

                    <!-- 6. Customers Card -->
                    <div class="bg-slate-100 p-6 rounded-2xl border border-slate-200/80 flex flex-col justify-between shadow-sm hover:shadow transition group space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="text-lg md:text-xl font-bold text-slate-700 uppercase tracking-wider">အကောင့်ဖွင့်သူစုစုပေါင်း</p>
                                <h3 class="text-xs md:text-sm font-extrabold text-slate-950 tracking-tight"><?php echo number_format($total_customers); ?> ယောက်</h3>
                            </div>
                            <div class="w-12 h-12 bg-white text-slate-700 group-hover:bg-slate-800 group-hover:text-white rounded-xl flex items-center justify-center text-lg font-semibold transition-colors duration-300 shadow-sm">
                                <i class="fa-solid fa-users"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-200 text-xs">
                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                <p class="text-slate-500 font-medium">စာအုပ်ဝယ်ယူသူ</p>
                                <p class="text-sm font-bold text-emerald-600 mt-0.5"><?php echo number_format($purchased_customers); ?> ယောက်</p>
                            </div>
                            <div class="bg-white p-2 rounded-xl border border-slate-200">
                                <p class="text-slate-500 font-medium">အကောင့်ဖွင့်ကြည့်သူ</p>
                                <p class="text-sm font-bold text-slate-700 mt-0.5"><?php echo number_format($registered_viewers); ?> ယောက်</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders Table -->
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 md:px-8 py-5 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-slate-50/50">
                        <div class="space-y-0.5">
                            <h3 class="font-bold text-slate-900 flex items-center text-base">
                                <i class="fa-solid fa-clock-rotate-left mr-2.5 text-slate-600 text-sm"></i>Recent Orders
                            </h3>
                            <p class="text-xs text-slate-500 font-medium">Latest 5 incoming store purchases</p>
                        </div>
                        <a href="orders.php" class="w-full sm:w-auto text-center inline-flex items-center justify-center text-xs font-bold text-indigo-600 hover:text-indigo-800 transition bg-indigo-50 hover:bg-indigo-100/80 px-3.5 py-2 rounded-xl">
                            View All Orders <i class="fa-solid fa-arrow-right ml-1.5 text-[10px]"></i>
                        </a>
                    </div>

                    <div class="overflow-x-auto w-full no-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[700px]">
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider border-b border-slate-200">
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
                                if ($recent_orders_query && mysqli_num_rows($recent_orders_query) > 0) {
                                    while ($order = mysqli_fetch_assoc($recent_orders_query)) {
                                        $status = strtolower($order['status'] ?? 'pending');
                                        $status_class = "bg-amber-100 text-amber-800 border-amber-200";

                                        if ($status === 'completed' || $status === 'paid') {
                                            $status_class = "bg-emerald-100 text-emerald-800 border-emerald-200";
                                        } elseif ($status === 'cancelled') {
                                            $status_class = "bg-rose-50 text-rose-700 border-rose-200";
                                        }
                                ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <td class="px-6 md:px-8 py-4 font-mono font-bold text-slate-900 text-xs">
                                                #<?php echo htmlspecialchars($order['order_number'] ?? $order['id']); ?>
                                            </td>
                                            <td class="px-6 md:px-8 py-4 text-slate-900 font-semibold">
                                                <?php echo htmlspecialchars($order['customer_name'] ?? 'Guest Customer'); ?>
                                            </td>
                                            <td class="px-6 md:px-8 py-4 font-bold text-slate-900">
                                                <?php echo number_format($order['total_amount'] ?? 0); ?> ကျပ်
                                            </td>
                                            <td class="px-6 md:px-8 py-4">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold border <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 md:px-8 py-4 text-slate-900 font-normal text-xs">
                                                <?php echo isset($order['created_at']) ? date('d M Y, h:i A', strtotime($order['created_at'])) : 'N/A'; ?>
                                            </td>
                                            <td class="px-6 md:px-8 py-4 text-center">
                                                <a href="orderdetail.php?id=<?php echo $order['id']; ?>" class="inline-flex items-center justify-center px-3 py-1.5 bg-blue-600  rounded-xl text-xs text-white font-bold transition border border-slate-200">
                                                    <i class="fa-solid fa-eye mr-1.5 text-white"></i>View Detail
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
        // Toggle Sidebar visibility on mobile screens
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
            }
        }

        // Toggle Notifications Dropdown
        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');

            if (notiDropdown) notiDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden');
        }

        // Toggle Admin Profile Dropdown
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            const profileDropdown = document.getElementById('profileDropdown');
            const notiDropdown = document.getElementById('notiDropdown');

            if (profileDropdown) profileDropdown.classList.toggle('hidden');
            if (notiDropdown) notiDropdown.classList.add('hidden');
        }

        // Close Dropdowns on outside click
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