<?php
session_start();
require_once '../config/db.php';

// Check admin authentication
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

// Validate Order ID from GET request
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);

// ==========================================================
// 1. BACKEND LOGIC: DATABASE TABLE UPDATE & STOCK MANAGEMENT
// ==========================================================
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $action_status = trim($_POST['update_status']); // Expected values: 'completed' or 'cancelled'
    
    if (in_array($action_status, ['completed', 'cancelled'])) {
        
        // Begin database transaction for safe atomic updates
        $conn->begin_transaction();

        try {
            if ($action_status === 'completed') {
                
                // Step A: Check book stock availability before approving order
                $check_stock_sql = "SELECT Order_item.book_id, Order_item.quantity as ordered_qty, Books.title, Books.stock 
                                    FROM Order_item 
                                    JOIN Books ON Order_item.book_id = Books.id 
                                    WHERE Order_item.order_id = ?";
                $check_stmt = $conn->prepare($check_stock_sql);
                $check_stmt->bind_param("i", $order_id);
                $check_stmt->execute();
                $stock_result = $check_stmt->get_result();

                $insufficient_books = [];
                $items_to_update = [];

                while ($row = $stock_result->fetch_assoc()) {
                    if ($row['stock'] < $row['ordered_qty']) {
                        $insufficient_books[] = $row['title'] . " (Available: " . $row['stock'] . ", Ordered: " . $row['ordered_qty'] . ")";
                    }
                    $items_to_update[] = $row;
                }
                $check_stmt->close();

                // Throw exception if any book stock is insufficient
                if (!empty($insufficient_books)) {
                    throw new Exception("Stock မလောက်ပါ။ - " . implode(", ", $insufficient_books));
                }

                // Step B: Deduct stock from Books table
                $u_stock = $conn->prepare("UPDATE Books SET stock = stock - ? WHERE id = ?");
                foreach ($items_to_update as $item) {
                    $u_stock->bind_param("ii", $item['ordered_qty'], $item['book_id']);
                    $u_stock->execute();
                }
                $u_stock->close();

                // Step C: Update status in Orders table
                $u_order = $conn->prepare("UPDATE Orders SET status = 'completed' WHERE id = ?");
                $u_order->bind_param("i", $order_id);
                $u_order->execute();
                $u_order->close();

                // Step D: Update status in Payment table
                $u_payment = $conn->prepare("UPDATE Payment SET status = 'completed' WHERE order_id = ?");
                $u_payment->bind_param("i", $order_id);
                $u_payment->execute();
                $u_payment->close();

                $message = "Payment approved, order completed, and stock updated successfully!";
            } 
            elseif ($action_status === 'cancelled') {
                
                // Update both Orders and Payment status to 'cancelled'
                $u_order = $conn->prepare("UPDATE Orders SET status = 'cancelled' WHERE id = ?");
                $u_order->bind_param("i", $order_id);
                $u_order->execute();
                $u_order->close();

                $u_payment = $conn->prepare("UPDATE Payment SET status = 'cancelled' WHERE order_id = ?");
                $u_payment->bind_param("i", $order_id);
                $u_payment->execute();
                $u_payment->close();

                $message = "Order and Payment have been cancelled successfully.";
            }

            // Commit transaction if all queries executed successfully
            $conn->commit();
            $message_type = "success";

        } catch (Exception $e) {
            // Rollback transaction on any error
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = "error";
        }
    }
}

// ==========================================================
// 2. FETCH ORDER & CUSTOMER DETAILS
// ==========================================================
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

// ==========================================================
// 3. FETCH PAYMENT DETAILS
// ==========================================================
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

// ==========================================================
// 4. FETCH ORDERED ITEMS (BOOKS)
// ==========================================================
$items_sql = "SELECT Order_item.*, Books.title, Books.book_image 
              FROM Order_item 
              LEFT JOIN Books ON Order_item.book_id = Books.id 
              WHERE Order_item.order_id = ?";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// ==========================================================
