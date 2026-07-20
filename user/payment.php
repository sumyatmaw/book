<?php
session_start();
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$error = "";
$message = "";

// Check Order ID from URL
if (!isset($_GET['order']) || empty($_GET['order'])) {
    header("Location: myorders.php");
    exit();
}

$order_id = intval($_GET['order']);
$user_id = $_SESSION['user_id'];

// Verify this order belongs to the logged-in customer and is pending
$order_stmt = $conn->prepare("SELECT * FROM Orders WHERE id = ? AND user_id = ? AND status = 'pending'");
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: myorders.php");
    exit();
}

$order_data = $order_result->fetch_assoc();
$total_amount = $order_data['total_amount'];
$order_stmt->close();

// Check if this order already has a completed payment (prevent duplicate submission)
$chk_pay = $conn->prepare("SELECT id, transaction_ref FROM Payment WHERE order_id = ? AND status != 'pending' LIMIT 1");
$chk_pay->bind_param("i", $order_id);
$chk_pay->execute();
$existing_payment = $chk_pay->get_result()->fetch_assoc();
$chk_pay->close();

if ($existing_payment) {
    $error = "This order has already been paid for.";
}

// Get available Payment Methods (all columns for display)
$methods_result = $conn->query("SELECT id, method_name, account_holder, account_number, description FROM payment_method WHERE is_active = 1");
$has_methods = $methods_result && $methods_result->num_rows > 0;

// Build methods array for JavaScript
$methods_json = [];
if ($has_methods) {
    $methods_result->data_seek(0);
    while ($m = $methods_result->fetch_assoc()) {
        $methods_json[] = $m;
    }
}

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment']) && empty($existing_payment)) {
    $payment_method_id = intval($_POST['payment_method_id'] ?? 0);
    $transaction_ref = trim($_POST['transaction_ref'] ?? '');

    if ($payment_method_id <= 0) {
        $error = "Please select a payment method.";
    } elseif (empty($transaction_ref)) {
        $error = "Please enter the Transaction Reference ID.";
    } elseif (!isset($_FILES['payment_slip']) || $_FILES['payment_slip']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload your payment slip.";
    } else {
        $file_name = $_FILES['payment_slip']['name'];
        $file_tmp = $_FILES['payment_slip']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Only JPG, JPEG, or PNG images are allowed for the payment slip.";
        } else {
            // Double-check for duplicate: verify no other payment with transaction_ref already exists for this order
            $dup_check = $conn->prepare("SELECT id FROM Payment WHERE order_id = ? AND transaction_ref != '' LIMIT 1");
            $dup_check->bind_param("i", $order_id);
            $dup_check->execute();
            $dup_result = $dup_check->get_result();
            $dup_check->close();

            if ($dup_result->num_rows > 0) {
                $error = "A payment has already been submitted for this order. You cannot submit another.";
            } else {
                $new_file_name = "slip_" . time() . "_" . uniqid() . "." . $file_ext;
                $upload_dir = "../uploads/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $current_time = date('Y-m-d H:i:s');

                    // UPDATE existing payment record created during checkout
                    $update_stmt = $conn->prepare("UPDATE Payment SET payment_method_id = ?, payment_date = ?, transaction_ref = ?, payment_slip = ? WHERE order_id = ? AND status = 'pending' AND transaction_ref = ''");
                    $update_stmt->bind_param("isssi", $payment_method_id, $current_time, $transaction_ref, $new_file_name, $order_id);

                    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                        $message = "Payment submitted successfully! Your order will be confirmed after admin approval.";
                        header("refresh:2; url=myorders.php");
                    } else {
                        $error = "Failed to save payment data. Please try again.";
                    }
                    $update_stmt->close();
                } else {
                    $error = "Failed to upload the payment slip.";
                }
            }
        }
    }
}

