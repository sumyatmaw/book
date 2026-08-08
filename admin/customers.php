<?php
/**
 * Customer Management Script
 * Displays customer listings with dynamic limit and pagination matching orders page layout.
 */

session_start();
require_once '../config/db.php';

// Authorization check: Ensure only Admin role can access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Retrieve flash messages from session
$message = $_SESSION['flash_message'] ?? "";
$error = $_SESSION['flash_error'] ?? "";
unset($_SESSION['flash_message'], $_SESSION['flash_error']);

// -------------------------------------------------------------------------
// DYNAMIC LIMIT & SMART PAGINATION SETUP FOR CUSTOMERS
// -------------------------------------------------------------------------
$allowed_limits = [10, 20, 30, 50, 100];
$limit = isset($_GET['limit']) && in_array(intval($_GET['limit']), $allowed_limits) ? intval($_GET['limit']) : 10;

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total customers count (Role: customer)
$total_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM Users WHERE role = 'customer'");
$total_stmt->execute();
$total_res = $total_stmt->get_result();
$totalCustomers = $total_res ? $total_res->fetch_assoc()['total'] : 0;
$total_stmt->close();

$total_pages = max(1, ceil($totalCustomers / $limit));
if ($page > $total_pages) $page = $total_pages;

// Calculate Showing entries boundaries
$showing_from = $totalCustomers > 0 ? $offset + 1 : 0;
$showing_to = min($offset + $limit, $totalCustomers);

// Fetch paginated customers
$sql = "SELECT id, name, email, phone, created_at 
        FROM Users 
        WHERE role = 'customer' 
        ORDER BY id DESC 
        LIMIT ? OFFSET ?";
$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Online Book Shop</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
</head>

