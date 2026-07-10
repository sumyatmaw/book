<?php
/**
 * Online Book Shop — Role-Based Header Component
 * Handles dynamic navigation for Guests, Customers, and Admins.
 * Includes cart count and total amount display.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
$base_url     = '/onlinebookshop';

// Session States
$is_logged_in = isset($_SESSION['user_id']);
$is_admin     = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$categories   = $categories ?? [];

// Compute cart count and total for navbar display
$cart_count = 0;
$cart_total = 0;

if (!$is_admin) {
    if ($is_logged_in && isset($conn)) {
        // Database cart for logged-in users
        $uid = $_SESSION['user_id'];
        $cart_q = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(totalprice),0) AS total FROM Cart_item WHERE user_id = ?");
        $cart_q->bind_param("i", $uid);
        $cart_q->execute();
        $cart_r = $cart_q->get_result()->fetch_assoc();
        $cart_count = intval($cart_r['qty'] ?? 0);
        $cart_total = floatval($cart_r['total'] ?? 0);
        $cart_q->close();
    } elseif (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
        // Session cart for guests
        foreach ($_SESSION['guest_cart'] as $gc) {
            $cart_count += intval($gc['quantity']);
            $cart_total += floatval($gc['totalprice']);
        }
    }
}

function nav_active(string $page): string {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<style>
/* Active nav link — amber highlight */
.header-nav a.active,
.header-nav button.active {
    color: #fbbf24 !important;
}

/* Desktop: category dropdown opens on hover */
@media (min-width: 768px) {
    .cat-dropdown:hover > .cat-dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile menu — smooth slide-down */
#mobileMenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
#mobileMenu.open {
    max-height: 85vh;
    overflow-y: auto;
}

