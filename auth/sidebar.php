<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url     = '/onlinebookshop';
$current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
$is_admin     = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

function sidebar_active($page) {
    global $current_page;
    return $current_page === $page 
        ? 'bg-indigo-600 text-white font-medium shadow-sm shadow-indigo-600/10' 
        : 'hover:bg-slate-800 hover:text-white transition font-medium';
}
?>

<!-- Custom Scrollbar Style for Sidebar -->
<style>
    /* Active nav link highlight */
    .header-nav a.active,
    .header-nav button.active {
        font-weight: 700;
        color: #1e293b !important;
    }

    /* Desktop: category dropdown opens on hover */
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

    /* Hamburger menu button bar animation */
    .hamburger-bar {
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    /* Scrollbar တစ်ခုလုံး၏ အကျယ် (5px is perfect for small scroll) */
    ::-webkit-scrollbar {
        width: 5px;
        /* ဒေါင်လိုက် scrollbar အကျယ် */
        height: 5px;
        /* အလျားလိုက် scrollbar အကျယ် */
    }

    /* Scrollbar နောက်ခံလမ်းကြောင်း (Track) */
    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        /* နောက်ခံအရောင် */
        border-radius: 10px;
        /* ထောင့်ကွေး ဆွဲခြင်း */
    }

    /* ဆွဲရွှေ့ရသည့် အတုံး (Thumb) */
    ::-webkit-scrollbar-thumb {
        background: #888;
        /* အတုံး၏ အရောင် */
        border-radius: 10px;
        /* ထောင့်ကွေး ဆွဲခြင်း */
    }

    /* Mouse ထောက်လိုက်သည့်အခါ ပြောင်းလဲမည့်အရောင် (Hover) */
    ::-webkit-scrollbar-thumb:hover {
        background: #555;
        /* FIXED: Removed the inline comment // which breaks CSS */
    }
</style>
<!-- SIDEBAR CONTAINER -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 text-white flex flex-col justify-between transform -translate-x-full transition-transform duration-300 md:relative md:translate-x-0 border-r border-slate-800 shrink-0">
    <div class="p-6 overflow-y-auto custom-sidebar-scrollbar flex-1">
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
            <?php if ($is_admin): ?>
                <!-- ================= ADMIN SIDEBAR MENU ================= -->
                <a href="<?= $base_url; ?>/admin/dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('dashboard'); ?>">
                    <i class="fa-solid fa-chart-pie w-5"></i><span>Dashboard</span>
                </a>

                <a href="<?= $base_url; ?>/admin/categories.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('categories'); ?>">
                    <i class="fa-solid fa-tags w-5"></i><span>Manage Categories</span>
                </a>

                <a href="<?= $base_url; ?>/admin/books.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('books'); ?>">
                    <i class="fa-solid fa-book w-5"></i><span>Manage Books</span>
                </a>

                <a href="<?= $base_url; ?>/admin/orders.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('orders'); ?>">
                    <i class="fa-solid fa-cart-shopping w-5"></i><span> Manage Orders</span>
                </a>
                
                <a href="<?= $base_url; ?>/admin/manage_payment.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('manage_payment'); ?>">
                    <i class="fa-solid fa-credit-card w-5"></i><span>Manage Payments</span>
                </a>
                
                <!-- <a href="<?= $base_url; ?>/admin/delivery.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('delivery'); ?>">
                    <i class="fa-solid fa-truck w-5"></i><span>Deliveries</span>
                </a> -->
                
                <a href="<?= $base_url; ?>/admin/customers.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('customers'); ?>">
                    <i class="fa-solid fa-users w-5"></i><span> Manage Customers</span>
                </a>

            <?php else: ?>
                <!-- ================= CUSTOMER SIDEBAR MENU ================= -->
                <a href="<?= $base_url; ?>/user/userdashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('userdashboard'); ?>">
                    <i class="fa-solid fa-chart-pie w-5"></i><span>My Dashboard</span>
                </a>
                <a href="<?= $base_url; ?>/user/myorders.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('myorders'); ?>">
                    <i class="fa-solid fa-box w-5"></i><span>My Orders</span>
                </a>
                <a href="<?= $base_url; ?>/user/cart.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('cart'); ?>">
                    <i class="fa-solid fa-shopping-basket w-5"></i><span>Shopping Cart</span>
                </a>
                <a href="<?= $base_url; ?>/user/userprofile.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl <?= sidebar_active('userprofile'); ?>">
                    <i class="fa-solid fa-user w-5"></i><span>Profile Settings</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <!-- Premium Red Sign Out Button in Sidebar -->
    <div class="p-4 border-t border-slate-800 bg-slate-950/30">
        <a href="<?= $base_url; ?>/auth/logout.php" class="flex items-center justify-center space-x-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition shadow-md shadow-rose-600/20 group">
            <i class="fa-solid fa-right-from-bracket group-hover:transform group-hover:translate-x-0.5 transition"></i><span>Sign Out</span>
        </a>
    </div>
</aside>