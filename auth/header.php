<?php

/**
 * Online Book Shop — Role-Based Header Component
 * Dynamic navigation for Guests and Customers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
$base_url     = '/onlinebookshop';

// Session States
$is_logged_in = isset($_SESSION['user_id']);

// Fetch user profile image dynamically if not set in session but logged in
if ($is_logged_in && isset($conn)) {
    $uid = $_SESSION['user_id'];
    $u_query = mysqli_query($conn, "SELECT profile_image FROM users WHERE id = '$uid'");
    if ($u_query && $u_row = mysqli_fetch_assoc($u_query)) {
        $_SESSION['user_image'] = $u_row['profile_image'];
    }
}

// Format Profile Image Path accurately for Header display
$default_avatar = 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
$raw_header_img = $_SESSION['user_image'] ?? '';

if (empty($raw_header_img)) {
    $user_header_src = $default_avatar;
} elseif (strpos($raw_header_img, 'http') === 0) {
    $user_header_src = $raw_header_img;
} elseif (strpos($raw_header_img, 'assets/') === 0) {
    $user_header_src = $base_url . '/' . $raw_header_img;
} else {
    $user_header_src = $base_url . '/assets/' . ltrim($raw_header_img, '/');
}

// Dynamic Database Fetch for Categories Dropdown List
$categories = [];
if (isset($conn)) {
    $cat_sql = "SELECT id, category_name FROM categories ORDER BY category_name ASC";
    $cat_result = $conn->query($cat_sql);
    if ($cat_result && $cat_result->num_rows > 0) {
        while ($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Dynamic Database Fetch for Authors Dropdown (From 'books' table)
$authors = [];
if (isset($conn)) {
    // DISTINCT နဲ့ နာမည်မထပ်အောင်ယူပြီး ORDER BY အက္ခရာစဉ် စီသည်
    $auth_sql = "SELECT DISTINCT author FROM books WHERE author IS NOT NULL AND TRIM(author) != '' ORDER BY author ASC";
    $auth_result = $conn->query($auth_sql);
    if ($auth_result && $auth_result->num_rows > 0) {
        while ($row = $auth_result->fetch_assoc()) {
            $authors[] = $row['author'];
        }
    }
}

// Compute cart count and total for navbar display
$cart_count = 0;
$cart_total = 0;

if ($is_logged_in && isset($conn)) {
    // Database cart for logged-in users
    $uid = $_SESSION['user_id'];
    $cart_q = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS qty, COALESCE(SUM(totalprice),0) AS total FROM cart_item WHERE user_id = ?");
    $cart_q->bind_param("i", $uid);
    $cart_q->execute();
    $cart_r = $cart_q->get_result()->fetch_assoc();
    $cart_count = intval($cart_r['qty'] ?? 0);
    $cart_total = floatval($cart_r['total'] ?? 0);
    $cart_q->close();
} elseif (isset($_SESSION['guest_cart']) && is_array($_SESSION['guest_cart'])) {
    // Session cart for guests
    foreach ($_SESSION['guest_cart'] as $gc) {
        $cart_count += intval($gc['quantity'] ?? 0);
        $cart_total += floatval($gc['totalprice'] ?? 0);
    }
}

function nav_active(string $page): string
{
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
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

<header class="bg-yellow-300 text-slate-900 sticky top-0 z-50 shadow-md">
    <div class="container mx-auto px-4 sm:px-6 py-3">

        <div class="flex items-center justify-between gap-3">

            <!-- Logo Section -->
            <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-2.5 shrink-0">
                <span class="w-9 h-9 bg-amber-500 rounded-lg flex items-center justify-center shadow-sm">
                    <i class="fa-solid fa-book-open text-sm text-slate-900"></i>
                </span>
                <span class="text-lg font-black tracking-tight hidden sm:inline">
                    Online<span class="text-amber-600">BookShop</span>
                </span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="header-nav hidden lg:flex items-center gap-1 text-sm font-semibold shrink-0">
                <a href="<?= $base_url; ?>/index.php" class="px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors <?= nav_active('index'); ?>">
                    Home
                </a>

                <a href="<?= $base_url; ?>/books.php" class="px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors <?= nav_active('books'); ?>">
                    Books
                </a>

                <div class="relative cat-dropdown">
                    <button class="cat-toggle px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors flex items-center gap-1.5 focus:outline-none" type="button">
                        Categories <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                    </button>
                    <ul class="cat-dropdown-menu absolute left-0 mt-1 w-56 bg-white text-slate-900 rounded-xl shadow-lg border border-slate-100 z-50 py-1.5 overflow-hidden">
                        <li><a href="<?= $base_url; ?>/index.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs font-semibold text-amber-600 transition-colors">View All</a></li>
                        <li>
                            <hr class="border-slate-100 my-1">
                        </li>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/index.php?cat_id=<?= (int)$cat['id']; ?>" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 truncate transition-colors">
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="px-4 py-2 text-xs text-slate-400 italic">No categories found</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
            <!-- Desktop Authors Dropdown -->
            <div class="relative cat-dropdown">
                <button class="cat-toggle px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors flex items-center gap-1.5 focus:outline-none" type="button">
                    Authors <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                </button>
                <ul class="cat-dropdown-menu absolute left-0 mt-1 w-56 bg-white text-slate-900 rounded-xl shadow-lg border border-slate-100 z-50 py-1.5 overflow-hidden max-h-60 overflow-y-auto">
                    <li>
                        <a href="<?= $base_url; ?>/books.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs font-semibold text-amber-600 transition-colors">
                            View All Authors
                        </a>
                    </li>
                    <li>
                        <hr class="border-slate-100 my-1">
                    </li>
                    <?php if (!empty($authors)): ?>
                        <?php foreach ($authors as $author_name): ?>
                            <li>
                                <a href="<?= $base_url; ?>/index.php?author=<?= urlencode($author_name); ?>" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 truncate transition-colors">
                                    <?= htmlspecialchars($author_name); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="px-4 py-2 text-xs text-slate-400 italic">No authors found</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Search Bar -->
            <form action="<?= $base_url; ?>/index.php" method="GET" class="hidden md:flex w-full max-w-xs lg:max-w-md mx-2">
                <div class="relative w-full">
                    <input type="text" name="search"
                        value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                        placeholder="Search books, authors..."
                        class="header-search w-full pl-4 pr-10 py-2.5 text-slate-900 text-sm rounded-xl placeholder:text-slate-400">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-800 transition-colors">
                        <i class="fas fa-search text-sm"></i>
                    </button>
                </div>
            </form>

            <!-- Mobile Hamburger Button -->
            <button id="hamburgerBtn"
                class="md:hidden flex flex-col justify-center items-center w-10 h-10 rounded-xl hover:bg-amber-400/50 transition-colors focus:outline-none gap-[5px]"
                aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="mobileMenu">
                <span class="hamburger-bar block w-5 h-0.5 bg-slate-900 rounded-full" id="bar1"></span>
                <span class="hamburger-bar block w-5 h-0.5 bg-slate-900 rounded-full" id="bar2"></span>
                <span class="hamburger-bar block w-5 h-0.5 bg-slate-900 rounded-full" id="full-bar3"></span>
            </button>

            <!-- Right Side User Options (Cart, Orders, Profile / Auth) -->
            <div class="header-nav hidden md:flex items-center gap-1 text-sm font-semibold shrink-0">
                <a href="<?= $base_url; ?>/user/cart.php" class="px-3 py-1.5 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors relative flex items-center gap-3 <?= nav_active('cart'); ?>">
                    <div class="relative">
                        <i class="fa-solid fa-cart-shopping text-xl text-slate-900"></i>
                        <span id="navCartBadge" class="absolute -top-1.5 -right-2 bg-red-500 text-white text-[9px] w-[18px] h-[18px] min-w-[18px] rounded-full flex items-center justify-center font-bold <?= $cart_count > 0 ? '' : 'hidden'; ?>"><?= $cart_count; ?></span>
                    </div>
                    <div class="flex flex-col text-left text-xs leading-tight">
                        <span class="text-slate-800 text-[10px] font-normal">စုစုပေါင်း:</span>
                        <span class="text-slate-900 font-bold"><span id="navCartTotal"><?= number_format($cart_total); ?></span> (ကျပ်)</span>
                    </div>
                </a>

                <span class="w-px h-5 bg-slate-400/50 mx-2"></span>

                <?php if ($is_logged_in): ?>
                    <a href="<?= $base_url; ?>/user/dashboard.php" class="px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors <?= nav_active('userprofile'); ?>">My Orders</a>

                    <div class="relative cat-dropdown">
                        <button class="cat-toggle px-1 py-1 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors flex items-center gap-1.5 focus:outline-none" type="button">
                            <img src="<?= htmlspecialchars($user_header_src); ?>"
                                onerror="this.onerror=null; this.src='<?= $base_url; ?>/uploads/profile/<?= htmlspecialchars(basename($raw_header_img)); ?>'; if(this.src.includes('undefined')||this.src.endsWith('/')) this.src='<?= $default_avatar; ?>';"
                                class="w-8 h-8 rounded-full object-cover border border-amber-600 shadow-sm bg-gray-100">
                            <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                        </button>
                        <ul class="cat-dropdown-menu absolute right-0 mt-1 w-48 bg-white text-slate-800 rounded-xl shadow-lg border border-slate-100 z-50 py-1.5 overflow-hidden">
                            <li><a href="<?= $base_url; ?>/user/dashboard.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 transition-colors"><i class="fa-solid fa-columns mr-2 text-slate-400"></i> My Dashboard</a></li>
                            <li><a href="<?= $base_url; ?>/user/userprofile.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 transition-colors"><i class="fa-solid fa-user mr-2 text-slate-400"></i> Profile</a></li>
                            <li>
                                <hr class="border-slate-100 my-1">
                            </li>
                            <li><a href="<?= $base_url; ?>/auth/logout.php" class="block px-4 py-2.5 hover:bg-red-50 text-xs text-red-600 transition-colors"><i class="fa-solid fa-right-from-bracket mr-2"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?= $base_url; ?>/auth/login.php" class="px-3 py-2 rounded-lg text-slate-900 hover:bg-amber-400/40 transition-colors <?= nav_active('login'); ?>">Login</a>
                    <a href="<?= $base_url; ?>/auth/register.php" class="px-3 py-2 rounded-lg bg-amber-500 text-slate-900 font-bold hover:bg-amber-600 transition-colors <?= nav_active('register'); ?>">Register</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mobile Search Bar -->
        <form action="<?= $base_url; ?>/index.php" method="GET" class="md:hidden mt-3">
            <div class="relative w-full">
                <input type="text" name="search"
                    value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                    placeholder="Search books, authors..."
                    class="header-search w-full pl-4 pr-10 py-2.5 text-slate-900 text-sm rounded-xl placeholder:text-slate-400">
                <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-800 transition-colors">
                    <i class="fas fa-search text-sm"></i>
                </button>
            </div>
        </form>

        <!-- Mobile Navigation Menu -->
        <div id="mobileMenu" class="md:hidden mt-3 border-t border-amber-400/50 pt-3 pb-2">
            <nav class="header-nav flex flex-col gap-0.5 text-sm font-semibold">

                <a href="<?= $base_url; ?>/index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('index'); ?>">
                    <i class="fa-solid fa-house text-xs w-5 text-center text-slate-700"></i> Home
                </a>
                <a href="<?= $base_url; ?>/books.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('books'); ?>">
                    <i class="fa-solid fa-book text-xs w-5 text-center text-slate-700"></i> Books
                </a>

                <div class="relative cat-dropdown">
                    <button class="cat-toggle w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors focus:outline-none" type="button">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-layer-group text-xs w-5 text-center text-slate-700"></i> Categories
                        </span>
                        <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                    </button>
                    <ul class="cat-dropdown-menu bg-white rounded-xl mx-4 mt-1 mb-1 py-1 border border-slate-200 max-h-48 overflow-y-auto shadow-sm">
                        <li><a href="<?= $base_url; ?>/index.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs font-semibold text-amber-600 transition-colors">View All</a></li>
                        <li>
                            <hr class="border-slate-100 my-1">
                        </li>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/index.php?cat_id=<?= (int)$cat['id']; ?>" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 truncate transition-colors">
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="px-4 py-2 text-xs text-slate-400 italic">No categories found</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <!-- Mobile Authors Dropdown -->
                <div class="relative cat-dropdown">
                    <button class="cat-toggle w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors focus:outline-none" type="button">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-user-pen text-xs w-5 text-center text-slate-700"></i> Authors
                        </span>
                        <i class="fas fa-chevron-down text-[8px] opacity-70"></i>
                    </button>
                    <ul class="cat-dropdown-menu bg-white rounded-xl mx-4 mt-1 mb-1 py-1 border border-slate-200 max-h-48 overflow-y-auto shadow-sm">
                        <li>
                            <a href="<?= $base_url; ?>/books.php" class="block px-4 py-2.5 hover:bg-amber-50 text-xs font-semibold text-amber-600 transition-colors">
                                View All Authors
                            </a>
                        </li>
                        <li>
                            <hr class="border-slate-100 my-1">
                        </li>
                        <?php if (!empty($authors)): ?>
                            <?php foreach ($authors as $author_name): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/index.php?author=<?= urlencode($author_name); ?>" class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-700 truncate transition-colors">
                                        <?= htmlspecialchars($author_name); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="px-4 py-2 text-xs text-slate-400 italic">No authors found</li>
                        <?php endif; ?>
                    </ul>
                </div>


                <a href class="border-amber-400/50 my-1 mx-4">

                    <a href="<?= $base_url; ?>/user/cart.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('cart'); ?>">
                        <i class="fa-solid fa-cart-shopping text-xs w-5 text-center text-slate-700"></i>
                        <span>စုစုပေါင်း:</span>
                        <span class="text-slate-900 font-bold ml-1"><span id="mNavCartCount"><?= $cart_count; ?></span> အုပ် / <span id="mNavCartTotal"><?= number_format($cart_total); ?></span> (ကျပ်)</span>
                    </a>

                    <?php if ($is_logged_in): ?>
                        <a href="<?= $base_url; ?>/user/userprofile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('userprofile'); ?>">
                            <i class="fa-solid fa-box text-xs w-5 text-center text-slate-700"></i> My Orders
                        </a>
                        <a href="<?= $base_url; ?>/user/userprofile.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('userprofile'); ?>">
                            <span class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars($user_header_src); ?>"
                                    onerror="this.onerror=null; this.src='<?= $base_url; ?>/uploads/profile/<?= htmlspecialchars(basename($raw_header_img)); ?>'; if(this.src.includes('undefined')||this.src.endsWith('/')) this.src='<?= $default_avatar; ?>';"
                                    class="w-6 h-6 rounded-full object-cover border border-amber-600 bg-gray-100">
                                Profile
                            </span>
                        </a>
                        <hr class="border-amber-400/50 my-1 mx-4">
                        <a href="<?= $base_url; ?>/auth/logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-red-100 text-red-600 transition-colors">
                            <i class="fa-solid fa-right-from-bracket text-xs w-5 text-center"></i> Logout
                        </a>
                    <?php else: ?>
                        <hr class="border-amber-400/50 my-1 mx-4">
                        <a href="<?= $base_url; ?>/auth/login.php" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-amber-400/40 transition-colors <?= nav_active('login'); ?>">
                            <i class="fa-solid fa-right-to-bracket text-xs w-5 text-center"></i> Login
                        </a>
                        <a href="<?= $base_url; ?>/auth/register.php" class="flex items-center gap-3 mx-4 mt-1 mb-1 px-4 py-3 rounded-xl bg-amber-500 text-slate-900 font-bold text-center hover:bg-amber-600 transition-colors <?= nav_active('register'); ?>">
                            <i class="fa-solid fa-user-plus text-xs w-5 text-center"></i> Register
                        </a>
                    <?php endif; ?>

            </nav>
        </div>
    </div>
</header>

<script>
    (function() {
        'use strict';

        var btn = document.getElementById('hamburgerBtn');
        var menu = document.getElementById('mobileMenu');
        var bar1 = document.getElementById('bar1');
        var bar2 = document.getElementById('bar2');
        var bar3 = document.getElementById('full-bar3');

        /* Hamburger menu animation event handler */
        if (btn && menu) {
            btn.addEventListener('click', function() {
                var open = menu.classList.toggle('open');
                btn.setAttribute('aria-expanded', open);
                bar1.style.transform = open ? 'translateY(7px) rotate(45deg)' : '';
                bar2.style.opacity = open ? '0' : '';
                bar3.style.transform = open ? 'translateY(-7px) rotate(-45deg)' : '';
            });
        }

        /* Category dropdown toggle handlers */
        document.querySelectorAll('.cat-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                var parent = toggle.closest('.cat-dropdown');
                document.querySelectorAll('.cat-dropdown.open').forEach(function(dd) {
                    if (dd !== parent) dd.classList.remove('open');
                });
                parent.classList.toggle('open');
            });
        });

        /* Close dropdown when clicking outside */
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.cat-dropdown.open').forEach(function(dd) {
                if (!dd.contains(e.target)) dd.classList.remove('open');
            });
        });

        /* Reset mobile menu state on window resize */
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth >= 768 && menu) {
                    menu.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                    bar1.style.transform = '';
                    bar2.style.opacity = '';
                    bar3.style.transform = '';
                }
            }, 100);
        });

        /* Live cart update API sync */
        window.refreshCartBadge = function() {
            if (document.getElementById('navCartBadge') === null && document.getElementById('mNavCartCount') === null) {
                return;
            }
            fetch('/onlinebookshop/user/cart_api.php')
                .then(function(r) {
                    return r.json();
                })
                .then(function(d) {
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
                    if (mCount) mCount.textContent = count;
                    if (mTotal) mTotal.textContent = total.toLocaleString();
                })
                .catch(function() {});
        };
    })();
</script>