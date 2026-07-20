<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Route Guard: Redirect guests or non-customer roles to the registration page
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/register.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";

// Fetch current user details from the database for autofilling profile fields
$user_phone = "";
$user_address = "";
$user_stmt = $conn->prepare("SELECT phone, address FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_res = $user_stmt->get_result();
if ($user_row = $user_res->fetch_assoc()) {
    $user_phone = $user_row['phone'] ?? '';
    $user_address = $user_row['address'] ?? '';
}
$user_stmt->close();

// Fetch active cart items mapped with book details for the current user
$stmt = $conn->prepare("SELECT ci.id, ci.book_id, ci.quantity, ci.unit_price, ci.totalprice, b.title, b.book_image FROM cart_item ci JOIN books b ON ci.book_id = b.id WHERE ci.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_items = [];
while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
}
$stmt->close();

// Redirect back to the cart page if the user has no items to check out
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Calculate the order subtotal dynamically from the retrieved database items
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['totalprice'];
}

$delivery_fee = 2500;
$total_amount = $subtotal + $delivery_fee;

// Process the order submission form when a POST request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');

    // Form input validation check
    if (empty($customer_name) || empty($phone) || empty($address) || empty($payment_method)) {
        $error = "All fields are required. Please fill in all information.";
    } else {
        
        // ---------------------------------------------------------------------
        // REMOVED: REAL-TIME STOCK QUANTITY VALIDATION
        // စတော့ လက်ကျန် စစ်ဆေးမှုကို လုံးဝ ပယ်ဖျက်လိုက်သဖြင့် Alert တက်မည်မဟုတ်ပါ။
        // ---------------------------------------------------------------------

        // Proceed with order finalization directly
        if (empty($error)) {
            
            // Update user profile records so administrators have up-to-date details
            $update_profile = $conn->prepare("UPDATE users SET phone = ?, address = ? WHERE id = ?");
            $update_profile->bind_param("ssi", $phone, $address, $user_id);
            $update_profile->execute();
            $update_profile->close();

            // Retrieve corresponding payment_method_id for the chosen method name
            $method_query = $conn->prepare("SELECT id FROM payment_method WHERE method_name = ? AND is_active = 1");
            $method_query->bind_param("s", $payment_method);
            $method_query->execute();
            $method_result = $method_query->get_result();
            $method_row = $method_result->fetch_assoc();
            $method_query->close();

            $payment_method_id = $method_row ? intval($method_row['id']) : 0;

            // Generate a standardized unique identifier string for the order reference
            $order_number = 'ORD-' . date('YmdHis') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

            // Step 1: Create a master order record in the Orders table
            $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, created_at, order_number) VALUES (?, ?, 'pending', NOW(), ?)");
            $order_stmt->bind_param("ids", $user_id, $total_amount, $order_number);

            if ($order_stmt->execute()) {
                $order_id = $conn->insert_id;
                $order_stmt->close();

                // Step 2: Bind items to the order list and simultaneously deduct stock levels
                $item_stmt = $conn->prepare("INSERT INTO order_item (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                $update_stock_stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");

                foreach ($cart_items as $item) {
                    // Record the transactional snapshot inside the Order_item structure
                    $item_stmt->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['unit_price']);
                    $item_stmt->execute();

                    // Subtract the ordered book quantity immediately from total inventory counts
                    $update_stock_stmt->bind_param("ii", $item['quantity'], $item['book_id']);
                    $update_stock_stmt->execute();
                }
                $item_stmt->close();
                $update_stock_stmt->close();

                // Step 3: Map a clean payment snapshot record linked directly to this transaction
                $payment_stmt = $conn->prepare("INSERT INTO payment (order_id, payment_method_id, amount, status, payment_date, transaction_ref, payment_slip) VALUES (?, ?, ?, 'pending', NOW(), '', '')");
                $payment_stmt->bind_param("iid", $order_id, $payment_method_id, $total_amount);
                $payment_stmt->execute();
                $payment_stmt->close();

                // Step 4: Wipe items from the temporary cart list database table
                $clear_stmt = $conn->prepare("DELETE FROM cart_item WHERE user_id = ?");
                $clear_stmt->bind_param("i", $user_id);
                $clear_stmt->execute();
                $clear_stmt->close();

                // Step 5: Redirect to the payment confirmation page (payment.php)
                header("Location: payment_submit.php?order_id=" . $order_id . "&method=" . urlencode($payment_method));
                exit();
            } else {
                $order_stmt->close();
                $error = "Failed to create order. Please try again later.";
            }
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
<body class="text-slate-900 antialiased min-h-screen flex flex-col justify-between">

    <?php include "../auth/header.php"; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-6 md:py-10 lg:py-12">
        
        <div class="mb-6 md:mb-8">
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Checkout</h1>
            <p class="text-xs md:text-sm text-slate-500 mt-1">Please fill out your billing and shipping information below to complete your order.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl shadow-sm flex items-start gap-3">
                <i class="fa-solid fa-circle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
                <p class="text-xs md:text-sm text-amber-800 font-medium"><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

            <!-- Left Content Area -->
            <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 shadow-sm border border-slate-100 space-y-6">
                <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                    <i class="fa-solid fa-address-card text-indigo-600 text-xl"></i>
                    <h2 class="text-lg font-bold text-slate-800 tracking-tight">Customer & Payment Information</h2>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Receiver Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required placeholder="Enter receiver's name"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Phone Number <span class="text-rose-500">*</span></label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user_phone ?: ($_SESSION['user_phone'] ?? '')) ?>" required placeholder="Enter mobile phone number (09xxxxxxxxx)"
                           class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800">
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Full Address (House No, Street, Township) <span class="text-rose-500">*</span></label>
                    <textarea name="address" rows="3" required placeholder="Enter complete delivery details"
                              class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800 resize-none leading-relaxed"><?= htmlspecialchars($user_address) ?></textarea>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Payment Method <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <select name="payment_method" required
                                class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-700 cursor-pointer">
                            <option value="" disabled selected>-- Choose online payment method --</option>
                            <option value="kpay">KBZPay</option>
                            <option value="wave">WavePay</option>
                            <option value="cbpay">CB Pay</option>
                            <option value="ayapay">AYA Pay</option>
                            <option value="banking">MAB Mobile Banking</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Content Area -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
                        <i class="fa-solid fa-cart-shopping text-indigo-600 text-lg"></i>
                        <h2 class="text-base md:text-lg font-bold text-slate-800 tracking-tight">Order Summary</h2>
                    </div>

                    <div class="space-y-4 max-h-64 overflow-y-auto border-b border-slate-100 pb-4 mb-4 pr-1">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-start gap-4 text-sm">
                            <div class="min-w-0">
                                <h4 class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($item['title']) ?></h4>
                                <p class="text-xs text-slate-400 mt-1">Qty: <span class="text-slate-700 font-medium"><?= $item['quantity'] ?></span> × <?= number_format($item['unit_price']) ?> ကျပ်</p>
                            </div>
                            <span class="font-bold text-slate-700 shrink-0"><?= number_format($item['totalprice']) ?> ကျပ်</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-3 text-sm border-b border-slate-100 pb-4 mb-4 text-slate-500">
                        <div class="flex justify-between">
                            <span>Subtotal</span>
                            <span class="font-medium text-slate-800"><?= number_format($subtotal) ?> ကျပ်</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Delivery Fee</span>
                            <span class="font-medium text-slate-800"><?= number_format($delivery_fee) ?> ကျပ်</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-6">
                        <span class="text-sm md:text-base font-bold text-slate-800">Total Amount</span>
                        <span class="text-xl font-extrabold text-indigo-600"><?= number_format($total_amount) ?> ကျပ်</span>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 px-4 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 active:scale-[0.99] cursor-pointer text-sm">
                        Proceed to Payment <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>

                <div class="text-center">
                    <a href="cart.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-indigo-600 transition-colors">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i> Back to Cart
                    </a>
                </div>
            </div>

        </form>
    </main>

    <?php include "../auth/footer.php"; ?>

</body>
</html>