/* Category dropdown menu setup */
.cat-dropdown-menu {
    display: none;
    opacity: 0;
    transform: translateY(-4px);
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.cat-dropdown.open > .cat-dropdown-menu {
    display: block;
    opacity: 1;
    transform: translateY(0);
}

/* Search placeholder color adjusted for white background */
.header-search::placeholder { color: #94a3b8; }

/* Hamburger Menu Bar Animations — Fixed for Perfect Centered Symmetry */
.hamburger-bar { 
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease; 
}
</style>

<header class="bg-slate-900 text-white sticky top-0 z-50 shadow-lg shadow-slate-900/20">
    <div class="container mx-auto px-4 sm:px-6 py-3">

        <div class="flex items-center justify-between gap-3">

            <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-2.5 shrink-0 group">
                <span class="w-9 h-9 bg-amber-500 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform duration-200 shadow-md shadow-amber-500/30">
                    <i class="fa-solid fa-book-open text-sm text-slate-900"></i>
                </span>
                <span class="text-lg font-black tracking-tight hidden sm:inline">
                    Online<span class="text-amber-500">BookShop</span>
                </span>
            </a>

            <!-- DESKTOP SEARCH BAR (Fixed yellow border issue on focus/hover) -->
            <form action="<?= $base_url; ?>/index.php" method="GET" class="hidden md:flex w-full max-w-sm lg:max-w-md mx-4">
                <div class="relative w-full">
                    <input type="text" name="search"
                           value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                           placeholder="Search books, authors..."
                           class="header-search w-full pl-4 pr-10 py-2.5 bg-white text-slate-900 text-sm border border-slate-300 rounded-xl focus:outline-none focus:ring-0 focus:border-slate-400 hover:border-slate-400 transition-all duration-200 placeholder:text-slate-400">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition-colors duration-200">
                        <i class="fas fa-search text-sm"></i>
                    </button>
                </div>
            </form>

            <!-- Hamburger Button with Perfect Centered Spacing -->
            <button id="hamburgerBtn"
                    class="md:hidden flex flex-col justify-center items-center w-10 h-10 rounded-xl hover:bg-slate-800 transition-colors duration-200 focus:outline-none focus:ring-0 gap-[5px]"
                    aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobileMenu">
                <span class="hamburger-bar block w-5 h-0.5 bg-white rounded-full" id="bar1"></span>
                <span class="hamburger-bar block w-5 h-0.5 bg-white rounded-full" id="bar2"></span>
                <span class="hamburger-bar block w-5 h-0.5 bg-white rounded-full" id="full-bar3"></span>
            </button>

            <!-- DESKTOP NAVIGATION -->
            <nav class="header-nav hidden md:flex items-center gap-1 text-sm font-semibold shrink-0">
                
                <a href="<?= $base_url; ?>/index.php" class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('index'); ?>">
                    Home
                </a>
                
                <a href="<?= $base_url; ?>/books.php" class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('books'); ?>">
                    Books
                </a>

                <div class="relative cat-dropdown">
                    <button class="cat-toggle px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 flex items-center gap-1.5 focus:outline-none" type="button">
                        Categories <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                    </button>
                    <ul class="cat-dropdown-menu absolute left-0 mt-1 w-56 bg-white text-slate-800 rounded-xl shadow-xl shadow-slate-900/10 border border-gray-100 z-50 py-1.5 overflow-hidden">
                        <li><a href="<?= $base_url; ?>/index.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs font-semibold text-amber-600 transition-colors duration-150">View All</a></li>
                        <li><hr class="border-gray-100 my-1"></li>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/index.php?cat_id=<?= (int)$cat['id']; ?>" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-600 truncate transition-colors duration-150">
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <span class="w-px h-5 bg-slate-700 mx-2"></span>

                <!-- ADMIN ROLE SPECIFIC NAVIGATION -->
                <?php if ($is_admin): ?>
                    <a href="<?= $base_url; ?>/admin/dashboard.php" class="px-3 py-2 rounded-lg text-amber-400 bg-slate-800 border border-amber-500/20 hover:bg-slate-700 transition-all duration-200">
                        <i class="fa-solid fa-gauge mr-1"></i> Dashboard
                    </a>
                    
                    <div class="relative cat-dropdown ml-1">
                        <button class="cat-toggle px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 flex items-center gap-2 focus:outline-none" type="button">
                            <span class="w-7 h-7 bg-amber-500 rounded-full flex items-center justify-center text-xs text-slate-900">
                                <i class="fa-solid fa-user-gear"></i>
                            </span>
                            <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                        </button>
                        <ul class="cat-dropdown-menu absolute right-0 mt-1 w-48 bg-white text-slate-800 rounded-xl shadow-xl shadow-slate-900/10 border border-gray-100 z-50 py-1.5 overflow-hidden">
                            <li class="px-4 py-2 text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-gray-50">Administrator</li>
                            <li><a href="<?= $base_url; ?>/admin/dashboard.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-600 transition-colors duration-150"><i class="fa-solid fa-chart-pie mr-2 text-gray-400"></i> Dashboard</a></li>
                            <li><hr class="border-gray-100 my-1"></li>
                            <li><a href="<?= $base_url; ?>/auth/logout.php" class="block px-4 py-2.5 hover:bg-red-50 text-xs text-red-600 transition-colors duration-150"><i class="fa-solid fa-right-from-bracket mr-2"></i> Logout</a></li>
                        </ul>
                    </div>

                <!-- CUSTOMER & GUEST ROLE NAVIGATION -->
                <?php else: ?>
                    <!-- Changed text to Burmese "စုစုပေါင်း" and "(ကျပ်)" for Desktop View -->
                    <a href="<?= $base_url; ?>/user/cart.php" class="px-3 py-1.5 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 relative flex items-center gap-3 <?= nav_active('cart'); ?>">
                        <div class="relative">
                            <i class="fa-solid fa-cart-shopping text-xl"></i>
                            <span id="navCartBadge" class="absolute -top-1.5 -right-2 bg-red-500 text-white text-[9px] w-[18px] h-[18px] min-w-[18px] rounded-full flex items-center justify-center font-bold <?= $cart_count > 0 ? '' : 'hidden'; ?>"><?= $cart_count; ?></span>
                        </div>
                        <div class="flex flex-col text-left text-xs leading-tight">
                            <span class="text-gray-400 text-[10px] font-normal">စုစုပေါင်း:</span>
                            <span class="text-amber-400 font-bold"><span id="navCartTotal"><?= number_format($cart_total); ?></span> (ကျပ်)</span>
                        </div>
                    </a>

                    <span class="w-px h-5 bg-slate-700 mx-2"></span>

                    <?php if ($is_logged_in): ?>
                        <a href="<?= $base_url; ?>/user/myorders.php" class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('myorders'); ?>">My Orders</a>
                        
                        <div class="relative cat-dropdown">
                            <button class="cat-toggle px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 flex items-center gap-2 focus:outline-none" type="button">
                                <span class="w-7 h-7 bg-slate-700 rounded-full flex items-center justify-center text-xs text-amber-400">
                                    <i class="fa-solid fa-user"></i>
                                </span>
                                <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                            </button>
                            <ul class="cat-dropdown-menu absolute right-0 mt-1 w-48 bg-white text-slate-800 rounded-xl shadow-xl shadow-slate-900/10 border border-gray-100 z-50 py-1.5 overflow-hidden">
                                <li><a href="<?= $base_url; ?>/user/userdashboard.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-600 transition-colors duration-150"><i class="fa-solid fa-columns mr-2 text-gray-400"></i> My Dashboard</a></li>
                                <li><a href="<?= $base_url; ?>/user/userprofile.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-600 transition-colors duration-150"><i class="fa-solid fa-user mr-2 text-gray-400"></i> Profile</a></li>
                                <li><hr class="border-gray-100 my-1"></li>
                                <li><a href="<?= $base_url; ?>/auth/logout.php" class="block px-4 py-2.5 hover:bg-red-50 text-xs text-red-600 transition-colors duration-150"><i class="fa-solid fa-right-from-bracket mr-2"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= $base_url; ?>/auth/login.php" class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('login'); ?>">Login</a>
                        <a href="<?= $base_url; ?>/auth/register.php" class="px-3 py-2 rounded-lg bg-amber-500 text-slate-900 font-bold hover:bg-amber-400 transition-all duration-200 <?= nav_active('register'); ?>">Register</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </div>

        <!-- MOBILE SEARCH BAR (Fixed yellow border issue on focus/hover) -->
        <form action="<?= $base_url; ?>/index.php" method="GET" class="md:hidden mt-3">
            <div class="relative w-full">
                <input type="text" name="search"
                       value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                       placeholder="Search books, authors..."
                       class="header-search w-full pl-4 pr-10 py-2.5 bg-white text-slate-900 text-sm border border-slate-300 rounded-xl focus:outline-none focus:ring-0 focus:border-slate-400 hover:border-slate-400 transition-all duration-200 placeholder:text-slate-400">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 transition-colors duration-200">
                    <i class="fas fa-search text-sm"></i>
                </button>
            </div>
        </form>

        <!-- MOBILE DROPDOWN MENU -->
        <div id="mobileMenu" class="md:hidden mt-3 border-t border-slate-800 pt-3 pb-2">
            <nav class="header-nav flex flex-col gap-0.5 text-sm font-semibold">
                
                <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('index'); ?>">
                    <i class="fa-solid fa-house text-xs w-5 text-center text-gray-500"></i> Home
                </a>
                <a href="<?= $base_url; ?>/books.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('books'); ?>">
                    <i class="fa-solid fa-book text-xs w-5 text-center text-gray-500"></i> Books
                </a>

                <div class="relative cat-dropdown">
                    <button class="cat-toggle w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 focus:outline-none" type="button">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-layer-group text-xs w-5 text-center text-gray-500"></i> Categories
                        </span>
                        <i class="fas fa-chevron-down text-[8px] opacity-60 transition-transform duration-200"></i>
                    </button>
                    <ul class="cat-dropdown-menu bg-slate-800/50 rounded-xl mx-4 mt-1 mb-1 py-1 border border-slate-700/50 max-h-48 overflow-y-auto">
                        <li><a href="<?= $base_url; ?>/index.php" class="block px-4 py-2.5 hover:bg-slate-700 text-xs font-semibold text-amber-400 transition-colors duration-150">View All</a></li>
                        <li><hr class="border-slate-700/50 my-1"></li>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/index.php?cat_id=<?= (int)$cat['id']; ?>" class="block px-4 py-2.5 hover:bg-slate-700 text-xs text-gray-300 truncate transition-colors duration-150">
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <hr class="border-slate-800 my-1 mx-4">

                <!-- MOBILE: ADMIN ROLE MENU -->
                <?php if ($is_admin): ?>
                    <a href="<?= $base_url; ?>/admin/dashboard.php" class="flex items-center gap-3 px-4 py-3 mx-4 rounded-xl bg-amber-500 text-slate-900 font-bold justify-center transition-colors duration-200 shadow-sm shadow-amber-500/20">
                        <i class="fa-solid fa-gauge text-xs"></i> Dashboard
                    </a>
                    <hr class="border-slate-800 my-1 mx-4">
                    <a href="<?= $base_url; ?>/auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 text-red-400 hover:text-red-300 transition-colors duration-200">
                        <i class="fa-solid fa-right-from-bracket text-xs w-5 text-center"></i> Admin Logout
                    </a>

                <!-- MOBILE: CUSTOMER ROLE MENU -->
                <?php else: ?>
                    <!-- Changed text to Burmese "စုစုပေါင်း" and "(ကျပ်)" for Mobile View -->
                    <a href="<?= $base_url; ?>/user/cart.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('cart'); ?>">
                        <i class="fa-solid fa-cart-shopping text-xs w-5 text-center text-gray-500"></i>
                        <span>စုစုပေါင်း:</span>
                        <span class="text-amber-400 font-bold ml-1"><span id="mNavCartCount"><?= $cart_count; ?></span> အုပ် / <span id="mNavCartTotal"><?= number_format($cart_total); ?></span> (ကျပ်)</span>
                    </a>

                    <?php if ($is_logged_in): ?>
                        <a href="<?= $base_url; ?>/user/myorders.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('myorders'); ?>">
                            <i class="fa-solid fa-box text-xs w-5 text-center text-gray-500"></i> My Orders
                        </a>
                        <a href="<?= $base_url; ?>/user/userprofile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('userprofile'); ?>">
                            <i class="fa-solid fa-user text-xs w-5 text-center text-gray-500"></i> Profile
                        </a>
                        <hr class="border-slate-800 my-1 mx-4">
                        <a href="<?= $base_url; ?>/auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 text-red-400 hover:text-red-300 transition-colors duration-200">
                            <i class="fa-solid fa-right-from-bracket text-xs w-5 text-center"></i> Logout
                        </a>
                    <?php else: ?>
                        <hr class="border-slate-800 my-1 mx-4">
                        <a href="<?= $base_url; ?>/auth/login.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('login'); ?>">
                            <i class="fa-solid fa-right-to-bracket text-xs w-5 text-center"></i> Login
                        </a>
                        <a href="<?= $base_url; ?>/auth/register.php" class="flex items-center gap-3 mx-4 mt-1 mb-1 px-4 py-3 rounded-xl bg-amber-500 text-slate-900 font-bold text-center hover:bg-amber-400 transition-colors duration-200 shadow-sm shadow-amber-500/20 <?= nav_active('register'); ?>">
                            <i class="fa-solid fa-user-plus text-xs w-5 text-center"></i> Register
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

            </nav>
        </div>
    </div>
</header>

<script>
(function () {
    'use strict';

    var btn  = document.getElementById('hamburgerBtn');
    var menu = document.getElementById('mobileMenu');
    var bar1 = document.getElementById('bar1');
    var bar2 = document.getElementById('bar2');
    var bar3 = document.getElementById('full-bar3');

    /* ---- Hamburger Animation Engine (Transforms seamlessly to 'X') ---- */
    if (btn && menu) {
        btn.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            btn.setAttribute('aria-expanded', open);
            bar1.style.transform = open ? 'translateY(7px) rotate(45deg)'  : '';
            bar2.style.opacity   = open ? '0'                               : '';
            bar3.style.transform = open ? 'translateY(-7px) rotate(-45deg)' : '';
        });
    }

    /* ---- Category Dropdown Handlers ---- */
    document.querySelectorAll('.cat-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var parent = toggle.closest('.cat-dropdown');
            document.querySelectorAll('.cat-dropdown.open').forEach(function (dd) {
                if (dd !== parent) dd.classList.remove('open');
            });
            parent.classList.toggle('open');
        });
    });

    /* ---- Document Overlay Click Closures ---- */
    document.addEventListener('click', function (e) {
        document.querySelectorAll('.cat-dropdown.open').forEach(function (dd) {
            if (!dd.contains(e.target)) dd.classList.remove('open');
        });
    });

    /* ---- Display Viewport Recalibration Listeners ---- */
    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth >= 768 && menu) {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                bar1.style.transform = '';
                bar2.style.opacity   = '';
                bar3.style.transform = '';
            }
        }, 100);
    });

    /* ---- Live Cart Update from API ---- */
    window.refreshCartBadge = function () {
        if (document.getElementById('navCartBadge') === null && document.getElementById('mNavCartCount') === null) {
            return; 
        }
        fetch('/onlinebookshop/user/cart_api.php')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var count = parseInt(d.count) || 0;
                var total = parseInt(d.total) || 0;

                var totalEl = document.getElementById('navCartTotal');
                var badge = document.getElementById('navCartBadge');

                if (totalEl) totalEl.textContent = total.toLocaleString();
                if (badge) {
                    badge.textContent = count;
                    badge.classList.toggle('hidden', count <= 0);
                }

                var mCount = document.getElementById('mNavCartCount');
                var mTotal = document.getElementById('mNavCartTotal');
                // Updated terminology in JavaScript API response mapping
                if (mCount) mCount.textContent = count + " အုပ်";
                if (mTotal) mTotal.textContent = total.toLocaleString() + " (ကျပ်)";
            })
            .catch(function () {});
    };
})();
</script>