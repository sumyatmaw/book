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
$admin_initial = strtoupper(substr($admin_name, 0, 1));

// Sync Profile Image from Database if not available in current session
if (!isset($_SESSION['user_image']) && isset($conn)) {
    $u_query = mysqli_query($conn, "SELECT profile_image FROM Users WHERE id = '$admin_id'");
    if ($u_query && $u_row = mysqli_fetch_assoc($u_query)) {
        $_SESSION['user_image'] = $u_row['profile_image'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Category
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name']);

        if (empty($category_name)) {
            $error = "Category name is required!";
        } else {
            $stmt = $conn->prepare("INSERT INTO Categories (category_name) VALUES (?)");
            $stmt->bind_param("s", $category_name);

            if ($stmt->execute()) {
                $message = "Category added successfully!";
            } else {
                $error = "Failed to add category!";
            }
            $stmt->close();
            header("Location: categories.php");
            exit();
        }
    }

    // Update Category
    if (isset($_POST['update_category'])) {
        $idToUpdate = (int)$_POST['category_id'];
        $category_name = trim($_POST['category_name']);

        if (empty($category_name)) {
            $error = "Category name is required!";
        } else {
            $update = $conn->prepare("UPDATE Categories SET category_name = ? WHERE id = ?");
            $update->bind_param("si", $category_name, $idToUpdate);

            if ($update->execute()) {
                header("Location: categories.php");
                exit();
            } else {
                $error = "Failed to update category!";
            }
            $update->close();
        }
    }

    // Delete Category
    if (isset($_POST['delete_category'])) {
        $idToDelete = (int)$_POST['category_id'];

        $stmt = $conn->prepare("DELETE FROM Categories WHERE id = ?");
        $stmt->bind_param("i", $idToDelete);
        $stmt->execute();
        $stmt->close();

        header("Location: categories.php");
        exit();
    }
}

// Fetch all categories
$result = $conn->query("SELECT * FROM Categories ORDER BY id DESC");
$allCategories = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$totalCategories = count($allCategories);

// Fetch Alert Badge Notifications
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM Books WHERE stock < 3");
$low_stock_count = mysqli_fetch_assoc($low_stock_query)['total'] ?? 0;

$pending_payments_query = mysqli_query($conn, "SELECT id, amount, status FROM Payment WHERE status = 'pending' ORDER BY id DESC LIMIT 3");
$pending_payments_count = mysqli_num_rows($pending_payments_query);

// Edit mode
$editCategory = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM Categories WHERE id = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $editCategory = $res->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Online Book Shop</title>
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
                    <i class="fa-solid fa-chart-pie w-5 text-slate-500"></i><span>Dashboard</span>
                </a>
                <a href="books.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-slate-800 hover:text-white rounded-xl font-medium transition">
                    <i class="fa-solid fa-book w-5 text-slate-500"></i><span>Manage Books</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 bg-indigo-600 text-white rounded-xl font-medium shadow-sm shadow-indigo-600/10">
                    <i class="fa-solid fa-tags w-5 text-indigo-200"></i><span>Categories</span>
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
                    <!-- CHANGED: Profile Button with Dynamic Image View Setup -->
                    <button onclick="toggleProfileDropdown(event)" id="profileBtn" class="w-8 h-8 rounded-full border border-slate-200 hover:border-indigo-500 flex items-center justify-center transition cursor-pointer overflow-hidden bg-slate-100">
                        <?php if (!empty($_SESSION['user_image'])): ?>
                            <img src="/onlinebookshop/uploads/profile/<?php echo $_SESSION['user_image']; ?>" 
                                 class="w-full h-full object-cover" 
                                 alt="Admin Profile">
                        <?php else: ?>
                            <i class="fa-solid fa-user text-sm text-slate-600"></i>
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
        <main class="flex-1 overflow-y-auto p-4 md:p-8 max-w-[1600px] w-full mx-auto">
            
            <!-- Page Header Status Block -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-tags text-indigo-600"></i> Categories
                    </h1>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalCategories; ?> categories discovered</p>
                </div>
                <span class="text-xs bg-indigo-50 text-indigo-700 px-3 py-1.5 rounded-full font-bold border border-indigo-100 w-fit">
                    <i class="fa-solid fa-layer-group mr-1"></i> <?= $totalCategories; ?> Total
                </span>
            </div>

            <!-- Flash Action Status Alerts -->
            <?php if (!empty($message)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="flex flex-col lg:flex-row gap-6 items-start">

                <!-- Category Manipulation Form Component -->
                <div class="w-full lg:w-96 bg-white p-5 rounded-2xl border border-slate-200/70 shadow-sm shrink-0 h-fit">
                    <div class="pb-3 border-b border-slate-100 mb-4">
                        <h3 class="font-bold text-slate-900 text-sm flex items-center">
                            <i class="fa-solid <?= $editCategory ? 'fa-pen-to-square text-amber-500' : 'fa-circle-plus text-indigo-500'; ?> mr-2"></i>
                            <?= $editCategory ? 'Update Category Details' : 'Add New Category'; ?>
                        </h3>
                    </div>

                    <form action="categories.php" method="POST" class="space-y-3.5">
                        <?php if ($editCategory): ?>
                            <input type="hidden" name="category_id" value="<?= $editCategory['id']; ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Category Name</label>
                            <input type="text" name="category_name"
                                   value="<?= $editCategory ? htmlspecialchars($editCategory['category_name']) : ''; ?>"
                                   required
                                   class="bg-slate-50/80 text-xs w-full rounded-xl p-2.5 border border-slate-200 outline-none focus:border-indigo-500 transition">
                        </div>

                        <div class="pt-2 flex gap-2">
                            <?php if ($editCategory): ?>
                                <button type="submit" name="update_category"
                                        class="flex-1 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold py-2.5 shadow-sm shadow-amber-500/10 transition cursor-pointer">
                                    Save Update
                                </button>
                                <a href="categories.php"
                                   class="flex-1 text-center bg-slate-100 text-slate-600 hover:bg-slate-200 rounded-xl text-xs font-bold py-2.5 transition">
                                    Cancel
                                </a>
                            <?php else: ?>
                                <button type="submit" name="add_category"
                                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs py-2.5 shadow-sm shadow-indigo-600/10 transition cursor-pointer">
                                    + Add Category
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Database Live Registry Table Layout -->
                <div class="w-full lg:flex-1 bg-white rounded-2xl border border-slate-200/60 shadow-sm overflow-hidden">
                    
                    <!-- Tablet and Desktop Table Canvas Interface -->
                    <div class="hidden sm:block">
                        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                            <h3 class="font-bold text-slate-900 flex items-center text-sm">
                                <i class="fa-solid fa-list text-indigo-500 mr-2"></i> Catalog Registry Stack
                            </h3>
                        </div>

                        <?php if (!empty($allCategories)): ?>
                            <div class="overflow-x-auto w-full no-scrollbar">
                                <table class="w-full text-left border-collapse min-w-[500px]">
                                    <thead>
                                        <tr class="bg-slate-50/70 text-slate-400 text-[11px] font-bold uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-6 py-3.5 w-16">#</th>
                                            <th class="px-6 py-3.5">Category Name</th>
                                            <th class="px-6 py-3.5 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700 font-medium">
                                        <?php $no = 1; foreach ($allCategories as $category): ?>
                                            <tr class="hover:bg-slate-50/40 transition <?= $editCategory && $editCategory['id'] == $category['id'] ? 'bg-amber-50/50' : ''; ?>">
                                                <td class="px-6 py-3.5 text-slate-400 font-medium"><?= $no++; ?></td>
                                                <td class="px-6 py-3.5 font-bold text-slate-900 text-sm"><?= htmlspecialchars($category['category_name']); ?></td>
                                                <td class="px-6 py-3.5 text-center">
                                                    <div class="flex items-center justify-center space-x-2.5">
                                                        <a href="categories.php?edit_id=<?= $category['id']; ?>"
                                                           class="inline-flex items-center justify-center px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 border border-amber-200/40 rounded-xl font-bold transition">
                                                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                        </a>
                                                        <form action="categories.php" method="POST" onsubmit="return confirm('Are you sure you want to completely remove this category item?');" class="inline">
                                                            <input type="hidden" name="category_id" value="<?= $category['id']; ?>">
                                                            <button type="submit" name="delete_category"
                                                                    class="inline-flex items-center justify-center px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/40 rounded-xl font-bold transition cursor-pointer">
                                                                <i class="fa-solid fa-trash-can mr-1"></i> Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="py-16 text-center text-slate-400 font-semibold">
                                <i class="fa-solid fa-layer-group text-4xl text-gray-200 mb-3"></i>
                                <p>No categories discovered.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mobile Adaptive Cards Screen Rendering Layout -->
                    <div class="sm:hidden p-4 space-y-3">
                        <?php if (!empty($allCategories)): ?>
                            <?php $no = 1; foreach ($allCategories as $category): ?>
                                <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm <?= $editCategory && $editCategory['id'] == $category['id'] ? 'ring-2 ring-amber-300 bg-amber-50/30' : ''; ?>">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-xs font-bold text-gray-400 shrink-0">
                                                <?= $no++; ?>
                                            </span>
                                            <span class="font-bold text-sm text-slate-800 truncate"><?= htmlspecialchars($category['category_name']); ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <a href="categories.php?edit_id=<?= $category['id']; ?>"
                                               class="w-8 h-8 bg-amber-50 hover:bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center transition-colors">
                                                <i class="fa-solid fa-pen-to-square text-xs"></i>
                                            </a>
                                            <form action="categories.php" method="POST" onsubmit="return confirm('Delete this category item?');" class="inline">
                                                <input type="hidden" name="category_id" value="<?= $category['id']; ?>">
                                                <button type="submit" name="delete_category"
                                                        class="w-8 h-8 bg-red-50 hover:bg-red-100 text-red-500 rounded-lg flex items-center justify-center transition-colors">
                                                    <i class="fa-solid fa-trash-can text-xs"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="bg-white p-12 rounded-xl border border-gray-100 shadow-sm text-center">
                                <i class="fa-solid fa-layer-group text-4xl text-gray-200 mb-3"></i>
                                <p class="text-gray-400 text-sm font-medium">No categories created yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // Sidebar Toggle
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
    }

    // Notifications Dropdown Toggle
    function toggleNotificationDropdown(e) {
        e.stopPropagation();
        document.getElementById('notiDropdown').classList.toggle('hidden');
        document.getElementById('profileDropdown').classList.add('hidden');
    }

    // Profile Menu Dropdown Toggle
    function toggleProfileDropdown(e) {
        e.stopPropagation();
        document.getElementById('profileDropdown').classList.toggle('hidden');
        document.getElementById('notiDropdown').classList.add('hidden');
    }

    // Window Dynamic Blur and Target Out-Click Dismissal
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