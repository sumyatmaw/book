<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";
$admin_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$admin_name = $_SESSION['user_name'] ?? 'Admin User';
$admin_email = $_SESSION['user_email'] ?? 'admin@bookshop.com';

// Fetch current admin profile image from session or database
$admin_image = $_SESSION['user_image'] ?? '';
if (empty($admin_image)) {
    $admin_query = mysqli_query($conn, "SELECT image FROM Users WHERE id = $admin_id");
    if ($admin_query && mysqli_num_rows($admin_query) > 0) {
        $admin_row = mysqli_fetch_assoc($admin_query);
        $admin_image = $admin_row['image'] ?? '';
    }
}
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
    header("Refresh: 2; URL=customers.php");
}

// -------------------------------------------------------------------------
// PAGINATION SETUP FOR CUSTOMERS
// -------------------------------------------------------------------------
$limit = 10; // Number of items per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Calculate total customer count
$total_result = $conn->query("SELECT COUNT(*) AS total FROM Users WHERE role = 'customer'");
$totalCustomers = $total_result ? $total_result->fetch_assoc()['total'] : 0;
$total_pages = ceil($totalCustomers / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch paginated customer records
$sql = "SELECT id, name, email, phone, address, created_at 
        FROM Users 
        WHERE role = 'customer' 
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
$stmt_page = $conn->prepare($sql);
$stmt_page->bind_param("ii", $limit, $offset);
$stmt_page->execute();
$result = $stmt_page->get_result();

// Fetch Live Alert Badge & Dropdown Notifications
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

    <div class="flex h-screen overflow-hidden">
        <?php include '../auth/sidebar.php'; ?>

        <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 hidden transition-opacity duration-300"></div>

        <div class="flex-1 flex flex-col overflow-hidden w-full">

            <!-- Dynamic Header Navigation Component Include -->
            <?php
            $page_title = "Customers Management";
            include '../auth/nav.php';
            ?>

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
                            <thead class="bg-white text-slate-700 uppercase text-[11px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-5 py-3 text-left font-semibold w-16">No</th>
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
                                    <?php
                                    // Set sequential number for current page
                                    $no = $offset + 1;
                                    while ($row = $result->fetch_assoc()):
                                    ?>
                                        <tr class="hover:bg-slate-50/40 transition align-top">
                                            <td class="px-5 py-4 font-bold text-gray-500"><?= $no++; ?></td>
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
                                    <tr>
                                        <td colspan="7" class="py-12 text-center text-gray-400 font-semibold">No customers found in the system.</td>
                                    </tr>
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
                        $m_no = $offset + 1;
                        while ($row = $result->fetch_assoc()):
                        ?>
                            <div class="bg-white p-4 rounded-xl border border-slate-200/60 shadow-sm">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-indigo-600">#<?= $m_no++; ?></span>
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

                <!-- PAGINATION CONTROLS CONTAINER -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white rounded-2xl border border-slate-200/60 p-4 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm">
                        <p class="text-xs text-slate-500 font-medium text-center sm:text-left">
                            Showing <span class="font-bold text-slate-700"><?= min($offset + 1, $totalCustomers); ?></span> to <span class="font-bold text-slate-700"><?= min($offset + $limit, $totalCustomers); ?></span> of <span class="font-bold text-slate-700"><?= $totalCustomers; ?></span> entries
                        </p>
                        <div class="flex items-center space-x-1">
                            <!-- Previous Page Button -->
                            <?php if ($page > 1): ?>
                                <a href="customers.php?page=<?= $page - 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                                </span>
                            <?php endif; ?>

                            <!-- Page Numbers Loop -->
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="px-3 py-1.5 bg-indigo-600 border border-indigo-600 rounded-lg text-xs font-bold text-white shadow-xs">
                                        <?= $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="customers.php?page=<?= $i; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                        <?= $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="customers.php?page=<?= $page + 1; ?>" class="px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </a>
                            <?php else: ?>
                                <span class="px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-400 cursor-not-allowed">
                                    Next <i class="fa-solid fa-chevron-right ml-1"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (overlay) overlay.classList.toggle('hidden');
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