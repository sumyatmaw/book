<?php
session_start();
require_once "../config/db.php";

// Admin Login Check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != "admin") {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

// Add Payment Method
if (isset($_POST['save'])) {

    $method_name    = trim($_POST['method_name']);
    $account_number = trim($_POST['account_number']);
    $account_holder = trim($_POST['account_holder']);
    $is_active      = $_POST['is_active'];
    $description    = trim($_POST['description']);

    if (
        empty($method_name) ||
        empty($account_number) ||
        empty($account_holder)
    ) {

        $error = "Please fill all required fields.";

    } else {

        $stmt = $conn->prepare("INSERT INTO payment_method(method_name,account_number,account_holder,is_active,description)
        VALUES(?,?,?,?,?)");

        $stmt->bind_param(
            "sssis",
            $method_name,
            $account_number,
            $account_holder,
            $is_active,
            $description
        );

        if($stmt->execute()){

            $message="Payment Method Added Successfully.";

        }else{

            $error="Insert Failed.";

        }

    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment Method - Online Book Shop</title>
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
                    <a href="categories.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-tags text-sm w-5 text-center text-slate-500"></i> Categories
                    </a>
                    <a href="books.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-book text-sm w-5 text-center text-slate-500"></i> Books
                    </a>
                    <a href="orders.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-shopping-bag text-sm w-5 text-center text-slate-500"></i> Orders
                    </a>
                    <a href="payments.php" class="sidebar-link active flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium transition-all duration-200">
                        <i class="fa-solid fa-credit-card text-sm w-5 text-center"></i> Payments
                    </a>
                    <a href="delivery.php" class="sidebar-link flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-slate-800/60 hover:text-white transition-all duration-200">
                        <i class="fa-solid fa-truck text-sm w-5 text-center text-slate-500"></i> Delivery
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
                <h1 class="text-lg font-black text-slate-900">Add Payment Method</h1>
            </div>

            <!-- Page header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-amber-500"></i> Add Payment Method
                    </h1>
                </div>
                <a href="payments.php" class="text-xs font-semibold text-amber-600 hover:text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg transition-colors w-fit">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Payments
                </a>
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

            <!-- Form card -->
            <div class="max-w-lg">
                <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                    <form method="POST" class="flex flex-col gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method Name *</label>
                            <input type="text" name="method_name" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="KBZ Pay / Wave Pay / CB Pay">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Account Number *</label>
                            <input type="text" name="account_number" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="09xxxxxxxxx">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Account Holder *</label>
                            <input type="text" name="account_holder" required
                                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Online Book Shop">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Status</label>
                            <select name="is_active" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Description</label>
                            <textarea name="description" rows="3"
                                      class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                      placeholder="Payment instructions or description"></textarea>
                        </div>
                        <button type="submit" name="save"
                                class="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 py-3 rounded-xl font-bold text-sm shadow-sm shadow-amber-500/20 transition flex items-center justify-center gap-2 mt-1">
                            <i class="fa-solid fa-save"></i> Save Payment Method
                        </button>
                    </form>
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