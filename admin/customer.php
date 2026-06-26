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

// OPTIONAL ACTION: DELETE CUSTOMER (အကောင့်ဖျက်သိမ်းခြင်း)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Safety check: Admin အကောင့်ကို မှားမဖျက်မိစေရန်
    $stmt = $conn->prepare("DELETE FROM Users WHERE id = ? AND role = 'customer'");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $message = "Customer account deleted successfully!";
    } else {
        $error = "Failed to delete customer account!";
    }
    $stmt->close();
    // URL ကို သန့်ရှင်းစေရန် မူလ page သို့ ပြန်ပို့ခြင်း
    header("Refresh: 2; URL=customer.php");
}

// FETCH ALL CUSTOMERS
$sql = "SELECT id, name, email, phone, address, created_at 
        FROM Users 
        WHERE role = 'customer' 
        ORDER BY id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Manage Customers</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition">Dashboard</a>
            <a href="orders.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition">Orders</a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Registered Customers</h2>

        <?php if (!empty($message)): ?>
            <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-700 border border-green-300">
                <?= $message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 rounded-lg bg-red-100 text-red-700 border border-red-300">
                <?= $error; ?>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-lg overflow-hidden text-sm">
                <thead class="bg-blue-100 text-gray-700">
                    <tr>
                        <th class="py-3 px-4 border w-16">ID</th>
                        <th class="py-3 px-4 border text-left">Customer Name</th>
                        <th class="py-3 px-4 border text-left">Email Address</th>
                        <th class="py-3 px-4 border">Phone Number</th>
                        <th class="py-3 px-4 border text-left">Shipping Address</th>
                        <th class="py-3 px-4 border">Joined Date</th>
                        <th class="py-3 px-4 border w-24">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 text-center align-top">
                                <td class="py-3 px-4 border font-medium text-gray-600"><?= $row['id']; ?></td>
                                
                                <td class="py-3 px-4 border text-left font-semibold text-gray-800">
                                    <?= htmlspecialchars($row['name']); ?>
                                </td>
                                
                                <td class="py-3 px-4 border text-left font-mono text-gray-600">
                                    <?= htmlspecialchars($row['email']); ?>
                                </td>

                                <td class="py-3 px-4 border text-gray-700">
                                    <?= htmlspecialchars($row['phone'] ?: 'N/A'); ?>
                                </td>
                                
                                <td class="py-3 px-4 border text-left text-gray-600 max-w-xs whitespace-pre-line">
                                    <?= htmlspecialchars($row['address'] ?: 'No address provided'); ?>
                                </td>
                                
                                <td class="py-3 px-4 border text-gray-500 text-xs">
                                    <?= date('d M Y', strtotime($row['created_at'])); ?>
                                </value>
                                
                                <td class="py-3 px-4 border">
                                    <a href="customer.php?delete_id=<?= $row['id']; ?>" 
                                       onclick="return confirm('Are you sure you want to delete this customer account? This action cannot be undone.')" 
                                       class="inline-block bg-red-500 hover:bg-red-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium transition shadow-sm">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500 italic">No customers found in the system.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>