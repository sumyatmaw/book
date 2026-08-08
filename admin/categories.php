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
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Single Scrollbar Styles */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile Sidebar positioning */
        @media (max-width: 767px) {
            #sidebar {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 50;
            }
        }
    </style>
</head>

<body class="min-h-full bg-slate-100 font-sans antialiased text-slate-800">

    <div class="flex min-h-screen w-full">

        <!-- ================= DYNAMIC SIDEBAR INCLUDE ================= -->
        <?php include '../auth/sidebar.php'; ?>

        <!-- MAIN CONTENT AREA (Single Scroll Environment) -->
        <div class="flex-1 flex flex-col min-w-0 w-full">

            <!-- Dynamic Header Navigation Component -->
            <header class="w-full shrink-0 z-10">
                <?php 
                    $page_title = "Categories Management";
                    include '../auth/nav.php'; 
                ?>
            </header>

            <!-- MAIN CANVAS -->
            <main class="flex-1 p-4 md:p-8 max-w-[1600px] w-full mx-auto">

                <!-- Page Header Status Block -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                            <i class="fa-solid fa-tags text-indigo-600"></i> Categories
                        </h1>
                        <p class="text-xs text-gray-900 mt-1"><?= $totalCategories; ?> categories discovered</p>
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
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Category Name</label>
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

                        <?php if (!empty($allCategories)): ?>
                            <div class="overflow-x-auto w-full">
                                <table class="w-full text-left border-collapse min-w-[500px]">
                                    <thead>
                                        <tr class="bg-slate-50 text-slate-900 text-[11px] font-bold uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-6 py-3.5 w-16">No</th>
                                            <th class="px-6 py-3.5">Category Name</th>
                                            <th class="px-6 py-3.5 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-xs text-slate-900 font-medium">
                                        <?php $no = 1;
                                        foreach ($allCategories as $category): ?>
                                            <tr class="bg-white transition hover:bg-slate-50/80 <?= $editCategory && $editCategory['id'] == $category['id'] ? 'bg-amber-50/50' : ''; ?>">
                                                <td class="px-6 py-3.5 text-slate-900 font-medium"><?= $no++; ?></td>
                                                <td class="px-6 py-3.5 font-bold text-slate-900 text-sm"><?= htmlspecialchars($category['category_name']); ?></td>
                                                <td class="px-6 py-3.5 text-center">
                                                    <div class="flex items-center justify-center space-x-2.5">
                                                        <a href="categories.php?edit_id=<?= $category['id']; ?>"
                                                            class="inline-flex items-center justify-center px-2.5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white border border-blue-200/40 rounded-xl font-bold transition">
                                                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                                        </a>
                                                        <form action="categories.php" method="POST" onsubmit="return confirm('Are you sure you want to completely remove this category item?');" class="inline">
                                                            <input type="hidden" name="category_id" value="<?= $category['id']; ?>">
                                                            <button type="submit" name="delete_category"
                                                                class="inline-flex items-center justify-center px-2.5 py-1.5 bg-red-500 hover:bg-red-600 text-white border border-red-200/40 rounded-xl font-bold transition cursor-pointer">
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
                            <div class="py-16 text-center text-slate-900 font-semibold">
                                <i class="fa-solid fa-layer-group text-4xl text-gray-200 mb-3"></i>
                                <p>No categories discovered.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Mobile Adaptive Cards Screen Rendering Layout -->
                        <div class="sm:hidden p-4 space-y-3">
                            <?php if (!empty($allCategories)): ?>
                                <?php $no = 1;
                                foreach ($allCategories as $category): ?>
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
                                    <p class="text-slate-900 text-sm font-medium">No categories created yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- UI Scripts -->
    <script>
        // Sidebar Toggle for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('-translate-x-full');
            }
        }

        // Notifications Dropdown Toggle
        function toggleNotificationDropdown(e) {
            e.stopPropagation();
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            if (notiDropdown) notiDropdown.classList.toggle('hidden');
            if (profileDropdown) profileDropdown.classList.add('hidden');
        }

        // Profile Menu Dropdown Toggle
        function toggleProfileDropdown(e) {
            e.stopPropagation();
            const profileDropdown = document.getElementById('profileDropdown');
            const notiDropdown = document.getElementById('notiDropdown');
            if (profileDropdown) profileDropdown.classList.toggle('hidden');
            if (notiDropdown) notiDropdown.classList.add('hidden');
        }

        // Window Dismissal
        window.addEventListener('click', function(e) {
            const notiDropdown = document.getElementById('notiDropdown');
            const profileDropdown = document.getElementById('profileDropdown');
            const notiBtn = document.getElementById('notiBtn');
            const profileBtn = document.getElementById('profileBtn');

            if (notiDropdown && !notiDropdown.contains(e.target) && (!notiBtn || !notiBtn.contains(e.target))) {
                notiDropdown.classList.add('hidden');
            }
            if (profileDropdown && !profileDropdown.contains(e.target) && (!profileBtn || !profileBtn.contains(e.target))) {
                profileDropdown.classList.add('hidden');
            }
        });
    </script>

</body>

</html>