$currentPage = 'payment';
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
    <title>Payment - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans text-slate-800 flex flex-col">

    <?php include '../auth/header.php'; ?>

    <div class="flex-1 flex items-center justify-center p-4 sm:p-6 my-6">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-md border border-gray-100 p-6 sm:p-8">

            <h1 class="text-2xl font-black text-slate-900 mb-2 flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-amber-500"></i> Payment
            </h1>
            <p class="text-sm font-bold text-gray-500 mb-6">
                Total Amount: <span class="text-blue-600 text-lg"><?= number_format($total_amount); ?> ကျပ်</span>
            </p>

            <!-- Error & Success Messages -->
            <?php if (!empty($message)): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($existing_payment)): ?>
                <!-- Already Paid Message -->
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                    <i class="fa-solid fa-circle-check text-3xl text-amber-500 mb-3"></i>
                    <p class="text-sm font-bold text-slate-800">Payment Already Submitted</p>
                    <p class="text-xs text-gray-500 mt-1">This order already has a pending payment awaiting admin approval.</p>
                    <a href="myorders.php" class="inline-block mt-4 bg-amber-500 hover:bg-amber-400 text-slate-900 px-5 py-2 rounded-xl text-xs font-bold transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Back to My Orders
                    </a>
                </div>
            <?php elseif (!$has_methods): ?>
                <!-- No Payment Methods Available -->
                <div class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                    <i class="fa-solid fa-ban text-3xl text-red-400 mb-3"></i>
                    <p class="text-sm font-bold text-slate-800">No Payment Methods Available</p>
                    <p class="text-xs text-gray-500 mt-1">There are no active payment methods configured. Please contact the administrator.</p>
                    <a href="myorders.php" class="inline-block mt-4 bg-gray-200 hover:bg-gray-300 text-slate-700 px-5 py-2 rounded-xl text-xs font-bold transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> Back to My Orders
                    </a>
                </div>
            <?php else: ?>
                <!-- Payment Form -->
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-5">

                    <!-- Payment Method Dropdown -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Payment Method</label>
                        <select name="payment_method_id" id="paymentMethodSelect"
                                class="w-full bg-white border border-gray-300 rounded-xl py-3 px-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 shadow-sm font-medium" required>
                            <option value="">-- Select Payment Method --</option>
                            <?php foreach ($methods_json as $method): ?>
                                <option value="<?= (int)$method['id']; ?>"
                                        data-name="<?= htmlspecialchars($method['method_name']); ?>"
                                        data-holder="<?= htmlspecialchars($method['account_holder']); ?>"
                                        data-number="<?= htmlspecialchars($method['account_number']); ?>"
                                        data-desc="<?= htmlspecialchars($method['description']); ?>">
                                    <?= htmlspecialchars($method['method_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selected Method Account Info (shown via JS) -->
                    <div id="methodInfo" class="hidden bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-2">
                        <h4 class="text-xs font-bold text-blue-700 uppercase tracking-wider mb-2">
                            <i class="fa-solid fa-building-columns mr-1"></i> Account Information
                        </h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-[11px] text-gray-500 font-medium">Method</span>
                                <p id="infoMethodName" class="font-bold text-slate-800">-</p>
                            </div>
                            <div>
                                <span class="text-[11px] text-gray-500 font-medium">Account Holder</span>
                                <p id="infoHolder" class="font-bold text-slate-800">-</p>
                            </div>
                            <div class="col-span-2">
                                <span class="text-[11px] text-gray-500 font-medium">Account Number</span>
                                <p id="infoNumber" class="font-bold text-blue-600 text-lg tracking-wide">-</p>
                            </div>
                        </div>
                        <div id="infoDescWrap" class="hidden pt-2 border-t border-blue-200">
                            <span class="text-[11px] text-gray-500 font-medium">Description</span>
                            <p id="infoDesc" class="text-xs text-gray-600 mt-0.5">-</p>
                        </div>
                    </div>

                    <!-- Transaction ID Input -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Transaction Reference ID</label>
                        <input type="text" name="transaction_ref" placeholder="Enter Transaction ID"
                               class="w-full border border-gray-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-sm shadow-sm" required>
                        <p class="text-[11px] text-gray-400 mt-1">Enter the reference/ID from your payment confirmation.</p>
                    </div>

                    <!-- Payment Slip Upload -->
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Upload Payment Slip</label>
                        <div class="w-full border border-gray-300 rounded-xl p-2 bg-white shadow-sm">
                            <input type="file" name="payment_slip" accept=".jpg,.jpeg,.png"
                                   class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition cursor-pointer" required>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">JPG, JPEG, or PNG format only.</p>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="confirm_payment"
                            class="w-full bg-blue-600 text-white py-3.5 rounded-xl hover:bg-blue-700 font-bold transition shadow-md flex items-center justify-center gap-2 mt-2">
                        <i class="fa-solid fa-lock text-xs"></i> Confirm Payment
                    </button>

                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../auth/footer.php'; ?>

    <script>
    // Payment method data from PHP
    const methodsData = <?= json_encode($methods_json); ?>;
    const methodsMap = {};
    methodsData.forEach(m => { methodsMap[m.id] = m; });

    const select = document.getElementById('paymentMethodSelect');
    const infoPanel = document.getElementById('methodInfo');

    if (select) {
        select.addEventListener('change', function() {
            const val = this.value;
            if (val && methodsMap[val]) {
                const m = methodsMap[val];
                document.getElementById('infoMethodName').textContent = m.method_name;
                document.getElementById('infoHolder').textContent = m.account_holder;
                document.getElementById('infoNumber').textContent = m.account_number;

                const descWrap = document.getElementById('infoDescWrap');
                const descEl = document.getElementById('infoDesc');
                if (m.description && m.description.trim() !== '') {
                    descEl.textContent = m.description;
                    descWrap.classList.remove('hidden');
                } else {
                    descWrap.classList.add('hidden');
                }

                infoPanel.classList.remove('hidden');
            } else {
                infoPanel.classList.add('hidden');
            }
        });
    }
    </script>
</body>
</html>
