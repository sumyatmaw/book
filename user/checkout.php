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

// Fetch active payment methods from database added by Admin
$payment_methods_db = [];
$pm_query = $conn->query("SELECT * FROM payment_method WHERE is_active = 1 ORDER BY id DESC");
if ($pm_query && $pm_query->num_rows > 0) {
    while ($pm_row = $pm_query->fetch_assoc()) {
        $payment_methods_db[$pm_row['id']] = [
            'id' => $pm_row['id'],
            'title' => $pm_row['method_name'],
            'holder' => $pm_row['account_holder'],
            'number' => $pm_row['account_number'],
            'description' => $pm_row['description'],
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
<html lang="en" class="h-full bg-slate-50">
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

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 py-6 md:py-10 lg:py-12">
        
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
            <!-- Empty Cart Display state -->
            <div class="text-center py-16 bg-white rounded-3xl border border-slate-100 shadow-sm max-w-md mx-auto p-8 space-y-4">
                <i class="fa-solid fa-basket-shopping text-4xl text-slate-300"></i>
                <h2 class="text-xl font-bold text-slate-800">Your cart is currently empty</h2>
                <p class="text-xs text-slate-500">Add some books to your cart before proceeding to checkout.</p>
                <a href="../index.php" class="inline-block bg-indigo-600 text-white font-semibold px-5 py-2.5 rounded-xl text-xs hover:bg-indigo-700 transition">Browse Books</a>
            </div>

        <?php else: ?>
            <!-- Primary Checkout & Payment Combined Form Page -->
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

            <form action="" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-start">

                <!-- Left Column: Customer Details + Payment QR & Slip Upload -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Section 1: Receiver Details -->
                    <div class="bg-white rounded-3xl p-5 sm:p-8 shadow-sm border border-slate-100 space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <i class="fa-solid fa-address-card text-indigo-600 text-xl"></i>
                            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Customer Information</h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500"> Address<span class="text-rose-500">*</span></label>
                            <textarea name="address" rows="3" required placeholder="Enter complete delivery details"
                                      class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800 resize-none leading-relaxed"><?= htmlspecialchars($user_address) ?></textarea>
                        </div>
                    </div>

                    <!-- Section 2: Payment Provider Details & Slip Upload -->
                    <div class="bg-white rounded-3xl p-5 sm:p-8 shadow-sm border border-slate-100 space-y-6">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <i class="fa-solid fa-wallet text-indigo-600 text-xl"></i>
                            <h2 class="text-lg font-bold text-slate-800 tracking-tight">Payment Details</h2>
                        </div>

                        <?php if (!empty($payment_methods_db)): ?>
                            <div class="space-y-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Select Payment Method <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <select name="payment_method" id="payment_method_select" required onchange="switchPaymentMethod(this.value)"
                                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm appearance-none focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-700 cursor-pointer font-medium">
                                        <?php foreach ($payment_methods_db as $pm_id => $pm): ?>
                                            <option value="<?= $pm_id; ?>"><?= htmlspecialchars($pm['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                        <i class="fa-solid fa-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Dynamic Account Box -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 items-center border border-slate-100 rounded-2xl p-4 sm:p-5 bg-slate-50/50">
                                <div class="border border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center gap-3">
                                    <img id="payment_qr_img" src="<?= htmlspecialchars($payment_methods_db[$first_method_key]['qr'] ?: 'https://placehold.co/200x200?text=No+QR+Code'); ?>" alt="Payment QR Code" class="w-36 h-36 md:w-40 md:h-40 object-contain rounded-lg">
                                    <p class="text-[11px] text-slate-400 font-medium text-center">Scan QR code or transfer to account number</p>
                                </div>

                                <div class="space-y-3.5 text-xs md:text-sm">
                                    <div>
                                        <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wide">Account Holder</span>
                                        <span id="payment_holder_name" class="text-base font-bold text-slate-800"><?= htmlspecialchars($payment_methods_db[$first_method_key]['holder']); ?></span>
                                    </div>
                                    <div>
                                        <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wide">Account Number</span>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span id="accountNumber" class="text-base font-extrabold text-indigo-600 tracking-wide"><?= htmlspecialchars($payment_methods_db[$first_method_key]['number']); ?></span>
                                            <button type="button" onclick="copyNumber()" class="text-slate-400 hover:text-indigo-600 transition cursor-pointer" title="Copy Account Number">
                                                <i class="fa-regular fa-copy text-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="payment_description_box" class="bg-amber-50 border border-amber-100 rounded-xl p-3 <?= empty($payment_methods_db[$first_method_key]['description']) ? 'hidden' : ''; ?>">
                                        <p class="text-[11px] leading-relaxed text-amber-800 font-medium">
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

                        <!-- Transaction Reference (Optional) -->
                        <!-- <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Transaction Ref / ID <span class="text-slate-400 font-normal">(Optional)</span></label>
                            <input type="text" name="transaction_ref" placeholder="Enter transaction reference ID"
                                   class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800">
                        </div> -->

                        <!-- Payment Slip File Upload -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Upload Payment Slip / Screenshot <span class="text-rose-500">*</span></label>
                            
                            <div class="flex items-center gap-3 w-full border border-slate-200 rounded-xl p-2 bg-slate-50/50">
                                <label for="file-upload" class="bg-white border border-slate-300 hover:border-indigo-500 hover:text-indigo-600 text-slate-700 font-medium text-xs md:text-sm px-4 py-2.5 rounded-lg cursor-pointer transition shrink-0 shadow-sm flex items-center gap-2">
                                    <i class="fa-solid fa-upload"></i>
                                    <span>Choose File</span>
                                </label>

                                <input type="file" name="payment_slip" id="file-upload" required accept="image/*" onchange="previewSmallImage(event)" class="hidden"/>

                                <div id="small-preview-container" class="hidden items-center gap-2 overflow-hidden">
                                    <img id="small-preview-img" src="#" alt="Slip Thumbnail" class="w-12 h-12 object-cover rounded-lg border border-slate-300 shadow-sm shrink-0">
                                    <button type="button" onclick="removeSmallImage()" class="text-slate-400 hover:text-rose-500 text-xs transition p-1" title="Remove image">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400">Allowed formats: JPG, JPEG, PNG, WEBP (Max: 2MB).</p>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Order Summary & Action Button -->
                <div class="space-y-6">
                    <div class="bg-white rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-100 sticky top-6">
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
                            Submit Order & Payment <i class="fa-solid fa-arrow-right text-xs"></i>
                        </button>
                    </div>

                    <div class="text-center">
                        <a href="cart.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-indigo-600 transition-colors">
                            <i class="fa-solid fa-chevron-left text-[10px]"></i> Back to Cart
                        </a>
                    </div>
                </div>

            </form>
        <?php endif; ?>
    </main>

    <?php include "../auth/footer.php"; ?>

    <!-- Client-side Interactive Scripts -->
    <script>
    const paymentMap = <?= json_encode($payment_methods_db); ?>;

    function switchPaymentMethod(methodKey) {
        if (paymentMap[methodKey]) {
            const data = paymentMap[methodKey];
            
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