<?php
/**
 * User Header Component — Online Book Shop
 *
 * Used by user-facing pages (dashboard, cart, orders, etc.)
 * Provides a consistent navigation bar with session-aware links.
 *
 * Expected variables (all optional):
 *   $categories    - array of ['id', 'category_name'] rows for dropdown
 *   $search_query  - string, current search term to pre-fill the search box
 *   $current_page  - string, page identifier for active link highlighting
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = $current_page ?? basename($_SERVER['PHP_SELF'], '.php');
$base_url     = '/onlinebookshop';
$is_logged_in = isset($_SESSION['user_id']);
$user_name    = $_SESSION['user_name'] ?? 'User';
$categories   = $categories ?? [];

function nav_active(string $page): string {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<style>
/* Active nav link */
.user-header-nav a.active {
    color: #fbbf24 !important;
}

/* Desktop: category dropdown on hover */
@media (min-width: 768px) {
    .cat-dropdown:hover > .cat-dropdown-menu {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }
}

/* Mobile menu slide */
#userMobileMenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
#userMobileMenu.open {
    max-height: 85vh;
    overflow-y: auto;
}

/* Dropdown */
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

.hamburger-bar {
    transition: transform 0.3s ease, opacity 0.3s ease;
}
</style>

<header class="bg-slate-900 text-white sticky top-0 z-50 shadow-lg shadow-slate-900/20">
    <div class="container mx-auto px-4 sm:px-6 py-3">

        <!-- Row 1: Logo | Search | Hamburger + Desktop Nav -->
        <div class="flex items-center justify-between gap-3">

            <!-- Logo -->
            <a href="<?= $base_url; ?>/user/userdashboard.php" class="flex items-center gap-2.5 shrink-0 group">
                <span class="w-9 h-9 bg-amber-500 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform duration-200 shadow-md shadow-amber-500/30">
                    <i class="fa-solid fa-book-open text-sm text-slate-900"></i>
                </span>
                <span class="text-lg font-black tracking-tight hidden sm:inline">
                    Online<span class="text-amber-500">BookShop</span>
                </span>
            </a>

            <!-- Desktop Search -->
            <form action="<?= $base_url; ?>/index.php" method="GET"
                  class="hidden md:flex w-full max-w-sm lg:max-w-md mx-4">
                <div class="relative w-full">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
                    <input type="text" name="search"
                           value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                           placeholder="စာအုပ်အမည်၊ စာရေးဆရာ ရှာဖွေရန်..."
                           class="w-full pl-10 pr-10 py-2.5 bg-slate-800/80 text-white text-sm border border-slate-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500/50 transition-all duration-200 placeholder:text-slate-500">
                    <button type="submit"
                            class="absolute right-1 top-1/2 -translate-y-1/2 bg-amber-500 text-slate-900 w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold hover:bg-amber-400 transition-colors duration-200">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            <!-- Hamburger (mobile) -->
            <button id="userHamburgerBtn"
                    class="md:hidden flex flex-col justify-center items-center w-10 h-10 rounded-xl hover:bg-slate-800 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500/50"
                    aria-label="Toggle navigation menu"
                    aria-expanded="false"
                    aria-controls="userMobileMenu">
                <span class="hamburger-bar block w-5 h-0.5 bg-white rounded-full" id="ubar1"></span>
                <span class="hamburger-bar block w-5 h-0.5 bg-white rounded-full mt-1.5" id="ubar2"></span>
                <span class="hamburger-bar block w-3.5 h-0.5 bg-white rounded-full mt-1.5 ml-auto" id="ubar3"></span>
            </button>

            <!-- Desktop Nav -->
            <nav class="user-header-nav hidden md:flex items-center gap-1 text-sm font-semibold shrink-0">
                <a href="<?= $base_url; ?>/user/userdashboard.php"
                   class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('userdashboard'); ?>">
                    Home
                </a>
                <a href="<?= $base_url; ?>/user/books.php"
                   class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('books'); ?>">
                    Books
                </a>
                <a href="<?= $base_url; ?>/user/cart.php"
                   class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 relative <?= nav_active('cart'); ?>">
                    Cart
                    <span id="cartCountDesktop"
                          class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[8px] min-w-[16px] h-4 rounded-full flex items-center justify-center font-bold hidden">0</span>
                </a>
                <a href="<?= $base_url; ?>/user/myorders.php"
                   class="px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 <?= nav_active('myorders'); ?>">
                    My Orders
                </a>

                <!-- Separator -->
                <span class="w-px h-5 bg-slate-700 mx-1"></span>

                <!-- User dropdown / profile -->
                <div class="relative cat-dropdown">
                    <button class="cat-toggle px-3 py-2 rounded-lg text-gray-300 hover:text-amber-400 hover:bg-slate-800/50 transition-all duration-200 flex items-center gap-2 focus:outline-none"
                            type="button">
                        <span class="w-7 h-7 bg-slate-700 rounded-full flex items-center justify-center text-xs font-bold text-amber-400">
                            <?= strtoupper(substr($user_name, 0, 1)); ?>
                        </span>
                        <span class="hidden lg:inline text-xs"><?= htmlspecialchars($user_name); ?></span>
                        <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                    </button>
                    <ul class="cat-dropdown-menu absolute right-0 mt-1 w-48 bg-white text-slate-800 rounded-xl shadow-xl shadow-slate-900/10 border border-gray-100 z-50 py-1.5 overflow-hidden">
                        <li><a href="<?= $base_url; ?>/user/userprofile.php"
                               class="block px-4 py-2.5 hover:bg-amber-50 text-xs text-slate-600 transition-colors duration-150">
                               <i class="fa-solid fa-user mr-2 text-gray-400"></i> Profile
                            </a></li>
                        <li><hr class="border-gray-100 my-1"></li>
                        <li><a href="<?= $base_url; ?>/auth/logout.php"
                               class="block px-4 py-2.5 hover:bg-red-50 text-xs text-red-600 transition-colors duration-150">
                               <i class="fa-solid fa-right-from-bracket mr-2"></i> Logout
                            </a></li>
                    </ul>
                </div>
            </nav>
        </div>

        <!-- Mobile Search -->
        <form action="<?= $base_url; ?>/index.php" method="GET"
              class="md:hidden mt-3">
            <div class="relative w-full">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs pointer-events-none"></i>
                <input type="text" name="search"
                       value="<?= isset($search_query) ? htmlspecialchars($search_query) : ''; ?>"
                       placeholder="စာအုပ်အမည်၊ စာရေးဆရာ ရှာဖွေရန်..."
                       class="w-full pl-10 pr-10 py-2.5 bg-slate-800/80 text-white text-sm border border-slate-700/50 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500/50 transition-all duration-200 placeholder:text-slate-500">
                <button type="submit"
                        class="absolute right-1 top-1/2 -translate-y-1/2 bg-amber-500 text-slate-900 w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold hover:bg-amber-400 transition-colors duration-200">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <!-- Mobile Slide-Down Menu -->
        <div id="userMobileMenu" class="md:hidden mt-3 border-t border-slate-800 pt-3 pb-2">
            <nav class="user-header-nav flex flex-col gap-0.5 text-sm font-semibold">
                <a href="<?= $base_url; ?>/user/userdashboard.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('userdashboard'); ?>">
                    <i class="fa-solid fa-house text-xs w-5 text-center text-gray-500"></i> Home
                </a>
                <a href="<?= $base_url; ?>/user/books.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('books'); ?>">
                    <i class="fa-solid fa-book text-xs w-5 text-center text-gray-500"></i> Books
                </a>
                <a href="<?= $base_url; ?>/user/cart.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('cart'); ?>">
                    <i class="fa-solid fa-cart-shopping text-xs w-5 text-center text-gray-500"></i> Cart
                </a>
                <a href="<?= $base_url; ?>/user/myorders.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('myorders'); ?>">
                    <i class="fa-solid fa-box text-xs w-5 text-center text-gray-500"></i> My Orders
                </a>

                <!-- Mobile Categories (expandable) -->
                <div class="relative cat-dropdown">
                    <button class="cat-toggle w-full flex items-center justify-between px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 focus:outline-none"
                            type="button">
                        <span class="flex items-center gap-3">
                            <i class="fa-solid fa-layer-group text-xs w-5 text-center text-gray-500"></i> Categories
                        </span>
                        <i class="fas fa-chevron-down text-[8px] opacity-60"></i>
                    </button>
                    <ul class="cat-dropdown-menu bg-slate-800/50 rounded-xl mx-4 mt-1 mb-1 py-1 border border-slate-700/50 max-h-48 overflow-y-auto">
                        <li><a href="<?= $base_url; ?>/user/userdashboard.php"
                               class="block px-4 py-2.5 hover:bg-slate-700 text-xs font-semibold text-amber-400 transition-colors duration-150">အားလုံးကြည့်ရန်</a></li>
                        <li><hr class="border-slate-700/50 my-1"></li>
                        <?php if (!empty($categories)): ?>
                            <?php foreach ($categories as $cat): ?>
                                <li>
                                    <a href="<?= $base_url; ?>/user/userdashboard.php?category_id=<?= (int)$cat['id']; ?>"
                                       class="block px-4 py-2.5 hover:bg-slate-700 text-xs text-gray-300 truncate transition-colors duration-150">
                                        <?= htmlspecialchars($cat['category_name']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Profile -->
                <a href="<?= $base_url; ?>/user/userprofile.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 transition-colors duration-200 <?= nav_active('userprofile'); ?>">
                    <i class="fa-solid fa-user text-xs w-5 text-center text-gray-500"></i> Profile
                </a>

                <hr class="border-slate-800 my-1 mx-4">

                <a href="<?= $base_url; ?>/auth/logout.php"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-800 text-red-400 hover:text-red-300 transition-colors duration-200">
                    <i class="fa-solid fa-right-from-bracket text-xs w-5 text-center"></i> Logout
                </a>
            </nav>
        </div>
    </div>
</header>

<script>
(function () {
    'use strict';

    var btn  = document.getElementById('userHamburgerBtn');
    var menu = document.getElementById('userMobileMenu');
    var bar1 = document.getElementById('ubar1');
    var bar2 = document.getElementById('ubar2');
    var bar3 = document.getElementById('ubar3');

    if (btn && menu) {
        btn.addEventListener('click', function () {
            var open = menu.classList.toggle('open');
            btn.setAttribute('aria-expanded', open);
            bar1.style.transform = open ? 'translateY(5.5px) rotate(45deg)'  : '';
            bar2.style.opacity   = open ? '0'                               : '';
            bar3.style.transform = open ? 'translateY(-5.5px) rotate(-45deg) translateX(-2px)' : '';
            bar3.style.width     = open ? '1.25rem' : '';
        });
    }

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

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.cat-dropdown.open').forEach(function (dd) {
            if (!dd.contains(e.target)) dd.classList.remove('open');
        });
    });

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
                bar3.style.width     = '';
            }
        }, 100);
    });
})();
</script>
