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

// ==========================================================
// 1. BACKEND LOGIC: DATABASE TABLE UPDATE (ADMIN ACTIONS)
// ==========================================================
$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = trim($_POST['update_status']); // 'completed' or 'cancelled'
    
    if (in_array($new_status, ['completed', 'cancelled'])) {
        
        $update_sql = "UPDATE Orders SET status = ? WHERE id = ?";
        $u_stmt = $conn->prepare($update_sql);
        $u_stmt->bind_param("si", $new_status, $order_id);
        
        if ($u_stmt->execute()) {
            $message = "Order status has been updated successfully!";
            $message_type = "success";
        } else {
            $message = "Something went wrong. Failed to update status in Database.";
            $message_type = "error";
        }
        $u_stmt->close();
        
       
        header("Location: orderdetail.php?id=" . $order_id);
        exit();
    }
}
// ==========================================================

// 2. FETCH ORDER & CUSTOMER DETAILS
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

// 3. FETCH PAYMENT DETAILS (IF EXISTS)
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

// 4. FETCH ORDERED ITEMS (BOOKS)
$items_sql = "SELECT Order_item.*, Books.title, Books.book_image 
              FROM Order_item 
              LEFT JOIN Books ON Order_item.book_id = Books.id 
              WHERE Order_item.order_id = ?";
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
    <title>Order Details - Online Book Shop</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800">

