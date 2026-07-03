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
        #adminSidebar { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @media (max-width: 1023px) {
            #adminSidebar { transform: translateX(-100%); position: fixed; top: 0; left: 0; bottom: 0; z-index: 40; }
            #adminSidebar.open { transform: translateX(0); }
            #sidebarOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 35; }
            #sidebarOverlay.open { display: block; }
        }
        .sidebar-link.active { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .sidebar-link.active i { color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex flex-1">

        <!-- Sidebar -->
        <aside id="adminSidebar" class="w-64 bg-[#0a1128] text-gray-300 flex flex-col justify-between border-r border-slate-800 shrink-0 lg:relative lg:translate-x-0">
            <div class="p-4 space-y-2">
                <div class="px-4 py-3 mb-2">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500">Admin Panel</h2>
                </div>
                <nav class="space-y-0.5">
                    <a href="dashboard.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-chart-pie text-sm w-5 text-center"></i> Dashboard
                    </a>
                    <a href="categories.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200">
                        <i class="fa-solid fa-tags text-sm w-5 text-center"></i> Categories
                    </a>
                    <a href="books.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-book text-sm w-5 text-center text-slate-500"></i> Books
                    </a>
                    <a href="orders.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-shopping-bag text-sm w-5 text-center text-slate-500"></i> Orders
                    </a>
                    <a href="payments.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-credit-card text-sm w-5 text-center text-slate-500"></i> Payments
                    </a>
                    <a href="customer.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-users text-sm w-5 text-center text-slate-500"></i> Customers
                    </a>
                    <hr class="border-slate-800 my-2">
                    <a href="adminprofile.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-user-gear text-sm w-5 text-center text-slate-500"></i> My Profile
                    </a>
                    <a href="../index.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-store text-sm w-5 text-center text-slate-500"></i> View Store
                    </a>
                </nav>
            </div>
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center gap-3 px-3 py-2">
                    <span class="w-8 h-8 bg-amber-500 rounded-lg flex items-center justify-center text-xs font-bold text-slate-900">A</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold text-white truncate">Admin</p>
                        <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 min-w-0">

            <!-- Mobile sidebar toggle -->
            <div class="lg:hidden flex items-center gap-3 mb-4">
                <button onclick="toggleSidebar()" class="w-10 h-10 bg-white rounded-xl border border-gray-200 flex items-center justify-center text-gray-600 hover:bg-gray-50 transition shadow-sm">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 class="text-lg font-black text-slate-900">Categories</h1>
            </div>

        <!-- Page header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-tags text-amber-500"></i> Categories
                </h1>
                <p class="text-xs text-gray-400 mt-1"><?= $totalCategories; ?> categories in your store</p>
            </div>
            <span class="text-xs bg-amber-50 text-amber-700 px-3 py-1.5 rounded-full font-bold border border-amber-100 w-fit">
                <i class="fa-solid fa-layer-group mr-1"></i> <?= $totalCategories; ?> Total
            </span>
        </div>

        <!-- Flash messages -->
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

        <div class="flex flex-col lg:flex-row gap-6">

            <!-- Form card -->
            <div class="lg:w-1/3 shrink-0">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm sticky top-24">
                    <h2 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <span class="w-7 h-7 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                            <i class="fa-solid <?= $editCategory ? 'fa-pen-to-square' : 'fa-plus' ?> text-xs"></i>
                        </span>
                        <?= $editCategory ? 'Update Category' : 'Add New Category'; ?>
                    </h2>

                    <form action="categories.php" method="POST" class="flex flex-col gap-4">
                        <?php if ($editCategory): ?>
                            <input type="hidden" name="category_id" value="<?= $editCategory['id']; ?>">
                        <?php endif; ?>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 mb-1.5">Category Name</label>
                            <input type="text" name="category_name"
                                   value="<?= $editCategory ? htmlspecialchars($editCategory['category_name']) : ''; ?>"
                                   placeholder=""
                                   required
                                   class="w-full px-4 py-3 text-sm rounded-xl border border-gray-200 bg-gray-50 text-slate-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-400 transition-all duration-200">
                        </div>

                        <?php if ($editCategory): ?>
                            <div class="flex gap-2">
                                <button type="submit" name="update_category"
                                        class="flex-1 bg-amber-500 hover:bg-amber-400 text-slate-900 py-2.5 rounded-xl text-sm font-bold transition-all duration-200 shadow-sm shadow-amber-500/20">
                                    <i class="fa-solid fa-check mr-1"></i> Update
                                </button>
                                <a href="categories.php"
                                   class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-600 py-2.5 rounded-xl text-sm font-bold transition-all duration-200">
                                    Cancel
                                </a>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="add_category"
                                    class="bg-amber-500 hover:bg-amber-400 text-slate-900 py-3 rounded-xl text-sm font-bold transition-all duration-200 shadow-sm shadow-amber-500/20 mt-1">
                                <i class="fa-solid fa-plus mr-1"></i> Add Category
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Categories list -->
            <div class="lg:w-2/3">
                <!-- Desktop table -->
                <div class="hidden sm:block bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-list text-amber-500"></i> All Categories
                        </h3>
                        <span class="text-[11px] text-gray-400 font-medium"><?= $totalCategories; ?> entries</span>
                    </div>

                    <?php if (!empty($allCategories)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50/80 text-gray-400 uppercase text-[11px] tracking-wider">
                                    <tr>
                                        <th class="px-6 py-3 text-left font-semibold w-16">#</th>
                                        <th class="px-6 py-3 text-left font-semibold">Category Name</th>
                                        <th class="px-6 py-3 text-right font-semibold">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($allCategories as $category): ?>
                                        <tr class="border-t border-gray-50 hover:bg-gray-50/50 transition-colors duration-150 <?= $editCategory && $editCategory['id'] == $category['id'] ? 'bg-amber-50/50 ring-1 ring-inset ring-amber-200' : ''; ?>">
                                            <td class="px-6 py-3.5 text-gray-400 font-medium"><?= $no++; ?></td>
                                            <td class="px-6 py-3.5 font-bold text-slate-800"><?= htmlspecialchars($category['category_name']); ?></td>
                                            <td class="px-6 py-3.5 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="categories.php?edit_id=<?= $category['id']; ?>"
                                                       class="inline-flex items-center gap-1 text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors duration-200">
                                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i> Edit
                                                    </a>
                                                    <form action="categories.php" method="POST" onsubmit="return confirm('Delete this category?');" class="inline">
                                                        <input type="hidden" name="category_id" value="<?= $category['id']; ?>">
                                                        <button type="submit" name="delete_category"
                                                                class="inline-flex items-center gap-1 text-red-500 hover:text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg text-xs font-bold transition-colors duration-200">
                                                            <i class="fa-solid fa-trash-can text-[10px]"></i> Delete
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
                        <div class="py-16 text-center">
                            <i class="fa-solid fa-layer-group text-4xl text-gray-200 mb-3"></i>
                            <p class="text-gray-400 text-sm font-medium">No categories yet</p>
                            <p class="text-gray-300 text-xs mt-1">Add your first category using the form.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Mobile card list -->
                <div class="sm:hidden space-y-3">
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
                                        <form action="categories.php" method="POST" onsubmit="return confirm('Delete this category?');" class="inline">
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
                            <p class="text-gray-400 text-sm font-medium">No categories yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    </div>

    <?php include '../auth/footer.php'; ?>

    <script>
    function toggleSidebar() {
        document.getElementById('adminSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
    </script>
</body>
</html>
