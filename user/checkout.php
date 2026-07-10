<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Customer Login Check (Guests are redirected to the registration page)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    // Redirect guest to register.php and pass the current URI for post-registration redirect
    header("Location: ../auth/register.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// FETCH CURRENT USER DATA FROM DATABASE (For autofilling phone and address)
$user_phone = "";
$user_address = "";
$user_stmt = $conn->prepare("SELECT phone, address FROM Users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
if ($user_row = $user_res->fetch_assoc()) {
    $user_phone = $user_row['phone'] ?? '';
    $user_address = $user_row['address'] ?? '';
}
$user_stmt->close();

// Fetch cart items from database for the logged-in user
$stmt = $conn->prepare("SELECT ci.id, ci.book_id, ci.quantity, ci.unit_price, ci.totalprice, b.title, b.book_image FROM Cart_item ci JOIN Books b ON ci.book_id = b.id WHERE ci.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_items = [];
while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
}
$stmt->close();

// If cart is empty, redirect back to cart page
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Calculate subtotal dynamically from database cart data
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['totalprice'];
}

$delivery_fee = 2500;
$total_amount = $subtotal + $delivery_fee;

// Handle POST form submission - create order in database
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');

    // Validate required fields
    if (empty($customer_name) || empty($phone) || empty($address) || empty($payment_method)) {
        $error = "All fields are required. Please fill in all information.";
    } else {
        
        // UPDATE CUSTOMER PROFILE IN USERS TABLE SO ADMIN CAN SEE THE DATA
        $update_profile = $conn->prepare("UPDATE Users SET phone = ?, address = ? WHERE id = ?");
        $update_profile->bind_param("ssi", $phone, $address, $user_id);
        $update_profile->execute();
        $update_profile->close();

        // Look up payment_method_id from payment_method table
        $method_query = $conn->prepare("SELECT id FROM payment_method WHERE method_name = ? AND is_active = 1");
        $method_query->bind_param("s", $payment_method);
        $method_query->execute();
        $method_result = $method_query->get_result();
        $method_row = $method_result->fetch_assoc();
        $method_query->close();

        $payment_method_id = $method_row ? intval($method_row['id']) : 0;

        // Generate unique order number
        $order_number = 'ORD-' . date('YmdHis') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

        // Insert order into Orders table
        $order_stmt = $conn->prepare("INSERT INTO Orders (user_id, total_amount, status, created_at, order_number) VALUES (?, ?, 'pending', NOW(), ?)");
        $order_stmt->bind_param("ids", $user_id, $total_amount, $order_number);

        if ($order_stmt->execute()) {
            $order_id = $conn->insert_id;
            $order_stmt->close();

            // Insert each cart item into Order_item table
            $item_stmt = $conn->prepare("INSERT INTO Order_item (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
            foreach ($cart_items as $item) {
                $item_stmt->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['unit_price']);
                $item_stmt->execute();
            }
            $item_stmt->close();

            // Insert a pending Payment record for this order
            $payment_stmt = $conn->prepare("INSERT INTO Payment (order_id, payment_method_id, amount, status, payment_date, transaction_ref, payment_slip) VALUES (?, ?, ?, 'pending', NOW(), '', '')");
            $payment_stmt->bind_param("iid", $order_id, $payment_method_id, $total_amount);
            $payment_stmt->execute();
            $payment_stmt->close();

            // Clear the user's cart after successful order creation
            $clear_stmt = $conn->prepare("DELETE FROM Cart_item WHERE user_id = ?");
            $clear_stmt->bind_param("i", $user_id);
            $clear_stmt->execute();
            $clear_stmt->close();

            // Redirect to payment submit page with real order ID and selected method
            header("Location: payment_submit.php?order_id=" . $order_id . "&method=" . urlencode($payment_method));
            exit();
        } else {
            $order_stmt->close();
            $error = "Failed to create order. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="text-slate-900 antialiased min-h-screen flex flex-col">

    <?php include "../auth/header.php"; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-10 lg:py-16">
        
        <div class="mb-10">
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900">Checkout</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-2">Please fill out your billing and shipping information below to complete your order.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-8 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl text-xs md:text-sm font-semibold flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-rose-500 text-base"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Left Column: Form Fields -->
            <div class="lg:col-span-7">
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/40 space-y-6">
                    
                    <div class="flex items-center gap-3 mb-2">
                        <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-base shadow-sm">
                            <i class="fa-solid fa-address-card"></i>
                        </span>
                        <h2 class="text-lg font-bold text-slate-800 tracking-tight">Customer & Payment Information</h2>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Receiver Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required class="w-full px-4 py-3 bg-slate-50/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white outline-none transition text-sm text-slate-800 placeholder-slate-400" placeholder="Enter full name">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user_phone ?: ($_SESSION['user_phone'] ?? '')) ?>" required class="w-full px-4 py-3 bg-slate-50/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white outline-none transition text-sm text-slate-800 placeholder-slate-400" placeholder="09xxxxxxxxx">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Full Address (House No, Street, Township)</label>
                        <textarea name="address" rows="3" required class="w-full px-4 py-3 bg-slate-50/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white outline-none transition text-sm text-slate-800 placeholder-slate-400 leading-relaxed" placeholder="Enter your complete home address for delivery"><?= htmlspecialchars($user_address) ?></textarea>
                    </div>

                    <div class="border-t border-slate-100 my-4 pt-4"></div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Payment Method</label>
                        <div class="relative">
                            <select name="payment_method" required class="w-full px-4 py-3 bg-slate-50/50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white outline-none transition text-sm text-slate-800 appearance-none cursor-pointer">
                                <option value="" disabled selected>-- Choose online payment method --</option>
                                <option value="kpay">KBZPay Wallet</option>
                                <option value="wave">WaveMoney</option>
                                <option value="cbpay">CB Pay</option>
                                <option value="ayapay">AYA Pay</option>
                                <option value="banking">Other Mobile Banking (KBZ, AYA, Yoma, etc.)</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-2 ml-1">* Please select the mobile banking or digital wallet system you intend to transfer funds through.</p>
                    </div>

                </div>
            </div>

            <!-- Right Column: Order Summary Side Box -->
            <div class="lg:col-span-5">
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-100 shadow-xl shadow-slate-200/40 sticky top-6">
                    <h2 class="text-lg font-bold text-slate-800 tracking-tight mb-5 flex items-center gap-2">
                        <span>🛒 Order Summary</span>
                    </h2>

                    <div class="divide-y divide-slate-100 max-h-64 overflow-y-auto mb-6 pr-1">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="py-3.5 flex justify-between items-start text-sm gap-4">
                            <div>
                                <h4 class="font-semibold text-slate-800 line-clamp-1"><?= htmlspecialchars($item['title']) ?></h4>
                                <p class="text-slate-400 text-xs mt-1">Qty: <span class="text-slate-700 font-medium"><?= $item['quantity'] ?></span> × <?= number_format($item['unit_price']) ?> MMK</p>
                            </div>
                            <span class="font-bold text-slate-700 shrink-0"><?= number_format($item['totalprice']) ?> MMK</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-slate-100 pt-5 space-y-3.5 text-sm text-slate-500">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="font-medium text-slate-800"><?= number_format($subtotal) ?> MMK</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Delivery Fee</span>
                            <span class="font-medium text-slate-800"><?= number_format($delivery_fee) ?> MMK</span>
                        </div>
                        <div class="flex justify-between text-base font-bold text-slate-900 border-t border-dashed border-slate-200 pt-4 mt-2">
                            <span>Total Amount</span>
                            <span class="text-indigo-600 text-lg font-extrabold"><?= number_format($total_amount) ?> MMK</span>
                        </div>
                    </div>

                    <button type="submit" class="w-full mt-8 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-6 rounded-2xl shadow-lg shadow-indigo-600/20 transition-all transform active:scale-[0.99] flex items-center justify-center gap-2 tracking-wide text-sm">
                        <span>Proceed to Payment</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>

                    <a href="cart.php" class="block text-center text-xs font-semibold text-slate-400 mt-5 hover:text-indigo-600 transition-colors">
                        <i class="fa-solid fa-chevron-left text-[10px] mr-1"></i> Back to Cart
                    </a>
                </div>
            </div>

        </form>
    </main>

    <?php include "../auth/footer.php"; ?>

</body>
</html>