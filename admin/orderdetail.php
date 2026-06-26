<?php
session_start();
require_once '../config/db.php';

// Admin login check
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Check if Order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);

// 1. FETCH ORDER & CUSTOMER DETAILS
$order_sql = "SELECT Orders.*, Users.name as customer_name, Users.email, Users.phone, Users.address 
              FROM Orders 
              LEFT JOIN Users ON Orders.user_id = Users.id 
              WHERE Orders.id = ?";
$stmt = $conn->prepare($order_sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: orders.php");
    exit();
}
$order = $order_result->fetch_assoc();
$stmt->close();

// 2. FETCH PAYMENT DETAILS (IF EXISTS)
$payment_sql = "SELECT Payment.*, payment_method.method_name 
                FROM Payment 
                LEFT JOIN payment_method ON Payment.payment_method_id = payment_method.id 
                WHERE Payment.order_id = ? LIMIT 1";
$p_stmt = $conn->prepare($payment_sql);
$p_stmt->bind_param("i", $order_id);
$p_stmt->execute();
$payment_result = $p_stmt->get_result();
$payment = $payment_result->fetch_assoc();
$p_stmt->close();

// 3. FETCH ORDERED ITEMS (BOOKS)
$items_sql = "SELECT Order_item.*, Books.title, Books.book_image 
              FROM Order_item 
              LEFT JOIN Books ON Order_item.book_id = Books.id 
              WHERE Order_item.order_id = ?";
unset($items_stmt);
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?= $order['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="bg-blue-600 text-white px-8 py-5 flex justify-between items-center shadow-md">
        <h1 class="text-2xl font-bold">Order Details</h1>
        <a href="orders.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition">
            &larr; Back to Orders
        </a>
    </div>

    <div class="max-w-6xl mx-auto mt-10 p-4 md:p-8 space-y-8">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            
            <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Customer Information</h2>
                <div class="space-y-2 text-sm text-gray-600">
                    <p><strong class="text-gray-800">Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Unknown User'); ?></p>
                    <p><strong class="text-gray-800">Email:</strong> <?= htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                    <p><strong class="text-gray-800">Phone:</strong> <?= htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                    <p><strong class="text-gray-800">Address:</strong> <?= nl2br(htmlspecialchars($order['address'] ?? 'N/A')); ?></p>
                </div>
            </div>

            <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Order Summary</h2>
                <div class="space-y-2 text-sm text-gray-600">
                    <p><strong class="text-gray-800">Order ID:</strong> <?= $order['id']; ?></p>
                    <p><strong class="text-gray-800">Order Number:</strong> <span class="text-blue-600 font-semibold"><?= htmlspecialchars($order['order_number'] ?? 'N/A'); ?></span></p>
                    <p><strong class="text-gray-800">Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                    <p><strong class="text-gray-800">Order Status:</strong> 
                        <?php 
                        $status = $order['status'];
                        $badge = "bg-gray-100 text-gray-800";
                        if ($status == 'Pending') $badge = "bg-yellow-100 text-yellow-800";
                        elseif ($status == 'Completed') $badge = "bg-green-100 text-green-800";
                        elseif ($status == 'Cancelled') $badge = "bg-red-100 text-red-800";
                        ?>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge; ?>"><?= $status ?: 'Pending'; ?></span>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Items Ordered</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-center border-collapse">
                    <thead class="bg-gray-50 text-gray-700 font-medium">
                        <tr>
                            <th class="py-3 px-4 border-b text-left">Book</th>
                            <th class="py-3 px-4 border-b">Price</th>
                            <th class="py-3 px-4 border-b">Quantity</th>
                            <th class="py-3 px-4 border-b text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $calculated_total = 0;
                        if ($items_result && $items_result->num_rows > 0): 
                            while ($item = $items_result->fetch_assoc()): 
                                $subtotal = $item['price'] * $item['quantity'];
                                $calculated_total += $subtotal;
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-4 px-4 border-b text-left flex items-center gap-3">
                                    <?php if (!empty($item['book_image'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($item['book_image']); ?>" class="w-12 h-16 object-cover rounded shadow-sm border">
                                    <?php endif; ?>
                                    <span class="font-medium text-gray-800"><?= htmlspecialchars($item['title']); ?></span>
                                </td>
                                <td class="py-4 px-4 border-b text-gray-600"><?= number_format($item['price'], 2); ?> MMK</td>
                                <td class="py-4 px-4 border-b font-medium text-gray-800"><?= $item['quantity']; ?></td>
                                <td class="py-4 px-4 border-b text-right font-semibold text-gray-800"><?= number_format($subtotal, 2); ?> MMK</td>
                            </tr>
                        <?php 
                            endwhile; 
                        endif; 
                        ?>
                        <tr class="bg-gray-50">
                            <td colspan="3" class="py-4 px-4 text-right font-bold text-gray-700">Total Amount:</td>
                            <td class="py-4 px-4 text-right font-bold text-xl text-blue-600"><?= number_format($order['total_amount'], 2); ?> MMK</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
            <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Payment Information</h2>
            <?php if ($payment): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                    <div class="space-y-2">
                        <p><strong class="text-gray-800">Payment Method:</strong> <?= htmlspecialchars($payment['method_name'] ?? 'N/A'); ?></p>
                        <p><strong class="text-gray-800">Amount Paid:</strong> <?= number_format($payment['amount'], 2); ?> MMK</p>
                        <p><strong class="text-gray-800">Transaction Ref:</strong> <?= htmlspecialchars($payment['transaction_ref']); ?></p>
                        <p><strong class="text-gray-800">Payment Date:</strong> <?= date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></p>
                        <p><strong class="text-gray-800">Payment Status:</strong> 
                            <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800"><?= htmlspecialchars($payment['status']); ?></span>
                        </p>
                    </div>
                    <div class="flex flex-col items-start md:items-center justify-center">
                        <span class="block font-medium text-gray-800 mb-2">Payment Slip / Screenshot</span>
                        <?php if (!empty($payment['payment_slip'])): ?>
                            <a href="../uploads/<?= htmlspecialchars($payment['payment_slip']); ?>" target="_blank">
                                <img src="../uploads/<?= htmlspecialchars($payment['payment_slip']); ?>" alt="Payment Slip" class="w-32 h-44 object-cover rounded-lg border shadow hover:scale-105 transition duration-200">
                            </a>
                            <span class="text-xs text-gray-400 mt-1">(Click image to view large)</span>
                        <?php else: ?>
                            <span class="text-gray-400 italic text-sm">No slip uploaded</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic text-sm py-2">No payment record found for this order yet.</p>
            <?php endif; ?>
        </div>

    </div>

</body>
</html>