<div class="flex h-screen overflow-hidden">
    
    <!-- SIDEBAR CONTAINER -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-950 text-slate-400 flex flex-col justify-between transform -translate-x-full transition-all duration-300 ease-in-out md:relative md:translate-x-0 border-r border-slate-800/60 shrink-0 shadow-xl md:shadow-none">
        <div class="p-6 overflow-y-auto no-scrollbar flex-1">
            <div class="flex items-center justify-between mb-8 px-2">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-gradient-to-tr from-indigo-600 to-violet-500 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-600/30">
                        <i class="fa-solid fa-book-open text-base"></i>
                    </div>
                    <div>
                        <span class="text-lg font-black tracking-tight bg-gradient-to-r from-white to-slate-400 bg-clip-text text-transparent">BookShop</span>
                        <p class="text-[10px] text-indigo-400/80 font-bold tracking-widest uppercase">Management</p>
                    </div>
                </div>
                <button onclick="toggleSidebar()" class="md:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-900 transition cursor-pointer">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <nav class="space-y-1">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-chart-pie w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Dashboard</span>
                </a>
                <a href="books.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-book w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Manage Books</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-tags w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Categories</span>
                </a>
                <a href="orders.php" class="flex items-center space-x-3 px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 text-white rounded-xl text-sm font-semibold tracking-wide shadow-md shadow-indigo-600/20">
                    <i class="fa-solid fa-cart-shopping w-5 text-indigo-100"></i>
                    <span>Orders</span>
                </a>
                <a href="manage_payment.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-credit-card w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Payments</span>
                </a>
                <a href="delivery.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-truck w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Deliveries</span>
                </a>
                <a href="customers.php" class="flex items-center space-x-3 px-4 py-3 text-slate-400 hover:bg-slate-900 hover:text-slate-100 rounded-xl text-sm font-semibold tracking-wide transition group">
                    <i class="fa-solid fa-users w-5 text-slate-500 group-hover:text-indigo-400 transition"></i>
                    <span>Customers</span>
                </a>
            </nav>
        </div>
        
        <div class="p-4 border-t border-slate-900 bg-slate-950">
            <a href="logout.php" class="flex items-center justify-center space-x-2 px-4 py-2.5 bg-slate-900/60 hover:bg-rose-500/10 hover:text-rose-400 text-slate-400 border border-slate-800/50 rounded-xl text-xs font-bold tracking-wide transition group">
                <i class="fa-solid fa-right-from-bracket group-hover:transform group-hover:translate-x-0.5 transition"></i>
                <span>Sign Out Account</span>
            </a>
        </div>
    </aside>

    <div id="sidebarOverlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 z-40 hidden transition-opacity duration-300"></div>

    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <!-- Mobile Header Toggle -->
        <header class="h-16 bg-white border-b border-slate-200/60 flex items-center px-4 md:px-8 z-40 shrink-0 lg:hidden">
            <button onclick="toggleSidebar()" class="p-2 rounded-xl text-slate-600 hover:bg-slate-100 transition flex flex-col justify-between w-9 h-9 p-2.5 cursor-pointer border border-slate-200 bg-white shadow-sm">
                <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
                <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
                <span class="w-full h-0.5 bg-slate-600 rounded-full transition-all"></span>
            </button>
            <h1 class="text-md font-bold text-slate-900 ml-3">Order Details</h1>
        </header>

        <!-- Main content -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 min-w-0 max-w-[1600px] w-full mx-auto">
            <div class="max-w-6xl space-y-8">

                <!-- Alert Message Notification Banner -->
                <?php if (!empty($message)): ?>
                    <div class="p-4 rounded-xl flex items-center gap-3 shadow-sm border <?= $message_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800' ?>">
                        <i class="fa-solid <?= $message_type === 'success' ? 'fa-circle-check text-emerald-500' : 'fa-circle-exclamation text-rose-500' ?> text-lg"></i>
                        <span class="text-sm font-medium"><?= $message; ?></span>
                    </div>
                <?php endif; ?>

                <!-- Order Action Management Controller Panel -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <div>
                        <h3 class="text-base font-bold tracking-tight text-slate-900 flex items-center gap-2">
                            <i class="fa-solid fa-sliders text-indigo-500 text-sm"></i> Order Management Actions
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">Control the system status state of this specific customer order.</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php if (strtolower($order['status'] ?? 'pending') === 'pending'): ?>
                            <form method="POST" onsubmit="return confirm('Are you completely sure you want to CANCEL this order?');" class="inline">
                                <button type="submit" name="update_status" value="cancelled" class="flex items-center gap-2 px-4 py-2.5 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold tracking-wide transition shadow-sm cursor-pointer">
                                    <i class="fa-solid fa-rectangle-xmark"></i>
                                    <span>Cancel Order</span>
                                </button>
                            </form>

                            <form method="POST" onsubmit="return confirm('Do you want to mark this order as completed and delivered?');" class="inline">
                                <button type="submit" name="update_status" value="completed" class="flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl text-xs font-bold tracking-wide transition shadow-md shadow-emerald-600/10 cursor-pointer">
                                    <i class="fa-solid fa-square-check"></i>
                                    <span>Mark as Completed</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-xs font-semibold text-slate-400 bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200 flex items-center gap-2 select-none">
                                <i class="fa-solid fa-circle-lock text-slate-400"></i> Order State Locked (<?= ucfirst(htmlspecialchars($order['status'])) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        
                <!-- Customer & Summary Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-user-tie text-slate-400 text-sm"></i> Customer Information</h2>
                        <div class="space-y-2 text-sm text-gray-600">
                            <p><strong class="text-gray-800">Name:</strong> <?= htmlspecialchars($order['customer_name'] ?? 'Unknown User'); ?></p>
                            <p><strong class="text-gray-800">Email:</strong> <?= htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                            <p><strong class="text-gray-800">Phone:</strong> <?= htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                            <p><strong class="text-gray-800">Address:</strong> <?= nl2br(htmlspecialchars($order['address'] ?? 'N/A')); ?></p>
                        </div>
                    </div>

                    <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                        <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-receipt text-slate-400 text-sm"></i> Order Summary</h2>
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
                <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-book-bookmark text-slate-400 text-sm"></i> Items Ordered</h2>
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
                                if ($items_result && $items_result->num_rows > 0): 
                                    while ($item = $items_result->fetch_assoc()): 
                                        $subtotal = $item['price'] * $item['quantity'];
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4 px-4 border-b text-left flex items-center gap-3">
                                            <?php if (!empty($item['book_image'])): ?>
                                                <img src="../uploads/<?= htmlspecialchars($item['book_image']); ?>" class="w-12 h-16 object-cover rounded shadow-sm border" onerror="this.onerror=null; this.src='../assets/<?= htmlspecialchars($item['book_image']); ?>';">
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

                <!-- Payment Information Card -->
                <div class="bg-white p-6 shadow-md rounded-2xl border border-gray-100">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2"><i class="fa-solid fa-credit-card text-slate-400 text-sm"></i> Payment Information</h2>
                    <?php if ($payment): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                            <div class="space-y-2">
                                <p><strong class="text-gray-800">Payment Method:</strong> <?= htmlspecialchars($payment['method_name'] ?? 'N/A'); ?></p>
                                <p><strong class="text-gray-800">Amount Paid:</strong> <?= number_format($payment['amount'], 2); ?> MMK</p>
                                <p><strong class="text-gray-800">Transaction Ref:</strong> <?= htmlspecialchars($payment['transaction_ref'] ?? 'N/A'); ?></p>
                                <p><strong class="text-gray-800">Payment Date:</strong> <?= !empty($payment['payment_date']) ? date('d M Y, h:i A', strtotime($payment['payment_date'])) : 'N/A'; ?></p>
                                <p><strong class="text-gray-800">Payment Status:</strong> 
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800"><?= htmlspecialchars($payment['status'] ?? 'Pending'); ?></span>
                                </p>
                            </div>
                            
                            <!-- Safe Slip Loader Utility -->
                            <div class="flex flex-col items-start md:items-center justify-center">
                                <span class="block font-medium text-gray-800 mb-2">Payment Slip / Screenshot</span>
                                <?php if (!empty($payment['payment_slip'])): 
                                    $slip_file = htmlspecialchars($payment['payment_slip']);
                                    
                                    $primary_src = "../uploads/" . $slip_file; 
                                ?>
                                    <a href="<?= $primary_src; ?>" target="_blank" id="slipLink" class="block relative group">
                                        <img src="<?= $primary_src; ?>" 
                                             id="slipImg"
                                             alt="Payment Slip" 
                                             class="w-32 h-44 object-cover rounded-lg border shadow-sm group-hover:scale-105 transition duration-200"
                                             onerror="handleSlipError(this, '<?= $slip_file; ?>')">
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
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }

    
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