<body class="bg-gray-300 font-sans antialiased text-slate-800 h-full overflow-hidden">

    <div class="flex h-screen overflow-hidden">

        <!-- Dynamic Sidebar Include -->
        <?php include '../auth/sidebar.php'; ?>

        <!-- WORKSPACE WRAPPER (Includes Navigation Bar and Main Content Area) -->
        <div class="flex-1 flex flex-col h-screen overflow-y-auto w-full">

            <!-- Dynamic Header Navigation Component Include -->
            <?php
            $page_title = "Customer Management";
            include '../auth/nav.php';
            ?>

            <!-- Main Content Canvas Area -->
            <main class="p-3 sm:p-5 md:p-8 space-y-6 max-w-[1600px] w-full mx-auto">

                <!-- Page Header -->
                <div class="mb-4 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-users text-indigo-600"></i> Customer Management
                    </h2>
                    <p class="text-xs text-slate-900 mt-1">ဝယ်ယူသူ Customer များ၏ အချက်အလက်များကို ကြည့်ရှုနိုင်ပါသည်။</p>
                </div>

                <!-- Success Flash Alert -->
                <?php if (!empty($message)): ?>
                    <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Error Flash Alert -->
                <?php if (!empty($error)): ?>
                    <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Customers Table Container -->
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto w-full no-scrollbar">
                        <table class="w-full text-left border-collapse min-w-[900px]">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-700 text-[11px] font-bold uppercase tracking-wider border-b border-slate-200">
                                    <th class="px-5 py-4">ID</th>
                                    <th class="px-5 py-4">Customer Name</th>
                                    <th class="px-5 py-4">Email Address</th>
                                    <th class="px-5 py-4">Phone Number</th>
                                    <th class="px-5 py-4">Joined Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-700">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <!-- ID -->
                                            <td class="px-5 py-4 font-bold text-indigo-600 font-mono">
                                                #<?= sprintf("%04d", $row['id']); ?>
                                            </td>

                                            <!-- Customer Name -->
                                            <td class="px-5 py-4 font-semibold text-slate-900">
                                                <?= htmlspecialchars($row['name']); ?>
                                            </td>

                                            <!-- Email -->
                                            <td class="px-5 py-4 text-slate-600">
                                                <i class="fa-regular fa-envelope text-slate-400 mr-1.5"></i>
                                                <?= htmlspecialchars($row['email'] ?? 'N/A'); ?>
                                            </td>

                                            <!-- Phone -->
                                            <td class="px-5 py-4 text-slate-600">
                                                <i class="fa-solid fa-phone text-slate-400 mr-1.5"></i>
                                                <?= htmlspecialchars($row['phone'] ?? 'N/A'); ?>
                                            </td>

                                            <!-- Date -->
                                            <td class="px-5 py-4 text-slate-500">
                                                <i class="fa-regular fa-calendar text-slate-400 mr-1.5"></i>
                                                <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="py-12 text-center text-slate-400 font-semibold">
                                            No customers found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- PAGINATION CONTROLS CONTAINER (Matching orders.php style) -->
                    <div class="px-4 sm:px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-center justify-between gap-4">
                        
                        <!-- SHOWING ENTRIES TEXT & DYNAMIC SELECT LIMIT DROPDOWN -->
                        <div class="flex items-center gap-2 text-xs text-slate-600 font-medium">
                            <span class="whitespace-nowrap">Showing</span>
                            <form method="GET" action="customer.php" class="inline-block">
                                <input type="hidden" name="page" value="1">
                                <select name="limit" onchange="this.form.submit()" class="bg-white border border-slate-300 text-slate-800 font-bold rounded-lg px-2 py-1 outline-none focus:border-indigo-500 cursor-pointer shadow-2xs">
                                    <?php foreach ($allowed_limits as $opt): ?>
                                        <option value="<?= $opt; ?>" <?= $limit == $opt ? 'selected' : ''; ?>><?= $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <span class="whitespace-nowrap">
                                (<?= $showing_from; ?>–<?= $showing_to; ?> of <?= $totalCustomers; ?> entries)
                            </span>
                        </div>

                        <!-- ADVANCED SMART PAGINATION LINKS -->
                        <?php if ($total_pages > 1): ?>
                            <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-1.5 text-xs">
                                
                                <!-- Previous Page Button -->
                                <?php if ($page > 1): ?>
                                    <a href="customer.php?page=<?= $page - 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1 shadow-2xs">
                                        <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                    </a>
                                <?php else: ?>
                                    <span class="px-2.5 sm:px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg font-bold text-slate-400 cursor-not-allowed flex items-center gap-1">
                                        <i class="fa-solid fa-chevron-left text-[10px]"></i> Prev
                                    </span>
                                <?php endif; ?>

                                <!-- Always display Page 1 -->
                                <?php if ($page == 1): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs">1</span>
                                <?php else: ?>
                                    <a href="customer.php?page=1&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">1</a>
                                <?php endif; ?>

                                <!-- Front Ellipsis -->
                                <?php if ($page > 3): ?>
                                    <span class="px-1.5 py-1 text-slate-400 font-bold select-none">...</span>
                                <?php endif; ?>

                                <!-- Middle Page Numbers -->
                                <?php 
                                $start = max(2, $page - 1);
                                $end = min($total_pages - 1, $page + 1);

                                for ($i = $start; $i <= $end; $i++): 
                                    if ($i == 1 || $i == $total_pages) continue;
                                ?>
                                    <?php if ($i == $page): ?>
                                        <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs"><?= $i; ?></span>
                                    <?php else: ?>
                                        <a href="customer.php?page=<?= $i; ?>&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?= $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Back Ellipsis -->
                                <?php if ($page < $total_pages - 2): ?>
                                    <span class="px-1.5 py-1 text-slate-400 font-bold select-none">...</span>
                                <?php endif; ?>

                                <!-- Always display Last Page -->
                                <?php if ($total_pages > 1): ?>
                                    <?php if ($page == $total_pages): ?>
                                        <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg font-bold text-white shadow-2xs"><?= $total_pages; ?></span>
                                    <?php else: ?>
                                        <a href="customer.php?page=<?= $total_pages; ?>&limit=<?= $limit; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition"><?= $total_pages; ?></a>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- Next Page Button -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="customer.php?page=<?= $page + 1; ?>&limit=<?= $limit; ?>" class="px-2.5 sm:px-3 py-1.5 bg-white border border-slate-200 rounded-lg font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition flex items-center gap-1 shadow-2xs">
                                        Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="px-2.5 sm:px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg font-bold text-slate-400 cursor-not-allowed flex items-center gap-1">
                                        Next <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                    </span>
                                <?php endif; ?>

                            </div>
                        <?php endif; ?>

                    </div>

                </div>

            </main>
        </div>
    </div>

    <!-- JavaScript Handlers for Sidebar and Dropdowns -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
            }
        }

        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');

            if (notiDropdown) notiDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden');
        }

        function toggleProfileDropdown(e) {
            e.stopPropagation();
            const profileDropdown = document.getElementById('profileDropdown');
            const notiDropdown = document.getElementById('notiDropdown');

            if (profileDropdown) profileDropdown.classList.toggle('hidden');
            if (notiDropdown) notiDropdown.classList.add('hidden');
        }

        window.addEventListener('click', function(e) {
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            const notiBtn = document.getElementById('notiBtn');
            const profileBtn = document.getElementById('profileBtn');

            if (notiDropdown && notiBtn && !notiDropdown.contains(e.target) && !notiBtn.contains(e.target)) {
                notiDropdown.classList.add('hidden');
            }
            if (profileDropdown && profileBtn && !profileDropdown.contains(e.target) && !profileBtn.contains(e.target)) {
                profileDropdown.classList.add('hidden');
            }
        });
    </script>
</body>

</html>