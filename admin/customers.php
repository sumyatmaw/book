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

// OPTIONAL ACTION: DELETE CUSTOMER
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Safety check: Avoid deleting admin accounts accidentally
    $stmt = $conn->prepare("DELETE FROM Users WHERE id = ? AND role = 'customer'");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Customer account deleted successfully!";
    } else {
        $error = "Failed to delete customer account!";
    }
    $stmt->close();
    // Refresh page to clear URL parameters safely
    header("Refresh: 2; URL=customers.php");
}

// FETCH ALL CUSTOMERS
$sql = "SELECT id, name, email, phone, address, created_at 
        FROM Users 
        WHERE role = 'customer' 
        ORDER BY id DESC";
$result = $conn->query($sql);
$totalCustomers = $result ? $result->num_rows : 0;

// Fetch Live Alert Badge & Dropdown Notifications (Synced with categories.php layout)
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 4");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_list_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = mysqli_num_rows($pending_payments_list_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Online Book Shop</title>
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
            <!-- Brand Logo Header -->
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
            
            <!-- Navigation Links -->
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
                <a href="manage_payment.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-credit-card w-5"></i><span>Payments</span>
                </a>
                <a href="delivery.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-truck w-5"></i><span>Deliveries</span>
                </a>
                <a href="customers.php" class="flex items-center space-x-3 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-sm shadow-indigo-600/10">
                    <i class="fa-solid fa-users w-5 text-indigo-200"></i><span>Customers</span>
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
                <h1 class="text-lg font-bold text-slate-800 md:text-xl">Categories Management</h1>
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
                                <?php while($payment = mysqli_fetch_assoc($pending_payments_list_query)): ?>
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
                
                <!-- Admin Profile Menu Button (Updated with Profile Image View matching orders.php) -->
                <div class="relative border-l border-slate-200 pl-4">
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 text-slate-600 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer overflow-hidden">
                        <?php if (!empty($profile_path) && file_exists($profile_path)): ?>
                            <img src="<?= htmlspecialchars($profile_path); ?>" alt="Admin" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-sm"></i>
                        <?php endif; ?>
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
                    <i class="fa-solid fa-users text-indigo-600"></i> Customers
                </h1>
                <p class="text-xs text-gray-400 mt-1"><?= $totalCustomers; ?> registered users</p>
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

            <!-- Desktop Table View -->
            <div class="hidden lg:block bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list text-indigo-500"></i> Registered Customer Directory
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/70 text-gray-400 uppercase text-[11px] tracking-wider border-b border-slate-100">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold w-16">ID</th>
                                <th class="px-5 py-3 text-left font-semibold">Customer Name</th>
                                <th class="px-5 py-3 text-left font-semibold">Email Address</th>
                                <th class="px-5 py-3 text-left font-semibold">Phone Number</th>
                                <th class="px-5 py-3 text-left font-semibold">Shipping Address</th>
                                <th class="px-5 py-3 text-center font-semibold">Joined Date</th>
                                <th class="px-5 py-3 text-right font-semibold w-24 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50/40 transition align-top">
                                    <td class="px-5 py-4 font-bold text-gray-400">#<?= $row['id']; ?></td>
                                    <td class="px-5 py-4 font-bold text-slate-900"><?= htmlspecialchars($row['name']); ?></td>
                                    <td class="px-5 py-4 font-mono text-gray-600"><?= htmlspecialchars($row['email']); ?></td>
                                    <td class="px-5 py-4 text-slate-700"><?= htmlspecialchars($row['phone'] ?: 'N/A'); ?></td>
                                    <td class="px-5 py-4 text-gray-600 max-w-xs whitespace-pre-line"><?= htmlspecialchars($row['address'] ?: 'No address provided'); ?></td>
                                    <td class="px-5 py-4 text-center text-gray-400 whitespace-nowrap"><?= date('d M Y', strtotime($row['created_at'])); ?></td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <a href="customers.php?delete_id=<?= $row['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this customer account? This action cannot be undone.')" 
                                           class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-sm shadow-rose-600/10 inline-flex items-center gap-1 cursor-pointer">
                                            <i class="fa-solid fa-trash-can text-[10px]"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="py-12 text-center text-gray-400 font-semibold">No customers found in the system.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="lg:hidden space-y-3">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php 
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()): 
                    ?>
                    <div class="bg-white p-4 rounded-xl border border-slate-200/60 shadow-sm">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-400">ID: #<?= $row['id']; ?></span>
                            <span class="text-[10px] font-semibold text-gray-400"><?= date('d M Y', strtotime($row['created_at'])); ?></span>
                        </div>
                        <h4 class="font-black text-slate-900 text-sm mb-1"><?= htmlspecialchars($row['name']); ?></h4>
                        <p class="text-xs font-mono text-indigo-600 mb-3 truncate"><?= htmlspecialchars($row['email']); ?></p>
                        
                        <div class="space-y-1.5 text-xs border-t border-slate-100 pt-2.5 font-medium">
                            <div>
                                <span class="text-gray-400 block text-[11px]">Phone</span>
                                <span class="text-slate-700"><?= htmlspecialchars($row['phone'] ?: 'N/A'); ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[11px]">Shipping Address</span>
                                <p class="text-slate-600 whitespace-pre-line mt-0.5"><?= htmlspecialchars($row['address'] ?: 'No address provided'); ?></p>
                            </div>
                        </div>

                        <div class="flex justify-end pt-3 mt-3 border-t border-slate-100">
                            <a href="customers.php?delete_id=<?= $row['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this customer account? This action cannot be undone.')" 
                               class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1.5 rounded-xl text-xs font-bold transition shadow-sm cursor-pointer">
                                <i class="fa-solid fa-trash-can mr-1 text-[10px]"></i> Delete Account
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-12 rounded-xl border border-slate-200/60 shadow-sm text-center">
                        <i class="fa-solid fa-users text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400 font-semibold text-sm">No customers found.</p>
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