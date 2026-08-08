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
$is_success = false;
$created_order_id = 0;

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

// Fetch active payment methods from database added by Admin (including logo/image)
$payment_methods_db = [];
$pm_query = $conn->query("SELECT * FROM payment_method WHERE is_active = 1 ORDER BY id DESC");
if ($pm_query && $pm_query->num_rows > 0) {
    while ($pm_row = $pm_query->fetch_assoc()) {
        // Detect logo field name from table (logo, image, or icon)
        $logo_file = $pm_row['logo'] ?? $pm_row['image'] ?? $pm_row['icon'] ?? '';
        
        $payment_methods_db[$pm_row['id']] = [
            'id' => $pm_row['id'],
            'title' => $pm_row['method_name'],
            'holder' => $pm_row['account_holder'],
            'number' => $pm_row['account_number'],
            'description' => $pm_row['description'],
            'logo' => !empty($logo_file) ? '../uploads/qr_codes/' . $logo_file : '',
            'qr' => !empty($pm_row['qr_code']) ? '../uploads/qr_codes/' . $pm_row['qr_code'] : ''
        ];
    }
}

// Calculate order total
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['totalprice'];
}
$delivery_fee = 2500;
$total_amount = $subtotal + $delivery_fee;

// Process the combined Order Creation and Payment Slip Submission Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If cart is empty, prevent form submission processing
    if (empty($cart_items)) {
        $error = "Your cart is empty. Please add items before checking out.";
    } else {
        $customer_name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $payment_method_id = intval($_POST['payment_method'] ?? 0);
        $tx_ref = trim($_POST['transaction_ref'] ?? '');

        // Form inputs validation
        if (empty($customer_name) || empty($phone) || empty($address) || empty($payment_method_id)) {
            $error = "All customer fields and payment method selection are required.";
        } elseif (empty($_FILES["payment_slip"]["name"])) {
            $error = "Payment slip image is required to complete checkout.";
        } else {
            // Handle Payment Slip Upload First
            $target_dir = "../assets/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $file_name = basename($_FILES["payment_slip"]["name"]);
            $generated_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", $file_name);
            $target_file = $target_dir . $generated_filename;
            $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

            if ($_FILES["payment_slip"]["size"] > 2000000) {
                $error = "Sorry, your payment slip file is too large. Maximum allowed size is 2MB.";
            } elseif ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "webp") {
                $error = "Sorry, only JPG, JPEG, PNG & WEBP files are allowed for payment slip.";
            } else {
                if (move_uploaded_file($_FILES["payment_slip"]["tmp_name"], $target_file)) {
                    $db_file_path = $generated_filename;

                    // Update User Profile Records
                    $update_profile = $conn->prepare("UPDATE users SET phone = ?, address = ? WHERE id = ?");
                    $update_profile->bind_param("ssi", $phone, $address, $user_id);
                    $update_profile->execute();
                    $update_profile->close();

                    $order_number = 'ORD-' . date('YmdHis') . '-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

                    // 1. Insert into 'orders' table
                    $order_stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, created_at, order_number) VALUES (?, ?, 'pending', NOW(), ?)");
                    $order_stmt->bind_param("ids", $user_id, $total_amount, $order_number);

                    if ($order_stmt->execute()) {
                        $order_id = $conn->insert_id;
                        $created_order_id = $order_id;
                        $order_stmt->close();

                        // 2. Insert into 'order_item' table and update inventory stock
                        $item_stmt = $conn->prepare("INSERT INTO order_item (order_id, book_id, quantity, price) VALUES (?, ?, ?, ?)");
                        $update_stock_stmt = $conn->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");

                        foreach ($cart_items as $item) {
                            $item_stmt->bind_param("iiid", $order_id, $item['book_id'], $item['quantity'], $item['unit_price']);
                            $item_stmt->execute();

                            $update_stock_stmt->bind_param("ii", $item['quantity'], $item['book_id']);
                            $update_stock_stmt->execute();
                        }
                        $item_stmt->close();
                        $update_stock_stmt->close();

                        // 3. Insert into 'payment' table
                        $payment_stmt = $conn->prepare("INSERT INTO payment (order_id, payment_method_id, amount, status, payment_date, transaction_ref, payment_slip) VALUES (?, ?, ?, 'pending', NOW(), ?, ?)");
                        $payment_stmt->bind_param("iidss", $order_id, $payment_method_id, $total_amount, $tx_ref, $db_file_path);
                        $payment_stmt->execute();
                        $payment_stmt->close();

                        // Clear cart items
                        $clear_stmt = $conn->prepare("DELETE FROM cart_item WHERE user_id = ?");
                        $clear_stmt->bind_param("i", $user_id);
                        $clear_stmt->execute();
                        $clear_stmt->close();

                        $is_success = true;
                    } else {
                        $order_stmt->close();
                        $error = "Failed to create order. Please try again later.";
                    }
                } else {
                    $error = "Sorry, there was an error uploading your payment slip file.";
                }
            }
        }
    }
}

