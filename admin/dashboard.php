<?php
session_start();

 //if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  //   header("Location: ../auth/login.php");
   // exit();
//}


// Sample dashboard data (later replace with database)
$totalBooks = 120;
$totalCategories = 12;
$totalOrders = 45;
$totalPayments = 38;

$recentOrders = [
    ['id' => 1001, 'customer' => 'Su Su', 'total' => 25000, 'status' => 'Pending', 'date' => '2026-06-23'],
    ['id' => 1002, 'customer' => 'Aung Aung', 'total' => 40000, 'status' => 'Confirmed', 'date' => '2026-06-22'],
    ['id' => 1003, 'customer' => 'Mya Mya', 'total' => 18000, 'status' => 'Shipped', 'date' => '2026-06-22'],
];

$recentPayments = [
    ['payment_id' => 1, 'order_id' => 1001, 'method' => 'KBZ Pay', 'amount' => 25000, 'status' => 'Paid'],
    ['payment_id' => 2, 'order_id' => 1002, 'method' => 'Cash on Delivery', 'amount' => 40000, 'status' => 'Pending'],
    ['payment_id' => 3, 'order_id' => 1003, 'method' => 'Wave Pay', 'amount' => 18000, 'status' => 'Paid'],
];

$lowStockBooks = [
    ['title' => 'Atomic Habits', 'stock' => 2],
    ['title' => 'Rich Dad Poor Dad', 'stock' => 1],
    ['title' => 'Clean Code', 'stock' => 3],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white p-5 hidden md:block">
        <h1 class="text-2xl font-bold mb-8 text-center">Book Shop Admin</h1>

        <nav class="space-y-2">
            <a href=" admindashboard.php" class="block px-4 py-3 rounded-lg bg-blue-600 font-medium">Dashboard</a>
            <a href="categories.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 transition">Manage Categories</a>
            <a href="books.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 transition">Manage Books</a>
            <a href="orders.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 transition">View Orders</a>
            <a href="payments.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 transition">View Payments</a>
            <a href="customer.php" class="block px-4 py-3 rounded-lg hover:bg-slate-800 transition">View Customer</a>
            <a href="../auth/logout.php" class="block px-4 py-3 rounded-lg hover:bg-red-600 transition">Logout</a>
            
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-4 md:p-8">

        <!-- Top Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <h2 class="text-3xl font-bold text-slate-800">Admin Dashboard</h2>
                <p class="text-slate-500">Manage books, categories, orders and payments.</p>
            </div>

            <div class="bg-white px-2 py-1 rounded-lg shadow text-slate-700 font-medium">
             <a href="../auth/logout.php" class="block px-4 py-3 rounded-lg hover:bg-red-600 transition">Logout</a>
            </div>
        </div>

        <!-- Dashboard Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8">

            <a href="books.php" class="bg-white rounded-2xl shadow p-6 border-l-4 border-blue-500 hover:shadow-lg transition block">
                <p class="text-slate-500 text-sm">Total Books</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2"><?= $totalBooks ?></h3>
               
            </a>

            <a href="categories.php" class="bg-white rounded-2xl shadow p-6 border-l-4 border-green-500 hover:shadow-lg transition block">
                <p class="text-slate-500 text-sm">Total Categories</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2"><?= $totalCategories ?></h3>
            
            </a>

            <a href="orders.php" class="bg-white rounded-2xl shadow p-6 border-l-4 border-purple-500 hover:shadow-lg transition block">
                <p class="text-slate-500 text-sm">Total Orders</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2"><?= $totalOrders ?></h3>
               
            </a>

            <a href="payments.php" class="bg-white rounded-2xl shadow p-6 border-l-4 border-orange-500 hover:shadow-lg transition block">
                <p class="text-slate-500 text-sm">Total Payments</p>
                <h3 class="text-3xl font-bold text-slate-800 mt-2"><?= $totalPayments ?></h3>
                
            </a>

        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <!-- Recent Orders -->
            <div class="xl:col-span-2 bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-slate-800">Recent Orders</h3>
                    <a href="orders.php" class="text-blue-600 text-sm font-medium hover:underline">View All</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 text-sm">
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Customer</th>
                                <th class="p-3">Total</th>
                                <th class="p-3">Status</th>
                                <th class="p-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3 font-medium">#<?= $order['id'] ?></td>
                                <td class="p-3"><?= htmlspecialchars($order['customer']) ?></td>
                                <td class="p-3">MMK <?= number_format($order['total']) ?></td>
                                <td class="p-3">
                                    <?php
                                        $statusClass = 'bg-yellow-100 text-yellow-700';
                                        if ($order['status'] === 'Confirmed') $statusClass = 'bg-blue-100 text-blue-700';
                                        if ($order['status'] === 'Shipped') $statusClass = 'bg-green-100 text-green-700';
                                        if ($order['status'] === 'Completed') $statusClass = 'bg-emerald-100 text-emerald-700';
                                        if ($order['status'] === 'Cancelled') $statusClass = 'bg-red-100 text-red-700';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $statusClass ?>">
                                        <?= $order['status'] ?>
                                    </span>
                                </td>
                                <td class="p-3"><?= $order['date'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Low Stock Books -->
            <div class="bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-slate-800">Low Stock Books</h3>
                    <a href="books.php" class="text-blue-600 text-sm font-medium hover:underline">Manage</a>
                </div>

                <div class="space-y-4">
                    <?php foreach ($lowStockBooks as $book): ?>
                    <div class="flex items-center justify-between border-b pb-3">
                        <div>
                            <p class="font-medium text-slate-800"><?= htmlspecialchars($book['title']) ?></p>
                            <p class="text-sm text-slate-500">Remaining Stock</p>
                        </div>
                        <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-semibold">
                            <?= $book['stock'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Payments -->
            <div class="xl:col-span-3 bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-slate-800">Recent Payments</h3>
                    <a href="payments.php" class="text-blue-600 text-sm font-medium hover:underline">View All</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600 text-sm">
                                <th class="p-3">Payment ID</th>
                                <th class="p-3">Order ID</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Amount</th>
                                <th class="p-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayments as $payment): ?>
                            <tr class="border-b hover:bg-slate-50">
                                <td class="p-3">#<?= $payment['payment_id'] ?></td>
                                <td class="p-3">#<?= $payment['order_id'] ?></td>
                                <td class="p-3"><?= htmlspecialchars($payment['method']) ?></td>
                                <td class="p-3">MMK <?= number_format($payment['amount']) ?></td>
                                <td class="p-3">
                                    <?php
                                        $paymentClass = $payment['status'] === 'Paid'
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-yellow-100 text-yellow-700';
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium <?= $paymentClass ?>">
                                        <?= $payment['status'] ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>