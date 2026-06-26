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

// UPDATE PAYMENT STATUS (e.g., Pending -> Approved / Rejected)
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
    }
}

// FETCH ALL PAYMENTS WITH ORDER NUMBER & CUSTOMER NAME
$sql = "SELECT Payment.*, Orders.order_number, Users.name as customer_name, payment_method.method_name 
        FROM Payment 
        LEFT JOIN Orders ON Payment.order_id = Orders.id 
        LEFT JOIN Users ON Orders.user_id = Users.id 
        LEFT JOIN payment_method ON Payment.payment_method_id = payment_method.id 
        ORDER BY Payment.id DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Manage Payments</h1>
        <div class="flex gap-3">
            <a href="dashboard.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Dashboard</a>
            <a href="orders.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Manage Orders</a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto mt-10 bg-white shadow-lg rounded-2xl p-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Payment Transactions</h2>

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
                        <th class="py-3 px-4 border">Payment ID</th>
                        <th class="py-3 px-4 border">Order Number</th>
                        <th class="py-3 px-4 border">Customer Name</th>
                        <th class="py-3 px-4 border">Method</th>
                        <th class="py-3 px-4 border">Amount</th>
                        <th class="py-3 px-4 border">Transaction Ref</th>
                        <th class="py-3 px-4 border">Slip</th>
                        <th class="py-3 px-4 border">Date</th>
                        <th class="py-3 px-4 border">Status</th>
                        <th class="py-3 px-4 border">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 text-center">
                                <td class="py-3 px-4 border font-medium"><?= $row['id']; ?></td>
                                
                                <td class="py-3 px-4 border text-blue-600 font-semibold">
                                    <a href="order_detail.php?id=<?= $row['order_id']; ?>" class="hover:underline">
                                        <?= htmlspecialchars($row['order_number'] ?? 'N/A'); ?>
                                    </a>
                                </td>
                                
                                <td class="py-3 px-4 border text-left">
                                    <?= htmlspecialchars($row['customer_name'] ?? 'Unknown User'); ?>
                                </td>

                                <td class="py-3 px-4 border font-medium text-gray-700">
                                    <?= htmlspecialchars($row['method_name'] ?? 'N/A'); ?>
                                </td>
                                
                                <td class="py-3 px-4 border font-bold text-gray-800">
                                    <?= number_format($row['amount'], 2); ?> MMK
                                </td>

                                <td class="py-3 px-4 border text-gray-600 font-mono">
                                    <?= htmlspecialchars($row['transaction_ref']); ?>
                                </td>

                                <td class="py-2 px-4 border text-center">
                                    <?php if (!empty($row['payment_slip'])): ?>
                                        <a href="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" target="_blank" class="inline-block">
                                            <img src="../uploads/<?= htmlspecialchars($row['payment_slip']); ?>" alt="Slip" class="w-10 h-14 object-cover rounded shadow border hover:scale-110 transition">
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 italic">No Slip</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="py-3 px-4 border text-gray-600 text-xs">
                                    <?= date('d M Y, h:i A', strtotime($row['payment_date'])); ?>
                                </td>
                                
                                <td class="py-3 px-4 border">
                                    <?php 
                                    $status = $row['status'];
                                    $badgeColor = "bg-gray-100 text-gray-800";
                                    if ($status == 'Pending') $badgeColor = "bg-yellow-100 text-yellow-800";
                                    elseif ($status == 'Approved' || $status == 'Completed') $badgeColor = "bg-green-100 text-green-800";
                                    elseif ($status == 'Rejected' || $status == 'Cancelled') $badgeColor = "bg-red-100 text-red-800";
                                    ?>
                                    <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badgeColor; ?>">
                                        <?= htmlspecialchars($status ?: 'Pending'); ?>
                                    </span>
                                </td>
                                
                                <td class="py-3 px-4 border">
                                    <form method="POST" action="" class="flex items-center justify-center gap-1">
                                        <input type="hidden" name="payment_id" value="<?= $row['id']; ?>">
                                        <select name="status" class="border border-gray-300 rounded px-1.5 py-1 bg-white text-xs focus:outline-none focus:ring-1 focus:ring-blue-500">
                                            <option value="Pending" <?= $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="Approved" <?= $status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="Rejected" <?= $status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                        <button type="submit" name="update_payment_status" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-xs font-medium transition">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="py-6 text-center text-gray-500">No payment records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>