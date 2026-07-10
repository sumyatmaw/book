<?php
session_start();
require_once '../config/db.php';

// Check if customer is properly logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php");
    exit();
}

// Strictly fetch only the current logged-in customer's ID
$user_id = intval($_SESSION['user_id']);
$base_url = '/onlinebookshop';

// Fetch orders with associated payment and delivery status specific to this user
$orders_query = "SELECT Orders.*, 
                        Payment.status AS payment_status, 
                        Payment.transaction_ref,
                        Delivery.delivery_status,
                        Delivery.receiver_name
                 FROM Orders 
                 LEFT JOIN Payment ON Orders.id = Payment.order_id 
                 LEFT JOIN Delivery ON Payment.id = Delivery.payment_id
                 WHERE Orders.user_id = ? 
                 ORDER BY Orders.created_at DESC";
                 
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$currentPage = 'myorders';
$categories = [];
$cat_result = $conn->query("SELECT * FROM Categories ORDER BY category_name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <?php include '../auth/header.php'; ?>

    <div class="flex-1 container mx-auto px-4 sm:px-6 py-8">

        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
            <div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-box text-amber-500"></i> My Orders
                </h1>
                <p class="text-xs text-gray-500 mt-1">Your order history and tracking</p>
            </div>
            <a href="<?= $base_url; ?>/books.php" class="text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-xl transition self-start sm:self-center">
                <i class="fa-solid fa-shopping-bag mr-1"></i> Browse Books
            </a>
        </div>

        <?php if ($result && $result->num_rows > 0): ?>
            <div class="space-y-6">
                <?php while ($row = $result->fetch_assoc()):
                    $order_status = strtolower($row['status']);
                    $payment_status = strtolower($row['payment_status'] ?? '');
                    $delivery_status = strtolower($row['delivery_status'] ?? '');
                    $has_transaction = !empty($row['transaction_ref']);

                    // Determine overall status for badge display
                    if ($order_status === 'completed') {
                        $display_status = 'Completed';
                        $status_class = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        $status_icon = 'fa-check-circle';
                    } elseif ($order_status === 'cancelled') {
                        $display_status = 'Cancelled';
                        $status_class = 'bg-red-50 text-red-700 border-red-200';
                        $status_icon = 'fa-times-circle';
                    } elseif ($payment_status === 'rejected') {
                        $display_status = 'Rejected';
                        $status_class = 'bg-red-50 text-red-700 border-red-200';
                        $status_icon = 'fa-times-circle';
                    } elseif ($payment_status === 'paid') {
                        $display_status = 'Paid';
                        $status_class = 'bg-blue-50 text-blue-700 border-blue-200';
                        $status_icon = 'fa-credit-card';
                    } else {
                        $display_status = 'Pending';
                        $status_class = 'bg-amber-50 text-amber-700 border-amber-200';
                        $status_icon = 'fa-clock';
                    }

                    // Define boolean timeline progression states
                    $step_order = true; 
                    $step_payment_submitted = $has_transaction || $payment_status === 'paid' || $payment_status === 'rejected';
                    $step_payment_approved = $payment_status === 'paid';
                    $step_packing = in_array($delivery_status, ['packing', 'shipping', 'delivered']);
                    $step_shipping = in_array($delivery_status, ['shipping', 'delivered']);
                    $step_delivered = $delivery_status === 'delivered';
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    
                    <!-- Order Meta Card Header Details -->
                    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h3 class="font-black text-slate-900 text-sm sm:text-base">
                                #<?= htmlspecialchars($row['order_number'] ?? $row['id']); ?>
                            </h3>
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                <?= date('d M Y, h:i A', strtotime($row['created_at'])); ?>
                            </p>
                        </div>
                        <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto">
                            <span class="inline-flex items-center gap-1.5 py-1 px-2.5 rounded-full text-[11px] font-bold border <?= $status_class; ?>">
                                <i class="fa-solid <?= $status_icon; ?> text-[10px]"></i> <?= $display_status; ?>
                            </span>
                            <span class="text-sm font-black text-blue-600">
                                <?= number_format($row['total_amount']); ?> MMK
                            </span>
                        </div>
                    </div>

                    <!-- Responsive Horizontal Tracking Timeline View Wrapper -->
                    <div class="px-5 sm:px-6 py-5 overflow-x-auto">
                        <div class="flex items-center justify-between min-w-[640px] pt-2 pb-4">
                            
                            <!-- Step 1: Order Placed -->
                            <div class="flex flex-col items-center flex-1 position-relative">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_order ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_order ? 'text-emerald-600' : 'text-gray-400' ?>">Order<br>Placed</p>
                            </div>

                            <!-- Connector -->
                            <div class="flex-1 h-0.5 -mt-6 <?= $step_payment_submitted ? 'bg-emerald-500' : 'bg-gray-200' ?>"></div>

                            <!-- Step 2: Payment Submitted -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_payment_submitted ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-receipt text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_payment_submitted ? 'text-emerald-600' : 'text-gray-400' ?>">Payment<br>Submitted</p>
                            </div>

                            <!-- Connector -->
                            <div class="flex-1 h-0.5 -mt-6 <?= $step_payment_approved ? 'bg-emerald-500' : 'bg-gray-200' ?>"></div>

                            <!-- Step 3: Payment Approved -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_payment_approved ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-credit-card text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_payment_approved ? 'text-emerald-600' : 'text-gray-400' ?>">Payment<br>Approved</p>
                            </div>

                            <!-- Connector -->
                            <div class="flex-1 h-0.5 -mt-6 <?= $step_packing ? 'bg-emerald-500' : 'bg-gray-200' ?>"></div>

                            <!-- Step 4: Packing -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_packing ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-box text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_packing ? 'text-emerald-600' : 'text-gray-400' ?>">Packing</p>
                            </div>

                            <!-- Connector -->
                            <div class="flex-1 h-0.5 -mt-6 <?= $step_shipping ? 'bg-emerald-500' : 'bg-gray-200' ?>"></div>

                            <!-- Step 5: Shipping -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_shipping ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-truck text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_shipping ? 'text-emerald-600' : 'text-gray-400' ?>">Shipping</p>
                            </div>

                            <!-- Connector -->
                            <div class="flex-1 h-0.5 -mt-6 <?= $step_delivered ? 'bg-emerald-500' : 'bg-gray-200' ?>"></div>

                            <!-- Step 6: Delivered -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm border-2 z-10 <?= $step_delivered ? 'bg-emerald-500 border-emerald-500 text-white shadow-md' : 'bg-gray-100 border-gray-300 text-gray-400' ?>">
                                    <i class="fa-solid fa-circle-check text-xs"></i>
                                </div>
                                <p class="text-[11px] font-bold text-center mt-2 <?= $step_delivered ? 'text-emerald-600' : 'text-gray-400' ?>">Delivered</p>
                            </div>
                        </div>
                    </div>

                    <!-- Contextual Footer Action Banner Trigger Button -->
                    <div class="px-5 sm:px-6 py-4 border-t border-gray-100 bg-slate-50/50 flex items-center justify-between">
                        <div>
                            <?php if ($display_status === 'Pending' && $payment_status !== 'rejected'): ?>
                                <a href="payment.php?order=<?= $row['id']; ?>" class="inline-flex items-center gap-1.5 bg-blue-600 text-white text-xs font-bold px-5 py-2.5 rounded-xl hover:bg-blue-700 transition shadow-sm">
                                    <i class="fa-solid fa-credit-card text-[10px]"></i> Pay Now
                                </a>
                            <?php elseif ($display_status === 'Rejected'): ?>
                                <a href="payment.php?order=<?= $row['id']; ?>" class="inline-flex items-center gap-1.5 bg-amber-500 text-slate-900 text-xs font-bold px-5 py-2.5 rounded-xl hover:bg-amber-400 transition shadow-sm">
                                    <i class="fa-solid fa-redo text-[10px]"></i> Retry Payment
                                </a>
                            <?php else: ?>
                                <span class="text-xs text-gray-400 font-normal">
                                    <?php if ($step_delivered): ?>
                                        <i class="fa-solid fa-circle-check text-emerald-500 mr-1"></i> Order completed
                                    <?php elseif ($step_shipping): ?>
                                        <i class="fa-solid fa-truck text-blue-500 mr-1"></i> Your order is on the way
                                    <?php elseif ($step_payment_approved): ?>
                                        <i class="fa-solid fa-clock text-amber-500 mr-1"></i> Awaiting delivery
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

        <?php else: ?>
            <!-- Fallback Blank State View Container -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-12 text-center max-w-sm mx-auto">
                    <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-gray-400">
                        <i class="fa-solid fa-box-open text-xl"></i>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 mb-1">No Orders Yet</h3>
                    <p class="text-xs text-gray-400 leading-relaxed mb-5">You haven't placed any orders yet.</p>
                    <a href="<?= $base_url; ?>/books.php" class="inline-flex bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-5 py-2.5 rounded-xl shadow-sm transition">
                        Browse Books
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php include '../auth/footer.php'; ?>

</body>
</html>
<?php
$stmt->close();
$conn->close();
?>