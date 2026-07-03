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

// Update payment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_status'])) {
    $payment_id = intval($_POST['payment_id']);
    $status = trim($_POST['status']);

    if (!empty($status)) {
        $stmt = $conn->prepare("UPDATE Payment SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $payment_id);
        if ($stmt->execute()) {
            $message = "Payment status updated successfully!";
        } else {
            $error = "Failed to update payment status!";
        }
        $stmt->close();
        header("Location: payments.php");
        exit();
    }
}

// Fetch all payments (Fix: Removed non-existent payment_method table join)
$sql = "SELECT Payment.*, Orders.order_number, Users.name as customer_name
        FROM Payment
        LEFT JOIN Orders ON Payment.order_id = Orders.id
        LEFT JOIN Users ON Orders.user_id = Users.id
        ORDER BY Payment.id DESC";
$result = $conn->query($sql);
$totalPayments = $result ? $result->num_rows : 0;

// Count by status
$pending = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='pending'")->fetch_assoc()['t'] ?? 0;
$paid = $conn->query("SELECT COUNT(*) as t FROM Payment WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
$totalAmount = $conn->query("SELECT SUM(amount) as t FROM Payment WHERE status='paid'")->fetch_assoc()['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Online Book Shop</title>
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
                <h1 class="text-lg font-black text-slate-900">Payments</h1>
            </div>

            <!-- Page header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-amber-500"></i> Payments
                    </h1>
                    <p class="text-xs text-gray-400 mt-1"><?= $totalPayments; ?> payment records</p>
                </div>
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

            <!-- Stats cards -->
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 mb-6">
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-clock text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $pending; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Pending</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-check-circle text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-black text-gray-900"><?= $paid; ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Paid</p>
                </div>
                <div class="bg-white p-4 sm:p-5 rounded-2xl border border-gray-100 shadow-sm col-span-2 lg:col-span-1">
                    <div class="flex items-center justify-between mb-3">
                        <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center">
                            <i class="fa-solid fa-wallet text-sm"></i>
                        </div>
                    </div>
                    <h3 class="text-lg sm:text-xl font-black text-gray-900">MMK <?= number_format($totalAmount); ?></h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Total Collected</p>
                </div>
            </div>

            <!-- Desktop table -->
            <div class="hidden lg:block bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-receipt text-amber-500"></i> All Transactions
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80 text-gray-400 uppercase text-[11px] tracking-wider">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">ID</th>
                                <th class="px-5 py-3 text-left font-semibold">Order</th>
                                <th class="px-5 py-3 text-left font-semibold">Customer</th>
                                <th class="px-5 py-3 text-left font-semibold">Method ID</th>
                                <th class="px-5 py-3 text-left font-semibold">Amount</th>
                                <th class="px-5 py-3 text-left font-semibold">Ref</th>
                                <th class="px-5 py-3 text-left font-semibold">Slip</th>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                                <th class="px-5 py-3 text-left font-semibold">Status</th>
                                <th class="px-5 py-3 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()):
                                    $st = $row['status'];
                                    $st_class = match(strtolower($st)) {
                                        'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                        default => 'bg-amber-50 text-amber-700 border-amber-200'
                                    };
                                ?>
                                <tr class="border-t border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="px-5 py-3 text-gray-400 font-medium">#<?= $row['id']; ?></td>
                                    <td class="px-5 py-3">
                                        <a href="orderdetail.php?id=<?= $row['order_id']; ?>" class="font-bold text-amber-600 hover:text-amber-700">
                                            #<?= htmlspecialchars($row['order_number'] ?? $row['order_id']); ?>
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></td>
                                    <td class="px-5 py-3 text-gray-600">Method #<?= htmlspecialchars($row['payment_method_id'] ?? 'N/A'); ?></td>
                                    <td class="px-5 py-3 font-black text-gray-900">MMK <?= number_format($row['amount']); ?></td>
                                    <td class="px-5 py-3 font-mono text-xs text-gray-500 max-w-[120px] truncate"><?= htmlspecialchars($row['transaction_ref'] ?? '—'); ?></td>
                                    <td class="px-5 py-3">
                                        <?php if (!empty($row['payment_slip'])): ?>
                                            <a href="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="text-amber-500 hover:text-amber-600 text-xs font-medium inline-flex items-center gap-1">
                                                <i class="fa-solid fa-image"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap"><?= date('M d, Y H:i', strtotime($row['payment_date'])); ?></td>
                                    <td class="px-5 py-3">
                                        <span class="px-2 py-0.5 text-[11px] font-semibold rounded-full border <?= $st_class; ?>"><?= ucfirst($st); ?></span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="" class="flex items-center justify-end gap-1.5">
                                            <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                            <select name="status" class="border border-gray-200 rounded-lg px-2.5 py-1.5 bg-gray-50 text-xs focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-400 transition-all">
                                                <option value="pending" <?= strtolower($st) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="paid" <?= strtolower($st) === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                <option value="rejected" <?= strtolower($st) === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <button type="submit" name="update_payment_status"
                                                    class="bg-amber-500 hover:bg-amber-400 text-slate-900 px-3 py-1.5 rounded-lg text-xs font-bold transition-all duration-200">
                                                <i class="fa-solid fa-check text-[10px]"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="py-12 text-center text-gray-400 text-xs">No payment records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile card list -->
            <div class="lg:hidden space-y-3">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php
                    // Reset pointer for mobile rendering
                    $result->data_seek(0);
                    while ($row = $result->fetch_assoc()):
                        $st = $row['status'];
                        $st_class = match(strtolower($st)) {
                            'paid' => 'bg-emerald-50 text-emerald-700',
                            'rejected' => 'bg-red-50 text-red-700',
                            default => 'bg-amber-50 text-amber-700'
                        };
                    ?>
                    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <a href="orderdetail.php?id=<?= $row['order_id']; ?>" class="font-bold text-amber-600 text-sm">
                                    #<?= htmlspecialchars($row['order_number'] ?? $row['order_id']); ?>
                                </a>
                                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></p>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-bold rounded-full <?= $st_class; ?> shrink-0"><?= ucfirst($st); ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                            <div>
                                <span class="text-gray-400">Amount</span>
                                <p class="font-black text-gray-900">MMK <?= number_format($row['amount']); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Method ID</span>
                                <p class="font-medium text-gray-700">#<?= htmlspecialchars($row['payment_method_id'] ?? 'N/A'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Ref</span>
                                <p class="font-mono text-gray-600 truncate"><?= htmlspecialchars($row['transaction_ref'] ?? '—'); ?></p>
                            </div>
                            <div>
                                <span class="text-gray-400">Date</span>
                                <p class="text-gray-600"><?= date('M d, H:i', strtotime($row['payment_date'])); ?></p>
                            </div>
                        </div>
                        <!-- Slip + Action -->
                        <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                            <?php if (!empty($row['payment_slip'])): ?>
                                <a href="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="text-amber-500 hover:text-amber-600 text-xs font-medium inline-flex items-center gap-1">
                                    <i class="fa-solid fa-image"></i> View Slip
                                </a>
                            <?php else: ?>
                                <span class="text-gray-300 text-xs">No slip</span>
                            <?php endif; ?>
                            <form method="POST" action="" class="flex items-center gap-1.5">
                                <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                <select name="status" class="border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50 text-[11px] focus:outline-none focus:ring-2 focus:ring-amber-500/40">
                                    <option value="pending" <?= strtolower($st) === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?= strtolower($st) === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="rejected" <?= strtolower($st) === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                                <button type="submit" name="update_payment_status"
                                        class="bg-amber-500 hover:bg-amber-400 text-slate-900 px-2.5 py-1.5 rounded-lg text-[11px] font-bold transition-all">
                                    <i class="fa-solid fa-check text-[9px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-12 rounded-xl border border-gray-100 shadow-sm text-center">
                        <i class="fa-solid fa-receipt text-4xl text-gray-200 mb-3"></i>
                        <p class="text-gray-400 text-sm font-medium">No payment records found.</p>
                    </div>
                <?php endif; ?>
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