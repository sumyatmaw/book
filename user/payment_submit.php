<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';

// Route Guard
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header("Location: ../auth/register.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$method = isset($_GET['method']) ? trim($_GET['method']) : '';

// Fetch order details and total amount from the database
$order_amount = 0;
$stmt = $conn->prepare("SELECT total_amount FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $order_amount = $row['total_amount'];
} else {
    // Redirect if order does not exist or does not belong to the user
    header("Location: cart.php");
    exit();
}
$stmt->close();

// Assign unique payment account settings map configurations
$payment_details = [
    'kpay' => [
        'title' => 'Kpay',
        'holder' => 'Su Myat Maw',
        'number' => '09403502387',
        'qr' => '../assets/Kpay_qr.png'
    ],
    'wave' => [
        'title' => 'WavePay',
        'holder' => 'Su Myat Maw',
        'number' => '09403502387',
        'qr' => '../assets/Kpay_qr.png'
    ],
    'cbpay' => [
        'title' => 'CB Pay',
        'holder' => 'Su Myat Maw',
        'number' => '09403502387',
        'qr' => '../assets/Kpay_qr.png'
    ],
    'ayapay' => [
        'title' => 'AYA Pay',
        'holder' => 'Su Myat Maw',
        'number' => '09403502387',
        'qr' => '../assets/Kpay_qr.png'
    ],
    'banking' => [
        'title' => 'MAB Mobile Banking',
        'holder' => 'Su Myat Maw',
        'number' => '09403502387',
        'qr' => '../assets/Kpay_qr.png'
    ]
];

// Fallback to Kpay defaults if requested provider missing
$current_payment = $payment_details[$method] ?? $payment_details['kpay'];

$error = "";
$is_success = false; // Flag to display the embedded success state layout instead of redirecting

// Handle Form Submission Data Posts
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tx_ref = trim($_POST['transaction_ref'] ?? '');
    
    // CHANGED: Redirect target directory straight to the project assets folder where files are expected
    $target_dir = "../assets/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = basename($_FILES["payment_slip"]["name"]);
    // Clean filename to prevent spaces or special characters breakage
    $generated_filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.\-_]/", "", $file_name);
    $target_file = $target_dir . $generated_filename;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $uploadOk = 1;

    if (empty($tx_ref) || empty($_FILES["payment_slip"]["name"])) {
        $error = "All fields are required.";
        $uploadOk = 0;
    }

    // Check file size (Max 2MB constraint validation)
    if ($_FILES["payment_slip"]["size"] > 2000000) {
        $error = "Sorry, your file is too large. Maximum size is 2MB.";
        $uploadOk = 0;
    }

    // Allow certain distinct image asset formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        $error = "Sorry, only JPG, JPEG, & PNG files are allowed.";
        $uploadOk = 0;
    }

    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["payment_slip"]["tmp_name"], $target_file)) {
            // CHANGED: Save only the dynamic pure filename string into database to sync properly with Admin panel queries
            $db_file_path = $generated_filename;
            $update_stmt = $conn->prepare("UPDATE payment SET transaction_ref = ?, payment_slip = ?, status = 'pending' WHERE order_id = ?");
            $update_stmt->bind_param("ssi", $tx_ref, $db_file_path, $order_id);
            
            if ($update_stmt->execute()) {
                // Switch rendering state internally instead of firing external page redirection rules
                $is_success = true; 
            } else {
                $error = "Something went wrong. Please try again.";
            }
            $update_stmt->close();
        } else {
            $error = "Sorry, there was an error uploading your file.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Payment Slip</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="text-slate-900 antialiased min-h-screen flex flex-col justify-between">

    <?php include "../auth/header.php"; ?>

    <main class="flex-grow max-w-2xl w-full mx-auto px-4 py-6 md:py-10">
        <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-sm border border-slate-100 space-y-8">
            
            <?php if ($is_success): ?>
                <!-- ================= SUCCESS CARD STATE LAYER ================= -->
                <div class="text-center py-8 space-y-5">
                    <div class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto text-emerald-500 text-4xl border border-emerald-100 shadow-sm">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div class="space-y-2">
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Payment Submitted Successfully!</h1>
                        <p class="text-xs md:text-sm text-slate-500 max-w-sm mx-auto leading-relaxed">
                            We have received your payment slip details. Our administrators will verify the transfer and update your order status shortly.
                        </p>
                    </div>
                    
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 max-w-xs mx-auto text-xs text-slate-500 space-y-1.5">
                        <div>Order Reference: <span class="font-bold text-slate-700">#<?= $order_id ?></span></div>
                        <div>Status: <span class="font-bold text-amber-600">Pending Verification</span></div>
                    </div>

                    <div class="pt-4">
                        <a href="order_details.php?order_id=<?= $order_id ?>" class="inline-flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition text-sm shadow-md cursor-pointer">
                            View Order Details
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- ================= ORIGINAL SLIP INPUT FORM ================= -->
                <!-- Header Title -->
                <div class="text-center space-y-2">
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Submit Payment Slip</h1>
                    <p class="text-xs md:text-sm text-slate-500">Please transfer using <span class="text-indigo-600 font-semibold"><?= htmlspecialchars($current_payment['title']) ?></span> and upload the slip below.</p>
                </div>

                <!-- Error Banner -->
                <?php if (!empty($error)): ?>
                    <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl shadow-sm flex items-start gap-3">
                        <i class="fa-solid fa-circle-exclamation text-amber-500 mt-0.5 shrink-0"></i>
                        <p class="text-xs md:text-sm text-amber-800 font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Total Amount Card -->
                <div class="bg-indigo-50/50 border border-indigo-100 rounded-2xl p-5 md:p-6 text-center space-y-1">
                    <span class="text-xs font-bold text-indigo-500 uppercase tracking-wider block">Total Amount to Transfer</span>
                    <h2 class="text-2xl md:text-3xl font-extrabold text-indigo-700"><?= number_format($order_amount) ?> ကျပ်</h2>
                </div>

                <!-- QR & Account Details Section (Responsive Grid Configured) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center border border-slate-100 rounded-2xl p-4 bg-slate-50/30">
                    <!-- QR Code Box -->
                    <div class="border border-dashed border-slate-200 rounded-2xl p-4 bg-white flex flex-col items-center justify-center gap-3">
                        <img src="<?= htmlspecialchars($current_payment['qr']) ?>" alt="Payment QR Code" onerror="this.src='https://placehold.co/200x200?text=QR+Code'" class="w-36 h-36 md:w-40 md:h-40 object-contain rounded-lg">
                        <p class="text-xs text-slate-400 font-medium text-center">Scan or transfer and keep the receipt</p>
                    </div>

                    <!-- Text Details -->
                    <div class="space-y-3.5 text-xs md:text-sm">
                        <div>
                            <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wide">Account Name</span>
                            <span class="text-base font-bold text-slate-800"><?= htmlspecialchars($current_payment['holder']) ?></span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wide">Account Number</span>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span id="accountNumber" class="text-base font-extrabold text-indigo-600 tracking-wide"><?= htmlspecialchars($current_payment['number']) ?></span>
                                <button type="button" onclick="copyNumber()" class="text-slate-400 hover:text-indigo-600 transition cursor-pointer" title="Copy Account Number">
                                    <i class="fa-regular fa-copy text-sm"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 font-semibold block uppercase tracking-wide">Description</span>
                            <span class="text-sm font-medium text-slate-600">Admin</span>
                        </div>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
                            <p class="text-[11px] leading-relaxed text-amber-800 font-medium">
                                <strong class="text-amber-600 font-bold">Important:</strong> After transferring, please save the payment slip/screenshot carefully.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form Submissions -->
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- Step 1: File Upload -->
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-800">1. Upload Payment Slip / Screenshot <span class="text-rose-500">*</span></label>
                        <div class="flex items-center w-full border border-slate-200 rounded-xl p-2 bg-slate-50/50">
                            <input type="file" name="payment_slip" id="file-upload" required accept="image/*" class="block w-full text-xs md:text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer cursor-pointer"/>
                        </div>
                        <p class="text-[11px] text-slate-400">JPG, JPEG, or PNG format only (Maximum size: 2MB).</p>
                    </div>

                    <!-- Step 2: Transaction Reference -->
                    <div class="space-y-2">
                        <label class="block text-sm font-bold text-slate-800">2. Transaction Reference ID <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input type="text" name="transaction_ref" required placeholder="EG. 20260706XXXX" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition bg-slate-50/50 text-slate-800 uppercase tracking-wide">
                        </div>
                        <p class="text-[11px] text-slate-400">Enter the reference/ID from your payment confirmation.</p>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3.5 px-4 rounded-xl transition shadow-lg shadow-emerald-600/20 active:scale-[0.99] cursor-pointer text-center block text-sm">
                        Submit Payment Slip
                    </button>
                </form>

                <!-- Back navigation link -->
                <div class="text-center pt-2 border-t border-slate-100">
                    <a href="checkout.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-indigo-600 transition-colors">
                        Back to Checkout
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include "../auth/footer.php"; ?>

    <!-- Copy to Clipboard Functional Script -->
    <script>
    function copyNumber() {
        var numText = document.getElementById("accountNumber").innerText;
        navigator.clipboard.writeText(numText).then(function() {
            alert("Account number copied to clipboard: " + numText);
        }, function(err) {
            console.error('Could not copy text: ', err);
        });
    }
    </script>
</body>
</html>