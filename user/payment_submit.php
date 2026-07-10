<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Customer Login Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$message = "";

// Read order_id and payment method from URL parameters (passed from checkout.php)
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$payment_method_key = isset($_GET['method']) ? trim($_GET['method']) : 'kpay';

// Redirect if no valid order_id provided
if ($order_id <= 0) {
    header("Location: myorders.php");
    exit();
}

// Fetch order details from database and verify it belongs to the logged-in customer
$order_stmt = $conn->prepare("SELECT * FROM Orders WHERE id = ? AND user_id = ?");
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
$chk_pay = $conn->prepare("SELECT id FROM Payment WHERE order_id = ? AND status != 'pending' LIMIT 1");
$chk_pay->bind_param("i", $order_id);
$chk_pay->execute();
$existing_payment = $chk_pay->get_result()->fetch_assoc();
$chk_pay->close();

if ($existing_payment) {
    $error = "This order has already been paid for.";
}

// Fetch payment method details from database for display
$method_query = $conn->prepare("SELECT method_name, account_holder, account_number, description FROM payment_method WHERE method_name = ? AND is_active = 1");
$method_query->bind_param("s", $payment_method_key);
$method_query->execute();
$method_result = $method_query->get_result();
$method_data = $method_result->fetch_assoc();
$method_query->close();

// Fallback to default if method not found in database
$current_method = $method_data ? $method_data : [
    'method_name' => strtoupper($payment_method_key),
    'account_holder' => 'N/A',
    'account_number' => 'N/A',
    'description' => ''
];

// Handle POST form submission - upload slip and update payment record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($existing_payment)) {
    $transaction_id = trim($_POST['transaction_id'] ?? '');

    // Validate required fields
    if (empty($transaction_id)) {
        $error = "Please enter the Transaction Reference ID.";
    } elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please upload your payment slip screenshot.";
    } else {
        // Validate uploaded file type - only allow image files
        $file_name = $_FILES['screenshot']['name'];
        $file_tmp = $_FILES['screenshot']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png'];

        if (!in_array($file_ext, $allowed_extensions)) {
            $error = "Only JPG, JPEG, or PNG images are allowed for the payment slip.";
        } else {
            // Double-check: verify no other payment with a transaction_ref already exists for this order
            $dup_check = $conn->prepare("SELECT id FROM Payment WHERE order_id = ? AND transaction_ref != '' LIMIT 1");
            $dup_check->bind_param("i", $order_id);
            $dup_check->execute();
            $dup_result = $dup_check->get_result();
            $dup_check->close();

            if ($dup_result->num_rows > 0) {
                $error = "A payment has already been submitted for this order. You cannot submit another.";
            } else {
                // Generate unique file name and ensure upload directory exists
                $new_file_name = "slip_" . time() . "_" . uniqid() . "." . $file_ext;
                $upload_dir = "../uploads/slips/";

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Move uploaded file to the target directory
                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    $current_time = date('Y-m-d H:i:s');

                    
                    $update_stmt = $conn->prepare("UPDATE Payment SET transaction_ref = ?, payment_slip = ?, payment_date = ? WHERE order_id = ? AND status = 'pending' AND transaction_ref = ''");
                    $update_stmt->bind_param("sssi", $transaction_id, $new_file_name, $current_time, $order_id);

                    if ($update_stmt->execute() && $update_stmt->affected_rows > 0) {
                        $message = "Payment submitted successfully! Your order will be confirmed after admin approval.";
                       
                    } else {
                        $error = "Failed to save payment data. The payment record may not exist or has already been updated. Please try again.";
                    }
                    $update_stmt->close();
                } else {
                    $error = "Failed to upload the payment slip file. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Payment - Online Book Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 font-sans min-h-screen">

    <nav class="bg-white shadow-sm border-b border-gray-200 p-4">
        <div class="max-w-3xl mx-auto flex justify-between items-center">
            <a href="../index.php" class="text-xl font-bold text-indigo-600">📚 BookShop</a>
            <span class="text-gray-500 text-sm">Order ID: #<?= htmlspecialchars($order_id) ?></span>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 py-8">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 md:p-8">

            <div class="text-center mb-8">
                <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Submit Payment Slip</h1>
                <p class="text-sm text-gray-500">Please transfer using <span class="font-bold text-indigo-600"><?= htmlspecialchars($current_method['method_name']) ?></span> and upload the slip below.</p>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 text-center mb-8">
                <span class="text-sm text-indigo-600 font-medium block mb-1">Total Amount to Transfer</span>
                <span class="text-3xl font-black text-indigo-700"><?= number_format($total_amount) ?> MMK</span>
            </div>

            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm font-semibold">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-600 rounded-xl text-sm font-semibold">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($existing_payment)): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-center">
                    <p class="text-sm font-bold text-gray-800">Payment Already Submitted</p>
                    <p class="text-xs text-gray-500 mt-1">This order already has a pending payment awaiting admin approval.</p>
                    <a href="myorders.php" class="inline-block mt-4 bg-amber-500 hover:bg-amber-400 text-white px-5 py-2 rounded-xl text-xs font-bold transition">
                        Back to My Orders
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center border-b border-gray-100 pb-8 mb-8">
                    <div class="flex flex-col items-center justify-center p-4 bg-gray-50 rounded-xl border border-gray-200 border-dashed">
                        <div class="w-48 h-48 flex items-center justify-center bg-white rounded-lg shadow-sm p-2">
                            <div class="text-center">
                                <p class="text-lg font-bold text-indigo-600"><?= htmlspecialchars($current_method['method_name']) ?></p>
                                <p class="text-xs text-gray-400 mt-2">Scan or transfer to the account below</p>
                            </div>
                        </div>
                        <span class="text-xs text-gray-500 mt-3">Transfer and keep the receipt</span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <span class="text-xs text-gray-400 block font-medium">Account Holder Name</span>
                            <span class="text-lg font-bold text-gray-800"><?= htmlspecialchars($current_method['account_holder']) ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block font-medium">Account Number</span>
                            <span class="text-xl font-mono font-black text-indigo-600 tracking-wide"><?= htmlspecialchars($current_method['account_number']) ?></span>
                        </div>
                        <?php if (!empty($current_method['description'])): ?>
                        <div>
                            <span class="text-xs text-gray-400 block font-medium">Description</span>
                            <span class="text-sm text-gray-600"><?= htmlspecialchars($current_method['description']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="bg-amber-50 border border-amber-100 p-3 rounded-lg text-xs text-amber-700">
                            <strong>Important:</strong> After transferring, please save the payment slip/screenshot carefully.
                        </div>
                    </div>
                </div>

                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">1. Upload Payment Slip / Screenshot <span class="text-red-500">*</span></label>
                        <input type="file" name="screenshot" accept=".jpg,.jpeg,.png" required class="w-full text-sm text-gray-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-200 rounded-xl p-2 cursor-pointer bg-white">
                        <p class="text-[11px] text-gray-400 mt-1">JPG, JPEG, or PNG format only.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">2. Transaction Reference ID <span class="text-red-500">*</span></label>
                        <input type="text" name="transaction_id" required class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition uppercase font-mono" placeholder="Eg. 20260706xxxx">
                        <p class="text-[11px] text-gray-400 mt-1">Enter the reference/ID from your payment confirmation.</p>
                    </div>

                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-emerald-100 transition-all text-center block text-base">
                        Submit Payment Slip
                    </button>

                    <a href="checkout.php" class="block text-center text-xs text-gray-400 hover:underline hover:text-gray-600">Back to Checkout</a>
                </form>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>