// Get the first payment method key for initial load
$first_method_key = !empty($payment_methods_db) ? array_key_first($payment_methods_db) : null;
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout & Payment — Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="text-slate-900 antialiased min-h-screen flex flex-col justify-between">

    <?php include "../auth/header.php"; ?>

    <main class="flex-grow max-w-[1600px] w-full mx-auto px-4 py-6 md:py-10 lg:py-12">
        
        <?php if ($is_success): ?>
            <!-- Success Confirmation State Screen -->
            <div class="max-w-xl mx-auto bg-white rounded-3xl p-6 sm:p-8 md:p-12 shadow-sm border border-slate-100 text-center space-y-6">
                <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto text-emerald-500 text-4xl border border-emerald-100 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="space-y-2">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Order & Payment Submitted!</h1>
                    <p class="text-xs md:text-sm text-slate-500 leading-relaxed">
                        Thank you for your order! We have received your payment slip details. Our team will verify your transfer and confirm your order shortly.
                    </p>
                </div>
                
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 max-w-xs mx-auto text-xs text-slate-600 space-y-1.5">
                    <div>Order Reference: <span class="font-bold text-slate-800">#<?= $created_order_id ?></span></div>
                    <div>Status: <span class="font-bold text-amber-600">Pending Verification</span></div>
                </div>

                <div class="pt-2">
                    <a href="order_details.php?order_id=<?= $created_order_id ?>" class="inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition text-sm shadow-md cursor-pointer">
                        View Order Details
                    </a>
                </div>
            </div>

        <?php elseif (empty($cart_items)): ?>
            <!-- Empty Cart Display State -->
            <div class="text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm max-w-md mx-auto p-8 space-y-4">
                <i class="fa-solid fa-basket-shopping text-4xl text-slate-300"></i>
                <h2 class="text-xl font-bold text-slate-800">Your cart is currently empty</h2>
                <p class="text-xs text-slate-500">Add some books to your cart before proceeding to checkout.</p>
                <a href="../index.php" class="inline-block bg-indigo-600 text-white font-semibold px-5 py-2.5 rounded-xl text-xs hover:bg-indigo-700 transition">Browse Books</a>
            </div>

        <?php else: ?>
            <!-- Primary Checkout Page Heading -->
            <div class="mb-6 md:mb-8">
                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">Checkout & Payment</h1>
                <p class="text-xs md:text-sm text-slate-500 mt-1">Provide your delivery information and upload payment transfer slip to complete order.</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl shadow-sm flex items-start gap-3">
                    <i class="fa-solid fa-circle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
                    <p class="text-xs md:text-sm text-amber-800 font-medium"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <!-- Form layout: Displays 3 cards side-by-side horizontally on desktop displays -->
            <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-stretch">
                    
                <!-- Card 1: Customer Information -->
                <div class="bg-white rounded-3xl p-5 sm:p-8 shadow-sm border border-slate-100 flex flex-col justify-between h-full">
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <i class="fa-solid fa-address-card text-indigo-600 text-xl"></i>
                            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Customer Information</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Receiver Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" value="<?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>" required placeholder="Enter receiver's name"
                                       class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Phone Number <span class="text-rose-500">*</span></label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user_phone ?: ($_SESSION['user_phone'] ?? '')) ?>" required placeholder="Enter mobile phone number"
                                       class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500"> Address<span class="text-rose-500">*</span></label>
                                <textarea name="address" rows="4" required placeholder=""
                                          class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800 resize-none leading-relaxed"><?= htmlspecialchars($user_address) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Payment Details -->
                <div class="bg-white rounded-3xl p-5 sm:p-8 shadow-sm border border-slate-100 flex flex-col justify-between h-full">
                    <div class="space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <i class="fa-solid fa-wallet text-indigo-600 text-xl"></i>
                            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Payment Details</h2>
                        </div>

                        <?php if (!empty($payment_methods_db)): ?>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Select Payment Method <span class="text-rose-500">*</span></label>
                                
                                <!-- Custom Payment Method Dropdown with Logo Preview -->
                                <div class="relative">
                                    <!-- Custom Dropdown Trigger Button -->
                                    <button type="button" id="pm_dropdown_btn" onclick="togglePaymentDropdown()" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm bg-slate-50/50 text-slate-800 flex items-center justify-between focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition">
                                        <div class="flex items-center gap-3" id="pm_selected_display">
                                            <?php $first_pm = $payment_methods_db[$first_method_key]; ?>
                                            <?php if (!empty($first_pm['logo'])): ?>
                                                <img id="selected_pm_logo" src="<?= htmlspecialchars($first_pm['logo']); ?>" alt="<?= htmlspecialchars($first_pm['title']); ?>" class="w-6 h-6 object-contain rounded-md shrink-0">
                                            <?php else: ?>
                                                <div id="selected_pm_logo_fallback" class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                                                    <?= strtoupper(substr($first_pm['title'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <span id="selected_pm_title" class="font-semibold text-slate-800"><?= htmlspecialchars($first_pm['title']); ?></span>
                                        </div>
                                        <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform duration-200" id="pm_dropdown_arrow"></i>
                                    </button>

                                    <!-- Hidden radio inputs for form submission -->
                                    <?php foreach ($payment_methods_db as $pm_id => $pm): ?>
                                        <input type="radio" name="payment_method" id="pm_radio_<?= $pm_id ?>" value="<?= $pm_id; ?>" <?= $pm_id == $first_method_key ? 'checked' : ''; ?> class="sr-only">
                                    <?php endforeach; ?>

                                    <!-- Dropdown Options Menu -->
                                    <div id="pm_dropdown_menu" class="hidden absolute z-30 w-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-lg max-h-60 overflow-y-auto py-1">
                                        <?php foreach ($payment_methods_db as $pm_id => $pm): ?>
                                            <div onclick="selectPaymentOption('<?= $pm_id ?>')" class="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 cursor-pointer transition text-sm">
                                                <?php if (!empty($pm['logo'])): ?>
                                                    <img src="<?= htmlspecialchars($pm['logo']); ?>" alt="<?= htmlspecialchars($pm['title']); ?>" class="w-6 h-6 object-contain rounded-md shrink-0">
                                                <?php else: ?>
                                                    <div class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">
                                                        <?= strtoupper(substr($pm['title'], 0, 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="font-medium text-slate-800"><?= htmlspecialchars($pm['title']); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Account Details Box -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center border border-slate-100 rounded-2xl p-4 bg-slate-50/50">
                                <div class="border border-dashed border-slate-200 rounded-2xl p-3 bg-white flex flex-col items-center justify-center gap-2">
                                    <img id="payment_qr_img" src="<?= htmlspecialchars($payment_methods_db[$first_method_key]['qr'] ?: 'https://placehold.co/200x200?text=No+QR+Code'); ?>" alt="Payment QR Code" class="w-28 h-28 md:w-32 md:h-32 object-contain rounded-lg">
                                    <p class="text-[10px] text-slate-400 font-medium text-center">Scan QR code or transfer to account number</p>
                                </div>

                                <div class="space-y-3 text-xs md:text-sm">
                                    <div>
                                        <span class="text-[10px] text-slate-400 font-semibold block uppercase tracking-wide">Account Holder</span>
                                        <span id="payment_holder_name" class="text-sm font-bold text-slate-800"><?= htmlspecialchars($payment_methods_db[$first_method_key]['holder']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-[10px] text-slate-400 font-semibold block uppercase tracking-wide">Account Number</span>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span id="accountNumber" class="text-sm font-extrabold text-indigo-600 tracking-wide"><?= htmlspecialchars($payment_methods_db[$first_method_key]['number']); ?></span>
                                            <button type="button" onclick="copyNumber()" class="text-slate-400 hover:text-indigo-600 transition cursor-pointer" title="Copy Account Number">
                                                <i class="fa-regular fa-copy text-xs"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="payment_description_box" class="bg-amber-50 border border-amber-100 rounded-xl p-2.5 <?= empty($payment_methods_db[$first_method_key]['description']) ? 'hidden' : ''; ?>">
                                        <p class="text-[10px] leading-relaxed text-amber-800 font-medium">
                                            <strong class="text-amber-600 font-bold">Note:</strong> <span id="payment_description_text"><?= htmlspecialchars($payment_methods_db[$first_method_key]['description']); ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-4 bg-red-50 border border-red-100 rounded-2xl text-red-600 text-xs font-semibold">
                                No active payment methods available at the moment. Please contact support.
                            </div>
                        <?php endif; ?>

                        <!-- Payment Slip Upload Input -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Upload Payment Slip / Screenshot <span class="text-rose-500">*</span></label>
                            
                            <div class="relative flex items-center gap-3 w-full border border-slate-200 rounded-xl p-2 bg-slate-50/50">
                                <label for="file-upload" class="bg-white border border-slate-300 hover:border-indigo-500 hover:text-indigo-600 text-slate-700 font-medium text-xs md:text-sm px-4 py-2.5 rounded-lg cursor-pointer transition shrink-0 shadow-sm flex items-center gap-2">
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Choose File</span>
                                </label>

                                <input type="file" name="payment_slip" id="file-upload" required accept="image/*" onchange="previewSmallImage(event)" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer pointer-events-auto z-10" />

                                <div id="small-preview-container" class="hidden items-center gap-2 overflow-hidden shrink-0 z-20">
                                    <img id="small-preview-img" src="#" alt="Slip Thumbnail" class="w-10 h-10 object-cover rounded-lg border border-slate-300 shadow-sm shrink-0">
                                    <button type="button" onclick="removeSmallImage()" class="text-slate-400 hover:text-rose-500 text-xs transition p-1" title="Remove image">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Order Summary -->
                <div class="bg-white rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4 mb-4">
                            <i class="fa-solid fa-cart-shopping text-indigo-600 text-lg"></i>
                            <h2 class="text-base md:text-lg font-bold text-slate-800 tracking-tight">Order Summary</h2>
                        </div>

                        <div class="space-y-4 max-h-56 overflow-y-auto border-b border-slate-100 pb-4 mb-4 pr-1">
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
                            <div class="flex justify-between text-gray-900 font-bold">
                                <span>Total Amount</span>
                                <span class="font-medium text-gray-900 font-bold"><?= number_format($subtotal) ?> ကျပ်</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 px-4 rounded-xl transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 active:scale-[0.99] cursor-pointer text-sm">
                            Submit Order & Payment <i class="fa-solid fa-arrow-right text-xs"></i>
                        </button>

                        <div class="text-center">
                            <a href="cart.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-indigo-600 transition-colors">
                                <i class="fa-solid fa-chevron-left text-[10px]"></i> Back to Cart
                            </a>
                        </div>
                    </div>
                </div>

            </form>
        <?php endif; ?>
    </main>

    <?php include "../auth/footer.php"; ?>

    <!-- Client-side Interactive Scripts -->
    <script>
    const paymentMap = <?= json_encode($payment_methods_db); ?>;

    function togglePaymentDropdown() {
        const menu = document.getElementById('pm_dropdown_menu');
        const arrow = document.getElementById('pm_dropdown_arrow');
        menu.classList.toggle('hidden');
        arrow.classList.toggle('rotate-180');
    }

    function selectPaymentOption(methodKey) {
        if (paymentMap[methodKey]) {
            const data = paymentMap[methodKey];

            // Check corresponding radio input
            const radio = document.getElementById('pm_radio_' + methodKey);
            if (radio) radio.checked = true;

            // Update trigger display with logo & title
            const display = document.getElementById('pm_selected_display');
            let logoHtml = '';
            if (data.logo && data.logo !== '') {
                logoHtml = `<img src="${data.logo}" alt="${data.title}" class="w-6 h-6 object-contain rounded-md shrink-0">`;
            } else {
                const initial = data.title.substring(0, 2).toUpperCase();
                logoHtml = `<div class="w-6 h-6 rounded-md bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs shrink-0">${initial}</div>`;
            }
            display.innerHTML = `${logoHtml}<span class="font-semibold text-slate-800">${data.title}</span>`;

            // Close dropdown menu
            togglePaymentDropdown();

            // Update QR image with fallback placeholder if empty
            const qrImg = document.getElementById('payment_qr_img');
            qrImg.src = data.qr !== '' ? data.qr : 'https://placehold.co/200x200?text=No+QR+Code';

            // Update holder name & account number
            document.getElementById('payment_holder_name').innerText = data.holder;
            document.getElementById('accountNumber').innerText = data.number;

            // Update description box visibility & content
            const descBox = document.getElementById('payment_description_box');
            const descText = document.getElementById('payment_description_text');
            if (data.description && data.description.trim() !== '') {
                descText.innerText = data.description;
                descBox.classList.remove('hidden');
            } else {
                descBox.classList.add('hidden');
            }
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const btn = document.getElementById('pm_dropdown_btn');
        const menu = document.getElementById('pm_dropdown_menu');
        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
            const arrow = document.getElementById('pm_dropdown_arrow');
            if (arrow) arrow.classList.remove('rotate-180');
        }
    });

    function copyNumber() {
        var numText = document.getElementById("accountNumber").innerText;
        navigator.clipboard.writeText(numText).then(function() {
            alert("Account number copied: " + numText);
        }, function(err) {
            console.error('Copy failed: ', err);
        });
    }

    function previewSmallImage(event) {
        const input = event.target;
        const previewContainer = document.getElementById('small-preview-container');
        const previewImage = document.getElementById('small-preview-img');

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.classList.remove('hidden');
                previewContainer.classList.add('flex');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeSmallImage() {
        const input = document.getElementById('file-upload');
        const previewContainer = document.getElementById('small-preview-container');
        const previewImage = document.getElementById('small-preview-img');

        input.value = '';
        previewImage.src = '#';
        previewContainer.classList.add('hidden');
        previewContainer.classList.remove('flex');
    }
    </script>
</body>
</html>