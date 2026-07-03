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

// UPDATE ORDER STATUS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = trim($_POST['status']);

    if (!empty($status)) {
        $stmt = $conn->prepare("UPDATE Orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        if ($stmt->execute()) {
            $message = "Order status updated successfully!";
        } else {
            $error = "Failed to update order status!";
        }
        $stmt->close();
    }
}

// FETCH ALL ORDERS WITH CUSTOMER NAME
$sql = "SELECT Orders.*, Users.name as customer_name 
        FROM Orders 
        LEFT JOIN Users ON Orders.user_id = Users.id 
        ORDER BY Orders.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col font-sans text-slate-800">

    <?php include '../auth/header.php'; ?>

    <div class="max-w-7xl mx-auto mt-10 px-4 flex-1 w-full">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Customer Orders</h2>

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
                        <th class="py-3 px-4 border">Order ID</th>
                        <th class="py-3 px-4 border">Order Number</th>
                        <th class="py-3 px-4 border">Customer Name</th>
                        <th class="py-3 px-4 border">Total Amount</th>
                        <th class="py-3 px-4 border">Order Date</th>
                        <th class="py-3 px-4 border">Status</th>
                        <th class="py-3 px-4 border">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 text-center">
                                <td class="py-3 px-4 border font-medium"><?= $row['id']; ?></td>
                                
                                <td class="py-3 px-4 border text-blue-600 font-semibold">
                                    <?= htmlspecialchars($row['order_number'] ?? 'N/A'); ?>
                                </td>
                                
                                <td class="py-3 px-4 border text-left">
                                    <?= htmlspecialchars($row['customer_name'] ?? 'Unknown User'); ?>
                                </td>
                                
                                <td class="py-3 px-4 border font-bold text-gray-800">
                                    <?= number_format($row['total_amount'], 2); ?> MMK
                                </td>
                                
                                <td class="py-3 px-4 border text-gray-600">
                                    <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                                </td>
                                
                                <td class="py-3 px-4 border">
                                    <?php 
                                    $status = $row['status'];
                                    $badgeColor = "bg-gray-100 text-gray-800"; // default
                                    if ($status == 'Pending') $badgeColor = "bg-yellow-100 text-yellow-800";
                                    elseif ($status == 'Completed') $badgeColor = "bg-green-100 text-green-800";
                                    elseif ($status == 'Cancelled') $badgeColor = "bg-red-100 text-red-800";
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badgeColor; ?>">
                                        <?= htmlspecialchars($status ?: 'Pending'); ?>
                                    </span>
                                </td>
                                
                                <td class="py-3 px-4 border">
                                    <form method="POST" action="" class="flex items-center justify-center gap-2">
                                        <input type="hidden" name="order_id" value="<?= $row['id']; ?>">
                                        <select name="status" class="border border-gray-300 rounded px-2 py-1 bg-white text-sm focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            <option value="Pending" <?= $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Completed" <?= $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="Cancelled" <?= $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs font-medium transition">
                                            Update
                                        </button>
                                        <a href="orderdetail.php?id=<?= $row['id']; ?>" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-xs font-medium transition">
                                            View
                                        </a>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-6 text-center text-gray-500">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php include '../auth/footer.php'; ?>