// 5. RESOLVE DYNAMIC SLIP IMAGE PATH
// ==========================================================
$actual_slip_path = 'https://placehold.co/150x200?text=No+Slip'; // Default fallback path
if ($payment && !empty($payment['payment_slip'])) {
    $slip_filename = $payment['payment_slip'];
    $base_name = pathinfo($slip_filename, PATHINFO_FILENAME);
    
    if (file_exists('../assets/' . $slip_filename)) {
        $actual_slip_path = '../assets/' . $slip_filename;
    } elseif (file_exists('../assets/' . $base_name . '.jpg')) {
        $actual_slip_path = '../assets/' . $base_name . '.jpg';
    } elseif (file_exists('../assets/' . $base_name . '.jpeg')) {
        $actual_slip_path = '../assets/' . $base_name . '.jpeg';
    } elseif (file_exists('../assets/' . $base_name . '.png')) {
        $actual_slip_path = '../assets/' . $base_name . '.png';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Online Book Shop</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-gray-300 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">
    
   
          
                
    <<?php include '../auth/sidebar.php'; ?>
        
        <!-- Mobile Header Toggle -->
        <header class="h-16 bg-white border-b border-slate-200/60 flex items-center justify-between px-4 sm:px-6 z-40 shrink-0 md:hidden">
            <div class="flex items-center">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-100 transition flex flex-col justify-between w-9 h-9 p-2.5 cursor-pointer border border-slate-200 bg-white shadow-sm">
                    <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
                    <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
                    <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
                </button>
                <h1 class="text-md font-bold text-slate-900 ml-3">Order Details</h1>
            </div>
        </header>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 min-w-0 max-w-[1600px] w-full mx-auto">
            <div class="max-w-6xl space-y-6 sm:space-y-8">

                <!-- Alert Message Notification Banner -->
                <?php if (!empty($message)): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 shadow-sm border <?= $message_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?> text-lg"></i>
                        <span class="text-sm font-medium"><?= $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Order Action Management Controller Panel -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div>
                        <h3 class="text-base font-bold tracking-tight text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-sliders text-indigo-500 text-sm"></i> Order Management Actions
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Control the system status state of this specific customer order.</p>
                    </div>

                    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3">
                        <?php if (strtolower($order['status'] ?? 'pending') === 'pending'): ?>
                            <form method="POST" onsubmit="return confirm('Are you completely sure you want to CANCEL this order?');" class="w-full sm:w-auto">
                                <button type="submit" name="update_status" value="cancelled" class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold tracking-wide transition shadow-sm cursor-pointer">
                                    <i class="fa-solid fa-rectangle-xmark"></i>
                                    <span>Cancel Order</span>
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Do you want to mark this order as completed and deduct book stock?');" class="w-full sm:w-auto">
                                <button type="submit" name="update_status" value="completed" class="w-full sm:w-auto flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold tracking-wide transition shadow-md shadow-emerald-600/10 cursor-pointer">
                                    <i class="fa-solid fa-square-check"></i>
                                    <span>Mark as Completed</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="w-full sm:w-auto text-xs font-semibold text-slate-400 bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200 flex items-center justify-center gap-2 select-none">
                                <i class="fa-solid fa-circle-lock text-slate-400"></i> Order State Locked (<?= ucfirst(htmlspecialchars($order['status'])) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        
                <!-- Customer & Summary Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">
                    <div class="bg-white p-5 sm:p-6 shadow-md rounded-2xl border border-gray-100">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-user-tie text-slate-400 text-sm"></i> Customer Information</h2>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><strong class="text-gray-800">Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Unknown User'); ?></p>
                            <p><strong class="text-gray-800">Email:</strong> <?= htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                            <p><strong class="text-gray-800">Phone:</strong> <?= htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                            <p><strong class="text-gray-800">Address:</strong> <?= nl2br(htmlspecialchars($order['address'] ?? 'N/A')); ?></p>
                        </div>
                    </div>

                    <div class="bg-white p-5 sm:p-6 shadow-md rounded-2xl border border-gray-100">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-receipt text-slate-400 text-sm"></i> Order Summary</h2>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><strong class="text-gray-800">Order ID:</strong> <?= $order['id']; ?></p>
                            <p><strong class="text-gray-800">Order Number:</strong> <span class="text-blue-600 font-semibold"><?= htmlspecialchars($order['order_number'] ?? 'N/A'); ?></span></p>
                            <p><strong class="text-gray-800">Order Date:</strong> <?= date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                            <p><strong class="text-gray-800">Order Status:</strong> 
                                <?php 
                                $status = $order['status'] ?? 'pending';
                                $badge = "bg-gray-100 text-gray-800";
                                if (strtolower($status) == 'pending') $badge = "bg-yellow-100 text-yellow-800 border border-yellow-200";
                                elseif (strtolower($status) == 'completed') $badge = "bg-emerald-100 text-emerald-800 border border-emerald-200";
                                elseif (strtolower($status) == 'cancelled') $badge = "bg-rose-100 text-rose-800 border border-rose-200";
                                ?>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge; ?>"><?= ucfirst(htmlspecialchars($status)); ?></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Items Ordered Table -->
                <div class="bg-white p-5 sm:p-6 shadow-md rounded-2xl border border-gray-100">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-book-bookmark text-slate-400 text-sm"></i> Items Ordered</h2>
                    <div class="overflow-x-auto no-scrollbar">
                        <table class="w-full text-sm text-center border-collapse min-w-[500px]">
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
                                if ($items_result && $items_result->num_rows > 0): 
                                    while ($item = $items_result->fetch_assoc()): 
                                        $subtotal = $item['price'] * $item['quantity'];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4 px-4 border-b text-left flex items-center gap-3">
                                            <?php if (!empty($item['book_image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($item['book_image']); ?>" class="w-10 h-14 sm:w-12 sm:h-16 object-cover rounded shadow-sm border shrink-0" onerror="this.onerror=null; this.src='../assets/<?= htmlspecialchars($item['book_image']); ?>';">
                                            <?php endif; ?>
                                            <span class="font-medium text-gray-800 line-clamp-2"><?= htmlspecialchars($item['title']); ?></span>
                                        </td>
                                        <td class="py-4 px-4 border-b text-gray-600 whitespace-nowrap"><?= number_format($item['price'], 2); ?> ကျပ်</td>
                                        <td class="py-4 px-4 border-b font-medium text-gray-800 whitespace-nowrap"><?= $item['quantity']; ?></td>
                                        <td class="py-4 px-4 border-b text-right font-semibold text-gray-800 whitespace-nowrap"><?= number_format($subtotal, 2); ?> ကျပ်</td>
                                    </tr>
                                <?php 
                                    endwhile; 
                                endif; 
                                ?>
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="py-4 px-4 text-right font-bold text-gray-700">Total Amount:</td>
                                    <td class="py-4 px-4 text-right font-bold text-lg sm:text-xl text-blue-600 whitespace-nowrap"><?= number_format($order['total_amount'], 2); ?> ကျပ်</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payment Information Card -->
                <div class="bg-white p-5 sm:p-6 shadow-md rounded-2xl border border-gray-100">
                    <h2 class="text-lg sm:text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-credit-card text-slate-400 text-sm"></i> Payment Information</h2>
                    <?php if ($payment): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                            <div class="space-y-2">
                                <p><strong class="text-gray-900">Payment Method:</strong> <?= htmlspecialchars($payment['method_name'] ?? 'N/A'); ?></p>
                                <p><strong class="text-gray-900">Amount Paid:</strong> <?= number_format($payment['amount'], 2); ?> ကျပ်</p>
                                <!-- <p><strong class="text-gray-800">Transaction Ref:</strong> 
                                <?= htmlspecialchars($payment['transaction_ref'] ?? 'N/A'); ?></p> -->
                                <p><strong class="text-gray-900">Payment Date:</strong> <?= !empty($payment['payment_date']) ? date('d M Y, h:i A', strtotime($payment['payment_date'])) : 'N/A'; ?></p>
                                <p><strong class="text-gray-900">Payment Status:</strong> 
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800"><?= htmlspecialchars($payment['status'] ?? 'Pending'); ?></span>
                                </p>
                            </div>
                            
                            <!-- Safe Slip Loader Utility -->
                            <div class="flex flex-col items-start md:items-center justify-center">
                                <span class="block font-medium text-gray-900 mb-2">Payment Slip / Screenshot</span>
                                <?php if (!empty($payment['payment_slip'])): ?>
                                    <a href="<?= $actual_slip_path; ?>" target="_blank" id="slipLink" class="block relative group">
                                       <img src="<?= $actual_slip_path; ?>" 
                                            onerror="handleSlipError(this, '<?= htmlspecialchars($payment['payment_slip']); ?>')" 
                                            class="w-40 sm:w-48 h-auto object-cover rounded-xl border border-slate-200 shadow-sm cursor-pointer hover:opacity-90 transition" 
                                            alt="Payment Slip" />
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
        </main>
    </div>
</div>

<script>
    // Toggle mobile navigation sidebar
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    // Dynamic fallback handler for slip images
    function handleSlipError(imgElement, filename) {
        if (!imgElement.getAttribute('data-tried-admin')) {
            imgElement.setAttribute('data-tried-admin', 'true');
            const secondarySrc = "../admin/uploads/" + filename;
            imgElement.src = secondarySrc;
            document.getElementById('slipLink').href = secondarySrc;
        } else {
            imgElement.onerror = null; 
            imgElement.src = "https://placehold.co/150x200/eaeaea/444444?text=Image+Not+Found";
            document.getElementById('slipLink').removeAttribute('href');
            document.getElementById('slipLink').style.cursor = 'default';
        }
    }
</script>
</body>
</html>